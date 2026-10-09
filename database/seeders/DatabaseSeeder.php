<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {// 1. Primero creamos los roles ejecutando tu RoleSeeder
        $this->call([
            RoleSeeder::class,
        ]);

        // 2. Creamos un Administrador de prueba
        $admin = User::create([
            'name' => 'Admin de Pruebas',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);
        $admin->assignRole('administrador');

        // 3. Creamos un Alumno de prueba
        $alumno = User::create([
            'name' => 'Juan Alumno',
            'email' => 'alumno@test.com',
            'password' => bcrypt('password123'),
        ]);
        $alumno->assignRole('alumno');

        // 4. Creamos una Empresa de prueba
        $empresa = User::create([
            'name' => 'Empresa Test S.A.',
            'email' => 'empresa@test.com',
            'password' => bcrypt('password123'),
        ]);
        $empresa->assignRole('empresa');

        // 4. Creamos una Empresa de prueba
        $coordinador = User::create([
            'name' => 'Coordinador Test S.A.',
            'email' => 'coordinador@test.com',
            'password' => bcrypt('password123'),
        ]);
        $coordinador->assignRole('coordinador');
    }
}
