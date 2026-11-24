<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use App\Models\Reporte;
use Illuminate\Validation\ValidationException;

/**
 * Impide cambios de estado no permitidos en los reportes y que cambien cuando la práctica ya no está en curso.
 */
class ReporteObserver
{
    public function updating(Reporte $reporte): void
    {
        if (! $reporte->isDirty('estado')) {
            return;
        }

        $desde = $reporte->getOriginal('estado');
        $hacia = $reporte->estado;

        if ($desde instanceof ReporteEstado && ! $desde->puedePasarA($hacia)) {
            $this->fallar("Un reporte «{$desde->value}» no puede pasar a «{$hacia->value}».");
        }

        // Una vez validada la carta de término las horas quedan fijas
        if ($hacia !== ReporteEstado::Anulado && $reporte->practica->estado !== PracticaEstado::EnCurso) {
            $this->fallar('Los reportes solo cambian de estado mientras la práctica está en curso.');
        }
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['reporte' => $mensaje]);
    }
}
