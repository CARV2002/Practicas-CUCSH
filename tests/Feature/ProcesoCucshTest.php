<?php

/**
 * Paso 2: adaptación al proceso real del CUCSH (respuestas de la Fase 0).
 *  - La Coordinación aprueba las vacantes de la empresa antes de publicarlas.
 *  - Expediente del alumno: grado, turno, IMSS validado y Carta de Presentación generada.
 *  - Reportes según el esquema de cada carrera y oficio de aceptación interno.
 *  - Dictamen emitido por cada Coordinación y turnos de Secretaría/División.
 *  - Representante legal, convenios vigentes y «Tu coordinación».
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EsquemaReportes;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Enums\Turno;
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
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Services\GeneradorCartaPresentacion;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

function pcPdf(string $nombre = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function pcUsuario(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

function pcCoordinador(Carrera $carrera): User
{
    $u = pcUsuario(Rol::Coordinador);
    Personal::create(['user_id' => $u->id, 'carrera_id' => $carrera->id, 'puesto' => "Coordinación {$carrera->carrera_clave}"]);

    return $u;
}

function pcAlumno(Carrera $carrera, string $codigo, bool $imssValidado = true): Alumno
{
    $u = pcUsuario(Rol::Alumno);
    $a = Alumno::create([
        'user_id' => $u->id, 'carrera_id' => $carrera->id, 'num_control' => $codigo,
        'grado' => 7, 'turno' => 'vespertino', 'vigencia_imss' => now()->addYear()->toDateString(),
    ]);

    $a->addMedia(pcPdf('imss.pdf'))->toMediaCollection('constancia_imss');
    if ($imssValidado) {
        // Expediente completo (kardex + IMSS) validado: requisito para postularse y hacer prácticas
        $a = expedienteDe($a, 70);
    }

    return $a;
}

/** Texto plano de un .docx (para revisar el contenido de la carta). */
function pcTextoDocx(string $ruta): string
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
    $datos = ['division_id' => $div->id, 'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8];
    $this->abog = Carrera::create($datos + ['carrera_clave' => 'ABOG', 'nombre' => 'Licenciatura en Abogado']);
    $this->crim = Carrera::create($datos + ['carrera_clave' => 'CRIM', 'nombre' => 'Licenciatura en Criminología']);

    $this->coordAbog = pcCoordinador($this->abog);
    $this->coordCrim = pcCoordinador($this->crim);

    $this->vinculacion = pcUsuario(Rol::Vinculacion);
    Personal::create(['user_id' => $this->vinculacion->id, 'puesto' => 'Vinculación']);

    $this->empresaUser = pcUsuario(Rol::Empresa);
    $this->empresa = Empresa::create([
        'user_id' => $this->empresaUser->id, 'rfc' => 'EPC010101AB1', 'razon_social' => 'Despacho Ejemplo S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto', 'representante_legal' => 'Lic. Rosa Firma',
    ]);

    $this->convenio = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-PC-001',
        'fecha_recepcion' => now()->subMonths(3), 'fecha_inicio_vigencia' => now()->subMonth(),
        'fecha_fin_vigencia' => now()->addYears(3), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 5,
    ]);
    $this->convenio->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);

    $this->vacante = [
        'convenio_id' => $this->convenio->id, 'carrera_id' => $this->abog->id, 'ciclo' => '2027A',
        'titulo' => 'Auxiliar de litigio', 'descripcion' => 'Apoyo', 'actividades' => "Archivar\nRedactar",
        'perfil_requerido' => 'Ordenado', 'horario' => 'L-V 9 a 13', 'cupos_totales' => 2,
    ];

    $this->programa = fn (array $extra = []) => Programa::create($extra + [
        'convenio_id' => $this->convenio->id, 'carrera_id' => $this->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Programa publicado', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 3, 'estado' => ProgramaEstado::Publicado,
    ]);
});

/* -------------------------- 1. Aprobación de vacantes -------------------------- */

test('la vacante de la empresa nace pendiente y solo la coordinación de su carrera la publica', function () {
    Notification::fake();
    $alumno = pcAlumno($this->abog, '215100001');
    $ajeno = pcAlumno($this->crim, '215100002');

    $this->actingAs($this->empresaUser)->post(route('empresa.programas.store'), $this->vacante)->assertSessionHasNoErrors();
    $programa = Programa::firstOrFail();

    expect($programa->estado)->toBe(ProgramaEstado::PendienteAprobacion)
        ->and(Programa::abiertos()->count())->toBe(0);
    Notification::assertSentTo($this->coordAbog, AvisoOperativo::class);
    Notification::assertNotSentTo($this->coordCrim, AvisoOperativo::class);

    // Ni la empresa ni otra coordinación pueden publicarla
    $this->actingAs($this->empresaUser)->post(route('empresa.programas.estado', $programa), ['estado' => 'publicado'])->assertSessionHasErrors('estado');
    $this->actingAs($this->coordCrim)->post(route('coordinacion.programas.aprobar', $programa))->assertForbidden();
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.estado', $programa), ['estado' => 'publicado'])->assertSessionHasErrors('programa');

    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.aprobar', $programa))->assertSessionHasNoErrors();

    expect($programa->refresh()->estado)->toBe(ProgramaEstado::Publicado)
        ->and(Programa::abiertos()->count())->toBe(1);
    Notification::assertSentTo($this->empresaUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'aprobó'));
    Notification::assertSentTo($alumno->user, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'Nueva vacante'));
    Notification::assertNotSentTo($ajeno->user, AvisoOperativo::class);
});

test('la empresa no puede ofertar por URL directa a una carrera sin dictamen aprobatorio ni con un convenio no vigente', function () {
    Notification::fake();

    // CRIM no aprobó el convenio: el formulario no la ofrece y el servidor tampoco la acepta
    $this->actingAs($this->empresaUser)->post(route('empresa.programas.store'), ['carrera_id' => $this->crim->id] + $this->vacante)
        ->assertSessionHasErrors('carrera_id');

    // Un convenio propio pero vencido no sirve aunque tenga otro vigente
    $vencido = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-PC-000',
        'fecha_recepcion' => now()->subYears(4), 'fecha_inicio_vigencia' => now()->subYears(4),
        'fecha_fin_vigencia' => now()->subMonth(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 5,
    ]);
    $vencido->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $this->actingAs($this->empresaUser)->post(route('empresa.programas.store'), ['convenio_id' => $vencido->id] + $this->vacante)
        ->assertSessionHasErrors('convenio_id');

    expect(Programa::count())->toBe(0);
    Notification::assertNotSentTo($this->coordCrim, AvisoOperativo::class);
});

test('rechazar una vacante exige motivo y la edición de la empresa la regresa a revisión', function () {
    Notification::fake();
    $this->actingAs($this->empresaUser)->post(route('empresa.programas.store'), $this->vacante);
    $programa = Programa::firstOrFail();

    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.rechazar', $programa), ['motivo' => ''])->assertSessionHasErrors('motivo');
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.rechazar', $programa), ['motivo' => 'El horario no es compatible con clases'])
        ->assertSessionHasNoErrors();

    expect($programa->refresh()->estado)->toBe(ProgramaEstado::Rechazado)
        ->and($programa->motivo_rechazo)->toBe('El horario no es compatible con clases');
    Notification::assertSentTo($this->empresaUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'horario no es compatible'));

    // La empresa corrige: vuelve a pendiente y se borra el motivo
    $this->actingAs($this->empresaUser)->put(route('empresa.programas.update', $programa), ['horario' => 'Sábados'] + $this->vacante)
        ->assertSessionHasNoErrors();
    expect($programa->refresh()->estado)->toBe(ProgramaEstado::PendienteAprobacion)
        ->and($programa->motivo_rechazo)->toBeNull();
});

/* -------------------- 2. Expediente del alumno y carta -------------------- */

test('el alumno captura grado y turno desde su perfil', function () {
    $alumno = pcAlumno($this->abog, '215100003');

    $this->actingAs($alumno->user)->put(route('alumno.datos-escolares'), ['grado' => 13, 'turno' => 'nocturno'])
        ->assertSessionHasErrors(['grado', 'turno']);
    $this->actingAs($alumno->user)->put(route('alumno.datos-escolares'), ['grado' => 8, 'turno' => 'mixto'])
        ->assertSessionHasNoErrors();

    expect($alumno->refresh()->grado)->toBe(8)
        ->and($alumno->turno)->toBe(Turno::Mixto);
    $this->actingAs($alumno->user)->get(route('profile.edit'))->assertOk()->assertSee('Datos escolares');
});

test('sin expediente validado no hay postulación; validado, la carta lleva carrera, grado y turno', function () {
    $alumno = pcAlumno($this->abog, '215100004', imssValidado: false);
    $programa = ($this->programa)();

    // Solo con el IMSS (sin kardex) no puede postularse
    $this->actingAs($alumno->user)->post(route('alumno.oferta-programas.postular', $programa), ['siiau' => '1'])
        ->assertSessionHasErrors('postulacion');

    // La coordinación revisa el IMSS; otra coordinación no puede
    $imss = $alumno->documentoVigente('constancia_imss');
    $this->actingAs($this->coordAbog)->get(route('coordinacion.alumnos.archivos', [$alumno, $imss]))->assertOk();
    $this->actingAs($this->coordCrim)->post(route('coordinacion.documentos.resolver', $imss), ['estado' => 'validada'])->assertForbidden();
    $this->actingAs($this->coordAbog)->post(route('coordinacion.documentos.resolver', $imss), ['estado' => 'rechazada'])->assertSessionHasErrors('motivo');

    // Con kardex e IMSS validados se postula y la coordinación emite la carta
    $alumno = expedienteDe($alumno, 70);
    $this->actingAs($alumno->user)->post(route('alumno.oferta-programas.postular', $programa), ['siiau' => '1'])->assertSessionHasNoErrors();
    $po = Postulacion::where('alumno_id', $alumno->id)->firstOrFail();
    // Sin el escaneo firmado y sellado no se emite
    $this->actingAs($this->coordAbog)->post(route('coordinacion.postulaciones.carta', $po))->assertSessionHasErrors('carta');
    // El formato que se imprime, firma y sella lleva carrera, grado y turno
    $this->actingAs($this->coordAbog)->get(route('coordinacion.postulaciones.carta.formato', $po))->assertOk();
    $texto = pcTextoDocx(app(GeneradorCartaPresentacion::class)->generar($po->fresh(), $this->coordAbog->personal));
    $this->actingAs($this->coordAbog)->post(route('coordinacion.postulaciones.carta', $po), ['carta' => pcPdf('firmada.pdf')])->assertSessionHasNoErrors();
    expect($po->refresh()->estado)->toBe(PostulacionEstado::CartaPresentacionEmitida);

    $carta = $po->getFirstMedia('carta_presentacion');
    expect($carta->file_name)->toBe('carta-presentacion-215100004.pdf')
        ->and($carta->getCustomProperty('firmada'))->toBeTrue();

    expect($texto)->toContain('Licenciatura en Abogado')
        ->toContain('7.º semestre')
        ->toContain('turno Vespertino')
        ->toContain('215100004')
        ->toContain('LIC. ROSA FIRMA');   // dirigida al representante legal de la empresa

    // El alumno la descarga desde sus documentos
    $this->actingAs($alumno->user)->get(route('alumno.documentos.descargar', $carta))->assertOk();
});

test('si el IMSS vence antes de emitir la carta, la carta se bloquea hasta renovarlo', function () {
    $alumno = expedienteDe(pcAlumno($this->abog, '215100005'), 70);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => ($this->programa)()->id,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $alumno->update(['vigencia_imss' => now()->subDay()]);

    $this->actingAs($this->coordAbog)->post(route('coordinacion.postulaciones.carta', $po), ['carta' => pcPdf('firmada.pdf')])->assertSessionHasErrors('postulacion');
    expect($alumno->fresh()->estadoExpediente())->toBe('imss_vencido');
});

test('la coordinación revisa kardex e IMSS juntos: devuelve con motivo y el alumno reemplaza solo el que falló', function () {
    Notification::fake();
    [$u] = [pcUsuario(Rol::Alumno)];
    $alumno = Alumno::create(['user_id' => $u->id, 'carrera_id' => $this->abog->id, 'num_control' => '215100009', 'grado' => 7, 'turno' => 'vespertino']);

    // El alumno entrega su expediente completo en un solo envío
    $this->actingAs($u->fresh())->post(route('alumno.documentos.expediente'), [
        'porcentaje_creditos' => 70, 'kardex' => pcPdf('kardex-2025B.pdf'),
        'imss' => pcPdf('imss.pdf'), 'vigencia_imss' => now()->addYear()->toDateString(),
    ])->assertSessionHasNoErrors();
    Notification::assertSentTo($this->coordAbog, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'envió su kardex y su constancia IMSS'));
    $this->actingAs($this->coordAbog)->get(route('coordinacion.lista-practicantes'))->assertSee('Expedientes por revisar')->assertSee('Ambos correctos: autorizar al alumno');

    // Devolver exige qué documento y por qué
    $this->actingAs($this->coordAbog)->post(route('coordinacion.alumnos.expediente', $alumno), ['decision' => 'devolver'])
        ->assertSessionHasErrors(['documentos', 'motivo']);
    $this->actingAs($this->coordAbog)->post(route('coordinacion.alumnos.expediente', $alumno), [
        'decision' => 'devolver', 'documentos' => ['kardex'], 'motivo' => 'El kardex es de un ciclo anterior.',
    ])->assertSessionHasNoErrors();

    $alumno->refresh();
    expect($alumno->estadoExpediente())->toBe('devuelto')
        ->and($alumno->documentoVigente('constancia_imss')->getCustomProperty('estado'))->toBeNull();   // el IMSS no se tocó
    Notification::assertSentTo($u, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'ciclo anterior'));

    // Devuelto: no puede postularse ni se puede autorizar
    $this->actingAs($u->fresh())->post(route('alumno.oferta-programas.postular', ($this->programa)()), ['siiau' => '1'])->assertSessionHasErrors('postulacion');
    $this->actingAs($this->coordAbog)->post(route('coordinacion.alumnos.expediente', $alumno), ['decision' => 'autorizar'])->assertSessionHasErrors('revision');

    // Reemplaza solo el kardex; la coordinación recibe aviso y autoriza
    $this->actingAs($u->fresh())->post(route('alumno.documentos.kardex'), ['archivo' => pcPdf('kardex-2026A.pdf')])->assertSessionHasNoErrors();
    Notification::assertSentTo($this->coordAbog, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'reemplazó el kardex'));
    $this->actingAs($this->coordAbog)->post(route('coordinacion.alumnos.expediente', $alumno), ['decision' => 'autorizar'])->assertSessionHasNoErrors();

    expect($alumno->fresh()->expedienteValidado())->toBeTrue();
    Notification::assertSentTo($u, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'Ya puedes postularte'));

    // Ya validados: no tiene caso reemplazarlos
    $this->actingAs($u->fresh())->post(route('alumno.documentos.kardex'), ['archivo' => pcPdf('otro.pdf')])->assertSessionHasErrors('archivo');
    $this->actingAs($u->fresh())->post(route('alumno.documentos.imss'), ['archivo' => pcPdf('otro.pdf'), 'vigencia_imss' => now()->addYear()->toDateString()])
        ->assertSessionHasErrors('archivo');
});

/* ---------------- 3. Reportes por carrera y modalidad interna ---------------- */

test('el alumno solo entrega los tipos de reporte del esquema de su carrera', function () {
    $this->actingAs($this->coordAbog)->put(route('coordinacion.reglas.update', $this->abog), [
        'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8,
        'esquema_reportes' => 'parcial_final', 'coordinacion_correo' => 'abogado@csh.udg.mx',
    ])->assertSessionHasNoErrors();
    expect($this->abog->refresh()->esquema_reportes)->toBe(EsquemaReportes::ParcialFinal);

    $alumno = pcAlumno($this->abog, '215100006');
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => ($this->programa)()->id,
        'estado' => PostulacionEstado::Aceptada, 'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonths(2),
        'fecha_fin' => today()->addMonths(2), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso]);

    $reporte = fn (string $tipo) => $this->actingAs($alumno->user)->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => $tipo, 'periodo_inicio' => today()->subMonth()->toDateString(), 'periodo_fin' => today()->toDateString(),
        'horas_reportadas' => 60, 'archivo' => pcPdf(),
    ]);

    $reporte('mensual')->assertSessionHasErrors('tipo');      // su carrera no pide mensuales
    $reporte('intermedio')->assertSessionHasNoErrors();        // parcial
    $reporte('intermedio')->assertSessionHasErrors('tipo');    // el parcial se entrega una sola vez

    $this->actingAs($alumno->user)->get(route('alumno.mis-documentos'))->assertOk()->assertSee('Parcial');
});

test('la práctica interna no se activa sin el oficio de aceptación interno', function () {
    $alumno = pcAlumno($this->abog, '215100007');
    $interno = Programa::create(['convenio_id' => null, 'carrera_id' => $this->abog->id, 'modalidad' => 'interna', 'ciclo' => '2027A',
        'titulo' => 'Clínica jurídica', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 1, 'estado' => ProgramaEstado::Publicado]);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $interno->id, 'estado' => PostulacionEstado::CartaAceptacionRecibida,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);

    $po->addMedia(pcPdf('aceptacion.pdf'))->toMediaCollection('carta_aceptacion');
    $fechas = ['fecha_inicio' => today()->toDateString(), 'fecha_fin' => today()->addMonths(5)->toDateString()];

    $this->actingAs($this->coordAbog)->post(route('coordinacion.postulaciones.activar', $po), $fechas)
        ->assertSessionHasErrors('practica');
    expect(Practica::count())->toBe(0);

    $this->actingAs($this->coordAbog)->post(route('coordinacion.postulaciones.oficio-interno', $po), ['archivo' => pcPdf('oficio-interno.pdf')])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->coordAbog)->post(route('coordinacion.postulaciones.activar', $po), $fechas)->assertSessionHasNoErrors();

    expect(Practica::firstOrFail()->estado)->toBe(PracticaEstado::EnCurso);
});

/* -------------------- 4. Convenio: dictamen y turnos -------------------- */

test('cada coordinación emite su dictamen; Vinculación registra los turnos y ya no captura dictámenes', function () {
    Notification::fake();
    $c = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Solicitado, 'fecha_recepcion' => today()->subDays(10),
        'objetivos' => 'Proyectos de investigación criminal', 'actividades_propuestas' => 'Análisis de casos', 'plazas_estimadas' => 2,
    ]);
    $dAbog = $c->dictamenes()->create(['carrera_id' => $this->abog->id]);
    $dCrim = $c->dictamenes()->create(['carrera_id' => $this->crim->id]);
    foreach (Empresa::coleccionesObligatorias() as $col) {
        $m = $this->empresa->addMedia(pcPdf())->toMediaCollection($col);
        $this->empresa->resolverDocumento($m, ValidacionEstado::Validada, null, $this->vinculacion->id);
    }

    $this->actingAs($this->vinculacion)->post(route('admin.convenios.validacion-academica', $c))->assertSessionHasNoErrors();
    Notification::assertSentTo($this->coordAbog, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'dictamen'));

    // Turnos (se envió hoy a las carreras): la División recibe el mismo día o después de Secretaría
    $this->actingAs($this->vinculacion)->post(route('admin.convenios.turnos', $c), [
        'fecha_turno_secretaria' => today()->toDateString(), 'fecha_turno_division' => today()->subDay()->toDateString(),
    ])->assertSessionHasErrors('fecha_turno_division');
    $this->actingAs($this->vinculacion)->post(route('admin.convenios.turnos', $c), [
        'fecha_turno_secretaria' => today()->toDateString(), 'fecha_turno_division' => today()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($c->refresh()->fecha_turno_secretaria->toDateString())->toBe(today()->toDateString());

    // La coordinación ve la propuesta y dictamina solo su carrera
    $this->actingAs($this->coordAbog)->get(route('coordinacion.lista-dictamenes'))->assertOk()->assertSee('Análisis de casos');
    $this->actingAs($this->coordAbog)->post(route('coordinacion.dictamenes.registrar', $dCrim), ['dictamen' => 'aprobatorio', 'documento' => UploadedFile::fake()->create('dictamen.pdf', 40, 'application/pdf')])->assertForbidden();
    $this->actingAs($this->vinculacion)->post(route('coordinacion.dictamenes.registrar', $dAbog), ['dictamen' => 'aprobatorio', 'documento' => UploadedFile::fake()->create('dictamen.pdf', 40, 'application/pdf')])->assertForbidden();

    $this->actingAs($this->coordAbog)->post(route('coordinacion.dictamenes.registrar', $dAbog), ['dictamen' => 'aprobatorio', 'documento' => UploadedFile::fake()->create('dictamen.pdf', 40, 'application/pdf')])->assertSessionHasNoErrors();
    $this->actingAs($this->coordCrim)->post(route('coordinacion.dictamenes.registrar', $dCrim), ['dictamen' => 'negativo', 'documento' => UploadedFile::fake()->create('dictamen.pdf', 40, 'application/pdf'), 'observaciones' => 'No afín'])
        ->assertSessionHasNoErrors();

    expect($dAbog->refresh()->dictamen)->toBe(Dictamen::Aprobatorio)
        ->and($dAbog->dictaminador->user_id)->toBe($this->coordAbog->id)
        ->and($c->refresh()->estado)->toBe(ConvenioEstado::EnValidacionAcademica);   // sigue en la etapa: falta el borrador
    Notification::assertSentTo($this->vinculacion, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'Ya están todos los dictámenes'));

    // Vinculación solo da seguimiento: ya no hay ruta para capturar
    expect(Route::has('admin.dictamenes.registrar'))->toBeFalse();
    $this->actingAs($this->vinculacion)->get(route('admin.lista-dictamenes', ['dictamen' => 'todos']))->assertOk()->assertDontSee('Emitir dictamen');
});

/* -------------- 5. Representante legal, convenios vigentes y contacto -------------- */

test('la empresa no solicita convenio sin representante legal', function () {
    $u = pcUsuario(Rol::Empresa);
    $e = Empresa::create(['user_id' => $u->id, 'rfc' => 'ENR020202CD2', 'razon_social' => 'Sin Representante',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto']);
    foreach (Empresa::coleccionesObligatorias() as $col) {
        $e->addMedia(pcPdf())->toMediaCollection($col);
    }
    solicitudLista($e, [$this->abog->id]);
    $e->update(['representante_legal' => null]);

    $this->actingAs($u)->post(route('empresa.convenio.solicitar'))->assertSessionHasErrors('convenio');

    // Completa la sección «Declaraciones legales» (la ruta anterior de datos legales también la guarda)
    $this->actingAs($u)->put(route('empresa.datos-legales'), ['representante_legal' => 'Ing. Pedro Firma', 'representante_cargo' => 'Director',
        ...datosNotarialesPrueba(), 'objeto_social' => 'servicios de consultoría legal', 'facultad_origen' => 'acta'])->assertSessionHasNoErrors();
    $this->actingAs($u)->post(route('empresa.convenio.solicitar'))->assertSessionHasNoErrors();

    expect($e->refresh()->representante_legal)->toBe('Ing. Pedro Firma')
        ->and($e->convenios()->count())->toBe(1);
});

test('los convenios vigentes son públicos y el alumno ve a su coordinación', function () {
    $this->abog->update(['coordinacion_correo' => 'coordinacion.abogado@csh.udg.mx', 'coordinacion_telefono' => '33 3819 3300 ext. 23500']);
    $alumno = pcAlumno($this->abog, '215100008');

    $otra = Empresa::create(['user_id' => pcUsuario(Rol::Empresa)->id, 'rfc' => 'OTR030303EF3', 'razon_social' => 'Solo Criminologia A.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'C']);
    $cv = Convenio::create(['empresa_id' => $otra->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-PC-002',
        'fecha_recepcion' => now()->subMonths(3), 'fecha_inicio_vigencia' => now()->subMonth(), 'fecha_fin_vigencia' => now()->addYear(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $cv->dictamenes()->create(['carrera_id' => $this->crim->id, 'dictamen' => Dictamen::Aprobatorio]);

    // El panel del alumno ya no lleva lista de convenios: la oferta de programas muestra las empresas de su carrera
    expect(Route::has('alumno.convenios-vigentes'))->toBeFalse();

    $this->actingAs($alumno->user)->get(route('alumno.dashboard'))
        ->assertOk()->assertSee('Tu coordinación')->assertSee('coordinacion.abogado@csh.udg.mx')
        ->assertSee('to='.rawurlencode('coordinacion.abogado@csh.udg.mx'), false)
        ->assertSee('Copiar correo');

    // El sitio público: Estudiantes enlaza a la lista de convenios vigentes y al directorio (en Contacto)
    $this->get(route('estudiantes'))->assertOk()
        ->assertSee(route('convenios-vigentes'))->assertSee(route('contacto').'#directorio', false);
    $this->get(route('convenios-vigentes'))->assertOk()
        ->assertSee('Despacho Ejemplo S.C.')->assertSee('Solo Criminologia A.C.');
    $this->get(route('contacto'))->assertOk()->assertSee('Escribe a tu coordinación')->assertSee('coordinacion.abogado@csh.udg.mx');
});

test('la coordinación solo registra proyectos internos; las vacantes externas son de la empresa', function () {
    $datos = ['carrera_id' => $this->abog->id, 'ciclo' => '2027A', 'titulo' => 'Revista jurídica', 'descripcion' => 'x',
        'actividades' => "Corregir\nEditar", 'perfil_requerido' => 'p', 'cupos_totales' => 2];

    // Aunque intente mandarla como externa con convenio, se guarda como interna
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.store'), $datos + ['modalidad' => 'externa', 'convenio_id' => $this->convenio->id])
        ->assertSessionHasNoErrors();
    $interno = Programa::where('titulo', 'Revista jurídica')->firstOrFail();
    expect($interno->esInterno())->toBeTrue()->and($interno->convenio_id)->toBeNull()
        ->and($interno->estado)->toBe(ProgramaEstado::Borrador);

    // La vacante de la empresa no la edita ni la borra la coordinación: la aprueba, rechaza o cierra
    $externa = ($this->programa)();
    $this->actingAs($this->coordAbog)->put(route('coordinacion.programas.update', $externa), $datos)->assertSessionHasErrors('programa');
    $this->actingAs($this->coordAbog)->delete(route('coordinacion.programas.destroy', $externa))->assertSessionHasErrors('programa');
    // Cerrarla exige observaciones para la empresa
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.estado', $externa), ['estado' => 'cerrado'])->assertSessionHasErrors('observaciones');
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.estado', $externa), ['estado' => 'cerrado', 'observaciones' => 'El horario no coincide con lo reportado.'])
        ->assertSessionHasNoErrors();
    expect($externa->refresh()->estado)->toBe(ProgramaEstado::Cerrado)
        ->and($externa->observaciones_cierre)->toBe('El horario no coincide con lo reportado.');

    // La coordinación no reabre ni despublica una externa: eso es de la empresa
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.estado', $externa), ['estado' => 'publicado'])->assertSessionHasErrors('programa');
    $this->actingAs($this->coordAbog)->get(route('coordinacion.lista-programas'))->assertOk()
        ->assertSee('Cerrada por la coordinación');

    // La empresa la reabre; como la cerró la coordinación, vuelve a revisión
    $this->actingAs($this->empresaUser)->post(route('empresa.programas.estado', $externa), ['estado' => 'publicado'])->assertSessionHasNoErrors();
    expect($externa->refresh()->estado)->toBe(ProgramaEstado::PendienteAprobacion);
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.aprobar', $externa))->assertSessionHasNoErrors();
    expect($externa->refresh()->estado)->toBe(ProgramaEstado::Publicado)
        ->and($externa->observaciones_cierre)->toBeNull();

    // Si la cerró la propia empresa, la reabre directo
    $this->actingAs($this->empresaUser)->post(route('empresa.programas.estado', $externa), ['estado' => 'cerrado'])->assertSessionHasNoErrors();
    $this->actingAs($this->empresaUser)->post(route('empresa.programas.estado', $externa), ['estado' => 'publicado'])->assertSessionHasNoErrors();
    expect($externa->refresh()->estado)->toBe(ProgramaEstado::Publicado);
});
