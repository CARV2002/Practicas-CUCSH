<?php

namespace App\Actions;

use App\Enums\PostulacionEstado;
use App\Models\Postulacion;
use Illuminate\Validation\ValidationException;

/** El alumno regresa la carta de aceptación sellada por la empresa y el admin la da por recibida. */
class RegistrarCartaAceptacion
{
    public function __invoke(Postulacion $postulacion): Postulacion
    {
        if ($postulacion->estado !== PostulacionEstado::CartaPresentacionEmitida) {
            throw ValidationException::withMessages([
                'postulacion' => 'Primero debe emitirse la carta de presentación.',
            ]);
        }

        if ($postulacion->getMedia('carta_aceptacion')->isEmpty()) {
            throw ValidationException::withMessages([
                'postulacion' => 'Falta adjuntar la carta de aceptación de la empresa.',
            ]);
        }

        $postulacion->update(['estado' => PostulacionEstado::CartaAceptacionRecibida]);

        return $postulacion;
    }
}
