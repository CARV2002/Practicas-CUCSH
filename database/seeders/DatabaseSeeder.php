<?php

namespace Database\Seeders;

use App\Services\Auditoria;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotente: se puede correr varias veces sin duplicar nada.
     *  - En producción solo crea los roles y el catálogo real (5 divisiones, 15 carreras).
     *  - En local además crea el personal (administrador, Vinculación y las 15 coordinaciones) y datos de
     *    operación realistas: empresas y alumnos en cada situación del proceso (OperacionSeeder).
     */
    public function run(): void
    {
        Auditoria::sinRegistro(fn () => $this->sembrar());
    }

    private function sembrar(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(CarreraSeeder::class);

        // En producción NO se siembran cuentas con contraseña conocida:
        // el administrador se crea con `php artisan practicas:crear-administrador correo@udg.mx`
        // y desde Usuarios da de alta a Vinculación y a cada coordinación.
        if (! app()->environment('local')) {
            return;
        }

        $this->call(PersonalSeeder::class);
        $this->call(OperacionSeeder::class);
    }
}
