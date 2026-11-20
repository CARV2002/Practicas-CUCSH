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
        'representante_legal' => 'Lic. María Representante',
        'representante_cargo' => 'Directora General',
        'domicilio_fiscal' => 'Av. Juárez 100, Guadalajara, Jalisco',
        ...datosNotarialesPrueba(),
        'objeto_social' => 'la prestación de servicios de consultoría',
        'facultad_firmante' => 'el poder notarial 567 del 2 de febrero de 2020',
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
        ->and($user->alumno->turno)->toBe(\App\Enums\Turno::Matutino)
        ->and($user->aviso_privacidad_at)->not->toBeNull();
});

test('companies register with the company role and profile', function () {
    $response = $this->post('/registro/empresa', datosEmpresa());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'rh@empresa.com')->firstOrFail();

    expect($user->hasRole(Rol::Empresa->value))->toBeTrue()
        ->and($user->empresa->rfc)->toBe('EPR010101AB1')
        ->and($user->empresa->representante_legal)->toBe('Lic. María Representante')
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

test('una sociedad captura su escritura por separado y una dependencia pública su decreto', function () {
    $sinEscritura = array_diff_key(datosEmpresa(), array_flip(\App\Support\DatosNotariales::CAMPOS));

    $this->post('/registro/empresa', $sinEscritura)
        ->assertSessionHasErrors(['escritura_numero', 'escritura_fecha', 'notario_nombre', 'notaria_numero', 'notaria_ciudad']);
    $this->assertGuest();

    $this->post('/registro/empresa', array_merge($sinEscritura, ['tipo_organismo' => 'publico', 'constitucion' => '']))
        ->assertSessionHasErrors('constitucion');

    $this->post('/registro/empresa', array_merge($sinEscritura, ['tipo_organismo' => 'publico', 'constitucion' => 'el decreto 123 publicado el 1 de enero de 2000']))
        ->assertSessionHasNoErrors();
    $empresa = \App\Models\Empresa::latest('id')->first();
    expect($empresa->escritura_numero)->toBeNull()->and($empresa->constitucion)->toBe('el decreto 123 publicado el 1 de enero de 2000');
});

test('el código de alumno son exactamente 9 números y el nombre lleva nombre y apellido', function () {
    foreach (['21512345', '2151234567', '21512345A', 'ABCDEFGHI'] as $malo) {
        $this->post('/registro/alumno', datosAlumno(['num_control' => $malo]))
            ->assertSessionHasErrors(['num_control' => 'El código de alumno son exactamente 9 números (por ejemplo 219123456).']);
    }
    $this->post('/registro/alumno', datosAlumno(['name' => 'Ana']))->assertSessionHasErrors('name');
    $this->post('/registro/alumno', datosAlumno(['name' => 'Ana López 3']))->assertSessionHasErrors('name');
    $this->assertGuest();

    // Pegado con espacios o guiones: se limpia y se acepta
    $this->post('/registro/alumno', datosAlumno(['num_control' => '215-123 456', 'name' => '  Ana   López  ']))->assertSessionHasNoErrors();
    expect(\App\Models\Alumno::first()->num_control)->toBe('215123456')
        ->and(User::first()->name)->toBe('Ana López');
});

test('la empresa captura RFC, teléfono y datos notariales con su formato', function () {
    $this->post('/registro/empresa', datosEmpresa(['contacto_telefono' => '33 1234']))
        ->assertSessionHasErrors(['contacto_telefono' => 'El teléfono son 10 números, sin espacios ni guiones (por ejemplo 3312345678).']);
    $this->post('/registro/empresa', datosEmpresa(['escritura_numero' => 'doce', 'notaria_numero' => 'cinco']))
        ->assertSessionHasErrors(['escritura_numero', 'notaria_numero']);
    $this->post('/registro/empresa', datosEmpresa(['representante_legal' => 'Director']))->assertSessionHasErrors('representante_legal');
    $this->post('/registro/empresa', datosEmpresa(['domicilio_fiscal' => 'Calle 1']))->assertSessionHasErrors('domicilio_fiscal');
    $this->assertGuest();

    // RFC en minúsculas o con guiones y teléfono con espacios: se normalizan
    $this->post('/registro/empresa', datosEmpresa(['rfc' => 'abc-010101-ab1', 'contacto_telefono' => '33 1234 5678', 'escritura_numero' => '12,345']))
        ->assertSessionHasNoErrors();
    $empresa = \App\Models\Empresa::first();
    expect($empresa->rfc)->toBe('ABC010101AB1')->and($empresa->contacto_telefono)->toBe('3312345678')->and($empresa->escritura_numero)->toBe('12345');
});
