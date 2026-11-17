<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Carrera;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cuentas de personal institucional para pruebas locales (contraseña: password123).
 *
 *   admin@test.com        Administrador técnico (superusuario, alcance global)
 *   vinculacion@test.com  Vinculación / Lorena (solo convenios, alcance global)
 *   coord.abog@test.com   Coordinación de ABOG
 *   coord.crim@test.com   Coordinación de CRIM
 *   coord.demo@test.com   Coordinación de DEMO
 *
 * Idempotente. Si una carrera aún no existe, su coordinador se crea en la siguiente corrida.
 */
class PersonalSeeder extends Seeder
{
    /** Carreras con coordinador demo => [correo, nombre]. */
    public const COORDINADORES = [
        'ABOG' => ['coord.abog@test.com', 'Coordinación de Abogado'],
        'CRIM' => ['coord.crim@test.com', 'Coordinación de Criminología'],
        'DEMO' => ['coord.demo@test.com', 'Coordinación Demo'],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $this->call(RolesSeeder::class);

        self::administrador();
        self::vinculacion();

        foreach (array_keys(self::COORDINADORES) as $clave) {
            self::coordinador($clave);
        }
    }

    /** Administrador técnico: superusuario sin alcance (global). */
    public static function administrador(): Personal
    {
        $user = self::usuario('admin@test.com', 'Administrador Técnico', Rol::Administrador);

        return Personal::firstOrCreate(['user_id' => $user->id], ['puesto' => 'Administración técnica del sistema']);
    }

    /** Lorena: Vinculación (convenios). Es el perfil que reciben los pasos de ConvenioWorkflow. */
    public static function vinculacion(): Personal
    {
        $user = self::usuario('vinculacion@test.com', 'Lorena Vinculación', Rol::Vinculacion);

        return Personal::firstOrCreate(['user_id' => $user->id], ['puesto' => 'Vinculación · Prácticas Profesionales']);
    }

    /** Coordinador de esa carrera (null si la carrera aún no existe). */
    public static function coordinador(string $clave): ?Personal
    {
        $carrera = Carrera::where('carrera_clave', $clave)->first();

        if (! $carrera || ! isset(self::COORDINADORES[$clave])) {
            return null;
        }

        [$email, $nombre] = self::COORDINADORES[$clave];
        $user = self::usuario($email, $nombre, Rol::Coordinador);

        return Personal::firstOrCreate(['user_id' => $user->id], [
            'carrera_id' => $carrera->id,
            'puesto' => "Coordinación de {$carrera->nombre}",
        ]);
    }

    private static function usuario(string $email, string $nombre, Rol $rol): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => 'password123', 'aviso_privacidad_at' => now()]   // el cast "hashed" la encripta
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->syncRoles([$rol->value]);

        return $user;
    }
}
