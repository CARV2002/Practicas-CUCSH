<?php

namespace App\Observers;

use App\Enums\PracticaEstado;
use App\Enums\ValidacionEstado;
use App\Models\CartaTermino;
use Illuminate\Validation\ValidationException;

/**
 * La práctica no se cierra "a mano": su estado se infiere de sus documentos hijos.
 */
class CartaTerminoObserver
{
    /** No se puede validar la carta de término si las horas validadas no alcanzan. */
    public function updating(CartaTermino $carta): void
    {
        if ($carta->isDirty('estado_validacion') && $carta->estado_validacion === ValidacionEstado::Validada) {
            $practica = $carta->practica;

            if (! $practica->horasCompletas()) {
                throw ValidationException::withMessages([
                    'carta_termino' => "Horas validadas insuficientes: {$practica->progresoTexto()}.",
                ]);
            }
        }
    }

    public function updated(CartaTermino $carta): void
    {
        if ($carta->wasChanged('estado_validacion') && $carta->estado_validacion === ValidacionEstado::Validada) {
            $carta->practica->update(['estado' => PracticaEstado::EnCierre]);
        }
    }
}
