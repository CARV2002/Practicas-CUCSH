<?php

use App\Enums\Rol;
use App\Models\Carrera;
use App\Models\Division;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => $this->seed(RolesSeeder::class));

test('sin correo verificado no se entra a ningún panel', function () {
    $paneles = [
        Rol::Alumno->value => 'alumno.dashboard',
        Rol::Empresa->value => 'empresa.dashboard',
        Rol::Administrador->value => 'admin.dashboard',
    ];

    foreach ($paneles as $rol => $ruta) {
        $user = User::factory()->unverified()->create();
        $user->assignRole($rol);

        $this->actingAs($user)->get(route($ruta))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }
});

test('un usuario sin verificar aún puede ver el aviso, la ayuda y cerrar sesión', function () {
    $user = User::factory()->unverified()->create();
    $user->assignRole(Rol::Alumno->value);

    $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    $this->actingAs($user)->get(route('ayuda'))->assertOk();
    $this->actingAs($user)->post('/logout')->assertRedirect('/');
});

test('al registrarse se envía el correo y al confirmarlo se entra al panel', function () {
    Notification::fake();

    $division = Division::create(['nombre' => 'División de prueba']);
    $carrera = Carrera::create([
        'division_id' => $division->id, 'carrera_clave' => 'TEST', 'nombre' => 'Carrera de prueba',
        'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8,
    ]);

    $this->post('/registro/alumno', [
        'name' => 'Ana Alumna', 'email' => 'ana@example.com',
        'password' => 'password-segura-123', 'password_confirmation' => 'password-segura-123',
        'carrera_id' => $carrera->id, 'num_control' => '215123456', 'aviso_privacidad' => '1',
    ])->assertRedirect();

    $user = User::where('email', 'ana@example.com')->firstOrFail();
    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get('/dashboard')->assertRedirect(route('verification.notice'));

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id, 'hash' => sha1($user->email),
    ]);
    $this->get($url);

    $this->get('/dashboard')->assertRedirect(route('alumno.dashboard'));
});

test('la coordinación puede marcar un correo como verificado', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Rol::Administrador->value);
    $pendiente = User::factory()->unverified()->create();

    $this->actingAs($admin)->post(route('admin.usuarios.verificar', $pendiente))->assertRedirect();

    expect($pendiente->refresh()->hasVerifiedEmail())->toBeTrue();
});