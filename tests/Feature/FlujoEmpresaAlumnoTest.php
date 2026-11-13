<?php

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
use App\Models\CartaTermino;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\OficioAcreditacion;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function escPdf(string $nombre = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function escUsuario(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

function escEmpresa(string $rfc): array
{
    $u = escUsuario(Rol::Empresa);
    $e = Empresa::create([
        'user_id' => $u->id,
        'rfc' => $rfc,
        'razon_social' => "Empresa $rfc",
        'tipo_organismo' => TipoOrganismo::Privado,
        'contacto_nombre' => 'Contacto',
    ]);

    return [$u, $e];
}

function escAlumno(Carrera $carrera, string $control): array
{
    $u = escUsuario(Rol::Alumno);
    $a = Alumno::create([
        'user_id' => $u->id,
        'carrera_id' => $carrera->id,
        'num_control' => $control,
        'vigencia_imss' => now()->addYear()->toDateString(),
    ]);

    return [$u, $a];
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);

    $division = Division::create(['nombre' => 'División de prueba']);
    $datos = [
        'division_id' => $division->id,
        'horas_requeridas' => 250,
        'porcentaje_creditos_minimo' => 50,
        'creditos_otorgados' => 8
    ];
    $this->abogado = Carrera::create($datos + ['carrera_clave' => 'ABOG', 'nombre' => 'Abogado']);
    $this->historia = Carrera::create($datos + ['carrera_clave' => 'HIST', 'nombre' => 'Historia']);

    $this->adminUser = escUsuario(Rol::Administrador);
    $this->lorena = Personal::create(['user_id' => $this->adminUser->id, 'puesto' => 'Coordinación']);

    [$this->empresaUser, $this->empresa] = escEmpresa('EPA010101AB1');
    [$this->otraEmpresaUser, $this->otraEmpresa] = escEmpresa('EPB020202CD2');

    // Convenio formalizado y vigente, solo con Abogado aprobada
    $this->convenio = Convenio::create([
        'empresa_id' => $this->empresa->id,
        'estado' => ConvenioEstado::Formalizado,
        'folio' => 'CV-T-001',
        'fecha_recepcion' => now()->subMonths(3),
        'fecha_inicio_vigencia' => now()->subMonth(),
        'fecha_fin_vigencia' => now()->addYears(3),
        'objetivos' => 'x',
        'actividades_propuestas' => 'y',
        'plazas_estimadas' => 5,
    ]);
    $this->convenio->dictamenes()->create(['carrera_id' => $this->abogado->id, 'dictamen' => Dictamen::Aprobatorio]);

    [$this->anaUser, $this->ana] = escAlumno($this->abogado, '215000001');
    [$this->luisUser, $this->luis] = escAlumno($this->historia, '215000002');

    $this->programa = fn(array $extra = []) => Programa::create($extra + [
        'convenio_id' => $this->convenio->id,
        'carrera_id' => $this->abogado->id,
        'modalidad' => 'externa',
        'ciclo' => '2027A',
        'titulo' => 'Auxiliar jurídico',
        'descripcion' => 'Apoyo',
        'actividades' => ['Archivar'],
        'perfil_requerido' => 'Ordenado',
        'cupos_totales' => 2,
        'estado' => ProgramaEstado::Publicado,
    ]);
});

/* ------------------------------ FLUJO COMPLETO ------------------------------ */

test('empresa publica, alumno se postula y la práctica llega a acreditada', function () {
    // 1. La empresa crea el programa (nace en borrador) y lo publica
    $this->actingAs($this->empresaUser)->post(route('empresa.programas.store'), [
        'convenio_id' => $this->convenio->id,
        'carrera_id' => $this->abogado->id,
        'ciclo' => '2027A',
        'titulo' => 'Auxiliar jurídico',
        'descripcion' => 'Apoyo',
        'actividades' => "Archivar\nAtender usuarios",
        'perfil_requerido' => 'Ordenado',
        'cupos_totales' => 2,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $programa = Programa::firstOrFail();
    expect($programa->estado)->toBe(ProgramaEstado::Borrador)
        ->and($programa->actividades)->toBe(['Archivar', 'Atender usuarios']);

    // Un alumno no ve borradores
    $this->actingAs($this->anaUser)->get(route('alumno.oferta-programas'))->assertDontSee('Auxiliar jurídico');

    $this->actingAs($this->empresaUser)
        ->post(route('empresa.programas.estado', $programa), ['estado' => 'publicado'])
        ->assertSessionHasNoErrors()->assertRedirect();

    // 2. Ana lo ve y se postula con kardex
    $this->actingAs($this->anaUser)->get(route('alumno.oferta-programas'))->assertSee('Auxiliar jurídico');

    $this->actingAs($this->anaUser)->post(route('alumno.oferta-programas.postular', $programa), [
        'porcentaje_creditos_declarado' => 70,
        'kardex' => escPdf('kardex.pdf'),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $po = Postulacion::firstOrFail();
    expect($po->estado)->toBe(PostulacionEstado::Pendiente)
        ->and($po->getMedia('kardex'))->toHaveCount(1);

    // 3. Coordinación emite la carta de presentación (reserva cupo)
    $this->actingAs($this->adminUser)->post(route('admin.postulaciones.carta', $po))->assertSessionHasNoErrors()->assertRedirect();
    expect($po->refresh()->estado)->toBe(PostulacionEstado::CartaPresentacionEmitida)
        ->and($programa->refresh()->cupos_disponibles)->toBe(1);

    // La empresa ya ve al candidato
    $this->actingAs($this->empresaUser)->get(route('empresa.programas.postulantes', $programa))->assertSee('215000001');

    // 4. Se registra la carta de aceptación y se activa la práctica
    $this->actingAs($this->adminUser)
        ->post(route('admin.postulaciones.aceptacion', $po), ['archivo' => escPdf('aceptacion.pdf')])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($po->refresh()->estado)->toBe(PostulacionEstado::CartaAceptacionRecibida);

    $this->actingAs($this->adminUser)->post(route('admin.postulaciones.activar', $po), [
        'fecha_inicio' => today()->toDateString(),
        'fecha_fin' => today()->addMonths(6)->toDateString(),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $practica = Practica::firstOrFail();
    expect($practica->estado)->toBe(PracticaEstado::EnCurso)->and($practica->horas_requeridas)->toBe(250);
    $this->actingAs($this->anaUser)->get(route('alumno.mis-practicas'))->assertOk();

    // 5. Reporte: el alumno sube el firmado, la Coordinación lo valida y la empresa retroalimenta
    $this->actingAs($this->anaUser)->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => 'final',
        'periodo_inicio' => today()->toDateString(),
        'periodo_fin' => today()->toDateString(),
        'horas_reportadas' => 250,
        'archivo' => escPdf('reporte.pdf'),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $reporte = Reporte::firstOrFail();
    expect($reporte->getMedia('reporte_firmado'))->toHaveCount(1);

    $this->actingAs($this->adminUser)
        ->post(route('admin.reportes.resolver', $reporte), ['estado' => 'validado', 'evaluacion_profesor' => 'Bien'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($practica->refresh()->progresoTexto())->toBe('250/250');

    $this->actingAs($this->empresaUser)
        ->post(route('empresa.reportes.retroalimentar', $reporte), ['retroalimentacion_empresa' => 'Excelente'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($reporte->refresh()->retroalimentacion_empresa)->toBe('Excelente');

    // 6. Carta de término (el alumno la sube) y cierre
    $this->actingAs($this->anaUser)
        ->post(route('alumno.documentos.termino', $practica), ['archivo' => escPdf('termino.pdf')])
        ->assertSessionHasNoErrors()->assertRedirect();

   // Coordinación valida la carta, registra el oficio y confirma Control Escolar / SIIAU
    $admin = $this->actingAs($this->adminUser);

    $admin->post(route('admin.cierres.carta', $practica), ['estado' => 'validada'])->assertSessionHasNoErrors()->assertRedirect();
    expect($practica->refresh()->estado)->toBe(PracticaEstado::EnCierre);

    $admin->post(route('admin.cierres.oficio', $practica), ['archivo' => escPdf('oficio.pdf')])->assertSessionHasNoErrors()->assertRedirect();
    $admin->post(route('admin.cierres.enviar', $practica))->assertSessionHasNoErrors()->assertRedirect();
    expect($practica->refresh()->estado)->toBe(PracticaEstado::EnCierre);   // aún no acreditada

    $admin->post(route('admin.cierres.siiau', $practica))->assertSessionHasNoErrors()->assertRedirect();
    expect($practica->refresh()->estado)->toBe(PracticaEstado::Acreditada);
    $this->actingAs($this->anaUser)->get(route('alumno.mis-practicas'))->assertSee('Acreditada');
});

test('un alumno de otra carrera no puede postularse', function () {
    $programa = ($this->programa)();

    $this->actingAs($this->luisUser)->post(route('alumno.oferta-programas.postular', $programa), [
        'porcentaje_creditos_declarado' => 80,
        'kardex' => escPdf('kardex.pdf'),
    ])->assertSessionHasErrors('postulacion');

    expect(Postulacion::count())->toBe(0);
});

test('con créditos insuficientes no se postula', function () {
    $programa = ($this->programa)();

    $this->actingAs($this->anaUser)->post(route('alumno.oferta-programas.postular', $programa), [
        'porcentaje_creditos_declarado' => 30,
        'kardex' => escPdf('kardex.pdf'),
    ])->assertSessionHasErrors('porcentaje_creditos_declarado');
});

test('un programa cerrado o en borrador no admite postulaciones', function () {
    foreach ([ProgramaEstado::Cerrado, ProgramaEstado::Borrador] as $i => $estado) {
        $programa = ($this->programa)(['titulo' => "P$i", 'estado' => $estado]);

        $this->actingAs($this->anaUser)->post(route('alumno.oferta-programas.postular', $programa), [
            'porcentaje_creditos_declarado' => 70,
            'kardex' => escPdf('kardex.pdf'),
        ])->assertSessionHasErrors('postulacion');
    }
});

/* ------------------------- PERTENENCIA DE RECURSOS ------------------------- */

test('una empresa no puede tocar los programas de otra', function () {
    $programa = ($this->programa)();

    $this->actingAs($this->otraEmpresaUser)->post(route('empresa.programas.estado', $programa), ['estado' => 'cerrado'])->assertForbidden();
    $this->actingAs($this->otraEmpresaUser)->delete(route('empresa.programas.destroy', $programa))->assertForbidden();
    $this->actingAs($this->otraEmpresaUser)->get(route('empresa.programas.postulantes', $programa))->assertForbidden();

    expect($programa->refresh()->estado)->toBe(ProgramaEstado::Publicado);
});

test('una empresa no puede crear programas bajo el convenio de otra', function () {
    $this->actingAs($this->otraEmpresaUser)->post(route('empresa.programas.store'), [
        'convenio_id' => $this->convenio->id,
        'carrera_id' => $this->abogado->id,
        'ciclo' => '2027A',
        'titulo' => 'Intruso',
        'descripcion' => 'x',
        'actividades' => 'a',
        'perfil_requerido' => 'p',
        'cupos_totales' => 1,
    ])->assertSessionHasErrors('convenio_id');

    expect(Programa::count())->toBe(0);
});

test('una empresa no ve ni retroalimenta practicantes ajenos', function () {
    $programa = ($this->programa)();
    $po = Postulacion::create([
        'alumno_id' => $this->ana->id,
        'programa_id' => $programa->id,
        'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 70,
        'fecha_postulacion' => today()
    ]);
    $practica = Practica::create([
        'postulacion_id' => $po->id,
        'modalidad' => 'externa',
        'fecha_inicio' => today(),
        'fecha_fin' => today()->addMonths(6),
        'horas_requeridas' => 250,
        'estado' => PracticaEstado::EnCurso
    ]);
    $reporte = Reporte::create([
        'practica_id' => $practica->id,
        'tipo' => 'mensual',
        'periodo_inicio' => today(),
        'periodo_fin' => today()->addMonth(),
        'horas_reportadas' => 40,
        'estado' => ReporteEstado::Pendiente
    ]);

    $this->actingAs($this->otraEmpresaUser)->get(route('empresa.practicantes.show', $practica))->assertForbidden();
    $this->actingAs($this->otraEmpresaUser)
        ->post(route('empresa.reportes.retroalimentar', $reporte), ['retroalimentacion_empresa' => 'hack'])
        ->assertForbidden();
});

test('un alumno no puede subir ni descargar archivos de otro alumno', function () {
    $programa = ($this->programa)();
    $po = Postulacion::create([
        'alumno_id' => $this->ana->id,
        'programa_id' => $programa->id,
        'estado' => PostulacionEstado::CartaPresentacionEmitida,
        'porcentaje_creditos_declarado' => 70,
        'fecha_postulacion' => today()
    ]);

    // Subir a la postulación de Ana siendo Luis
    $this->actingAs($this->luisUser)
        ->post(route('alumno.documentos.kardex', $po), ['archivo' => escPdf()])->assertForbidden();
    $this->actingAs($this->luisUser)
        ->post(route('alumno.documentos.aceptacion', $po), ['archivo' => escPdf()])->assertForbidden();

    // Descargar el IMSS de Ana siendo Luis
    $media = $this->ana->addMedia(escPdf('imss.pdf'))->toMediaCollection('constancia_imss');

    $this->actingAs($this->luisUser)->get(route('alumno.documentos.descargar', $media))->assertNotFound();
    $this->actingAs($this->anaUser)->get(route('alumno.documentos.descargar', $media))->assertOk();
});

test('una empresa no descarga documentos de otra empresa', function () {
    $media = $this->empresa->addMedia(escPdf('acta.pdf'))->toMediaCollection('acta_constitutiva');

    $this->actingAs($this->otraEmpresaUser)->get(route('empresa.documentos.descargar', $media->id))->assertNotFound();
    $this->actingAs($this->empresaUser)->get(route('empresa.documentos.descargar', $media->id))->assertOk();
});

test('un archivo no se descarga a través de un convenio que no es suyo', function () {
    $media = $this->otraEmpresa->addMedia(escPdf('acta.pdf'))->toMediaCollection('acta_constitutiva');

    // El archivo es de otraEmpresa, pero se pide con el convenio de empresa A
    $this->actingAs($this->adminUser)
        ->get(route('admin.convenios.archivos.descargar', [$this->convenio, $media]))
        ->assertNotFound();
});

test('un reporte no puede rebasar las horas requeridas ni cubrir periodos futuros', function () {
    $programa = ($this->programa)();
    $po = Postulacion::create(['alumno_id' => $this->ana->id, 'programa_id' => $programa->id,
        'estado' => PostulacionEstado::Aceptada, 'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(5), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso]);

    $base = ['tipo' => 'mensual', 'periodo_inicio' => today()->subWeeks(2)->toDateString(),
        'periodo_fin' => today()->toDateString(), 'archivo' => escPdf()];

    $this->actingAs($this->anaUser)->post(route('alumno.documentos.reporte.crear', $practica), $base + ['horas_reportadas' => 300])
        ->assertSessionHasErrors('horas_reportadas');

    $this->actingAs($this->anaUser)->post(route('alumno.documentos.reporte.crear', $practica),
        ['periodo_fin' => today()->addMonth()->toDateString(), 'horas_reportadas' => 10, 'archivo' => escPdf()] + $base)
        ->assertSessionHasErrors('periodo_fin');

    expect(Reporte::count())->toBe(0);
});

test('se genera el borrador .docx del convenio con solo las carreras aprobadas', function () {
    // reutiliza el escenario de FlujoEmpresaAlumnoTest: convenio en etapa de redacción
    $this->convenio->update(['estado' => ConvenioEstado::EnValidacionAcademica]);
    $this->convenio->seguimientos()->create(['etapa' => 'redaccion_borrador', 'estado' => 'pendiente', 'fecha_registro' => today()]);

    $this->actingAs($this->adminUser)->post(route('admin.convenios.borrador.generar', $this->convenio), [
        'representante_nombre' => 'Lic. Pedro Pérez', 'representante_cargo' => 'Director General',
        'constitucion' => 'la escritura pública 123', 'atribuciones' => 'servicios jurídicos',
        'facultad_firmante' => 'poder notarial 45', 'domicilio_legal' => 'Av. Juárez 100, Guadalajara',
        'lugar_firma' => 'Guadalajara', 'fecha_firma' => today()->toDateString(),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $media = $this->convenio->refresh()->getMedia('borrador_convenio');
    expect($media)->toHaveCount(1)->and($media->first()->file_name)->toBe('borrador-convenio-v1.docx');
});

test('la carta de término no se valida si faltan horas y el cierre respeta su orden', function () {
    $programa = ($this->programa)();
    $po = Postulacion::create(['alumno_id' => $this->ana->id, 'programa_id' => $programa->id,
        'estado' => PostulacionEstado::Aceptada, 'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(5), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso]);

    $this->actingAs($this->anaUser)
        ->post(route('alumno.documentos.termino', $practica), ['archivo' => escPdf('termino.pdf')])
        ->assertSessionHasNoErrors();

    $admin = $this->actingAs($this->adminUser);

    // Sin horas completas no se puede validar
    $admin->post(route('admin.cierres.carta', $practica), ['estado' => 'validada'])->assertSessionHasErrors('carta_termino');
    expect($practica->refresh()->estado)->toBe(PracticaEstado::EnCurso);

    // Un rechazo exige motivo; con motivo, el alumno puede reenviar y vuelve a "pendiente"
    $admin->post(route('admin.cierres.carta', $practica), ['estado' => 'rechazada'])->assertSessionHasErrors('motivo');
    $admin->post(route('admin.cierres.carta', $practica), ['estado' => 'rechazada', 'motivo' => 'Falta la firma'])->assertSessionHasNoErrors();
    expect($practica->cartaTermino->refresh()->estado_validacion)->toBe(ValidacionEstado::Rechazada);

    $this->actingAs($this->anaUser)
        ->post(route('alumno.documentos.termino', $practica), ['archivo' => escPdf('termino2.pdf')])
        ->assertSessionHasNoErrors();
    expect($practica->cartaTermino->refresh()->estado_validacion)->toBe(ValidacionEstado::Pendiente);

    // El oficio y SIIAU no se pueden adelantar
    $admin->post(route('admin.cierres.oficio', $practica), ['archivo' => escPdf('oficio.pdf')])->assertSessionHasErrors('oficio');
    $admin->post(route('admin.cierres.enviar', $practica))->assertSessionHasErrors('oficio');
    $admin->post(route('admin.cierres.siiau', $practica))->assertSessionHasErrors('oficio');

    expect(OficioAcreditacion::count())->toBe(0)->and($practica->refresh()->estado)->toBe(PracticaEstado::EnCurso);
});

test('el alumno no puede reemplazar una carta de término ya validada', function () {
    $programa = ($this->programa)();
    $po = Postulacion::create(['alumno_id' => $this->ana->id, 'programa_id' => $programa->id,
        'estado' => PostulacionEstado::Aceptada, 'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(5), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCierre]);

    $this->actingAs($this->anaUser)
        ->post(route('alumno.documentos.termino', $practica), ['archivo' => escPdf()])
        ->assertSessionHasErrors('archivo');
});

test('una coordinación solo descarga archivos de cierre de su propia práctica', function () {
    $programa = ($this->programa)();
    $po = Postulacion::create(['alumno_id' => $this->ana->id, 'programa_id' => $programa->id,
        'estado' => PostulacionEstado::Aceptada, 'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(5), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso]);

    $carta = CartaTermino::create(['practica_id' => $practica->id]);
    $propio = $carta->addMedia(escPdf('termino.pdf'))->toMediaCollection('carta_termino');
    $ajeno = $this->ana->addMedia(escPdf('imss.pdf'))->toMediaCollection('constancia_imss');

    $this->actingAs($this->adminUser)->get(route('admin.cierres.archivos', [$practica, $propio]))->assertOk();
    $this->actingAs($this->adminUser)->get(route('admin.cierres.archivos', [$practica, $ajeno]))->assertNotFound();
});