<?php

namespace App\Policies;

use App\Models\Postulacion;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;

class PostulacionPolicy
{
    use ResuelveActores;

    /** El alumno dueño sube su kardex y su carta de aceptación. */
    public function subirDocumento(User $user, Postulacion $postulacion): bool
    {
        $alumnoId = $this->alumnoId($user);

        return $alumnoId !== null && (int) $postulacion->alumno_id === $alumnoId;
    }

    /** Validar, emitir carta, activar, rechazar: la Coordinación con alcance sobre la carrera del alumno. */
    public function gestionar(User $user, Postulacion $postulacion): bool
    {
        return $this->adminVeCarrera($user, (int) $postulacion->alumno->carrera_id);
    }
}