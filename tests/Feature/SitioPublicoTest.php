<?php

use App\Enums\ConvenioEstado;
use App\Enums\TipoOrganismo;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\User;

function pubEmpresaConConvenio(string $rfc, string $razon, array $convenio = [], bool $activa = true): Empresa
{
    $user = User::factory()->create(['activo' => $activa]);

    $empresa = Empresa::create([
        'user_id' => $user->id,
        'rfc' => $rfc,
        'razon_social' => $razon,
        'tipo_organismo' => TipoOrganismo::Publico,
        'contacto_nombre' => 'Contacto',
    ]);

    Convenio::create($convenio + [
        'empresa_id' => $empresa->id,
        'estado' => ConvenioEstado::Formalizado,
        'folio' => 'CV-PUB-' . $rfc,
        'fecha_recepcion' => now()->subMonths(3),
        'fecha_inicio_vigencia' => now()->subMonth(),
        'fecha_fin_vigencia' => now()->addYears(3),
        'objetivos' => 'x',
        'actividades_propuestas' => 'y',
        'plazas_estimadas' => 5,
    ]);

    return $empresa;
}

test('inicio es público y responde sin datos', function () {
    $this->get(route('inicio'))
        ->assertOk()
        ->assertSee('Prácticas Profesionales')
        ->assertSee('Aquí aparecerán las organizaciones con convenio vigente');
});

test('inicio solo lista organizaciones con convenio formalizado, vigente y cuenta activa', function () {
    pubEmpresaConConvenio('PUB010101AA1', 'Organización Vigente');
    pubEmpresaConConvenio('PUB020202BB2', 'Organización Sin Formalizar', ['estado' => ConvenioEstado::EnRevisionJuridica]);
    pubEmpresaConConvenio('PUB030303CC3', 'Organización Vencida', [
        'fecha_inicio_vigencia' => now()->subYears(4),
        'fecha_fin_vigencia' => now()->subYear(),
    ]);
    pubEmpresaConConvenio('PUB040404DD4', 'Organización Desactivada', [], activa: false);

    $this->get(route('inicio'))
        ->assertOk()
        ->assertSee('Organización Vigente')
        ->assertDontSee('Organización Sin Formalizar')
        ->assertDontSee('Organización Vencida')
        ->assertDontSee('Organización Desactivada');
});

test('inicio enlaza al login y al registro reales para visitantes', function () {
    $this->get(route('inicio'))
        ->assertOk()
        ->assertSee(route('login'), false)
        ->assertSee(route('registro.empresa'), false)
        ->assertDontSee('Symplicity');
});

test('inicio ofrece ir al panel cuando ya hay sesión', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertOk()
        ->assertSee('Mi panel');
});

test('estudiantes describe el proceso real y las horas y créditos de cada carrera', function () {
    $division = Division::create(['nombre' => 'División de Prueba']);
    Carrera::create([
        'division_id' => $division->id,
        'carrera_clave' => 'TST1',
        'nombre' => 'Licenciatura de Prueba',
        'horas_requeridas' => 321,
        'porcentaje_creditos_minimo' => 55.00,
        'creditos_otorgados' => 8,
    ]);

    $this->get(route('estudiantes'))
        ->assertOk()
        ->assertSee('División de Prueba')
        ->assertSee('Licenciatura de Prueba')
        ->assertSee('321 horas')
        ->assertSee('carta de presentación')
        ->assertSee('carta de término')
        ->assertSee('SIIAU')
        ->assertSee(route('registro.alumno'), false)
        ->assertDontSee('Oficio de Asignación')
        ->assertDontSee('500+')
        ->assertDontSee('<form', false);
});

test('estudiantes no muestra métricas cuando todavía no hay datos reales', function () {
    $this->get(route('estudiantes'))
        ->assertOk()
        ->assertDontSee('Prácticas acreditadas');
});

test('estudiantes ofrece ir al panel cuando ya hay sesión', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('estudiantes'))
        ->assertOk()
        ->assertSee('Ir a mi panel')
        ->assertDontSee(route('registro.alumno'), false);
});

test('empresas describe el proceso real del convenio con datos del sistema', function () {
    $division = Division::create(['nombre' => 'División de Prueba']);
    Carrera::create([
        'division_id' => $division->id,
        'carrera_clave' => 'TST2',
        'nombre' => 'Licenciatura en Carrera de Prueba',
        'horas_requeridas' => 200,
        'porcentaje_creditos_minimo' => 50.00,
        'creditos_otorgados' => 8,
    ]);
    pubEmpresaConConvenio('PUB050505EE5', 'Organización Vigente Empresas');
    pubEmpresaConConvenio('PUB060606FF6', 'Organización No Vigente', ['estado' => ConvenioEstado::EnFirmas]);

    $this->get(route('empresas'))
        ->assertOk()
        // Datos reales de la base
        ->assertSee('División de Prueba')
        ->assertSee('Carrera de Prueba')
        ->assertSee('Organización Vigente Empresas')
        ->assertDontSee('Organización No Vigente')
        // Plazos tomados del enum EtapaConvenio
        ->assertSee('1 a 3 días hábiles')
        ->assertSee('5 a 15 días hábiles')
        ->assertSee('sin plazo fijo')
        // Registro real en lugar del formulario falso
        ->assertSee(route('registro.empresa'), false)
        ->assertSee('practicas.profesionales.cucsh@csh.udg.mx')
        ->assertDontSee('vinculacion.cucsh@academicos.udg.mx')
        ->assertDontSee('<form', false)
        // Contenido que el sistema no respalda
        ->assertDontSee('Cultura y Comunicación')
        ->assertDontSee('firmados electrónicamente')
        ->assertDontSee('200+')
        ->assertDontSee('95%');
});

test('empresas funciona sin datos y no inventa métricas', function () {
    $this->get(route('empresas'))
        ->assertOk()
        ->assertSee('Cuatro fases hasta la firma')
        ->assertDontSee('Prácticas acreditadas');
});

test('empresas lista los tipos de organismo que acepta el registro', function () {
    $this->get(route('empresas'))
        ->assertOk()
        ->assertSee('Sociedad civil');
});

test('empresas ofrece ir al panel cuando ya hay sesión', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('empresas'))
        ->assertOk()
        ->assertSee('Ir a mi panel')
        ->assertDontSee('Registrar mi organización');
});
