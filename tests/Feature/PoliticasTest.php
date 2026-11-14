<?php

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Alumno;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

function polPdf(string $nombre = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function polUser(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

function polEmpresa(string $rfc): array
{
    $u = polUser(Rol::Empresa);

    return [$u, Empresa::create([
        'user_id' => $u->id, 'rfc' => $rfc, 'razon_social' => "Empresa $rfc",
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto',
    ])];
}

function polAlumno(Carrera $carrera, string $control): array
{
    $u = polUser(Rol::Alumno);

    return [$u, Alumno::create(['user_id' => $u->id, 'carrera_id' => $carrera->id, 'num_control' => $control])];
}

function polPractica(Alumno $alumno, Programa $programa): array
{
    $po = Postulacion::create([
        'alumno_id' => $alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today(),
    ]);

    $pr = Practica::create([
        'postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(5), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso,
    ]);

    return [$po, $pr];
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);

    $division = Division::create(['nombre' => 'División de prueba']);
    $datos = ['division_id' => $division->id, 'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8];
    $this->abog = Carrera::create($datos + ['carrera_clave' => 'ABOG', 'nombre' => 'Abogado']);
    $this->hist = Carrera::create($datos + ['carrera_clave' => 'HIST', 'nombre' => 'Historia']);

    // Tres tipos de administrador: global, con alcance solo a ABOG, y uno sin perfil de Personal
    $this->adminGlobal = polUser(Rol::Administrador);
    Personal::create(['user_id' => $this->adminGlobal->id, 'puesto' => 'Coordinación']);
    $this->adminAbog = polUser(Rol::Administrador);
    Personal::create(['user_id' => $this->adminAbog->id, 'carrera_id' => $this->abog->id, 'puesto' => 'Coordinador ABOG']);
    $this->adminSinPerfil = polUser(Rol::Administrador);

    [$this->userA, $this->empresaA] = polEmpresa('EPA010101AB1');
    [$this->userB, $this->empresaB] = polEmpresa('EPB020202CD2');

    $this->convenio = Convenio::create([
        'empresa_id' => $this->empresaA->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-P-001',
        'fecha_recepcion' => now()->subMonths(3), 'fecha_inicio_vigencia' => now()->subMonth(),
        'fecha_fin_vigencia' => now()->addYears(3), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 5,
    ]);
    foreach ([$this->abog, $this->hist] as $c) {
        $this->convenio->dictamenes()->create(['carrera_id' => $c->id, 'dictamen' => Dictamen::Aprobatorio]);
    }

    $this->convenioEnTramite = Convenio::create([
        'empresa_id' => $this->empresaA->id, 'estado' => ConvenioEstado::EnFirmas, 'fecha_recepcion' => now()->subDays(10),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 2,
    ]);

    $programa = fn (Carrera $c) => Programa::create([
        'convenio_id' => $this->convenio->id, 'carrera_id' => $c->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Programa '.$c->carrera_clave, 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 2, 'estado' => ProgramaEstado::Publicado,
    ]);
    $this->programaAbog = $programa($this->abog);
    $this->programaHist = $programa($this->hist);

    [$this->anaUser, $this->ana] = polAlumno($this->abog, '215000001');
    [$this->luisUser, $this->luis] = polAlumno($this->hist, '215000002');

    [$this->poAna, $this->practicaAna] = polPractica($this->ana, $this->programaAbog);
    [$this->poLuis, $this->practicaLuis] = polPractica($this->luis, $this->programaHist);

    $this->reporteAna = Reporte::create([
        'practica_id' => $this->practicaAna->id, 'tipo' => 'mensual', 'periodo_inicio' => today()->subWeeks(2),
        'periodo_fin' => today(), 'horas_reportadas' => 40,
    ]);
});

test('convenio: solo la empresa dueña y la coordinación con alcance lo ven', function () {
    expect($this->userA->can('view', $this->convenio))->toBeTrue()
        ->and($this->userB->can('view', $this->convenio))->toBeFalse()
        ->and($this->anaUser->can('view', $this->convenio))->toBeFalse()
        ->and($this->adminGlobal->can('view', $this->convenio))->toBeTrue()
        ->and($this->adminAbog->can('view', $this->convenio))->toBeTrue()            // ABOG aprobó en este convenio
        ->and($this->adminAbog->can('view', $this->convenioEnTramite))->toBeFalse()  // no lo solicitó su carrera
        ->and($this->adminSinPerfil->can('view', $this->convenio))->toBeFalse()      // sin perfil: se niega, no truena
        ->and($this->userA->can('gestionar', $this->convenio))->toBeFalse()          // la empresa no avanza su propio trámite
        ->and($this->adminGlobal->can('gestionar', $this->convenio))->toBeTrue();
});

test('la empresa solo descarga el convenio firmado cuando está formalizado', function () {
    expect($this->userA->can('descargarFirmado', $this->convenio))->toBeTrue()
        ->and($this->userA->can('descargarFirmado', $this->convenioEnTramite))->toBeFalse()
        ->and($this->userB->can('descargarFirmado', $this->convenio))->toBeFalse();
});

test('programas: la empresa dueña y la coordinación de esa carrera', function () {
    expect($this->userA->can('gestionar', $this->programaAbog))->toBeTrue()
        ->and($this->userB->can('gestionar', $this->programaAbog))->toBeFalse()
        ->and($this->anaUser->can('gestionar', $this->programaAbog))->toBeFalse()
        ->and($this->adminAbog->can('gestionar', $this->programaAbog))->toBeTrue()
        ->and($this->adminAbog->can('gestionar', $this->programaHist))->toBeFalse()
        ->and($this->adminGlobal->can('gestionar', $this->programaHist))->toBeTrue();
});

test('postulaciones: el alumno sube documentos pero no se valida a sí mismo', function () {
    expect($this->anaUser->can('subirDocumento', $this->poAna))->toBeTrue()
        ->and($this->luisUser->can('subirDocumento', $this->poAna))->toBeFalse()
        ->and($this->anaUser->can('gestionar', $this->poAna))->toBeFalse()
        ->and($this->userA->can('gestionar', $this->poAna))->toBeFalse()
        ->and($this->adminAbog->can('gestionar', $this->poAna))->toBeTrue()
        ->and($this->adminAbog->can('gestionar', $this->poLuis))->toBeFalse();
});

test('prácticas y reportes: cada actor toca solo lo que le corresponde', function () {
    expect($this->anaUser->can('view', $this->practicaAna))->toBeTrue()
        ->and($this->userA->can('view', $this->practicaAna))->toBeTrue()
        ->and($this->userB->can('view', $this->practicaAna))->toBeFalse()
        ->and($this->luisUser->can('view', $this->practicaAna))->toBeFalse()
        ->and($this->adminAbog->can('view', $this->practicaLuis))->toBeFalse()
        ->and($this->anaUser->can('entregarDocumentos', $this->practicaAna))->toBeTrue()
        ->and($this->userA->can('entregarDocumentos', $this->practicaAna))->toBeFalse()
        ->and($this->anaUser->can('gestionar', $this->practicaAna))->toBeFalse()
        ->and($this->userA->can('retroalimentar', $this->reporteAna))->toBeTrue()
        ->and($this->userB->can('retroalimentar', $this->reporteAna))->toBeFalse()
        ->and($this->anaUser->can('retroalimentar', $this->reporteAna))->toBeFalse()
        ->and($this->adminAbog->can('resolver', $this->reporteAna))->toBeTrue()
        ->and($this->anaUser->can('resolver', $this->reporteAna))->toBeFalse()
        ->and($this->anaUser->can('subirArchivo', $this->reporteAna))->toBeTrue();
});

test('descargas: matriz de quién puede bajar qué', function () {
    $kardex = $this->poAna->addMedia(polPdf())->toMediaCollection('kardex');
    $imss = $this->ana->addMedia(polPdf())->toMediaCollection('constancia_imss');
    $acta = $this->empresaA->addMedia(polPdf())->toMediaCollection('acta_constitutiva');
    $firmado = $this->convenio->addMedia(polPdf())->toMediaCollection('convenio_firmado');
    $borrador = $this->convenio->addMedia(polPdf())->toMediaCollection('borrador_convenio');
    $reporte = $this->reporteAna->addMedia(polPdf())->toMediaCollection('reporte_firmado');
    $oficio = OficioAcreditacion::create(['practica_id' => $this->practicaAna->id, 'emitido_por' => $this->adminGlobal->personal->id]);
    $oficioPdf = $oficio->addMedia(polPdf())->toMediaCollection('oficio_acreditacion');

    // Datos del alumno
    expect($this->anaUser->can('download', $kardex))->toBeTrue()
        ->and($this->luisUser->can('download', $kardex))->toBeFalse()
        ->and($this->userA->can('download', $kardex))->toBeFalse()          // la empresa no ve el kardex
        ->and($this->adminAbog->can('download', $kardex))->toBeTrue()
        ->and($this->anaUser->can('download', $imss))->toBeTrue()
        ->and($this->luisUser->can('download', $imss))->toBeFalse();

    // Expediente y convenio de la empresa
    expect($this->userA->can('download', $acta))->toBeTrue()
        ->and($this->userB->can('download', $acta))->toBeFalse()
        ->and($this->anaUser->can('download', $acta))->toBeFalse()
        ->and($this->adminGlobal->can('download', $acta))->toBeTrue()
        ->and($this->userA->can('download', $firmado))->toBeTrue()
        ->and($this->userB->can('download', $firmado))->toBeFalse()
        ->and($this->userA->can('download', $borrador))->toBeFalse()        // el borrador es interno
        ->and($this->adminGlobal->can('download', $borrador))->toBeTrue();

    // Reportes y oficio
    expect($this->userA->can('download', $reporte))->toBeTrue()             // la empresa ve los reportes de sus practicantes
        ->and($this->userB->can('download', $reporte))->toBeFalse()
        ->and($this->adminAbog->can('download', $oficioPdf))->toBeTrue()
        ->and($this->anaUser->can('download', $oficioPdf))->toBeFalse()
        ->and($this->adminSinPerfil->can('download', $oficioPdf))->toBeFalse();

    // Lo denegado responde 404 (no confirma que el archivo exista)
    expect(Gate::forUser($this->luisUser)->inspect('download', $imss)->status())->toBe(404);
});