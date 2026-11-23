<?php

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Str;
use App\Models\{Alumno, Carrera, Division, Empresa, Personal};
use App\Enums\TipoOrganismo;

/** Carrera de prueba (la usan el alumno y el coordinador). */
function carreraDePrueba(): Carrera
{
    return Carrera::firstOrCreate(['carrera_clave' => 'TEST'], [
        'division_id' => Division::firstOrCreate(['nombre' => 'División de prueba'])->id,
        'nombre' => 'Carrera de prueba',
        'horas_requeridas' => 250,
        'porcentaje_creditos_minimo' => 50,
        'creditos_otorgados' => 8,
    ]);
}

/** Pantallas EXCLUSIVAS de cada rol (nombres de ruta de routes/web.php). */
function pantallasPorRol(): array
{
    return [
        Rol::Alumno->value => ['alumno.dashboard', 'alumno.oferta-programas', 'alumno.mis-practicas', 'alumno.mis-documentos'],
        Rol::Empresa->value => ['empresa.dashboard', 'empresa.convenio', 'empresa.documentos', 'empresa.lista-programas', 'empresa.lista-practicantes'],
        Rol::Vinculacion->value => [
            'admin.dashboard',
            'admin.lista-convenios',
            'admin.lista-dictamenes',
            'admin.lista-empresas',
            'admin.informes',
        ],
        Rol::Coordinador->value => [
            'coordinacion.dashboard',
            'coordinacion.lista-programas',
            'coordinacion.lista-practicantes',
            'coordinacion.lista-reportes',
            'coordinacion.lista-cierres',
            'coordinacion.reglas',
        ],
    ];
}

/** Administración del sistema: solo el administrador. */
function pantallasDelSistema(): array
{
    return ['admin.lista-usuarios', 'admin.auditoria', 'admin.catalogos'];
}

/** El administrador entra a Vinculación, a Coordinación y a la administración del sistema. */
function pantallasDelAdministrador(): array
{
    return [
        ...pantallasPorRol()[Rol::Vinculacion->value],
        ...pantallasDelSistema(),
    ];
}

function usuarioConRol(string $rol): User
{
    $user = User::factory()->create();
    $user->assignRole($rol);

    match ($rol) {
        Rol::Administrador->value => Personal::create(['user_id' => $user->id, 'puesto' => 'Administración técnica']),
        Rol::Vinculacion->value => Personal::create(['user_id' => $user->id, 'puesto' => 'Vinculación']),
        Rol::Coordinador->value => Personal::create(['user_id' => $user->id, 'carrera_id' => carreraDePrueba()->id, 'puesto' => 'Coordinación TEST']),
        Rol::Empresa->value => Empresa::create([
            'user_id' => $user->id,
            'rfc' => 'T' . Str::upper(Str::random(11)),
            'razon_social' => 'Empresa ' . Str::random(4),
            'tipo_organismo' => TipoOrganismo::Privado,
            'contacto_nombre' => 'Contacto',
        ]),
        Rol::Alumno->value => Alumno::create([
            'user_id' => $user->id,
            'num_control' => Str::random(9),
            'carrera_id' => carreraDePrueba()->id,
        ]),
    };

    return $user;
}

beforeEach(fn() => $this->seed(RolesSeeder::class));

test('cada rol entra a todas sus pantallas y se renderizan', function () {
    foreach (pantallasPorRol() as $rol => $rutas) {
        $user = usuarioConRol($rol);

        foreach ($rutas as $ruta) {
            $this->actingAs($user)->get(route($ruta))->assertOk();
        }
    }
});

test('el administrador supervisa Vinculación y administra el sistema, pero no entra a las coordinaciones', function () {
    $admin = usuarioConRol(Rol::Administrador->value);

    foreach (pantallasDelAdministrador() as $ruta) {
        $this->actingAs($admin)->get(route($ruta))->assertOk();
    }
    // Autonomía académica: no valida kardex, no aprueba vacantes ni opera reportes o cierres
    foreach (pantallasPorRol()[Rol::Coordinador->value] as $ruta) {
        $this->actingAs($admin)->get(route($ruta))->assertForbidden();
    }
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Prácticas en todo el CUCSH')->assertSee('Coordinaciones en la plataforma')
        ->assertDontSee('Cierre de prácticas');
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

test('Vinculación no opera prácticas y Coordinación no opera convenios ni catálogos', function () {
    $vinculacion = usuarioConRol(Rol::Vinculacion->value);
    $coordinador = usuarioConRol(Rol::Coordinador->value);

    // Lorena ya no emite cartas, valida reportes ni cierra prácticas
    foreach (['coordinacion.lista-practicantes', 'coordinacion.lista-reportes', 'coordinacion.lista-cierres', 'coordinacion.lista-programas'] as $ruta) {
        $this->actingAs($vinculacion)->get(route($ruta))->assertForbidden();
    }

    // La coordinación no lleva el trámite de convenios
    foreach (['admin.lista-convenios', 'admin.lista-empresas', 'admin.lista-usuarios'] as $ruta) {
        $this->actingAs($coordinador)->get(route($ruta))->assertForbidden();
    }

    // Usuarios, auditoría y catálogo son solo del administrador
    foreach (pantallasDelSistema() as $ruta) {
        $this->actingAs($vinculacion)->get(route($ruta))->assertForbidden();
        $this->actingAs($coordinador)->get(route($ruta))->assertForbidden();
    }
    $this->actingAs($vinculacion)->post(route('admin.usuarios.store'), [])->assertForbidden();

    // Informes: Vinculación solo ve y exporta convenios
    $this->actingAs($vinculacion)->get(route('admin.informes'))->assertOk()
        ->assertSee('Informes de convenios')->assertDontSee('Resumen de prácticas');
    $this->actingAs($vinculacion)->get(route('admin.informes.exportar', 'convenios'))->assertOk();
    foreach (['practicas', 'postulaciones', 'programas'] as $tipo) {
        $this->actingAs($vinculacion)->get(route('admin.informes.exportar', $tipo))->assertForbidden();
    }
});

test('un visitante sin sesión es enviado al login', function () {
    foreach (pantallasDelAdministrador() as $ruta) {
        $this->get(route($ruta))->assertRedirect(route('login'));
    }

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

    expect(Rol::Vinculacion->panel())->toBe('admin.dashboard')
        ->and(Rol::Coordinador->panel())->toBe('coordinacion.dashboard');
});

test('una cuenta sin rol no entra a ningún panel', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
});

test('el sitio público se muestra sin iniciar sesión', function () {
    foreach (['/', '/estudiantes', '/empresas', '/signin', '/register', '/registro/alumno', '/registro/empresa'] as $url) {
        $this->get($url)->assertOk();
    }
});

test('personal sin perfil de Personal recibe un 403 y no un error', function () {
    foreach ([Rol::Administrador, Rol::Vinculacion, Rol::Coordinador] as $rol) {
        $user = User::factory()->create();
        $user->assignRole($rol->value);

        $this->actingAs($user)->get(route($rol->panel()))->assertForbidden();
    }
});

test('un coordinador sin carrera ni división asignada no entra a Coordinación', function () {
    $user = User::factory()->create();
    $user->assignRole(Rol::Coordinador->value);
    Personal::create(['user_id' => $user->id, 'puesto' => 'Coordinación sin alcance']);

    $this->actingAs($user)->get(route('coordinacion.dashboard'))->assertForbidden();
});

test('un coordinador solo ve en sus pantallas las carreras de su alcance', function () {
    $coordinador = usuarioConRol(Rol::Coordinador->value);
    $otra = Carrera::create([
        'division_id' => carreraDePrueba()->division_id, 'carrera_clave' => 'OTRA', 'nombre' => 'Carrera ajena',
        'horas_requeridas' => 300, 'porcentaje_creditos_minimo' => 60, 'creditos_otorgados' => 10,
    ]);

    $this->actingAs($coordinador)->get(route('coordinacion.reglas'))
        ->assertOk()->assertSee('Carrera de prueba')->assertDontSee('Carrera ajena');

    // No puede cambiar las reglas de otra carrera
    $this->actingAs($coordinador)
        ->put(route('coordinacion.reglas.update', $otra), ['horas_requeridas' => 1, 'porcentaje_creditos_minimo' => 1, 'creditos_otorgados' => 1])
        ->assertForbidden();

    $this->actingAs($coordinador)
        ->put(route('coordinacion.reglas.update', carreraDePrueba()), ['horas_requeridas' => 320, 'porcentaje_creditos_minimo' => 55, 'creditos_otorgados' => 9])
        ->assertRedirect();

    expect(carreraDePrueba()->fresh()->horas_requeridas)->toBe(320)
        ->and($otra->fresh()->horas_requeridas)->toBe(300);
});

test('el administrador da de alta coordinadores con alcance obligatorio y a Vinculación', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $admin = usuarioConRol(Rol::Administrador->value);
    $datos = ['name' => 'Coordinación Nueva', 'email' => 'coord.nueva@academicos.udg.mx'];

    // Solo correos institucionales
    $this->actingAs($admin)->post(route('admin.usuarios.store'), ['name' => 'Coordinación Nueva', 'email' => 'coord@gmail.com', 'rol' => 'coordinador', 'carrera_id' => carreraDePrueba()->id])
        ->assertSessionHasErrors('email');

    // Un coordinador sin carrera ni división se rechaza
    $this->actingAs($admin)->post(route('admin.usuarios.store'), $datos + ['rol' => 'coordinador'])
        ->assertSessionHasErrors('carrera_id');

    $this->actingAs($admin)->post(route('admin.usuarios.store'), $datos + ['rol' => 'coordinador', 'carrera_id' => carreraDePrueba()->id])
        ->assertSessionHasNoErrors();

    $nuevo = User::where('email', 'coord.nueva@academicos.udg.mx')->first();
    expect($nuevo->hasRole(Rol::Coordinador->value))->toBeTrue()
        ->and($nuevo->personal->carrera_id)->toBe(carreraDePrueba()->id);

    // Vinculación nace con alcance global aunque se mande una carrera
    $this->actingAs($admin)->post(route('admin.usuarios.store'), [
        'name' => 'Lorena Lozada', 'email' => 'vinculacion@csh.udg.mx',
        'rol' => 'vinculacion', 'carrera_id' => carreraDePrueba()->id,
    ])->assertSessionHasNoErrors();

    $lorena = User::where('email', 'vinculacion@csh.udg.mx')->first();
    expect($lorena->hasRole(Rol::Vinculacion->value))->toBeTrue()
        ->and($lorena->personal->tieneAlcanceGlobal())->toBeTrue();

    // Nadie conoce su contraseña: les llega un enlace y hasta usarlo su correo no está verificado
    expect($nuevo->hasVerifiedEmail())->toBeFalse();
    \Illuminate\Support\Facades\Notification::assertSentTo($nuevo, \App\Notifications\ConfigurarContrasena::class);
    $lorena->markEmailAsVerified();
    $nuevo->markEmailAsVerified();

    $this->actingAs($lorena)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($nuevo)->get(route('coordinacion.dashboard'))->assertOk();
});

test('el personal nuevo configura su propia contraseña con el enlace y su correo queda verificado', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $admin = usuarioConRol(Rol::Administrador->value);
    $this->actingAs($admin)->post(route('admin.usuarios.store'), ['name' => 'Coordinación Nueva', 'email' => 'nueva@csh.udg.mx', 'rol' => 'coordinador', 'carrera_id' => carreraDePrueba()->id])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'enlace'));
    $nuevo = User::where('email', 'nueva@csh.udg.mx')->firstOrFail();

    $token = null;
    \Illuminate\Support\Facades\Notification::assertSentTo($nuevo, \App\Notifications\ConfigurarContrasena::class, function ($n) use (&$token) { $token = $n->token; return true; });
    auth()->logout();

    // Un enlace falso no sirve: la pantalla lo dice antes de pedir la contraseña y ofrece pedir otro
    $this->get(route('cuenta.activar', ['token' => 'falso', 'email' => 'nueva@csh.udg.mx']))->assertOk()
        ->assertSee('Este enlace ya no es válido')->assertSee('Solicitar un enlace nuevo')->assertDontSee('Guardar y entrar');
    $this->post(route('cuenta.activar.store'), ['token' => 'falso', 'email' => 'nueva@csh.udg.mx', 'password' => 'Clave-Segura-2026', 'password_confirmation' => 'Clave-Segura-2026'])
        ->assertSessionHasErrors('email');

    $this->get(route('cuenta.activar', ['token' => $token, 'email' => 'nueva@csh.udg.mx']))->assertOk()->assertSee('Configura tu contraseña');
    $this->post(route('cuenta.activar.store'), ['token' => $token, 'email' => 'nueva@csh.udg.mx', 'password' => 'Clave-Segura-2026', 'password_confirmation' => 'Clave-Segura-2026'])
        ->assertRedirect(route('dashboard'));

    expect($nuevo->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(\Illuminate\Support\Facades\Hash::check('Clave-Segura-2026', $nuevo->fresh()->password))->toBeTrue();
    $this->assertAuthenticatedAs($nuevo->fresh());

    // El enlace es de un solo uso
    auth()->logout();
    $this->get(route('cuenta.activar', ['token' => $token, 'email' => 'nueva@csh.udg.mx']))->assertSee('Este enlace ya no es válido');
    $this->post(route('cuenta.activar.store'), ['token' => $token, 'email' => 'nueva@csh.udg.mx', 'password' => 'Otra-Clave-2026', 'password_confirmation' => 'Otra-Clave-2026'])
        ->assertSessionHasErrors('email');
});

test('ninguna ruta de otro rol se abre por URL directa (403)', function () {
    $usuarios = collect([Rol::Alumno, Rol::Empresa, Rol::Coordinador, Rol::Vinculacion, Rol::Administrador])
        ->mapWithKeys(fn (Rol $r) => [$r->value => usuarioConRol($r->value)]);
    $revisadas = 0;

    foreach (\Illuminate\Support\Facades\Route::getRoutes() as $ruta) {
        // Roles permitidos según su middleware «role:…» (si hay varios, deben cumplirse todos)
        $permitidos = null;
        foreach ($ruta->gatherMiddleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'role:')) {
                $lista = explode(',', substr($m, 5));
                $permitidos = $permitidos === null ? $lista : array_values(array_intersect($permitidos, $lista));
            }
        }
        if ($permitidos === null) {
            continue;
        }

        $url = '/' . preg_replace('/\{[^}]+\}/', '1', $ruta->uri());
        $metodo = collect($ruta->methods())->first(fn ($x) => $x !== 'HEAD');

        foreach ($usuarios as $rol => $usuario) {
            if (in_array($rol, $permitidos, true)) {
                continue;
            }
            $estado = $this->actingAs($usuario)->call($metodo, $url)->status();
            expect(in_array($estado, [403, 404], true))->toBeTrue("{$rol} abrió {$metodo} {$url} (respuesta {$estado})");
            $revisadas++;
        }
    }

    expect($revisadas)->toBeGreaterThan(300);
});
