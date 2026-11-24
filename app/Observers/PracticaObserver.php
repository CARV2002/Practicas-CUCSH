<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use App\Models\Practica;
use Illuminate\Validation\ValidationException;

/**
 * Cambios de estado de la práctica: impide transiciones no permitidas o cancelar una ya enviada a Control Escolar;
 * al cancelarla, cancela su postulación (libera el cupo) y anula los reportes que quedaron pendientes.
 */
class PracticaObserver
{
    public function updating(Practica $practica): void
    {
        if (! $practica->isDirty('estado')) {
            return;
        }

        $desde = $practica->getOriginal('estado');
        $hacia = $practica->estado;

        if ($desde instanceof PracticaEstado && ! $desde->puedePasarA($hacia)) {
            $this->fallar("Una práctica «{$desde->label()}» no puede pasar a «{$hacia->label()}».");
        }

        if (
            $hacia === PracticaEstado::Cancelada
            && $practica->oficioAcreditacion?->enviado_control_escolar_at !== null
        ) {
            $this->fallar('No se puede cancelar: el oficio de acreditación ya se envió a Control Escolar.');
        }
    }

    /** Al cancelar: se libera el cupo y se anula lo que quedó sin resolver. */
    public function updated(Practica $practica): void
    {
        if (! $practica->wasChanged('estado') || $practica->estado !== PracticaEstado::Cancelada) {
            return;
        }

        $postulacion = $practica->postulacion;

        if ($postulacion && $postulacion->estado === PostulacionEstado::Aceptada) {
            $postulacion->update(['estado' => PostulacionEstado::Cancelada]);
        }

        $practica->reportes()
            ->where('estado', ReporteEstado::Pendiente->value)
            ->get()
            ->each->update(['estado' => ReporteEstado::Anulado]);
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['practica' => $mensaje]);
    }
}
