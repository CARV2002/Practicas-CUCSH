<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotente: se puede correr varias veces sin duplicar nada.
     * En producción solo crea los roles; en local además crea usuarios y datos de prueba.
     */
   public function run(): void
    {
        \App\Services\Auditoria::sinRegistro(fn () => $this->sembrar());
    }
    private function sembrar(): void
    {
        $this->call(RolesSeeder::class);

        $this->call(CarreraSeeder::class);

        $this->call(ConvenioSeeder::class);

        // En producción NO se siembran cuentas con contraseña conocida:
        // el administrador técnico se crea con `php artisan practicas:crear-administrador correo@udg.mx`
        // y desde Usuarios da de alta a Vinculación; Vinculación da de alta a cada coordinación.
        if (! app()->environment('local')) {
            return;
        }

        // Datos ficticios para probar el flujo en local. Las divisiones y carreras reales
        // (con sus horas y % de créditos) se capturan desde el catálogo de la aplicación.
        $this->datosDemo();

        // Administrador técnico, Vinculación (Lorena) y una coordinación por carrera demo (ABOG, CRIM, DEMO)
        $this->call(PersonalSeeder::class);

        $this->call(DemoOperacionSeeder::class);
    }

    private function datosDemo(): void
    {
        $division = Division::firstOrCreate(['nombre' => 'División Demo']);

        $carrera = Carrera::firstOrCreate(['carrera_clave' => 'DEMO'], [
            'division_id' => $division->id,
            'nombre' => 'Carrera Demo',
            'horas_requeridas' => 250,
            'porcentaje_creditos_minimo' => 50,
            'creditos_otorgados' => 8,
        ]);

        $alumno = $this->usuario('alumno@test.com', 'Juan Alumno', Rol::Alumno);
        Alumno::firstOrCreate(['user_id' => $alumno->id], [
            'carrera_id' => $carrera->id,
            'num_control' => 'DEMO0001',
            'vigencia_imss' => now()->addYear(),
        ]);

        $empresa = $this->usuario('empresa@test.com', 'Contacto Demo', Rol::Empresa);
        Empresa::firstOrCreate(['user_id' => $empresa->id], [
            'rfc' => 'DEM010101AAA',
            'razon_social' => 'Empresa Demo S.A. de C.V.',
            'tipo_organismo' => TipoOrganismo::Privado,
            'contacto_nombre' => 'Contacto Demo',
        ]);
    }

    private function usuario(string $email, string $nombre, Rol $rol): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => 'password123']   // el cast "hashed" la encripta
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->assignRole($rol->value);

        return $user;
    }
}
