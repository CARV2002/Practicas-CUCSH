<?php

use App\Enums\Rol;
use App\Enums\Turno;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Division;
use App\Models\Empresa;
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
        'grado' => 6,
        'turno' => 'matutino',
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
        ->and($user->alumno->grado)->toBe(6)
        ->and($user->alumno->turno)->toBe(Turno::Matutino)
        ->and($user->aviso_privacidad_at)->not->toBeNull();
});

test('companies register with the company role and profile', function () {
    $response = $this->post('/registro/empresa', datosEmpresa());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'rh@empresa.com')->firstOrFail();

    expect($user->hasRole(Rol::Empresa->value))->toBeTrue()
        ->and($user->empresa->rfc)->toBe('EPR010101AB1')
        ->and($user->empresa->contacto_nombre)->toBe('Pedro Contacto')
        // Solo la cuenta: lo legal y el expediente se llenan después en «Mi convenio»
        ->and($user->empresa->representante_legal)->toBeNull();
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

test('el código de alumno son exactamente 9 números y el nombre lleva nombre y apellido', function () {
    foreach (['21512345', '2151234567', '21512345A', 'ABCDEFGHI'] as $malo) {
        $this->post('/registro/alumno', datosAlumno(['num_control' => $malo]))
            ->assertSessionHasErrors(['num_control' => 'El código de alumno son 9 números (ej. 000000000).']);
    }
    $this->post('/registro/alumno', datosAlumno(['name' => 'Ana']))->assertSessionHasErrors('name');
    $this->post('/registro/alumno', datosAlumno(['name' => 'Ana López 3']))->assertSessionHasErrors('name');
    $this->assertGuest();

    // Pegado con espacios o guiones: se limpia y se acepta
    $this->post('/registro/alumno', datosAlumno(['num_control' => '215-123 456', 'name' => '  Ana   López  ']))->assertSessionHasNoErrors();
    expect(Alumno::first()->num_control)->toBe('215123456')
        ->and(User::first()->name)->toBe('Ana López');
});

test('el registro de empresa valida el RFC del SAT y el teléfono', function () {
    $this->post('/registro/empresa', datosEmpresa(['contacto_telefono' => '33 1234']))
        ->assertSessionHasErrors(['contacto_telefono' => 'Escribe un teléfono real de 10 números (ej. 3312345678).']);
    // Genérico del SAT, fecha imposible y largo incorrecto
    $this->post('/registro/empresa', datosEmpresa(['rfc' => 'XAXX010101000']))->assertSessionHasErrors(['rfc' => 'Ese es un RFC genérico del SAT: escribe el RFC propio de tu organización.']);
    $this->post('/registro/empresa', datosEmpresa(['rfc' => 'ABC011340AB1']))->assertSessionHasErrors('rfc');
    $this->post('/registro/empresa', datosEmpresa(['rfc' => 'ABC0101']))->assertSessionHasErrors('rfc');
    $this->post('/registro/empresa', datosEmpresa(['name' => str_repeat('Nombre ', 15).'Largo']))->assertSessionHasErrors('name');
    $this->assertGuest();

    // Persona física (13) y RFC con guiones o en minúsculas: se normaliza
    $this->post('/registro/empresa', datosEmpresa(['rfc' => 'pegs-850812-xyz', 'contacto_telefono' => '33 1234 5678']))->assertSessionHasNoErrors();
    $empresa = Empresa::first();
    expect($empresa->rfc)->toBe('PEGS850812XYZ')->and($empresa->contacto_telefono)->toBe('3312345678');
});
