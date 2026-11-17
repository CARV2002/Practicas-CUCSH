<?php

namespace App\Policies\Concerns;

use App\Enums\Rol;
use App\Models\User;

trait ResuelveActores
{
    /**
     * Vinculación (o el administrador técnico) con perfil de Personal: lleva el trámite de convenios.
     * Sin perfil no tiene alcance: se le niega, no truena.
     */
    protected function esVinculacion(User $user): bool
    {
        return $user->hasAnyRole(Rol::deVinculacion()) && $user->personal !== null;
    }

    /** Coordinación de carrera/división (con alcance obligatorio) o administrador técnico. */
    protected function esCoordinacion(User $user): bool
    {
        if (! $user->hasAnyRole(Rol::deCoordinacion()) || $user->personal === null) {
            return false;
        }

        return $user->hasRole(Rol::Administrador->value) || $user->personal->tieneAlcanceDeCoordinacion();
    }

    /** Coordinación con alcance sobre esa carrera: la única que opera el proceso del alumno. */
    protected function coordinaCarrera(User $user, int $carreraId): bool
    {
        return $this->esCoordinacion($user) && $user->personal->puedeVerCarrera($carreraId);
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
