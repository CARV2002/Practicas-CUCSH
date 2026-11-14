<?php

namespace App\Policies;

use App\Models\Reporte;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;

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