<?php

/**
 * Aclaraciones del documento «Confirmación de sistema»:
 *  - La empresa captura sus declaraciones legales una vez y van solas al Formato de PP.
 *  - Duración pactada (cláusula UNDÉCIMA) dinámica y fin de vigencia calculado.
 *  - Casilla de SIIAU al postularse y convenios a punto de vencer sin postulaciones nuevas.
 *  - Baja o cambio de plaza con decisión sobre las horas.
 *  - Rechazo con instrucción de reiniciar el trámite y avisos de vencimiento por correo a Vinculación.
 */

use App\Actions\ActivarPractica;
use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Notifications\ConvenioActualizado;
use App\Services\ConvenioWorkflow;
use App\Support\Pendientes;
use App\Support\Requisitos;
use App\Support\TramiteAlumno;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function acUsuario(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

function acPdf(string $n = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($n, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function acTextoDocx(string $ruta): string
{
    $zip = new ZipArchive;
    $zip->open($ruta);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    return strip_tags(str_replace('</w:p>', "\n", $xml));
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);

    $div = Division::create(['nombre' => 'División de Estudios Jurídicos']);
    $this->abog = Carrera::create(['division_id' => $div->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Licenciatura en Abogado',
        'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);

    $this->coordUser = acUsuario(Rol::Coordinador);
    $this->coord = Personal::create(['user_id' => $this->coordUser->id, 'carrera_id' => $this->abog->id, 'puesto' => 'Coordinación']);
    $this->lorena = acUsuario(Rol::Vinculacion);
    $this->vinc = Personal::create(['user_id' => $this->lorena->id, 'puesto' => 'Vinculación']);

    $this->empresaUser = acUsuario(Rol::Empresa);
    $this->empresa = Empresa::create([
        'user_id' => $this->empresaUser->id, 'rfc' => 'ACL010101AB1', 'razon_social' => 'Aclaraciones S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto',
        'representante_legal' => 'Lic. Rosa Firma', 'representante_cargo' => 'Directora General',
        'domicilio_fiscal' => 'Av. Juárez 100, Guadalajara', 'constitucion' => 'la escritura pública 9876 ante el notario 12',
        'objeto_social' => 'la asesoría jurídica', 'facultad_firmante' => 'el poder notarial 4321',
    ]);

    $this->convenio = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-AC-001',
        'fecha_recepcion' => now()->subYear(), 'fecha_inicio_vigencia' => now()->subYear(), 'fecha_fin_vigencia' => now()->addYears(2),
        'objetivos' => 'x', 'actividades_propuestas' => 'Apoyo en litigio', 'plazas_estimadas' => 2,
    ]);
    $this->convenio->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);

    $this->programa = fn (Convenio $c) => Programa::create([
        'convenio_id' => $c->id, 'carrera_id' => $this->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Programa '.$c->id, 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 3, 'estado' => ProgramaEstado::Publicado,
    ]);

    $this->alumnoUser = acUsuario(Rol::Alumno);
    $this->alumno = Alumno::create(['user_id' => $this->alumnoUser->id, 'carrera_id' => $this->abog->id, 'num_control' => '219000001',
        'grado' => 7, 'turno' => 'matutino', 'vigencia_imss' => now()->addYears(2)->toDateString()]);
    $imss = $this->alumno->addMedia(acPdf('imss.pdf'))->toMediaCollection('constancia_imss');
    $this->alumno->resolverDocumento($imss, ValidacionEstado::Validada, null, $this->coordUser->id);
    $this->alumno = expedienteDe($this->alumno, 70);   // kardex + IMSS validados: ya puede postularse
});

test('el borrador usa las declaraciones que capturó la empresa y la duración pactada', function () {
    $convenio = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnValidacionAcademica,
        'fecha_recepcion' => today(), 'objetivos' => 'x', 'actividades_propuestas' => 'Apoyo', 'plazas_estimadas' => 1]);
    $convenio->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $convenio->seguimientos()->create(['etapa' => 'redaccion_borrador', 'estado' => 'pendiente', 'fecha_registro' => today()]);

    // Lorena no transcribe nada: solo elige la duración, la ciudad y la fecha de firma
    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador.generar', $convenio), [
        'vigencia_anios' => 4, 'lugar_firma' => 'Zapopan', 'fecha_firma' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    $docx = $convenio->refresh()->getFirstMedia('borrador_convenio');
    $texto = acTextoDocx($docx->getPath());

    expect($convenio->vigencia_anios)->toBe(4)
        ->and($docx->getCustomProperty('version'))->toBe(1)
        ->and($texto)->toContain('la escritura pública 9876 ante el notario 12')
        ->and($texto)->toContain('el poder notarial 4321')
        ->and($texto)->toContain('Directora General')
        ->and($texto)->toContain('ACL010101AB1')
        ->and($texto)->toContain('tendrá una vigencia por cuatro años')
        ->and($texto)->toContain('I. Que dentro de los objetivos');
});

test('al formalizar, la fecha de fin se calcula con la duración pactada', function () {
    Notification::fake();
    $c = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnFirmas, 'vigencia_anios' => 2,
        'fecha_recepcion' => today(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $c->seguimientos()->create(['etapa' => 'archivo_distribucion', 'estado' => 'pendiente', 'fecha_registro' => today()]);

    $this->actingAs($this->lorena)->post(route('admin.convenios.formalizar', $c), [
        'folio' => 'CV-AC-002', 'fecha_inicio_vigencia' => '2026-12-01', 'convenio_firmado' => acPdf('firmado.pdf'),
    ])->assertSessionHasNoErrors();

    $c->refresh();
    expect($c->estado)->toBe(ConvenioEstado::Formalizado)
        ->and($c->fecha_fin_vigencia->toDateString())->toBe('2028-11-30');
});

test('la empresa captura sus datos legales en Documentos', function () {
    $this->actingAs($this->empresaUser)->put(route('empresa.datos-legales'), [
        'representante_legal' => 'Ing. Nuevo Representante', 'representante_cargo' => 'Apoderado', 'domicilio_fiscal' => 'Calle 2 núm. 15, Guadalajara',
        ...datosNotarialesPrueba(), 'objeto_social' => 'servicios públicos de asesoría', 'facultad_firmante' => 'el reglamento interior vigente',
    ])->assertSessionHasNoErrors();

    expect($this->empresa->refresh()->representante_cargo)->toBe('Apoderado')
        ->and($this->empresa->escritura_numero)->toBe('1234')
        // La declaración del convenio se redacta sola con los datos notariales
        ->and($this->empresa->constitucion)->toContain('escritura pública número 1234')->toContain('Lic. Juan Notario, Notario Público número 5');
});

test('postularse exige confirmar SIIAU y un convenio con vigencia suficiente', function () {
    $programa = ($this->programa)($this->convenio);
    $datos = ['porcentaje_creditos_declarado' => 70, 'kardex' => acPdf('kardex.pdf')];

    $this->actingAs($this->alumnoUser)->post(route('alumno.oferta-programas.postular', $programa), $datos)
        ->assertSessionHasErrors('siiau');

    // Convenio que vence en 90 días: no se ofrece ni admite postulaciones
    $porVencer = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-AC-003',
        'fecha_recepcion' => now()->subYears(3), 'fecha_inicio_vigencia' => now()->subYears(3), 'fecha_fin_vigencia' => now()->addDays(90),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $porVencer->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $riesgo = ($this->programa)($porVencer);

    $this->actingAs($this->alumnoUser)->get(route('alumno.oferta-programas'))
        ->assertOk()->assertSee($programa->titulo)->assertDontSee($riesgo->titulo);
    $this->actingAs($this->alumnoUser)->post(route('alumno.oferta-programas.postular', $riesgo), $datos + ['siiau' => '1'])
        ->assertSessionHasErrors('postulacion');

    $this->actingAs($this->alumnoUser)->post(route('alumno.oferta-programas.postular', $programa), $datos + ['siiau' => '1'])
        ->assertSessionHasNoErrors();
    expect(Postulacion::where('programa_id', $programa->id)->first()->siiau_confirmado_at)->not->toBeNull();

    $this->actingAs($this->alumnoUser)->get(route('alumno.dashboard'))->assertOk()->assertSee('Agenda la materia en SIIAU');
});

test('la baja justificada con reportes validados (parcial acreditada) pasa las horas a la siguiente plaza', function () {
    Notification::fake();
    $programa = ($this->programa)($this->convenio);
    $po = Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonths(2),
        'fecha_fin' => today()->addMonths(3), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso]);
    Reporte::create(['practica_id' => $practica->id, 'tipo' => 'mensual', 'periodo_inicio' => today()->subMonth(), 'periodo_fin' => today(),
        'horas_reportadas' => 80, 'estado' => ReporteEstado::Validado]);

    $this->actingAs($this->coordUser)->post(route('coordinacion.practicas.cancelar', $practica), [])
        ->assertSessionHasErrors(['motivo_baja', 'detalle_baja']);

    $this->actingAs($this->coordUser)->post(route('coordinacion.practicas.cancelar', $practica), [
        'motivo_baja' => 'cierre_empresa', 'detalle_baja' => 'La organización cerró el área.',
    ])->assertSessionHasNoErrors();

    expect($practica->refresh()->estado)->toBe(PracticaEstado::Cancelada)
        ->and($this->alumno->fresh()->horasConservadas())->toBe(80)
        ->and(Requisitos::alumnoPostularse($this->alumno->fresh()))->toBeNull();
    Notification::assertSentTo($this->alumnoUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'Conservas tus 80 horas'));

    // Nueva plaza: la práctica arranca con las 80 horas reconocidas
    $otro = ($this->programa)($this->convenio);
    $po2 = Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $otro->id, 'estado' => PostulacionEstado::CartaAceptacionRecibida,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $po2->addMedia(acPdf('aceptacion.pdf'))->toMediaCollection('carta_aceptacion');
    $nueva = app(ActivarPractica::class)($po2, $this->coord, today(), today()->addMonths(4));

    expect($nueva->horas_reconocidas)->toBe(80)->and($nueva->horas_acumuladas)->toBe(80);
});

test('el rechazo pide reiniciar el trámite y el vencimiento avisa por correo a Vinculación', function () {
    Notification::fake();
    $c = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Solicitado,
        'fecha_recepcion' => today(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);

    app(ConvenioWorkflow::class)->rechazar($c, $this->vinc, 'Documentación incompleta.');
    Notification::assertSentTo($this->empresaUser, ConvenioActualizado::class, fn ($n) => str_contains($n->mensaje, 'inicia de nuevo el trámite'));

    $this->convenio->update(['fecha_fin_vigencia' => now()->addDays(45)]);
    $this->artisan('practicas:avisos-programados')->assertSuccessful();

    Notification::assertSentTo($this->lorena, AvisoOperativo::class, fn ($n) => $n->correo === true && str_contains($n->mensaje, 'vence'));
    Notification::assertSentTo($this->empresaUser, AvisoOperativo::class, fn ($n) => $n->correo === true);

    // Sin convenio vigente, la página explica que debe iniciar de nuevo
    $this->convenio->delete();
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()
        ->assertSee('Tu última solicitud fue rechazada')->assertSee('Puedes empezar el trámite de nuevo');
});

/* ------------------- Bajas: criterios de la Coordinación de Carrera ------------------- */

/** Práctica en curso del alumno con $horas validadas. */
function acPracticaConHoras($test, int $horas): Practica
{
    $programa = ($test->programa)($test->convenio);
    $po = Postulacion::create(['alumno_id' => $test->alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonths(2),
        'fecha_fin' => today()->addMonths(3), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso]);
    if ($horas > 0) {
        Reporte::create(['practica_id' => $practica->id, 'tipo' => 'mensual', 'periodo_inicio' => today()->subMonth(), 'periodo_fin' => today(),
            'horas_reportadas' => $horas, 'estado' => ReporteEstado::Validado]);
    }

    return $practica;
}

test('la baja sin reportes validados pierde las horas y deja postularse de nuevo', function () {
    Notification::fake();
    $practica = acPracticaConHoras($this, 0);

    $this->actingAs($this->coordUser)->post(route('coordinacion.practicas.cancelar', $practica), [
        'motivo_baja' => 'cambio_proyecto', 'detalle_baja' => 'El proyecto terminó antes del primer reporte.',
    ])->assertSessionHasNoErrors();

    expect($practica->refresh()->tipo_baja)->toBe('sin_reportes')
        ->and($practica->fecha_baja)->not->toBeNull()
        ->and($this->alumno->fresh()->horasConservadas())->toBe(0)
        ->and($this->alumno->fresh()->estaBloqueado())->toBeFalse()
        ->and(Requisitos::alumnoPostularse($this->alumno->fresh()))->toBeNull();
    Notification::assertSentTo($this->alumnoUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'no tenías reportes validados'));
});

test('la baja por sanción invalida las horas, bloquea al alumno y solo la coordinación lo desbloquea', function () {
    Notification::fake();
    $practica = acPracticaConHoras($this, 120);

    $this->actingAs($this->coordUser)->post(route('coordinacion.practicas.cancelar', $practica), [
        'motivo_baja' => 'abandono', 'detalle_baja' => 'Dejó de asistir sin avisar.',
    ])->assertSessionHasNoErrors();

    $alumno = $this->alumno->fresh();
    expect($practica->refresh()->tipo_baja)->toBe('sancion')
        ->and($alumno->horasConservadas())->toBe(0)
        ->and($alumno->motivoBloqueo())->toBe('sancion')
        ->and(Requisitos::alumnoPostularse($alumno))->toContain('sanción');

    // No puede postularse por su cuenta y su panel lo explica
    $otro = ($this->programa)($this->convenio);
    $this->actingAs($this->alumnoUser)->post(route('alumno.oferta-programas.postular', $otro), ['siiau' => '1'])
        ->assertSessionHasErrors('postulacion');
    $this->actingAs($this->alumnoUser)->get(route('alumno.dashboard'))->assertOk()->assertSee('No puedes postularte por ahora');

    // La coordinación lo ve en «Alumnos bloqueados» y hace un desbloqueo excepcional justificado
    $this->actingAs($this->coordUser)->get(route('coordinacion.lista-practicantes'))->assertOk()->assertSee('Alumnos bloqueados');
    $this->actingAs($this->coordUser)->post(route('coordinacion.alumnos.desbloquear', $alumno), [])
        ->assertSessionHasErrors('justificacion');
    $this->actingAs($this->coordUser)->post(route('coordinacion.alumnos.desbloquear', $alumno), [
        'justificacion' => 'La organización reconoció un error en el reporte de asistencia.',
    ])->assertSessionHasNoErrors();

    expect($alumno->fresh()->estaBloqueado())->toBeFalse()
        ->and($alumno->fresh()->desbloqueo_justificacion)->toContain('error en el reporte');
    Notification::assertSentTo($this->alumnoUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'levantó la restricción'));
});

test('al llegar a 2 bajas el alumno queda bloqueado para una tercera postulación', function () {
    Notification::fake();
    $primera = acPracticaConHoras($this, 0);
    $this->actingAs($this->coordUser)->post(route('coordinacion.practicas.cancelar', $primera), [
        'motivo_baja' => 'cambio_proyecto', 'detalle_baja' => 'Cambio de proyecto.',
    ])->assertSessionHasNoErrors();
    expect($this->alumno->fresh()->estaBloqueado())->toBeFalse();

    $segunda = acPracticaConHoras($this, 50);
    $this->actingAs($this->coordUser)->post(route('coordinacion.practicas.cancelar', $segunda), [
        'motivo_baja' => 'cierre_empresa', 'detalle_baja' => 'Cerró la organización.',
    ])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'quedó bloqueado'));

    $alumno = $this->alumno->fresh();
    expect($alumno->motivoBloqueo())->toBe('limite')
        ->and($alumno->horasConservadas())->toBe(50)   // la baja justificada conserva sus horas aunque quede bloqueado
        ->and(Requisitos::alumnoVerOferta($alumno))->toContain('límite de 2 bajas');
    Notification::assertSentTo($this->alumnoUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'límite de 2 bajas'));
});

test('el panel, mis documentos y la campana muestran la etapa real del alumno', function () {
    $programa = ($this->programa)($this->convenio);
    Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::CartaAceptacionRecibida,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);

    $tramite = TramiteAlumno::de($this->alumno->fresh());
    expect($tramite->paso())->toBe(4)
        ->and($tramite->situacion()['titulo'])->toBe('Tu carta de aceptación está por validarse');

    // Avisos ya no dice «Kardex e IMSS validados» cuando el alumno va en las cartas
    $this->actingAs($this->alumnoUser)->get(route('alumno.dashboard'))->assertOk()
        ->assertSee('Tu carta de aceptación está por validarse')->assertDontSee('Kardex e IMSS validados')
        ->assertSee('Cartas de presentación y aceptación');
    $this->actingAs($this->alumnoUser)->get(route('alumno.mis-documentos'))->assertOk()->assertSee('Cartas de presentación y aceptación');

    // «Por atender» vive en el panel, no en la campana
    expect(Pendientes::de($this->alumnoUser)[0]['mensaje'])->toContain('Tu carta de aceptación está por validarse');
    $this->actingAs($this->alumnoUser)->getJson(route('notificaciones.resumen'))->assertOk()->assertJsonMissingPath('pendientes');
});

test('la campana avisa al alumno que su coordinación le devolvió un documento aunque no tenga notificaciones', function () {
    // El alumno envía una constancia nueva (pendiente) y su coordinación se la devuelve
    $alumno = $this->alumno->fresh();
    $imss = $alumno->addMedia(acPdf('imss-nueva.pdf'))->toMediaCollection('constancia_imss');
    $alumno->resolverDocumento($imss, ValidacionEstado::Rechazada, 'Está vencida.', $this->coordUser->id);

    expect(Pendientes::de($this->alumnoUser)[0]['tipo'])->toBe('error');
    $this->actingAs($this->alumnoUser)->get(route('alumno.dashboard'))->assertOk()
        ->assertSee('Por atender')->assertSee('te devolvió tu constancia IMSS')->assertSee('Está vencida');
});

/* ------------------- Proceso de Vinculación (documento «PROCESO VINCU») ------------------- */

/** Solicitud nueva de la empresa con su expediente completo, aún sin revisar. */
function acSolicitudConExpediente($test): Convenio
{
    $convenio = app(ConvenioWorkflow::class)->solicitar($test->empresa, [
        'objetivos' => 'Formar practicantes', 'actividades_propuestas' => 'Apoyo en audiencias', 'plazas_estimadas' => 2,
    ], [$test->abog->id]);
    foreach (Empresa::coleccionesObligatorias() as $col) {
        $test->empresa->addMedia(acPdf("{$col}.pdf"))->toMediaCollection($col);
    }

    return $convenio;
}

test('al aprobar el último documento la propuesta llega sola a la coordinación de la carrera', function () {
    Notification::fake();
    $convenio = acSolicitudConExpediente($this);
    $media = $this->empresa->fresh()->media;

    foreach ($media->slice(0, -1) as $m) {
        $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $m]), ['estado' => 'validada'])
            ->assertSessionHas('status', 'Documento aprobado.');
    }
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::Solicitado);

    $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $media->last()]), ['estado' => 'validada'])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'se envió a las coordinaciones'));

    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnValidacionAcademica);
    Notification::assertSentTo($this->coordUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'emite el dictamen de pertinencia'));
});

test('la coordinación vota con su dictamen escaneado firmado y sellado', function () {
    Notification::fake();
    $convenio = acSolicitudConExpediente($this);
    foreach ($this->empresa->fresh()->media as $m) {
        $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $m]), ['estado' => 'validada']);
    }
    $dictamen = $convenio->dictamenes()->first();

    $this->actingAs($this->coordUser)->post(route('coordinacion.dictamenes.registrar', $dictamen), ['dictamen' => 'aprobatorio'])
        ->assertSessionHasErrors(['documento' => 'Sube tu dictamen escaneado, firmado y sellado (PDF).']);

    $this->actingAs($this->coordUser)->post(route('coordinacion.dictamenes.registrar', $dictamen), ['dictamen' => 'aprobatorio', 'documento' => acPdf('dictamen.pdf')])
        ->assertSessionHasNoErrors();

    expect($dictamen->fresh()->dictamen)->toBe(Dictamen::Aprobatorio)
        ->and($dictamen->fresh()->getMedia('dictamen_pertinencia'))->toHaveCount(1);
    // Lorena lo ve (y lo descarga) en el convenio; ella no califica
    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $convenio))->assertOk()
        ->assertSee('Dictamen académico por carrera')->assertSee('Dictamen firmado');
});

test('la bandeja de Vinculación usa nombres propios: por revisar, en mi oficina y sin PDF duplicado en informes', function () {
    acSolicitudConExpediente($this);

    $this->actingAs($this->lorena)->get(route('admin.lista-convenios'))->assertOk()
        ->assertSee('Por revisar')->assertSee('Te toca a ti')->assertSee('Atender');
    $this->actingAs($this->lorena)->get(route('admin.documentos-fisicos'))->assertOk()
        ->assertSee('En mi oficina')->assertDontSee('En Vinculación');
    $this->actingAs($this->lorena)->get(route('admin.informes'))->assertOk()->assertDontSee('PDF por situación');
});

test('tras el rechazo total la empresa ve el motivo por carrera y una nueva propuesta precargada', function () {
    Notification::fake();
    $this->convenio->update(['estado' => ConvenioEstado::Rechazado, 'fecha_fin_vigencia' => null, 'fecha_inicio_vigencia' => null]);
    $this->convenio->dictamenes()->update(['dictamen' => Dictamen::Negativo->value, 'observaciones' => 'Las actividades no son afines a Derecho.']);

    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()
        ->assertSee('Tu última solicitud fue rechazada')
        ->assertSee('Las actividades no son afines a Derecho.')
        ->assertSee('Nueva propuesta de convenio')
        ->assertSee('Apoyo en litigio');   // actividades de la propuesta anterior, para ajustarlas
});

/* ------------------- Paneles: pendientes reales, informes y acciones con sentido ------------------- */

test('el saludo del panel de coordinación suma lo mismo que la campana', function () {
    $programa = ($this->programa)($this->convenio);
    Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::Pendiente,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);

    $total = Pendientes::total($this->coordUser);
    $items = Pendientes::de($this->coordUser);

    expect($total)->toBe(1)->and(array_sum(array_column($items, 'n')))->toBe($total);
    $this->actingAs($this->coordUser)->get(route('coordinacion.dashboard'))->assertOk()
        ->assertSee('Tienes 1 pendiente por atender');
});

test('Vinculación descarga el informe de documentos físicos con los filtros de la pantalla', function () {
    $c = acSolicitudConExpediente($this);
    foreach ($this->empresa->fresh()->media as $m) {
        $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.resolver', [$c, $m]), ['estado' => 'validada']);
    }
    // El turno a las carreras es digital: el primer papel aparece cuando el expediente va a la Unidad Jurídica
    expect($c->documentosFisicos()->count())->toBe(0);
    $c->update(['estado' => ConvenioEstado::EnRevisionJuridica]);
    $c->prepararDocumentosFisicos($this->lorena->personal);
    expect($c->documentosFisicos()->count())->toBeGreaterThan(0);

    $this->actingAs($this->lorena)->get(route('admin.documentos-fisicos'))->assertOk()
        ->assertSee('Descargar informe')->assertSee('Calendario de regresos');

    $csv = $this->actingAs($this->lorena)->get(route('admin.informes.exportar', ['tipo' => 'documentos-fisicos', 'vista' => 'activos']))
        ->assertOk()->streamedContent();
    expect($csv)->toContain('Organización,"Folio del convenio",Papel')->toContain('Aclaraciones S.C.');

    // La coordinación no exporta papeles de Vinculación
    $this->actingAs($this->coordUser)->get(route('admin.informes.exportar', ['tipo' => 'documentos-fisicos']))->assertForbidden();
});

test('un programa con postulaciones no regresa a borrador; se cierra', function () {
    $interno = Programa::create(['convenio_id' => null, 'carrera_id' => $this->abog->id, 'modalidad' => 'interna', 'ciclo' => '2027A',
        'titulo' => 'Clínica jurídica', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 3, 'estado' => ProgramaEstado::Publicado]);

    // Sin postulaciones: se puede despublicar para corregir
    $this->actingAs($this->coordUser)->post(route('coordinacion.programas.estado', $interno), ['estado' => 'borrador'])->assertSessionHasNoErrors();
    $interno->refresh()->update(['estado' => ProgramaEstado::Publicado]);

    Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $interno->id, 'estado' => PostulacionEstado::Pendiente,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $this->actingAs($this->coordUser)->post(route('coordinacion.programas.estado', $interno), ['estado' => 'borrador'])
        ->assertSessionHasErrors('programa');
    expect($interno->fresh()->estado)->toBe(ProgramaEstado::Publicado);
    $this->actingAs($this->coordUser)->get(route('coordinacion.lista-programas'))->assertOk()->assertDontSee('Despublicar para corregir');
});

test('los reportes se filtran por vacantes internas o de organizaciones externas', function () {
    $externa = acPracticaConHoras($this, 10);
    $this->actingAs($this->coordUser)->get(route('coordinacion.lista-reportes', ['origen' => 'interno']))->assertOk()
        ->assertSee('Proyectos internos')->assertViewHas('reportes', fn ($r) => $r->total() === 0);
    $this->actingAs($this->coordUser)->get(route('coordinacion.lista-reportes', ['origen' => 'externo']))->assertOk()
        ->assertViewHas('reportes', fn ($r) => $r->total() === 1 && $r->first()->practica_id === $externa->id);
});

test('lo validado no se rechaza en ningún panel', function () {
    $alumno = $this->alumno->fresh();
    expect(fn () => $alumno->resolverDocumento($alumno->documentoVigente('kardex'), ValidacionEstado::Rechazada, 'Ya no sirve.', $this->coordUser->id))
        ->toThrow(ValidationException::class);
    expect($alumno->fresh()->documentoValidado('kardex'))->toBeTrue();
});
/* ------------------- Procedimiento de la empresa (documento «PROCESO EMPRESA») ------------------- */

test('Lorena valida varios documentos a la vez y lo validado ya no se rechaza', function () {
    Notification::fake();
    $convenio = acSolicitudConExpediente($this);
    $media = $this->empresa->fresh()->media;

    // Elige dos al azar y los valida juntos
    $dos = $media->shuffle()->take(2);
    $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.validar', $convenio), ['documentos' => $dos->pluck('id')->all()])
        ->assertSessionHas('status', 'Validaste 2 documento(s).');
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::Solicitado);

    // Uno ya validado no se puede rechazar
    $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $dos->first()]), ['estado' => 'rechazada', 'motivo' => 'Está borroso, súbelo de nuevo.'])
        ->assertSessionHasErrors('documento');
    expect($dos->first()->fresh()->getCustomProperty('estado'))->toBe('validada');

    // El resto: al completar el expediente se envía solo a las carreras
    $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.validar', $convenio), ['documentos' => $media->pluck('id')->all()])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'se envió a las coordinaciones'));
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnValidacionAcademica);
});

test('la empresa envía su expediente en un solo paso: solo PDF de hasta 5 MB', function () {
    $this->convenio->update(['estado' => ConvenioEstado::Rechazado]);   // sin trámite en curso: puede subir
    $pdf = fn (string $n) => acPdf($n);

    // Una imagen no pasa, ni un PDF de más de 5 MB, ni una constancia vieja
    $this->actingAs($this->empresaUser)->post(route('empresa.documentos.subir'), [
        'archivos' => [
            'acta_constitutiva' => UploadedFile::fake()->image('acta.jpg'),
            'identificacion_representante' => UploadedFile::fake()->create('ine.pdf', 6000, 'application/pdf'),
            'constancia_situacion_fiscal' => $pdf('csf.pdf'),
        ],
    ])->assertSessionHasErrors(['archivos.acta_constitutiva', 'archivos.identificacion_representante']);
    expect($this->empresa->fresh()->media)->toHaveCount(0);   // nada se guarda si algo falla

    $this->actingAs($this->empresaUser)->post(route('empresa.documentos.subir'), [
        'archivos' => ['acta_constitutiva' => $pdf('acta.pdf'), 'constancia_situacion_fiscal' => $pdf('csf.pdf')],
    ])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Enviamos 2 documentos'));
    expect($this->empresa->fresh()->documentoVigente('constancia_situacion_fiscal'))->not->toBeNull();
});

test('la solicitud se llena por secciones con borrador y no se envía incompleta', function () {
    $this->convenio->update(['estado' => ConvenioEstado::Rechazado]);
    $this->empresa->update(['representante_legal' => null, 'giro' => null]);

    // Guardar avance con datos a medias sí se puede; enviar no
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'perfiles'), ['carreras' => [$this->abog->id], 'objetivos' => 'Formar practicantes en litigio.'])
        ->assertSessionHasNoErrors()->assertRedirect(route('empresa.convenio', ['seccion' => 'perfiles']));
    expect($this->empresa->fresh()->solicitud_borrador['carreras'])->toBe([$this->abog->id]);
    $this->actingAs($this->empresaUser)->post(route('empresa.convenio.solicitar'))->assertSessionHasErrors('convenio');

    // Formatos: código postal y estado
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'generales'), ['razon_social' => 'Aclaraciones S.C.', 'contacto_nombre' => 'Ana Contacto', 'codigo_postal' => '441', 'entidad' => 'Narnia'])
        ->assertSessionHasErrors(['codigo_postal', 'entidad']);

    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()
        ->assertSee('Datos generales y fiscales')->assertSee('Declaraciones legales')->assertSee('Perfiles requeridos')->assertSee('Documentos en PDF');
});

test('Lorena devuelve con un motivo corto y le avisan cuando la empresa corrige ese documento', function () {
    Notification::fake();
    $convenio = acSolicitudConExpediente($this);
    $acta = $this->empresa->fresh()->documentoVigente('acta_constitutiva');

    // Sin motivo no se devuelve; «borroso» basta
    $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $acta]), ['estado' => 'rechazada', 'motivo' => '   '])
        ->assertSessionHasErrors(['motivo' => 'Escribe el motivo para que la empresa sepa qué corregir.']);
    $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $acta]), ['estado' => 'rechazada', 'motivo' => 'borroso'])
        ->assertSessionHasNoErrors();

    // Uno por uno: valida otro documento cualquiera
    $otro = $this->empresa->fresh()->documentoVigente('comprobante_domicilio');
    $this->actingAs($this->lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $otro]), ['estado' => 'validada'])->assertSessionHasNoErrors();

    // La empresa corrige el acta: a Lorena le llega un aviso claro que lleva al expediente
    $this->actingAs($this->empresaUser)->post(route('empresa.documentos.subir'), ['archivos' => ['acta_constitutiva' => acPdf('acta-legible.pdf')]])->assertSessionHasNoErrors();
    Notification::assertSentTo($this->lorena, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'Aclaraciones S.C. corrigió «Acta constitutiva»')
        && str_ends_with($n->url, '/admin/convenios/'.$convenio->id.'#expediente'));
});

test('con la solicitud en revisión la empresa sube lo que falta, pero no cambia lo que Vinculación revisa', function () {
    $convenio = app(ConvenioWorkflow::class)->solicitar($this->empresa, [
        'objetivos' => 'Formar practicantes', 'actividades_propuestas' => 'Apoyo en audiencias', 'plazas_estimadas' => 2,
    ], [$this->abog->id]);
    $this->empresa->addMedia(acPdf('acta.pdf'))->toMediaCollection('acta_constitutiva');   // en revisión

    $this->actingAs($this->empresaUser)->post(route('empresa.documentos.subir'), ['archivos' => ['acta_constitutiva' => acPdf('acta2.pdf')]])
        ->assertSessionHasErrors('archivos.acta_constitutiva');
    $this->actingAs($this->empresaUser)->post(route('empresa.documentos.subir'), ['archivos' => ['comprobante_domicilio' => acPdf('cd.pdf')]])
        ->assertSessionHasNoErrors();
    expect($this->empresa->fresh()->documentoVigente('comprobante_domicilio'))->not->toBeNull();
});
