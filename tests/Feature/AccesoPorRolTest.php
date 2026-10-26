<?php

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolesSeeder;

/** Pantallas de cada rol (nombres de ruta de routes/web.php). */
function pantallasPorRol(): array
{
    return [
        Rol::Alumno->value => ['alumno.dashboard', 'alumno.oferta-programas', 'alumno.mis-practicas', 'alumno.mis-documentos'],
        Rol::Empresa->value => ['empresa.dashboard', 'empresa.convenio', 'empresa.documentos', 'empresa.lista-programas', 'empresa.lista-practicantes'],
        Rol::Administrador->value => [
            'admin.dashboard', 'admin.lista-convenios', 'admin.lista-dictamenes', 'admin.lista-empresas',
            'admin.lista-programas', 'admin.lista-practicantes', 'admin.lista-reportes', 'admin.catalogos', 'admin.lista-usuarios',
        ],
    ];
}

function usuarioConRol(string $rol): User
{
    $user = User::factory()->create();
    $user->assignRole($rol);

    return $user;
}

beforeEach(fn () => $this->seed(RolesSeeder::class));

test('cada rol entra a todas sus pantallas y se renderizan', function () {
    foreach (pantallasPorRol() as $rol => $rutas) {
        $user = usuarioConRol($rol);

        foreach ($rutas as $ruta) {
            $this->actingAs($user)->get(route($ruta))->assertOk();
        }
    }
});

test('ningún rol entra a las pantallas de otro rol', function () {
    foreach (pantallasPorRol() as $rol => $rutas) {
        $user = usuarioConRol($rol);

        foreach (pantallasPorRol() as $otroRol => $rutasAjenas) {
            if ($otroRol === $rol) {
                continue;
            }

            foreach ($rutasAjenas as $ruta) {
                $this->actingAs($user)->get(route($ruta))->assertForbidden();
            }
        }
    }
});

test('un visitante sin sesión es enviado al login', function () {
    foreach (pantallasPorRol() as $rutas) {
        foreach ($rutas as $ruta) {
            $this->get(route($ruta))->assertRedirect(route('login'));
        }
    }
});

test('el dashboard envía a cada usuario al panel de su rol', function () {
    foreach (Rol::cases() as $rol) {
        $this->actingAs(usuarioConRol($rol->value))
            ->get('/dashboard')
            ->assertRedirect(route($rol->panel()));
    }
});

test('una cuenta sin rol no entra a ningún panel', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
});

test('el sitio público se muestra sin iniciar sesión', function () {
    foreach (['/', '/estudiantes', '/empresas', '/signin', '/register', '/registro/alumno', '/registro/empresa'] as $url) {
        $this->get($url)->assertOk();
    }
});
