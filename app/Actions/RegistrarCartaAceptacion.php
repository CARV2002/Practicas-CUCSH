<?php

namespace App\Actions;

use App\Enums\PostulacionEstado;
use App\Models\Postulacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\Avisos;
use App\Notifications\AvisoOperativo;

/**
 * El alumno regresa la carta de aceptación sellada por la empresa y la Coordinación la da por recibida.
 * Vuelve a leer la postulación con bloqueo y consulta los archivos en la BD (no en una relación en caché),
 * así un doble clic o un objeto desactualizado no pueden saltarse la regla.
 */
class RegistrarCartaAceptacion
{
    public function __invoke(Postulacion $postulacion): Postulacion
    {
        return DB::transaction(function () use ($postulacion) {
            $postulacion = Postulacion::lockForUpdate()->findOrFail($postulacion->id);

            if ($postulacion->estado !== PostulacionEstado::CartaPresentacionEmitida) {
                throw ValidationException::withMessages([
                    'postulacion' => 'Primero debe emitirse la carta de presentación.',
                ]);
            }

            if (!$postulacion->media()->where('collection_name', 'carta_aceptacion')->exists()) {
                throw ValidationException::withMessages([
                    'postulacion' => 'Falta adjuntar la carta de aceptación de la empresa.',
                ]);
            }

            $postulacion->update(['estado' => PostulacionEstado::CartaAceptacionRecibida]);

            Avisos::enviar($postulacion->alumno->user, new AvisoOperativo(
                mensaje: 'La Coordinación registró tu carta de aceptación de «' . $postulacion->programa->titulo . '». En breve activará tu práctica.',
                url: route('alumno.mis-practicas', absolute: false),
            ));

            return $postulacion;
        });
    }
}
