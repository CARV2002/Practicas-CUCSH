<?php

/**
 * El escenario «desde cero» deja una organización y un alumno recién registrados y, al volver a correrlo,
 * borra todo lo que hicieron (convenio, vacante, postulación, práctica, reportes y archivos).
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Models\Alumno;
use App\Models\Convenio;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Database\Seeders\PruebaDesdeCeroSeeder as Semilla;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

function pdcPdf(string $n): UploadedFile
{
    return UploadedFile::fake()->createWithContent($n, "%PDF-1.4\n%%EOF");
}

beforeEach(fn () => Storage::fake('local'));

test('crea la organización y el alumno como recién registrados', function () {
    $this->seed(Semilla::class);

    $empresa = Empresa::where('rfc', Semilla::EMPRESA_RFC)->firstOrFail();
    $alumno = Alumno::where('num_control', Semilla::ALUMNO_CONTROL)->firstOrFail();

    expect($empresa->user->email)->toBe(Semilla::EMPRESA_CORREO)
        ->and($empresa->user->hasRole('empresa'))->toBeTrue()
        ->and($empresa->user->hasVerifiedEmail())->toBeTrue()
        ->and($empresa->convenios()->count())->toBe(0)
        ->and(Media::where('model_type', Empresa::class)->count())->toBe(0)
        ->and($alumno->user->email)->toBe(Semilla::ALUMNO_CORREO)
        ->and($alumno->carrera->carrera_clave)->toBe('ABOG')
        ->and($alumno->getMedia('kardex'))->toHaveCount(0)
        ->and($alumno->vigencia_imss)->toBeNull();

    $this->post(route('login'), ['email' => Semilla::ALUMNO_CORREO, 'password' => 'password123']);
    $this->assertAuthenticatedAs($alumno->user);
});

test('al volver a correrlo borra todo lo que hicieron y los deja desde cero', function () {
    $this->seed(Semilla::class);
    $empresa = Empresa::where('rfc', Semilla::EMPRESA_RFC)->firstOrFail();
    $alumno = Alumno::where('num_control', Semilla::ALUMNO_CONTROL)->firstOrFail();

    // Recorrido avanzado: convenio formalizado, vacante, postulación, práctica con reporte y archivos
    $empresa->addMedia(pdcPdf('acta.pdf'))->toMediaCollection('acta_constitutiva');
    $alumno->addMedia(pdcPdf('kardex.pdf'))->toMediaCollection('kardex');
    $convenio = Convenio::create(['empresa_id' => $empresa->id, 'estado' => ConvenioEstado::Formalizado, 'fecha_recepcion' => now(), 'folio' => 'CV-PDC-1',
        'fecha_inicio_vigencia' => today()->subMonth(), 'fecha_fin_vigencia' => today()->addYear(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $convenio->dictamenes()->create(['carrera_id' => $alumno->carrera_id, 'dictamen' => Dictamen::Aprobatorio]);
    $programa = Programa::create(['convenio_id' => $convenio->id, 'carrera_id' => $alumno->carrera_id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Auxiliar jurídico', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p', 'cupos_totales' => 1, 'estado' => ProgramaEstado::Publicado]);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $programa->id, 'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $po->addMedia(pdcPdf('aceptacion.pdf'))->toMediaCollection('carta_aceptacion');
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today(),
        'fecha_fin' => today()->addMonths(4), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso]);
    Reporte::create(['practica_id' => $practica->id, 'tipo' => 'mensual', 'periodo_inicio' => today(), 'periodo_fin' => today()->addMonth(), 'horas_reportadas' => 40]);

    $this->seed(Semilla::class);

    expect(User::whereIn('email', [Semilla::EMPRESA_CORREO, Semilla::ALUMNO_CORREO])->count())->toBe(2)
        ->and(Convenio::withTrashed()->count())->toBe(0)
        ->and(Programa::count())->toBe(0)
        ->and(Postulacion::count())->toBe(0)
        ->and(Practica::withTrashed()->count())->toBe(0)
        ->and(Reporte::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        ->and(Alumno::where('num_control', Semilla::ALUMNO_CONTROL)->first()->getMedia('kardex'))->toHaveCount(0);
});
