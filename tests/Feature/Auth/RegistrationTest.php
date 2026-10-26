<?php

use App\Enums\Rol;
use App\Models\Carrera;
use App\Models\Division;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $division = Division::create(['nombre' => 'División de prueba']);

    $this->carrera = Carrera::create([
        'division_id' => $division->id,
        'carrera_clave' => 'TEST',
        'nombre' => 'Carrera de prueba',
        'horas_requeridas' => 250,
        'porcentaje_creditos_minimo' => 50,
        'creditos_otorgados' => 8,
    ]);
});

function datosAlumno(array $extra = []): array
{
    return array_merge([
        'name' => 'Ana Alumna',
        'email' => 'ana@example.com',
        'password' => 'password-segura-123',
        'password_confirmation' => 'password-segura-123',
        'carrera_id' => test()->carrera->id,
        'num_control' => '215123456',
        'aviso_privacidad' => '1',
    ], $extra);
}

function datosEmpresa(array $extra = []): array
{
    return array_merge([
        'name' => 'Pedro Contacto',
        'contacto_cargo' => 'Recursos Humanos',
        'contacto_telefono' => '3312345678',
        'email' => 'rh@empresa.com',
        'password' => 'password-segura-123',
        'password_confirmation' => 'password-segura-123',
        'razon_social' => 'Empresa de Prueba S.A. de C.V.',
        'rfc' => 'epr010101ab1',          // se normaliza a mayúsculas
        'tipo_organismo' => 'privado',
        'aviso_privacidad' => '1',
    ], $extra);
}

test('registration screens can be rendered', function () {
    $this->get('/register')->assertOk();
    $this->get('/registro/alumno')->assertOk();
    $this->get('/registro/empresa')->assertOk();
});

test('students register with the student role and profile', function () {
    $response = $this->post('/registro/alumno', datosAlumno());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'ana@example.com')->firstOrFail();

    expect($user->hasRole(Rol::Alumno->value))->toBeTrue()
        ->and($user->alumno->num_control)->toBe('215123456')
        ->and($user->alumno->carrera_id)->toBe($this->carrera->id)
        ->and($user->aviso_privacidad_at)->not->toBeNull();
});

test('companies register with the company role and profile', function () {
    $response = $this->post('/registro/empresa', datosEmpresa());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'rh@empresa.com')->firstOrFail();

    expect($user->hasRole(Rol::Empresa->value))->toBeTrue()
        ->and($user->empresa->rfc)->toBe('EPR010101AB1')
        ->and($user->empresa->contacto_nombre)->toBe('Pedro Contacto');
});

test('registration requires accepting the privacy notice', function () {
    $this->post('/registro/alumno', datosAlumno(['aviso_privacidad' => null]))
        ->assertSessionHasErrors('aviso_privacidad');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('companies must provide a valid and unique RFC', function () {
    $this->post('/registro/empresa', datosEmpresa(['rfc' => 'NO-ES-UN-RFC']))
        ->assertSessionHasErrors('rfc');

    $this->post('/registro/empresa', datosEmpresa());
    auth()->logout();

    $this->post('/registro/empresa', datosEmpresa(['email' => 'otra@empresa.com']))
        ->assertSessionHasErrors('rfc');
});

test('students must choose an existing career and a unique student code', function () {
    $this->post('/registro/alumno', datosAlumno(['carrera_id' => 9999]))
        ->assertSessionHasErrors('carrera_id');

    $this->post('/registro/alumno', datosAlumno());
    auth()->logout();

    $this->post('/registro/alumno', datosAlumno(['email' => 'otra@example.com']))
        ->assertSessionHasErrors('num_control');
});

test('the generic register endpoint no longer creates role-less accounts', function () {
    $this->post('/register', [
        'name' => 'Sin Rol',
        'email' => 'sinrol@example.com',
        'password' => 'password-segura-123',
        'password_confirmation' => 'password-segura-123',
    ])->assertStatus(405);

    expect(User::where('email', 'sinrol@example.com')->exists())->toBeFalse();
});

test('el administrador se crea con el comando y no por registro público', function () {
    $this->artisan('practicas:crear-administrador', ['email' => 'Lorena@udg.mx', '--nombre' => 'Lorena'])
        ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'clave-segura-123')
        ->assertSuccessful();

    $user = User::where('email', 'lorena@udg.mx')->firstOrFail();

    expect($user->hasRole(Rol::Administrador->value))->toBeTrue()
        ->and($user->personal)->not->toBeNull()
        ->and($user->personal->tieneAlcanceGlobal())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('el comando rechaza contraseñas cortas', function () {
    $this->artisan('practicas:crear-administrador', ['email' => 'lorena@udg.mx'])
        ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'corta')
        ->assertFailed();

    expect(User::where('email', 'lorena@udg.mx')->exists())->toBeFalse();
});
