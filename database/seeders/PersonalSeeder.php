<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Carrera;
use App\Models\Personal;
use App\Models\User;
use App\Support\Formatos;
use Illuminate\Database\Seeder;

/**
 * Cuentas de personal institucional para pruebas locales (contraseña: password123).
 *
 *   admin@test.com          Administrador (superusuario, alcance global)
 *   vinculacion@test.com    Vinculación / Lorena (solo convenios, alcance global)
 *   coord.<clave>@test.com  Una coordinación por cada carrera del catálogo (15, de las 5 divisiones)
 *
 * Idempotente. Si una carrera aún no existe, su coordinador se crea en la siguiente corrida.
 */
class PersonalSeeder extends Seeder
{
    /** Coordinación de cada carrera real => [correo, titular]. */
    public const COORDINADORES = [
        // División de Estudios Jurídicos
        'ABOG' => ['coord.abog@test.com', 'Dra. Laura Méndez Ochoa'],
        'ABOS' => ['coord.abos@test.com', 'Mtro. Ricardo Pulido Arana'],
        'CRIM' => ['coord.crim@test.com', 'Dr. Javier Cervantes Lugo'],
        // División de Estudios Políticos y Sociales
        'LREI' => ['coord.lrei@test.com', 'Dra. Mónica Valadez Ríos'],
        'EPYG' => ['coord.epyg@test.com', 'Mtro. Andrés Becerra Solís'],
        'SOCI' => ['coord.soci@test.com', 'Dra. Gabriela Torres Anaya'],
        // División de Estudios Históricos y Humanos
        'HIST' => ['coord.hist@test.com', 'Dr. Fernando Quiroz Medina'],
        'LETR' => ['coord.letr@test.com', 'Mtra. Elena Garibay Luna'],
        'FILO' => ['coord.filo@test.com', 'Dr. Tomás Rentería Silva'],
        'ANTR' => ['coord.antr@test.com', 'Dra. Rocío Navarro Peña'],
        // División de Estudios de Estado y Sociedad
        'GEOG' => ['coord.geog@test.com', 'Mtro. Luis Arellano Cortés'],
        'COMP' => ['coord.comp@test.com', 'Mtra. Diana Sepúlveda Ruiz'],
        // División de Cultura Internacional
        'DIFL' => ['coord.difl@test.com', 'Mtra. Claire Dubois Martínez'],
        'DILE' => ['coord.dile@test.com', 'Mtro. Kevin Herrera Walsh'],
        'ESCR' => ['coord.escr@test.com', 'Mtra. Paulina Ibarra Esparza'],
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

    /** Administrador: superusuario sin alcance (global). */
    public static function administrador(): Personal
    {
        $user = self::usuario('admin@test.com', 'Administrador', Rol::Administrador);

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

        // Directorio: el alumno escribe a su coordinación, no a Vinculación
        if (blank($carrera->coordinacion_correo)) {
            $carrera->update([
                'coordinacion_correo' => $email,
                'coordinacion_telefono' => Formatos::telefonoConExtension('3338193300', '2'.str_pad((string) $carrera->id, 4, '0', STR_PAD_LEFT)),
            ]);
        }

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
