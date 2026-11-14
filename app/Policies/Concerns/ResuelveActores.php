<?php

namespace App\Policies\Concerns;

use App\Enums\Rol;
use App\Models\User;

trait ResuelveActores
{
    /** Administrador con perfil de Personal (sin perfil no tiene alcance: se le niega, no truena). */
    protected function esAdmin(User $user): bool
    {
        return $user->hasRole(Rol::Administrador->value) && $user->personal !== null;
    }

    protected function adminVeCarrera(User $user, int $carreraId): bool
    {
        return $this->esAdmin($user) && $user->personal->puedeVerCarrera($carreraId);
    }

    protected function empresaId(User $user): ?int
    {
        return $user->hasRole(Rol::Empresa->value) ? $user->empresa?->id : null;
    }

    protected function alumnoId(User $user): ?int
    {
        return $user->hasRole(Rol::Alumno->value) ? $user->alumno?->id : null;
    }
}