<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reporte;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;

/**
 * Reportes: el alumno sube el suyo, la coordinación de la carrera lo valida y la organización lo consulta.
 */
class ReportePolicy
{
    use ResuelveActores;

    public function subirArchivo(User $user, Reporte $reporte): bool
    {
        return $user->can('entregarDocumentos', $reporte->practica);
    }

    public function resolver(User $user, Reporte $reporte): bool
    {
        return $user->can('gestionar', $reporte->practica);
    }

    /** Solo la empresa donde se hace la práctica aporta retroalimentación. */
    public function retroalimentar(User $user, Reporte $reporte): bool
    {
        return $this->empresaId($user) !== null && $user->can('view', $reporte->practica);
    }
}
