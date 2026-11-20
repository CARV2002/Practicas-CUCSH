<?php

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Alumno;
use App\Models\AuditLog;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\User;
use Database\Seeders\RolesSeeder;

function infUser(Rol $rol, array $extra = []): User
{
    $u = User::factory()->create($extra);
    $u->assignRole($rol->value);

    return $u;
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $div = Division::create(['nombre' => 'División de prueba']);
    $datos = ['division_id' => $div->id, 'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8];
    $this->abog = Carrera::create($datos + ['carrera_clave' => 'ABOG', 'nombre' => 'Abogado']);
    $this->hist = Carrera::create($datos + ['carrera_clave' => 'HIST', 'nombre' => 'Historia']);

    $this->adminGlobal = infUser(Rol::Administrador);
    Personal::create(['user_id' => $this->adminGlobal->id, 'puesto' => 'Coordinación']);
    $this->adminAbog = infUser(Rol::Administrador);
    Personal::create(['user_id' => $this->adminAbog->id, 'carrera_id' => $this->abog->id, 'puesto' => 'Coordinador ABOG']);

    $empresaUser = infUser(Rol::Empresa);
    $empresa = Empresa::create([
        'user_id' => $empresaUser->id, 'rfc' => 'EPA010101AB1', 'razon_social' => 'Empresa de Prueba',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto',
    ]);

    $convenio = Convenio::create([
        'empresa_id' => $empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-INF-001',
        'fecha_recepcion' => now()->subMonths(3), 'fecha_inicio_vigencia' => now()->subMonth(),
        'fecha_fin_vigencia' => now()->addYears(3), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 5,
    ]);

    // Dos alumnos de carreras distintas; el nombre de Ana intenta colar una fórmula
    foreach ([['ABOG', '215000001', '=HYPERLINK("http://sitio-malo.test")', $this->abog], ['HIST', '215000002', 'Luis Historia', $this->hist]] as [$clave, $control, $nombre, $carrera]) {
        $convenio->dictamenes()->create(['carrera_id' => $carrera->id, 'dictamen' => Dictamen::Aprobatorio]);

        $programa = Programa::create([
            'convenio_id' => $convenio->id, 'carrera_id' => $carrera->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
            'titulo' => "Programa $clave", 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
            'cupos_totales' => 2, 'estado' => ProgramaEstado::Publicado,
        ]);

        $user = infUser(Rol::Alumno, ['name' => $nombre]);
        $alumno = Alumno::create(['user_id' => $user->id, 'carrera_id' => $carrera->id, 'num_control' => $control]);

        $po = Postulacion::create([
            'alumno_id' => $alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::Aceptada,
            'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today(),
        ]);
        Practica::create([
            'postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
            'fecha_fin' => today()->addMonths(5), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso,
        ]);
    }
});

test('solo la coordinación ve los informes', function () {
    $this->actingAs($this->adminGlobal)->get(route('admin.informes'))->assertOk()->assertSee('Resumen de prácticas');

    $this->actingAs(conPerfil(infUser(Rol::Alumno)))->get(route('admin.informes'))->assertForbidden();
    $this->actingAs(conPerfil(infUser(Rol::Empresa)))->get(route('admin.informes.exportar', 'practicas'))->assertForbidden();
});

test('la exportación es un CSV con BOM, neutraliza fórmulas y respeta filtros', function () {
    $respuesta = $this->actingAs($this->adminGlobal)->get(route('admin.informes.exportar', 'practicas'))->assertOk();
    $csv = $respuesta->streamedContent();

    expect($respuesta->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Código de alumno')->toContain('215000001')->toContain('215000002')
        ->and($csv)->toContain("'=HYPERLINK")            // la fórmula queda neutralizada
        ->and($csv)->not->toContain(',=HYPERLINK')->not->toContain('"=HYPERLINK');

    $filtrado = $this->actingAs($this->adminGlobal)
        ->get(route('admin.informes.exportar', ['tipo' => 'practicas', 'estado' => 'acreditada']))->streamedContent();

    expect($filtrado)->not->toContain('215000001');
});

test('una coordinación de una carrera solo exporta lo de su alcance', function () {
    $csv = $this->actingAs($this->adminAbog)->get(route('admin.informes.exportar', 'practicas'))->streamedContent();

    expect($csv)->toContain('215000001')->not->toContain('215000002');

    $programas = $this->actingAs($this->adminAbog)->get(route('admin.informes.exportar', 'programas'))->streamedContent();

    expect($programas)->toContain('Programa ABOG')->not->toContain('Programa HIST');
});

test('cada exportación deja rastro en la auditoría', function () {
    $this->actingAs($this->adminGlobal)->get(route('admin.informes.exportar', 'convenios'))->streamedContent();

    $log = AuditLog::where('accion', 'informe.exportado')->firstOrFail();

    expect((int) $log->user_id)->toBe($this->adminGlobal->id)->and($log->descripcion)->toContain('convenios');
});

test('los cuatro informes tienen sus encabezados', function () {
    foreach (['practicas' => 'Horas validadas', 'postulaciones' => '% créditos declarado', 'convenios' => 'Días para vencer', 'programas' => 'Cupos disponibles'] as $tipo => $columna) {
        $csv = $this->actingAs($this->adminGlobal)->get(route('admin.informes.exportar', $tipo))->streamedContent();

        expect($csv)->toContain($columna);
    }

    $this->actingAs($this->adminGlobal)->get('/admin/informes/otro/exportar')->assertNotFound();
});