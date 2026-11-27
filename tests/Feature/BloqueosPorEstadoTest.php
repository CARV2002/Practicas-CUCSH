<?php

/*
 * Auditoría de bloqueos por estado y por rol: cada prueba intenta un envío directo (sin pasar por la pantalla,
 * que ya oculta o deshabilita la acción) y confirma que el servidor lo rechaza SIN cambiar la base de datos.
 */

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
use App\Models\CartaTermino;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Support\Pendientes;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function bqPdf(string $nombre = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function bqUsuario(Rol $rol, array $personal = []): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);
    if (in_array($rol, [Rol::Coordinador, Rol::Vinculacion, Rol::Administrador], true)) {
        Personal::create(['user_id' => $u->id, 'puesto' => $rol->value] + $personal);
    }

    return $u->fresh();
}

/** Alumno con kardex e IMSS validados (IMSS vigente hasta la fecha indicada). */
function bqAlumno(Carrera $carrera, User $coord, ?string $vigencia = null): Alumno
{
    $u = bqUsuario(Rol::Alumno);
    $a = Alumno::create(['user_id' => $u->id, 'carrera_id' => $carrera->id, 'num_control' => (string) random_int(210000000, 229999999),
        'grado' => 7, 'turno' => 'matutino', 'vigencia_imss' => $vigencia ?? now()->addYear()->toDateString(), 'porcentaje_creditos' => 80]);
    foreach (['kardex', 'constancia_imss'] as $col) {
        $a->resolverDocumento($a->addMedia(bqPdf("$col.pdf"))->toMediaCollection($col), ValidacionEstado::Validada, null, $coord->id);
    }

    return $a->fresh();
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed(RolesSeeder::class);

    $division = Division::create(['nombre' => 'División de Estudios Jurídicos']);
    $this->carrera = Carrera::create(['division_id' => $division->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Licenciatura en Abogado',
        'horas_requeridas' => 300, 'porcentaje_creditos_minimo' => 60, 'creditos_otorgados' => 8]);
    $this->coord = bqUsuario(Rol::Coordinador, ['carrera_id' => $this->carrera->id]);

    $this->empresaUser = bqUsuario(Rol::Empresa);
    $this->empresa = Empresa::create(['user_id' => $this->empresaUser->id, 'rfc' => 'BQE010101AB1', 'razon_social' => 'Bloqueos S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Laura Pérez Ruiz',
        'descripcion' => 'Despacho dedicado a la asesoría jurídica de organizaciones civiles.', 'proyectos_principales' => 'Asesoría legal gratuita.']);

    // Convenio vigente con vacante publicada para la carrera
    $this->convenio = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-BQ-1',
        'fecha_recepcion' => now()->subYear(), 'fecha_inicio_vigencia' => today()->subMonths(6), 'fecha_fin_vigencia' => today()->addYears(2),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 2]);
    $this->convenio->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Aprobatorio]);
    $this->programa = Programa::create(['convenio_id' => $this->convenio->id, 'carrera_id' => $this->carrera->id, 'modalidad' => 'externa',
        'ciclo' => '2027A', 'titulo' => 'Auxiliar jurídico', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 2, 'estado' => ProgramaEstado::Publicado]);
});

// ── Empresa ─────────────────────────────────────────────────────────────────────────────────────────────

test('empresa: con un trámite en revisión ninguna sección de la solicitud se puede guardar', function () {
    Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnRevisionJuridica, 'fecha_recepcion' => now(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);

    foreach (['generales', 'legales', 'perfiles'] as $seccion) {
        $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', $seccion), [
            'razon_social' => 'Otra Razón Social S.C.', 'contacto_nombre' => 'Laura Pérez Ruiz', 'representante_legal' => 'Mario López Díaz',
            'objetivos' => 'Objetivos modificados durante la revisión del convenio.',
        ])->assertSessionHasErrors('convenio');
    }

    $e = $this->empresa->fresh();
    expect($e->razon_social)->toBe('Bloqueos S.C.')
        ->and($e->representante_legal)->toBeNull()
        ->and($e->solicitud_borrador)->toBeNull();
});

test('empresa: con convenio vigente la solicitud se congela y se abre solo en la ventana de renovación', function () {
    $datos = ['razon_social' => 'Bloqueos Renovados S.C.', 'contacto_nombre' => 'Laura Pérez Ruiz'];

    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'generales'), $datos)->assertSessionHasErrors('convenio');
    expect($this->empresa->fresh()->razon_social)->toBe('Bloqueos S.C.');

    // A 40 días de vencer ya puede renovar: la edición se habilita
    $this->convenio->update(['fecha_fin_vigencia' => today()->addDays(40)]);
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'generales'), $datos)->assertSessionHasNoErrors();
    expect($this->empresa->fresh()->razon_social)->toBe('Bloqueos Renovados S.C.');
});

test('empresa: «Mi perfil» solo consulta los datos; se editan en «Mi convenio» con sus mismos bloqueos', function () {
    // Ya no existe una segunda vía para editar la organización desde el perfil
    $this->actingAs($this->empresaUser)->patch('/empresa/perfil', ['contacto_nombre' => 'Otra Persona Ruiz'])->assertNotFound();
    expect($this->empresa->fresh()->contacto_nombre)->toBe('Laura Pérez Ruiz');

    // Convenio vigente: se muestran fijos, sin formulario
    $this->actingAs($this->empresaUser)->get(route('profile.edit', ['seccion' => 'organizacion']))->assertOk()
        ->assertSee('Laura Pérez Ruiz')->assertSee('Si necesitas corregir algo urgente, escribe a Vinculación.')
        ->assertDontSee('Editar en Mi convenio');

    // La ruta anterior de «datos legales» sigue las reglas de la solicitud: con convenio vigente, nada
    $this->actingAs($this->empresaUser)->put(route('empresa.datos-legales'), ['representante_legal' => 'Mario López Díaz'])->assertSessionHasErrors('convenio');
    expect($this->empresa->fresh()->representante_legal)->toBeNull();

    // Sin convenio vigente ni trámite: el perfil manda a editar en «Mi convenio»
    $this->convenio->update(['estado' => ConvenioEstado::Rechazado]);
    $this->actingAs($this->empresaUser)->get(route('profile.edit', ['seccion' => 'organizacion']))->assertOk()->assertSee('Editar en Mi convenio');
});

test('empresa: el domicilio largo ya no rompe «Datos generales» y los números aceptan solo formatos reales', function () {
    $this->convenio->update(['estado' => ConvenioEstado::Rechazado]);
    $base = ['razon_social' => 'Bloqueos S.C.', 'contacto_nombre' => 'Laura Pérez Ruiz', 'codigo_postal' => '44100',
        'colonia' => str_repeat('Colonia Larga ', 8), 'municipio' => str_repeat('Municipio Largo ', 7), 'entidad' => 'Jalisco',
        'calle' => str_repeat('Calle Larga ', 10)];

    // Texto libre en los números: se rechaza sin guardar
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'generales'), $base + ['numero_exterior' => '988-Lorem Ipsu', 'numero_interior' => 'Lorem'])
        ->assertSessionHasErrors(['numero_exterior', 'numero_interior']);
    expect($this->empresa->fresh()->calle)->toBeNull();

    // Formatos reales (se guardan en mayúsculas) con calle, colonia y municipio al máximo: el domicilio completo cabe
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'generales'), $base + ['numero_exterior' => '12-b', 'numero_interior' => 'local 2'])
        ->assertSessionHasNoErrors();
    $e = $this->empresa->fresh();
    expect($e->numero_exterior)->toBe('12-B')
        ->and($e->numero_interior)->toBe('LOCAL 2')
        ->and(mb_strlen((string) $e->domicilio_fiscal))->toBeGreaterThan(255);
});

// ── Alumno ──────────────────────────────────────────────────────────────────────────────────────────────

test('alumno: el envío conjunto del expediente no se repite ni pisa lo validado', function () {
    $alumno = bqAlumno($this->carrera, $this->coord);
    $antes = $alumno->media()->count();

    $this->actingAs($alumno->user)->post(route('alumno.documentos.expediente'), [
        'kardex' => bqPdf('kardex2.pdf'), 'imss' => bqPdf('imss2.pdf'), 'vigencia_imss' => today()->addYears(2)->toDateString(), 'porcentaje_creditos' => 99,
    ])->assertSessionHasErrors('expediente');

    $alumno->refresh();
    expect($alumno->media()->count())->toBe($antes)
        ->and((float) $alumno->porcentaje_creditos)->toBe(80.0)
        ->and($alumno->expedienteValidado())->toBeTrue();
});

test('alumno: un IMSS validado y vigente no se reemplaza; en sus últimos 30 días sí se renueva', function () {
    $alumno = bqAlumno($this->carrera, $this->coord, today()->addMonths(6)->toDateString());
    $antes = $alumno->media()->count();

    $this->actingAs($alumno->user)->post(route('alumno.documentos.imss'), ['archivo' => bqPdf(), 'vigencia_imss' => today()->addYear()->toDateString()])
        ->assertSessionHasErrors('archivo');
    expect($alumno->fresh()->media()->count())->toBe($antes)
        ->and($alumno->fresh()->documentoValidado('constancia_imss'))->toBeTrue();

    $alumno->update(['vigencia_imss' => today()->addDays(20)]);
    $this->actingAs($alumno->user->fresh())->post(route('alumno.documentos.imss'), ['archivo' => bqPdf(), 'vigencia_imss' => today()->addYear()->toDateString()])
        ->assertSessionHasNoErrors();
    expect($alumno->fresh()->vigencia_imss->toDateString())->toBe(today()->addYear()->toDateString());
});

test('alumno: con la carta de presentación emitida no cambia su grado ni su turno', function () {
    $alumno = bqAlumno($this->carrera, $this->coord);
    Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $this->programa->id, 'estado' => PostulacionEstado::CartaPresentacionEmitida,
        'porcentaje_creditos_declarado' => 80, 'fecha_postulacion' => today()]);

    $this->actingAs($alumno->user)->put(route('alumno.datos-escolares'), ['grado' => 9, 'turno' => 'vespertino'])->assertSessionHasErrors('grado');
    expect($alumno->fresh()->grado)->toBe(7);

    $this->actingAs($alumno->user)->get(route('profile.edit'))->assertOk()->assertSee('Tus datos escolares están fijos');
});

test('alumno: la carta de aceptación y la de término validadas ya no se vuelven a subir', function () {
    $alumno = bqAlumno($this->carrera, $this->coord);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $this->programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 80, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonths(3),
        'fecha_fin' => today()->addMonth(), 'horas_requeridas' => 300, 'estado' => PracticaEstado::EnCierre]);
    CartaTermino::create(['practica_id' => $practica->id, 'estado_validacion' => ValidacionEstado::Validada]);

    $this->actingAs($alumno->user)->post(route('alumno.documentos.aceptacion', $po), ['archivo' => bqPdf()])->assertSessionHasErrors('archivo');
    $this->actingAs($alumno->user)->post(route('alumno.documentos.termino', $practica), ['archivo' => bqPdf()])->assertSessionHasErrors('archivo');
    $this->actingAs($alumno->user)->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => 'final', 'periodo_inicio' => today()->subMonth()->toDateString(), 'periodo_fin' => today()->toDateString(), 'horas_reportadas' => 10,
        'archivo' => bqPdf(),
    ])->assertSessionHasErrors();

    expect($po->fresh()->getMedia('carta_aceptacion'))->toHaveCount(0)
        ->and($practica->fresh()->cartaTermino->estado_validacion)->toBe(ValidacionEstado::Validada)
        ->and($practica->reportes()->count())->toBe(0);
});

test('alumno: un reporte validado no admite otro archivo', function () {
    $alumno = bqAlumno($this->carrera, $this->coord);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $this->programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 80, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(3), 'horas_requeridas' => 300, 'estado' => PracticaEstado::EnCurso]);
    $reporte = Reporte::create(['practica_id' => $practica->id, 'tipo' => 'mensual', 'numero' => 1, 'periodo_inicio' => today()->subMonth(),
        'periodo_fin' => today(), 'horas_reportadas' => 40, 'estado' => ReporteEstado::Validado]);

    $this->actingAs($alumno->user)->post(route('alumno.documentos.reporte', $reporte), ['archivo' => bqPdf()])->assertSessionHasErrors('archivo');

    expect($reporte->fresh()->estado)->toBe(ReporteEstado::Validado)
        ->and($reporte->getMedia('reporte_firmado'))->toHaveCount(0);
});

// ── Coordinación y administrador ────────────────────────────────────────────────────────────────────────

test('coordinación: no resuelve un reporte sin escaneo ni reabre la vacante que cerró la empresa', function () {
    $alumno = bqAlumno($this->carrera, $this->coord);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $this->programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 80, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(3), 'horas_requeridas' => 300, 'estado' => PracticaEstado::EnCurso]);
    $reporte = Reporte::create(['practica_id' => $practica->id, 'tipo' => 'mensual', 'numero' => 1, 'periodo_inicio' => today()->subMonth(),
        'periodo_fin' => today(), 'horas_reportadas' => 40, 'estado' => ReporteEstado::Pendiente]);

    $this->actingAs($this->coord)->post(route('coordinacion.reportes.resolver', $reporte), ['estado' => 'validado'])->assertSessionHasErrors('reporte');
    expect($reporte->fresh()->estado)->toBe(ReporteEstado::Pendiente);

    $this->programa->update(['estado' => ProgramaEstado::Cerrado]);
    $this->actingAs($this->coord)->post(route('coordinacion.programas.estado', $this->programa), ['estado' => 'publicado'])->assertSessionHasErrors('programa');
    expect($this->programa->fresh()->estado)->toBe(ProgramaEstado::Cerrado);
});

test('administrador: solo consulta convenios y no tiene acciones operativas de coordinación', function () {
    $admin = bqUsuario(Rol::Administrador);
    $this->convenio->update(['estado' => ConvenioEstado::EnFirmas]);

    $this->actingAs($admin)->get(route('admin.convenios.show', $this->convenio))->assertOk();
    $this->actingAs($admin)->post(route('admin.convenios.rechazar', $this->convenio), ['motivo' => 'Prueba de rechazo directo'])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.convenios.formalizar', $this->convenio), ['folio' => 'CV-X-1', 'fecha_inicio_vigencia' => today()->toDateString()])->assertForbidden();
    $this->actingAs($admin)->post(route('coordinacion.programas.estado', $this->programa), ['estado' => 'cerrado'])->assertForbidden();

    expect($this->convenio->fresh()->estado)->toBe(ConvenioEstado::EnFirmas)
        ->and($this->programa->fresh()->estado)->toBe(ProgramaEstado::Publicado);
});

// ── Contadores ──────────────────────────────────────────────────────────────────────────────────────────

test('coordinación: la carta de aceptación que sube el alumno cuenta como pendiente por validar', function () {
    $alumno = bqAlumno($this->carrera, $this->coord);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $this->programa->id, 'estado' => PostulacionEstado::CartaPresentacionEmitida,
        'porcentaje_creditos_declarado' => 80, 'fecha_postulacion' => today()]);

    expect(Pendientes::conteosCoordinacion($this->coord->personal)['cartas_aceptacion'])->toBe(0);

    $this->actingAs($alumno->user)->post(route('alumno.documentos.aceptacion', $po), ['archivo' => bqPdf()])->assertSessionHasNoErrors();

    expect(Pendientes::conteosCoordinacion($this->coord->personal)['cartas_aceptacion'])->toBe(1);
    $this->actingAs($alumno->user)->get(route('alumno.dashboard'))->assertOk()->assertSee('Tu carta de aceptación está por validarse');
});

test('alumno: con el kardex devuelto no se reenvía todo el expediente (el IMSS validado se respeta)', function () {
    $alumno = bqAlumno($this->carrera, $this->coord);
    $kardex = $alumno->addMedia(bqPdf('kardex-v2.pdf'))->toMediaCollection('kardex');
    $alumno->resolverDocumento($kardex, ValidacionEstado::Rechazada, 'Ilegible', $this->coord->id);
    expect($alumno->fresh()->estadoExpediente())->toBe('devuelto');
    $imss = $alumno->documentoVigente('constancia_imss');

    $this->actingAs($alumno->user)->post(route('alumno.documentos.expediente'), [
        'kardex' => bqPdf('k.pdf'), 'imss' => bqPdf('i.pdf'), 'vigencia_imss' => today()->addYears(2)->toDateString(), 'porcentaje_creditos' => 90,
    ])->assertSessionHasErrors('expediente');

    expect($alumno->fresh()->documentoVigente('constancia_imss')->is($imss))->toBeTrue()
        ->and($alumno->fresh()->documentoValidado('constancia_imss'))->toBeTrue();
});
