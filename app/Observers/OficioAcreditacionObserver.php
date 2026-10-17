<?php

namespace App\Observers;

use App\Enums\PracticaEstado;
use App\Enums\ValidacionEstado;
use App\Models\OficioAcreditacion;
use Illuminate\Validation\ValidationException;

/**
 * La práctica queda "acreditada" solo cuando Control Escolar confirma la captura en SIIAU.
 * Hasta entonces permanece "en cierre".
 */
class OficioAcreditacionObserver
{
    public function updating(OficioAcreditacion $oficio): void
    {
        if ($oficio->isDirty('enviado_control_escolar_at') && $oficio->enviado_control_escolar_at !== null) {
            if ($oficio->estado_validacion !== ValidacionEstado::Validada) {
                $this->fallar('El oficio debe estar validado antes de enviarse a Control Escolar.');
            }

            if ($oficio->practica->estado !== PracticaEstado::EnCierre) {
                $this->fallar('La práctica aún no está en cierre (falta validar la carta de término).');
            }
        }

        if ($oficio->isDirty('capturado_siiau_at')
            && $oficio->capturado_siiau_at !== null
            && $oficio->enviado_control_escolar_at === null) {
            $this->fallar('No se puede registrar la captura en SIIAU de un oficio que no se ha enviado.');
        }
    }

    public function updated(OficioAcreditacion $oficio): void
    {
        if ($oficio->wasChanged('capturado_siiau_at') && $oficio->capturado_siiau_at !== null) {
            $oficio->practica->update(['estado' => PracticaEstado::Acreditada]);
        }
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['oficio' => $mensaje]);
    }
}
