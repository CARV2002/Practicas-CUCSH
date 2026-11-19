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
        'name' => 'Lorena Vinculación', 'email' => 'lorena@cucsh.test', 'rol' => Rol::Vinculacion->value,
        'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026',
    ])->assertSessionHasNoErrors();
    $lorena = User::where('email', 'lorena@cucsh.test')->firstOrFail();

    // ── 2. Alumno: se registra y sube su IMSS cuando su carrera AÚN NO tiene coordinación ──
    auth()->logout();
    $this->post('/registro/alumno', [
        'name' => 'Carlos Moreno', 'email' => 'carlos@alumnos.udg.mx', 'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026',
        'carrera_id' => $carrera->id, 'num_control' => '219701473', 'grado' => 8, 'turno' => 'matutino', 'aviso_privacidad' => '1',
    ])->assertSessionHasNoErrors();
    $alumnoUser = User::where('email', 'carlos@alumnos.udg.mx')->firstOrFail();
    Notification::assertSentTo($alumnoUser, VerifyEmail::class);   // confirmación de correo
    $alumnoUser->markEmailAsVerified();                             // (el alumno abre el enlace)
    $alumno = $alumnoUser->alumno;

    $this->actingAs($alumnoUser)->post(route('alumno.documentos.imss'), [
        'archivo' => e2ePdf('imss.pdf'), 'vigencia_imss' => now()->addYear()->toDateString(),
    ])->assertSessionHasNoErrors();

    // Sin coordinación, el aviso no se pierde: lo recibe el administrador
    expect(e2eAvisos($this->admin, 'no tiene coordinación activa'))->toBe(1);
    $this->actingAs($alumnoUser)->get(route('alumno.dashboard'))->assertOk()->assertSee('Tu coordinación aún no está en la plataforma');

    // ── 3. Administrador crea la coordinación: hereda el pendiente ──────────────
    $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
        'name' => 'Coordinación de Antropología', 'email' => 'antropologia@cucsh.test', 'rol' => Rol::Coordinador->value,
        'carrera_id' => $carrera->id, 'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026',
    ])->assertSessionHasNoErrors();
    $coord = User::where('email', 'antropologia@cucsh.test')->firstOrFail();
    expect(e2eAvisos($coord, '1 constancia(s) IMSS por revisar'))->toBe(1);

    // Ve el IMSS en su panel aunque el alumno no se haya postulado, y lo valida
    $this->actingAs($coord)->get(route('coordinacion.lista-practicantes'))->assertOk()
        ->assertSee('Constancias IMSS por revisar')->assertSee('Carlos Moreno');
    $imss = $alumno->documentoVigente('constancia_imss');
    $this->actingAs($coord)->post(route('coordinacion.documentos.resolver', $imss), ['estado' => 'validada'])->assertSessionHasNoErrors();
    expect($alumno->fresh()->documentoValidado('constancia_imss'))->toBeTrue();

    // ── 4. Empresa: registro con datos legales, expediente y solicitud ──────────
    auth()->logout();
    $this->post('/registro/empresa', [
        'name' => 'Ana Contacto', 'contacto_cargo' => 'RH', 'email' => 'rh@museo.test',
        'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026',
        'razon_social' => 'Museo Regional A.C.', 'rfc' => 'MRE010101AB1', 'tipo_organismo' => 'sociedad_civil',
        'representante_legal' => 'Mtra. Elena Firma', 'representante_cargo' => 'Directora General',
        'domicilio_fiscal' => 'Av. Hidalgo 100, Guadalajara', 'constitucion' => 'la escritura pública 777 ante el notario 3',
        'objeto_social' => 'la difusión del patrimonio cultural', 'facultad_firmante' => 'el poder notarial 888',
        'aviso_privacidad' => '1',
    ])->assertSessionHasNoErrors();
    $empresaUser = User::where('email', 'rh@museo.test')->firstOrFail();
    $empresaUser->markEmailAsVerified();
    $empresa = $empresaUser->empresa;

    foreach (Empresa::coleccionesObligatorias() as $col) {
        $this->actingAs($empresaUser->fresh())->post(route('empresa.documentos.subir'), ['coleccion' => $col, 'archivo' => e2ePdf("$col.pdf")])->assertSessionHasNoErrors();
    }
    $empresaUser = $empresaUser->fresh();   // cada petición real recarga al usuario y sus archivos
    $this->actingAs($empresaUser)->post(route('empresa.convenio.solicitar'), [
        'objetivos' => 'Vincular estudiantes con proyectos de museografía', 'actividades_propuestas' => 'Catalogación y guía de visitas',
        'plazas_estimadas' => 3, 'carreras' => [$carrera->id],
    ])->assertSessionHasNoErrors();
    $convenio = Convenio::where('empresa_id', $empresa->id)->firstOrFail();
    Notification::assertSentTo($lorena, NuevaSolicitudConvenio::class);

    // ── 5. Vinculación valida el expediente y turna a la carrera ────────────────
    foreach ($empresa->fresh()->media as $media) {
        $this->actingAs($lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $media]), ['estado' => 'validada'])->assertSessionHasNoErrors();
    }
    $this->actingAs($lorena)->post(route('admin.convenios.validacion-academica', $convenio))->assertSessionHasNoErrors();
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnValidacionAcademica);
    expect(e2eAvisos($coord, 'emite el dictamen de pertinencia'))->toBe(1);

    $this->actingAs($lorena)->post(route('admin.convenios.turnos', $convenio), [
        'fecha_turno_secretaria' => today()->toDateString(), 'fecha_turno_division' => today()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($convenio->documentosFisicos()->where('tipo', TF::OficioTurno->value)->first()->estado)->toBe(EF::EnDivision);

    // ── 6. Coordinación dictamina ───────────────────────────────────────────────
    $dictamen = ConvenioCarrera::where('convenio_id', $convenio->id)->firstOrFail();
    $this->actingAs($coord)->post(route('coordinacion.dictamenes.registrar', $dictamen), ['dictamen' => 'aprobatorio'])->assertSessionHasNoErrors();
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

    $this->actingAs($lorena)->post(route('admin.convenios.firmas', $convenio), ['etapa' => 'firma_empresa'])->assertSessionHasNoErrors();
    $this->actingAs($lorena)->post(route('admin.convenios.firmas', $convenio), ['etapa' => 'firma_rectoria'])->assertSessionHasNoErrors();
    expect($convenio->documentosFisicos()->where('estado', EF::Firmado->value)->count())->toBe(3);

    $this->actingAs($lorena)->post(route('admin.convenios.formalizar', $convenio), [
        'folio' => 'CV-ANTR-001', 'fecha_inicio_vigencia' => today()->subMonths(3)->toDateString(), 'convenio_firmado' => e2ePdf('firmado.pdf'),
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

    // ── 9. Alumno se postula (SIIAU), carta de presentación y aceptación ────────
    $this->actingAs($alumnoUser)->get(route('alumno.oferta-programas'))->assertSee('Auxiliar de museografía');
    $this->actingAs($alumnoUser)->post(route('alumno.oferta-programas.postular', $programa), [
        'porcentaje_creditos_declarado' => 75, 'kardex' => e2ePdf('kardex.pdf'), 'siiau' => '1',
    ])->assertSessionHasNoErrors();
    $postulacion = Postulacion::firstOrFail();
    expect(e2eAvisos($coord, 'se postuló'))->toBe(1);

    $this->actingAs($coord)->post(route('coordinacion.postulaciones.carta', $postulacion))->assertSessionHasNoErrors();
    expect($postulacion->fresh()->estado)->toBe(PostulacionEstado::CartaPresentacionEmitida);

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
