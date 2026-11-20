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
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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
    $zip = new ZipArchive();
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
        'titulo' => 'Programa ' . $c->id, 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
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
        'representante_legal' => 'Ing. Nuevo', 'representante_cargo' => 'Apoderado', 'domicilio_fiscal' => 'Calle 2',
        'constitucion' => 'decreto del 1 de enero', 'objeto_social' => 'servicios públicos', 'facultad_firmante' => 'reglamento interior',
    ])->assertSessionHasNoErrors();

    expect($this->empresa->refresh()->representante_cargo)->toBe('Apoderado')
        ->and($this->empresa->constitucion)->toBe('decreto del 1 de enero');
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
        ->and(\App\Support\Requisitos::alumnoPostularse($this->alumno->fresh()))->toBeNull();
    Notification::assertSentTo($this->alumnoUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'Conservas tus 80 horas'));

    // Nueva plaza: la práctica arranca con las 80 horas reconocidas
    $otro = ($this->programa)($this->convenio);
    $po2 = Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $otro->id, 'estado' => PostulacionEstado::CartaAceptacionRecibida,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $nueva = app(ActivarPractica::class)($po2, $this->coord, today(), today()->addMonths(4));

    expect($nueva->horas_reconocidas)->toBe(80)->and($nueva->horas_acumuladas)->toBe(80);
});

test('el rechazo pide reiniciar el trámite y el vencimiento avisa por correo a Vinculación', function () {
    Notification::fake();
    $c = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Solicitado,
        'fecha_recepcion' => today(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);

    app(ConvenioWorkflow::class)->rechazar($c, $this->vinc, 'Documentación incompleta.');
    Notification::assertSentTo($this->empresaUser, ConvenioActualizado::class, fn ($n) => str_contains($n->mensaje, 'iniciar de nuevo todo el trámite'));

    $this->convenio->update(['fecha_fin_vigencia' => now()->addDays(45)]);
    $this->artisan('practicas:avisos-programados')->assertSuccessful();

    Notification::assertSentTo($this->lorena, AvisoOperativo::class, fn ($n) => $n->correo === true && str_contains($n->mensaje, 'vence'));
    Notification::assertSentTo($this->empresaUser, AvisoOperativo::class, fn ($n) => $n->correo === true);

    // Sin convenio vigente, la página explica que debe iniciar de nuevo
    $this->convenio->delete();
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()
        ->assertSee('Tu última solicitud fue rechazada')->assertSee('iniciar de nuevo todo el trámite');
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
        ->and(\App\Support\Requisitos::alumnoPostularse($this->alumno->fresh()))->toBeNull();
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
        ->and(\App\Support\Requisitos::alumnoPostularse($alumno))->toContain('sanción');

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
        ->and(\App\Support\Requisitos::alumnoVerOferta($alumno))->toContain('límite de 2 bajas');
    Notification::assertSentTo($this->alumnoUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'límite de 2 bajas'));
});

test('el panel, mis documentos y la campana muestran la etapa real del alumno', function () {
    $programa = ($this->programa)($this->convenio);
    Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::CartaAceptacionRecibida,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);

    $tramite = \App\Support\TramiteAlumno::de($this->alumno->fresh());
    expect($tramite->paso())->toBe(4)
        ->and($tramite->situacion()['titulo'])->toBe('Tu carta de aceptación quedó registrada');

    // Avisos ya no dice «Kardex e IMSS validados» cuando el alumno va en las cartas
    $this->actingAs($this->alumnoUser)->get(route('alumno.dashboard'))->assertOk()
        ->assertSee('Tu carta de aceptación quedó registrada')->assertDontSee('Kardex e IMSS validados')
        ->assertSee('Cartas de presentación y aceptación');
    $this->actingAs($this->alumnoUser)->get(route('alumno.mis-documentos'))->assertOk()->assertSee('Cartas de presentación y aceptación');

    $this->actingAs($this->alumnoUser)->getJson(route('notificaciones.resumen'))->assertOk()
        ->assertJsonPath('pendientes.0.mensaje', fn ($m) => str_contains($m, 'Tu carta de aceptación quedó registrada'));
});

test('la campana avisa al alumno que su coordinación le devolvió un documento aunque no tenga notificaciones', function () {
    $alumno = $this->alumno->fresh();
    $imss = $alumno->documentoVigente('constancia_imss');
    $alumno->resolverDocumento($imss, ValidacionEstado::Rechazada, 'Está vencida.', $this->coordUser->id);

    $this->actingAs($this->alumnoUser)->getJson(route('notificaciones.resumen'))->assertOk()
        ->assertJsonPath('pendientes.0.tipo', 'error')
        ->assertJsonPath('pendientes.0.mensaje', fn ($m) => str_contains($m, 'te devolvió tu constancia IMSS') && str_contains($m, 'Está vencida'));
});
