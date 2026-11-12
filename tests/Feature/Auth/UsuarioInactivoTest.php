<?php

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(fn () => $this->seed(RolesSeeder::class));

function usuarioDe(string $rol, bool $activo = true): User
{
    $user = User::factory()->create(['activo' => $activo]);
    $user->assignRole($rol);

    return $user;
}

test('un usuario inactivo no puede iniciar sesión aunque la contraseña sea correcta', function () {
    $user = usuarioDe(Rol::Alumno->value, activo: false);

    $this->post('/signin', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('un usuario activo sigue entrando normalmente', function () {
    $user = usuarioDe(Rol::Empresa->value);

    $this->post('/signin', ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('el mensaje de cuenta desactivada solo aparece con la contraseña correcta', function () {
    $user = usuarioDe(Rol::Alumno->value, activo: false);

    $this->post('/signin', ['email' => $user->email, 'password' => 'incorrecta'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('si desactivan a un usuario con sesión abierta, la siguiente petición lo saca', function () {
    $user = usuarioDe(Rol::Administrador->value);
    $this->actingAs($user);

    $user->update(['activo' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('un administrador no puede desactivarse a sí mismo', function () {
    $admin = usuarioDe(Rol::Administrador->value);
    $admin->personal()->create(['puesto' => 'Coordinación']);

    $this->actingAs($admin)
        ->post(route('admin.usuarios.activo', $admin))
        ->assertSessionHasErrors('usuario');

    expect($admin->refresh()->activo)->toBeTrue();
});