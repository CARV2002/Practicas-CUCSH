<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Postulacion;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;

/**
 * Postulaciones: la coordinación de la carrera las gestiona; el alumno sube sus documentos a la suya.
 */
class PostulacionPolicy
{
    use ResuelveActores;

    /** El alumno dueño sube su kardex y su carta de aceptación. */
    public function subirDocumento(User $user, Postulacion $postulacion): bool
    {
        $alumnoId = $this->alumnoId($user);

        return $alumnoId !== null && (int) $postulacion->alumno_id === $alumnoId;
    }

    /** Validar, emitir carta, activar, rechazar: la Coordinación de la carrera del alumno (Vinculación no). */
    public function gestionar(User $user, Postulacion $postulacion): bool
    {
        return $this->coordinaCarrera($user, (int) $postulacion->alumno->carrera_id);
    }
}
