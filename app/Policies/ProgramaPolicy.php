<?php

namespace App\Policies;

use App\Models\Programa;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;

class ProgramaPolicy
{
    use ResuelveActores;

    /** Editar, publicar/cerrar, eliminar y ver postulantes. */
    public function gestionar(User $user, Programa $programa): bool
    {
        if ($this->esAdmin($user)) {
            return $this->adminVeCarrera($user, (int) $programa->carrera_id);
        }

        $empresaId = $this->empresaId($user);
        $duenoId = $programa->convenio?->empresa_id;

        return $empresaId !== null && $duenoId !== null && (int) $duenoId === $empresaId;
    }
}