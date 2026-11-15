<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Services\Auditoria;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

abstract class Controller
{
    use AuthorizesRequests;

    /** Entrega un archivo privado y deja rastro de quién lo bajó (salvo el alumno con sus propios archivos). */
    protected function entregar(Media $media)
    {
        if (! auth()->user()?->hasRole(Rol::Alumno->value)) {
            Auditoria::registrar(
                'documento.descargado',
                "Descargó «{$media->file_name}» ({$media->collection_name}) de ".class_basename($media->model_type)." #{$media->model_id}",
                cambios: ['coleccion' => $media->collection_name, 'archivo' => $media->file_name],
                tipo: class_basename($media->model_type),
                id: (int) $media->model_id,
            );
        }

        return $media->toResponse(request());
    }
}