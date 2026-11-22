<?php

/**
 * Proceso completo de punta a punta, por HTTP y como lo hace cada usuario:
 *   administrador crea carrera y cuentas → empresa se registra y tramita su convenio → Vinculación lo lleva
 *   hasta formalizarlo (con documentos físicos) → la coordinación dictamina y aprueba la vacante →
 *   el alumno se registra, sube su IMSS, se postula, hace su práctica y la cierra hasta quedar acreditado.
 *
 * Incluye el caso real reportado: el alumno sube su IMSS ANTES de que exista la coordinación de su carrera.
 */

use App\Enums\ConvenioEstado;
use App\Enums\EstadoDocumentoFisico as EF;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\Rol;
use App\Enums\TipoDocumentoFisico as TF;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Programa;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Notifications\ConvenioActualizado;
use App\Notifications\NuevaSolicitudConvenio;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function e2ePdf(string $n = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($n, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

/** Avisos recibidos por un usuario que contienen un texto. */
function e2eAvisos(User $u, string $texto): int
{
    return Notification::sent($u, AvisoOperativo::class)->filter(fn ($n) => str_contains($n->mensaje, $texto))->count();
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);

    // Administrador (cuenta inicial creada por comando)
    $this->admin = User::factory()->create(['email' => 'admin@cucsh.test']);
    $this->admin->assignRole(Rol::Administrador->value);
    Personal::create(['user_id' => $this->admin->id, 'puesto' => 'Administrador']);
});

test('proceso completo: carrera, convenio, vacante y práctica hasta la acreditación', function () {
    Notification::fake();

    // ── 1. Administrador: división y carrera nuevas ─────────────────────────────
    $this->actingAs($this->admin)->post(route('admin.catalogos.divisiones.store'), ['nombre' => 'División de Estudios Antropológicos'])->assertSessionHasNoErrors();
    $division = \App\Models\Division::firstOrFail();
    $this->actingAs($this->admin)->post(route('admin.catalogos.carreras.store'), [
        'division_id' => $division->id, 'carrera_clave' => 'antr', 'nombre' => 'Licenciatura en Antropología',
        'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 60, 'creditos_otorgados' => 8,
    ])->assertSessionHasNoErrors();
    $carrera = Carrera::where('carrera_clave', 'ANTR')->firstOrFail();

    // Cuenta de Vinculación
    $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
        'name' => 'Lorena Vinculación', 'email' => 'lorena@csh.udg.mx', 'rol' => Rol::Vinculacion->value,
    ])->assertSessionHasNoErrors();
    $lorena = User::where('email', 'lorena@csh.udg.mx')->firstOrFail();
    $lorena->markEmailAsVerified();   // (configura su contraseña con el enlace de su correo)

    // ── 2. Alumno: se registra cuando su carrera AÚN NO tiene coordinación ──
    auth()->logout();
    $this->post('/registro/alumno', [
        'name' => 'Carlos Moreno', 'email' => 'carlos@alumnos.udg.mx', 'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026',
        'carrera_id' => $carrera->id, 'num_control' => '219701473', 'grado' => 8, 'turno' => 'matutino', 'aviso_privacidad' => '1',
    ])->assertSessionHasNoErrors();
    $alumnoUser = User::where('email', 'carlos@alumnos.udg.mx')->firstOrFail();
    Notification::assertSentTo($alumnoUser, VerifyEmail::class);   // confirmación de correo
    $alumnoUser->markEmailAsVerified();                             // (el alumno abre el enlace)
    $alumno = $alumnoUser->alumno;

    // Si algo le llega a una carrera sin coordinación, el aviso no se pierde: lo recibe el administrador
    \App\Services\Avisos::aCoordinacion($carrera->id, new AvisoOperativo(mensaje: 'Pendiente de prueba', url: '/'));
    expect(e2eAvisos($this->admin, 'no tiene coordinación activa'))->toBe(1);
    $this->actingAs($alumnoUser)->get(route('alumno.dashboard'))->assertOk()->assertSee('Tu coordinación aún no está en la plataforma');

    // ── 3. Administrador crea la coordinación: hereda el pendiente ──────────────
    $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
        'name' => 'Coordinación de Antropología', 'email' => 'antropologia@academicos.udg.mx', 'rol' => Rol::Coordinador->value,
        'carrera_id' => $carrera->id,
    ])->assertSessionHasNoErrors();
    $coord = User::where('email', 'antropologia@academicos.udg.mx')->firstOrFail();
    $coord->markEmailAsVerified();

    // ── 4. Empresa: cuenta corta; luego la solicitud por secciones, el expediente en un envío y la solicitud ──
    auth()->logout();
    $this->post('/registro/empresa', [
        'name' => 'Ana Contacto', 'contacto_cargo' => 'Recursos Humanos', 'contacto_telefono' => '3312345678', 'email' => 'rh@museo.test',
        'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026',
        'razon_social' => 'Museo Regional A.C.', 'rfc' => 'MRE010101AB1', 'tipo_organismo' => 'sociedad_civil',
        'aviso_privacidad' => '1',
    ])->assertSessionHasNoErrors();
    $empresaUser = User::where('email', 'rh@museo.test')->firstOrFail();
    $empresaUser->markEmailAsVerified();
    $empresa = $empresaUser->empresa;

    $this->actingAs($empresaUser)->put(route('empresa.convenio.seccion', 'generales'), [
        'razon_social' => 'Museo Regional A.C.', 'giro' => 'Museo y difusión cultural',
        'descripcion' => 'Museo dedicado a la conservación y difusión del patrimonio cultural de Jalisco.',
        'proyectos_principales' => 'Exposiciones temporales y visitas guiadas', 'calle' => 'Av. Hidalgo', 'numero_exterior' => '100',
        'colonia' => 'Centro', 'codigo_postal' => '44100', 'municipio' => 'Guadalajara', 'entidad' => 'Jalisco',
        'contacto_nombre' => 'Ana Contacto', 'contacto_cargo' => 'Recursos Humanos', 'contacto_telefono' => '3312345678',
    ])->assertSessionHasNoErrors();
    // Guarda su avance aunque le falte el poder; luego lo completa
    $this->actingAs($empresaUser)->put(route('empresa.convenio.seccion', 'legales'), [
        'representante_legal' => 'Mtra. Elena Firma', 'representante_cargo' => 'Directora General', ...datosNotarialesPrueba(),
        'objeto_social' => 'la difusión del patrimonio cultural', 'facultad_origen' => 'poder',
    ])->assertSessionHasNoErrors();
    expect(\App\Support\SolicitudEmpresa::faltantes($empresa->fresh())['legales'])->not->toBeEmpty();
    $this->actingAs($empresaUser)->put(route('empresa.convenio.seccion', 'legales'), [
        'representante_legal' => 'Mtra. Elena Firma', 'representante_cargo' => 'Directora General', ...datosNotarialesPrueba(),
        'objeto_social' => 'la difusión del patrimonio cultural', 'facultad_origen' => 'poder',
        'poder_escritura_numero' => '888', 'poder_fecha' => '2020-02-02', 'poder_notario_nombre' => 'Lic. Juan Notario', 'poder_notaria_numero' => '5', 'poder_notaria_ciudad' => 'Guadalajara',
    ])->assertSessionHasNoErrors();
    $this->actingAs($empresaUser)->put(route('empresa.convenio.seccion', 'perfiles'), [
        'carreras' => [$carrera->id], 'objetivos' => 'Vincular estudiantes con proyectos de museografía',
        'actividades_propuestas' => 'Catalogación de piezas y guía de visitas escolares', 'plazas_estimadas' => 3,
        'medio_conocimiento' => 'Por un egresado del CUCSH', 'experiencia_previa' => '4 practicantes desde 2021',
    ])->assertSessionHasNoErrors();
    $empresa->refresh();
    expect($empresa->domicilio_fiscal)->toBe('Av. Hidalgo 100, col. Centro, C.P. 44100, Guadalajara, Jalisco')
        ->and($empresa->facultad_firmante)->toContain('poder notarial contenido en la escritura pública número 888')
        ->and($empresa->obligatorias())->toContain('poder_notarial');

    // Un solo envío con todo el expediente (PDF); la constancia y el comprobante con su fecha de emisión
    $archivos = collect($empresa->obligatorias())->mapWithKeys(fn ($c) => [$c => e2ePdf("$c.pdf")])->all();
    // Al enviarlos, como la solicitud aún no se manda, regresa a «Mi convenio» para enviarla
    $this->actingAs($empresaUser->fresh())->post(route('empresa.documentos.subir'), ['archivos' => $archivos])
        ->assertSessionHasNoErrors()->assertRedirect(route('empresa.convenio', ['seccion' => 'documentos']));

    $empresaUser = $empresaUser->fresh();   // cada petición real recarga al usuario y sus archivos
    $this->actingAs($empresaUser)->post(route('empresa.convenio.solicitar'))->assertSessionHasNoErrors();
    expect($empresa->fresh()->solicitud_borrador)->toBeNull();
    $convenio = Convenio::where('empresa_id', $empresa->id)->firstOrFail();
    Notification::assertSentTo($lorena, NuevaSolicitudConvenio::class);

    // ── 5. Vinculación valida el expediente: al aprobar el último documento se turna sola a la carrera ──
    foreach ($empresa->fresh()->media as $media) {
        $this->actingAs($lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $media]), ['estado' => 'validada'])->assertSessionHasNoErrors();
    }
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnValidacionAcademica);
    expect(e2eAvisos($coord, 'emite el dictamen de pertinencia'))->toBe(1);

    $this->actingAs($lorena)->post(route('admin.convenios.turnos', $convenio), [
        'fecha_turno_secretaria' => today()->toDateString(), 'fecha_turno_division' => today()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($convenio->documentosFisicos()->where('tipo', TF::OficioTurno->value)->first()->estado)->toBe(EF::EnDivision);

    // ── 6. Coordinación dictamina ───────────────────────────────────────────────
    $dictamen = ConvenioCarrera::where('convenio_id', $convenio->id)->firstOrFail();
    $this->actingAs($coord)->post(route('coordinacion.dictamenes.registrar', $dictamen), ['dictamen' => 'aprobatorio', 'documento' => \Illuminate\Http\UploadedFile::fake()->create('dictamen.pdf', 40, 'application/pdf')])->assertSessionHasNoErrors();
    expect(e2eAvisos($lorena, 'emitió dictamen aprobatorio'))->toBe(1);

    // ── 7. Vinculación: borrador, Jurídica, firmas y formalización ──────────────
    $this->actingAs($lorena)->post(route('admin.convenios.borrador.generar', $convenio), [
        'vigencia_anios' => 3, 'lugar_firma' => 'Zapopan', 'fecha_firma' => today()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($lorena)->post(route('admin.convenios.juridica.turnar', $convenio))->assertSessionHasNoErrors();
    $this->actingAs($lorena)->post(route('admin.convenios.juridica.dictamen', $convenio), ['resultado' => 'aprobado'])->assertSessionHasNoErrors();
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnFirmas)
        ->and($convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->count())->toBe(3);

    foreach ($convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->get() as $juego) {
        $this->actingAs($lorena)->post(route('admin.documentos-fisicos.mover', $juego), ['estado' => EF::ConEmpresa->value])->assertSessionHasNoErrors();
    }
    $this->actingAs($empresaUser)->get(route('empresa.convenio'))->assertSee('juegos originales del convenio por firmar');

    // Cada firma se registra con su evidencia escaneada
    $this->actingAs($lorena)->post(route('admin.convenios.firmas', $convenio), ['etapa' => 'firma_empresa'])->assertSessionHasErrors('evidencia');
    $this->actingAs($lorena)->post(route('admin.convenios.firmas', $convenio), ['etapa' => 'firma_empresa', 'evidencia' => e2ePdf('firma-empresa.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($lorena)->post(route('admin.convenios.firmas', $convenio), ['etapa' => 'firma_rectoria', 'evidencia' => e2ePdf('juego-firmado.pdf')])->assertSessionHasNoErrors();
    // El juego firmado por Rectoría ya es el convenio firmado; la bitácora dice quién firmó y guarda la evidencia
    expect($convenio->getMedia('convenio_firmado'))->toHaveCount(1)
        ->and($convenio->seguimientos()->where('etapa', 'firma_rectoria')->first()->getMedia('documento_etapa'))->toHaveCount(1);
    $this->actingAs($lorena)->get(route('admin.convenios.show', $convenio))->assertOk()
        ->assertSee('Mtra. Elena Firma, Museo Regional A.C.')->assertSee('Rectoría del CUCSH')->assertSee('Registrado en el sistema por')
        ->assertSee('El convenio firmado ya se subió al registrar la firma de Rectoría.');
    expect($convenio->documentosFisicos()->where('estado', EF::Firmado->value)->count())->toBe(3);

    $this->actingAs($lorena)->post(route('admin.convenios.formalizar', $convenio), [
        'folio' => 'CV-ANTR-001', 'fecha_inicio_vigencia' => today()->subMonths(3)->toDateString(),
    ])->assertSessionHasNoErrors();
    $convenio->refresh();
    expect($convenio->estado)->toBe(ConvenioEstado::Formalizado)->and($convenio->estaVigente())->toBeTrue()
        ->and($convenio->fecha_fin_vigencia->toDateString())->toBe(today()->subMonths(3)->addYears(3)->subDay()->toDateString());
    Notification::assertSentTo($empresaUser, ConvenioActualizado::class, fn ($n) => str_contains($n->mensaje, 'formalizado'));

    // ── 8. Empresa propone vacante; la coordinación la aprueba ──────────────────
    $this->actingAs($empresaUser)->post(route('empresa.programas.store'), [
        'convenio_id' => $convenio->id, 'carrera_id' => $carrera->id, 'ciclo' => '2027A', 'titulo' => 'Auxiliar de museografía',
        'descripcion' => 'Apoyo en exposiciones', 'actividades' => "Catalogar piezas\nGuiar visitas", 'perfil_requerido' => 'Interés en patrimonio',
        'horario' => 'L-V 9 a 13', 'cupos_totales' => 2,
    ])->assertSessionHasNoErrors();
    $programa = Programa::firstOrFail();
    expect(e2eAvisos($coord, 'registró la vacante'))->toBe(1);
    $this->actingAs($coord)->post(route('coordinacion.programas.aprobar', $programa))->assertSessionHasNoErrors();

    // ── 9. Expediente del alumno (kardex + IMSS) ANTES de postularse ─────────────
    // Sin expediente validado la oferta está con candado
    $this->actingAs($alumnoUser->fresh())->get(route('alumno.oferta-programas'))->assertOk()
        ->assertSee('Primero entrega tu kardex y tu constancia IMSS a tu coordinación')->assertDontSee('Auxiliar de museografía');

    // Entrega kardex e IMSS juntos; la coordinación recibe aviso
    $this->actingAs($alumnoUser->fresh())->post(route('alumno.documentos.expediente'), [
        'porcentaje_creditos' => 75, 'kardex' => e2ePdf('kardex.pdf'),
        'imss' => e2ePdf('imss.pdf'), 'vigencia_imss' => now()->addYear()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect(e2eAvisos($coord, 'envió su kardex y su constancia IMSS'))->toBe(1);
    $this->actingAs($alumnoUser->fresh())->get(route('alumno.dashboard'))->assertOk()->assertSee('Tu expediente está en revisión');
    $this->actingAs($coord)->get(route('coordinacion.lista-practicantes'))->assertOk()
        ->assertSee('Expedientes por revisar')->assertSee('Ambos correctos: autorizar al alumno');

    // La coordinación detecta un IMSS dado de baja: lo devuelve y el alumno reemplaza solo ese PDF
    $this->actingAs($coord)->post(route('coordinacion.alumnos.expediente', $alumno), [
        'decision' => 'devolver', 'documentos' => ['imss'], 'motivo' => 'El IMSS aparece dado de baja.',
    ])->assertSessionHasNoErrors();
    expect(e2eAvisos($alumnoUser, 'dado de baja'))->toBe(1);
    $this->actingAs($alumnoUser->fresh())->get(route('alumno.mis-documentos'))->assertOk()
        ->assertSee('Tu coordinación te pidió corregir un documento')->assertSee('El IMSS aparece dado de baja.');
    $this->actingAs($alumnoUser->fresh())->post(route('alumno.documentos.imss'), [
        'archivo' => e2ePdf('imss-vigente.pdf'), 'vigencia_imss' => now()->addYear()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect(e2eAvisos($coord, 'reemplazó la constancia IMSS'))->toBe(1);

    // Ambos correctos: autoriza y el alumno recibe «ya puedes postularte»
    $this->actingAs($coord)->post(route('coordinacion.alumnos.expediente', $alumno), ['decision' => 'autorizar'])->assertSessionHasNoErrors();
    expect(e2eAvisos($alumnoUser, 'validó tu kardex y tu constancia IMSS'))->toBe(1);

    // ── 10. Postulación (SIIAU) y cartas ────────────────────────────────────────
    $this->actingAs($alumnoUser->fresh())->get(route('alumno.oferta-programas'))->assertSee('Auxiliar de museografía');
    $this->actingAs($alumnoUser->fresh())->post(route('alumno.oferta-programas.postular', $programa), ['siiau' => '1'])->assertSessionHasNoErrors();
    $postulacion = Postulacion::firstOrFail();
    expect(e2eAvisos($coord, 'se postuló'))->toBe(1)
        ->and((float) $postulacion->porcentaje_creditos_declarado)->toBe(75.0);

    $this->actingAs($coord)->post(route('coordinacion.postulaciones.carta', $postulacion))->assertSessionHasNoErrors();
    expect($postulacion->fresh()->estado)->toBe(PostulacionEstado::CartaPresentacionEmitida);
    $this->actingAs($alumnoUser->fresh())->get(route('alumno.mis-documentos'))->assertOk()->assertSee('Carta generada: imprímela y preséntala en la organización');

    $this->actingAs($alumnoUser)->post(route('alumno.documentos.aceptacion', $postulacion), ['archivo' => e2ePdf('aceptacion.pdf')])->assertSessionHasNoErrors();
    expect(e2eAvisos($coord, 'subió su carta de aceptación'))->toBe(1);
    $this->actingAs($coord)->post(route('coordinacion.postulaciones.aceptacion', $postulacion))->assertSessionHasNoErrors();   // la coordinación la revisa y registra
    $this->actingAs($coord)->post(route('coordinacion.postulaciones.activar', $postulacion), [
        'fecha_inicio' => today()->subMonths(2)->toDateString(), 'fecha_fin' => today()->addMonths(2)->toDateString(),
    ])->assertSessionHasNoErrors();
    $practica = $postulacion->fresh()->practica;
    expect($practica->estado)->toBe(PracticaEstado::EnCurso);

    // ── 10. Reporte, validación y horas ─────────────────────────────────────────
    $this->actingAs($alumnoUser)->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => 'final', 'periodo_inicio' => today()->subMonths(2)->toDateString(), 'periodo_fin' => today()->toDateString(),
        'horas_reportadas' => 250, 'archivo' => e2ePdf('reporte.pdf'),
    ])->assertSessionHasNoErrors();
    $reporte = $practica->reportes()->firstOrFail();
    $this->actingAs($coord)->post(route('coordinacion.reportes.resolver', $reporte), ['estado' => 'validado'])->assertSessionHasNoErrors();
    expect($practica->fresh()->horasCompletas())->toBeTrue();

    // ── 11. Cierre: carta de término → oficio → Control Escolar → SIIAU ─────────
    $this->actingAs($alumnoUser)->post(route('alumno.documentos.termino', $practica), ['archivo' => e2ePdf('termino.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.cierres.carta', $practica), ['estado' => 'validada'])->assertSessionHasNoErrors();
    expect($practica->fresh()->estado)->toBe(PracticaEstado::EnCierre);
    $this->actingAs($coord)->post(route('coordinacion.cierres.oficio', $practica), ['archivo' => e2ePdf('oficio.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.cierres.enviar', $practica))->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.cierres.siiau', $practica))->assertSessionHasNoErrors();

    expect($practica->fresh()->estado)->toBe(PracticaEstado::Acreditada)
        ->and(Alumno::find($alumno->id)->yaAcreditado())->toBeTrue();
    // El alumno fue avisado en cada paso que le toca
    foreach (['Nueva vacante disponible', 'fue validada', 'registró tu carta de aceptación', 'quedó activa', 'quedó acreditada'] as $paso) {
        expect(e2eAvisos($alumnoUser, $paso))->toBeGreaterThanOrEqual(1, "Falta el aviso al alumno: {$paso}");
    }
    // y la empresa, de lo que le toca
    foreach (['aprobó tu vacante', 'se presentará', 'inició su práctica', 'concluyó y acreditó'] as $paso) {
        expect(e2eAvisos($empresaUser, $paso))->toBeGreaterThanOrEqual(1, "Falta el aviso a la empresa: {$paso}");
    }
    $this->actingAs($alumnoUser)->get(route('alumno.dashboard'))->assertOk();

    // ── Vinculación ve la empresa como vigente en su PDF y en el CSV ────────────
    expect($empresa->fresh()->situacion()['clave'])->toBe('vigente');
    $this->actingAs($lorena)->get(route('admin.empresas.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
    $csv = $this->actingAs($lorena)->get(route('admin.informes.exportar', ['tipo' => 'empresas', 'estado' => 'vigente']))->assertOk()->streamedContent();
    expect($csv)->toContain('Museo Regional A.C.')->toContain('Convenio vigente');
});

test('la vista previa muestra el PDF en línea y un archivo Word como ficha de descarga', function () {
    $alumnoUser = User::factory()->create();
    $alumnoUser->assignRole(Rol::Alumno->value);
    $div = \App\Models\Division::create(['nombre' => 'D']);
    $c = Carrera::create(['division_id' => $div->id, 'carrera_clave' => 'X', 'nombre' => 'X', 'horas_requeridas' => 1, 'porcentaje_creditos_minimo' => 1, 'creditos_otorgados' => 1]);
    $alumno = Alumno::create(['user_id' => $alumnoUser->id, 'carrera_id' => $c->id, 'num_control' => '1']);
    $pdf = $alumno->addMedia(e2ePdf('imss.pdf'))->toMediaCollection('constancia_imss');

    $this->actingAs($alumnoUser)->get(route('alumno.documentos.descargar', $pdf) . '?ver=1')
        ->assertOk()->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename="imss.pdf"');
    $this->actingAs($alumnoUser)->get(route('alumno.documentos.descargar', $pdf))
        ->assertOk()->assertHeader('content-disposition');
});

test('un envío que excede post_max_size regresa al formulario con un mensaje claro', function () {
    $u = User::factory()->create();
    $u->assignRole(Rol::Alumno->value);

    $this->actingAs($u)->from(route('alumno.mis-documentos'))
        ->call('POST', route('alumno.documentos.imss'), [], [], [], ['CONTENT_LENGTH' => 600 * 1024 * 1024, 'CONTENT_TYPE' => 'multipart/form-data; boundary=x'])
        ->assertRedirect(route('alumno.mis-documentos'))
        ->assertSessionHasErrors('archivo');
});
