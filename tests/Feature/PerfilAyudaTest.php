<?php

/**
 * Mi perfil y Centro de ayuda especializados por rol.
 */

use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use Database\Seeders\RolesSeeder;

function paUsuario(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $div = Division::create(['nombre' => 'División de prueba']);
    $this->carrera = Carrera::create(['division_id' => $div->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Abogado',
        'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);
});

test('Vinculación y el Administrador no tienen Centro de ayuda', function (Rol $rol) {
    $u = paUsuario($rol);
    Personal::create(['user_id' => $u->id]);

    $this->actingAs($u)->get(route('ayuda'))->assertRedirect(route('dashboard'));
    $this->actingAs($u)->get(route('profile.edit'))->assertOk()->assertDontSee(route('ayuda'), false);
})->with([Rol::Vinculacion, Rol::Administrador]);

test('cada rol ve su propia guía en el Centro de ayuda', function () {
    $alumno = paUsuario(Rol::Alumno);
    Alumno::create(['user_id' => $alumno->id, 'carrera_id' => $this->carrera->id, 'num_control' => '215000300']);
    $this->actingAs($alumno)->get(route('ayuda'))->assertOk()
        ->assertSee('Centro de ayuda · Alumnos')->assertSee('Sube tu constancia IMSS')->assertSee('Tu coordinación');

    $coord = paUsuario(Rol::Coordinador);
    Personal::create(['user_id' => $coord->id, 'carrera_id' => $this->carrera->id]);
    $this->actingAs($coord)->get(route('ayuda'))->assertOk()
        ->assertSee('Centro de ayuda · Coordinación de carrera')->assertSee('Dictamina convenios')->assertSee(route('coordinacion.reglas'), false);

    $empresa = paUsuario(Rol::Empresa);
    Empresa::create(['user_id' => $empresa->id, 'rfc' => 'PAY010101AB1', 'razon_social' => 'Org Ayuda',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'C']);
    $this->actingAs($empresa)->get(route('ayuda'))->assertOk()
        ->assertSee('Centro de ayuda · Organizaciones')->assertSee('Solicite el convenio')->assertSee('Vinculación del CUCSH');
});

test('el perfil muestra la sección propia de cada rol', function () {
    $alumno = paUsuario(Rol::Alumno);
    Alumno::create(['user_id' => $alumno->id, 'carrera_id' => $this->carrera->id, 'num_control' => '215000301']);
    $this->actingAs($alumno)->get(route('profile.edit'))->assertOk()
        ->assertSee('Datos escolares')->assertSee('¿Estás listo para postularte?')->assertSee('Grado y turno capturados');

    $coord = paUsuario(Rol::Coordinador);
    Personal::create(['user_id' => $coord->id, 'carrera_id' => $this->carrera->id]);
    $this->actingAs($coord)->get(route('profile.edit'))->assertOk()
        ->assertSee('Carreras a tu cargo')->assertSee('Contacto que ven los alumnos');
});

test('Mi perfil de la organización muestra sus datos sin formularios para editarlos', function () {
    $u = paUsuario(Rol::Empresa);
    Empresa::create(['user_id' => $u->id, 'rfc' => 'PAY010101AB2', 'razon_social' => 'Org Perfil',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Ana Contacto', 'descripcion' => 'Despacho jurídico']);

    $this->actingAs($u)->get(route('profile.edit', ['seccion' => 'organizacion']))->assertOk()
        ->assertSee('Ana Contacto')->assertSee('Despacho jurídico')->assertSee('Org Perfil')
        ->assertSee('Editar en Mi convenio')
        ->assertDontSee('Guardar datos de la organización');
});
