<?php

/*
 * Dos reglas del trámite del alumno:
 *  - Créditos: con menos del mínimo de su carrera el expediente queda «No cumple requisitos», nunca «Ya puedes postularte».
 *  - Carta de término: la emite la organización; al completar las horas se avisa al alumno y a la organización.
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
use App\Support\Pendientes;
use App\Support\Requisitos;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function ctPdf(string $nombre = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function ctUsuario(Rol $rol, array $personal = []): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);
    if ($rol === Rol::Coordinador) {
        Personal::create(['user_id' => $u->id, 'puesto' => 'Coordinación'] + $personal);
    }

    return $u->fresh();
}

/** Alumno con kardex e IMSS validados por su coordinación y el % de créditos que se indique. */
function ctAlumno(Carrera $carrera, float $creditos, User $coord): Alumno
{
    $u = ctUsuario(Rol::Alumno);
    $a = Alumno::create(['user_id' => $u->id, 'carrera_id' => $carrera->id, 'num_control' => (string) random_int(210000000, 229999999),
        'grado' => 7, 'turno' => 'matutino', 'vigencia_imss' => now()->addYear(), 'porcentaje_creditos' => $creditos]);
    foreach (['kardex', 'constancia_imss'] as $col) {
        $a->resolverDocumento($a->addMedia(ctPdf("$col.pdf"))->toMediaCollection($col), ValidacionEstado::Validada, null, $coord->id);
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
    $this->coord = ctUsuario(Rol::Coordinador, ['carrera_id' => $this->carrera->id]);
});

test('con créditos bajo el mínimo el expediente queda «no cumple» y la oferta sigue cerrada', function () {
    $alumno = ctAlumno($this->carrera, 45, $this->coord);

    expect($alumno->estadoExpediente())->toBe('no_cumple')
        ->and($alumno->expedienteValidado())->toBeFalse()
        ->and(Requisitos::alumnoVerOferta($alumno))->toContain('requiere al menos el 60%');

    $this->actingAs($alumno->user)->get(route('alumno.dashboard'))
        ->assertOk()
        ->assertDontSee('Ya puedes postularte')
        ->assertSee('No cumples el mínimo de créditos de tu carrera');

    $this->actingAs($alumno->user)->get(route('alumno.mis-documentos'))
        ->assertOk()
        ->assertSee('No cumples el mínimo de créditos de tu carrera')
        ->assertSee('Enviar kardex actualizado');
});

test('el mismo expediente con créditos suficientes sí queda validado', function () {
    $alumno = ctAlumno($this->carrera, 72, $this->coord);

    expect($alumno->estadoExpediente())->toBe('validado')
        ->and(Requisitos::alumnoVerOferta($alumno))->toBeNull();
});

test('el alumno no puede enviar un kardex con menos créditos que el mínimo de su carrera', function () {
    $alumno = ctAlumno($this->carrera, 45, $this->coord);

    // Por debajo del mínimo: se rechaza el envío y no llega a la coordinación
    $this->actingAs($alumno->user)->post(route('alumno.documentos.kardex'), ['porcentaje_creditos' => 55, 'archivo' => ctPdf('kardex.pdf')])
        ->assertSessionHasErrors(['porcentaje_creditos' => 'Tu carrera requiere al menos el 60% de créditos cursados para iniciar el trámite.']);
    expect($alumno->fresh()->estadoExpediente())->toBe('no_cumple');

    // Sin capturar el porcentaje tampoco: es obligatorio mientras no cumpla
    $this->actingAs($alumno->user)->post(route('alumno.documentos.kardex'), ['archivo' => ctPdf('kardex.pdf')])
        ->assertSessionHasErrors('porcentaje_creditos');

    // Con los créditos ya cumplidos: kardex nuevo a revisión con su porcentaje
    $this->actingAs($alumno->user)->post(route('alumno.documentos.kardex'), ['porcentaje_creditos' => 64.5, 'archivo' => ctPdf('kardex-nuevo.pdf')])
        ->assertSessionHasNoErrors();
    $alumno->refresh();
    expect((float) $alumno->porcentaje_creditos)->toBe(64.5)
        ->and($alumno->estadoExpediente())->toBe('en_revision');
});

test('el primer envío del expediente se rechaza con el mensaje del mínimo de la carrera', function () {
    $u = ctUsuario(Rol::Alumno);
    Alumno::create(['user_id' => $u->id, 'carrera_id' => $this->carrera->id, 'num_control' => '219876543', 'grado' => 7, 'turno' => 'matutino']);

    $this->actingAs($u->fresh())->post(route('alumno.documentos.expediente'), [
        'porcentaje_creditos' => 35, 'kardex' => ctPdf('k.pdf'), 'imss' => ctPdf('i.pdf'), 'vigencia_imss' => now()->addYear()->toDateString(),
    ])->assertSessionHasErrors(['porcentaje_creditos' => 'Tu carrera requiere al menos el 60% de créditos cursados para iniciar el trámite.']);

    expect($u->fresh()->alumno->documentoVigente('kardex'))->toBeNull();
});

test('la coordinación no puede validar por separado un kardex con créditos insuficientes', function () {
    $u = ctUsuario(Rol::Alumno);
    $alumno = Alumno::create(['user_id' => $u->id, 'carrera_id' => $this->carrera->id, 'num_control' => '219876544', 'grado' => 7, 'turno' => 'matutino',
        'vigencia_imss' => now()->addYear(), 'porcentaje_creditos' => 40]);
    $kardex = $alumno->addMedia(ctPdf('kardex.pdf'))->toMediaCollection('kardex');

    $this->actingAs($this->coord)->post(route('coordinacion.documentos.resolver', $kardex), ['estado' => 'validada'])
        ->assertSessionHasErrors('revision');

    expect($kardex->fresh()->getCustomProperty('estado'))->not->toBe(ValidacionEstado::Validada->value);
});

test('al completar las horas se pide la carta de término a la organización, que la emite', function () {
    $alumno = ctAlumno($this->carrera, 72, $this->coord);
    $empresaUser = ctUsuario(Rol::Empresa);
    $empresa = Empresa::create(['user_id' => $empresaUser->id, 'rfc' => 'DRA120601KP3', 'razon_social' => 'Despacho Ruiz & Asociados S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto Despacho']);
    $convenio = Convenio::create(['empresa_id' => $empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-CT-1',
        'fecha_recepcion' => now()->subYear(), 'fecha_inicio_vigencia' => now()->subMonths(6), 'fecha_fin_vigencia' => now()->addYears(2),
        'objetivos' => 'x', 'actividades_propuestas' => 'x', 'plazas_estimadas' => 2, 'medio_conocimiento' => 'x', 'experiencia_previa' => 'x', 'vigencia_anios' => 2]);
    $convenio->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Aprobatorio]);
    $programa = Programa::create(['convenio_id' => $convenio->id, 'carrera_id' => $this->carrera->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Auxiliar jurídico', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p', 'cupos_totales' => 2, 'estado' => ProgramaEstado::Publicado]);
    $postulacion = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 72, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $postulacion->id, 'autorizado_por' => $this->coord->personal->id, 'modalidad' => 'externa',
        'fecha_inicio' => today()->subMonths(3), 'fecha_fin' => today()->addMonth(), 'horas_requeridas' => 300, 'estado' => PracticaEstado::EnCurso]);

    Reporte::create(['practica_id' => $practica->id, 'tipo' => 'mensual', 'periodo_inicio' => today()->subMonths(3), 'periodo_fin' => today()->subMonth(),
        'horas_reportadas' => 200, 'estado' => ReporteEstado::Validado]);
    $ultimo = Reporte::create(['practica_id' => $practica->id, 'tipo' => 'mensual', 'periodo_inicio' => today()->subMonth(), 'periodo_fin' => today(),
        'horas_reportadas' => 100, 'estado' => ReporteEstado::Pendiente]);
    $ultimo->addMedia(ctPdf('reporte.pdf'))->toMediaCollection('reporte_firmado');

    // Antes de completar las horas la organización no tiene nada pendiente de la carta
    expect(collect(Pendientes::de($empresaUser->fresh()))->pluck('mensaje')->implode(' '))->not->toContain('carta de término');

    $this->actingAs($this->coord)->post(route('coordinacion.reportes.resolver', $ultimo), ['estado' => ReporteEstado::Validado->value])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($alumno->user, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'pide a tu jefe directo en la organización tu carta de término'));
    Notification::assertSentTo($empresaUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'emite su carta de término en la hoja membretada'));

    // La organización lo ve como pendiente y en la ficha del practicante, hasta que el alumno suba la carta
    expect(collect(Pendientes::de($empresaUser->fresh()))->pluck('mensaje')->implode(' '))->toContain('emite su carta de término');
    $this->actingAs($empresaUser)->get(route('empresa.practicantes.show', $practica))
        ->assertOk()
        ->assertSee('completó sus horas: emite su carta de término');
});
