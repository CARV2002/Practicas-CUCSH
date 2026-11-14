<?php

namespace App\Policies;

use App\Models\Practica;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;

class PracticaPolicy
{
    use ResuelveActores;

    /** Alumno dueño, empresa donde la realiza o Coordinación con alcance. */
    public function view(User $user, Practica $practica): bool
    {
        if ($this->gestionar($user, $practica)) {
            return true;
        }

        $postulacion = $practica->postulacion;
        $alumnoId = $this->alumnoId($user);
        $empresaId = $this->empresaId($user);
        $duenoEmpresa = $postulacion->programa->convenio?->empresa_id;

        return ($alumnoId !== null && (int) $postulacion->alumno_id === $alumnoId)
            || ($empresaId !== null && $duenoEmpresa !== null && (int) $duenoEmpresa === $empresaId);
    }

    public function gestionar(User $user, Practica $practica): bool
    {
        return $this->adminVeCarrera($user, (int) $practica->postulacion->alumno->carrera_id);
    }

    /** Crear reportes y subir la carta de término. */
    public function entregarDocumentos(User $user, Practica $practica): bool
    {
        $alumnoId = $this->alumnoId($user);

        return $alumnoId !== null && (int) $practica->postulacion->alumno_id === $alumnoId;
    }
}