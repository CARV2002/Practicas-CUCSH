<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Alta de la cuenta de la Coordinación de Prácticas (rol administrador).
 * Es la forma de crear al administrador en producción: pide la contraseña, no queda en ningún archivo.
 *
 *   php artisan practicas:crear-administrador correo@udg.mx --nombre="Nombre Apellido"
 */
class CrearAdministrador extends Command
{
    protected $signature = 'practicas:crear-administrador {email : Correo de la cuenta} {--nombre=Coordinación de Prácticas : Nombre de la persona}';

    protected $description = 'Crea o actualiza la cuenta de un administrador de la Coordinación de Prácticas con su perfil de Personal';

    public function handle(): int
    {
        $password = (string) $this->secret('Contraseña (mínimo 8 caracteres)');

        if (mb_strlen($password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        foreach (Rol::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }

        $user = User::updateOrCreate(
            ['email' => mb_strtolower($this->argument('email'))],
            ['name' => $this->option('nombre'), 'password' => $password]   // el cast "hashed" la encripta
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->assignRole(Rol::Administrador->value);

        // Sin división ni carrera = alcance global: es el perfil que reciben todos los pasos de ConvenioWorkflow
        Personal::firstOrCreate(['user_id' => $user->id], [
            'puesto' => 'Coordinación de Prácticas Profesionales',
        ]);

        $this->info("Administrador listo: {$user->email}");

        return self::SUCCESS;
    }
}
