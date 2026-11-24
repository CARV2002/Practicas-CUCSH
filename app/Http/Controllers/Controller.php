<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Services\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base de los controladores: autorización (policies) y entrega de archivos privados con registro en la bitácora.
 */
abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Descarga autorizada en un solo paso: el archivo debe pertenecer al modelo de la URL (si no, 404, para no
     * revelar que existe), luego MediaPolicy decide si este usuario puede verlo, y al final se entrega.
     */
    protected function entregarDe(Model $dueno, Media $media): Response
    {
        abort_unless(
            $media->model_type === $dueno->getMorphClass() && (int) $media->model_id === (int) $dueno->getKey(),
            404
        );

        $this->authorize('download', $media);

        return $this->entregar($media);
    }

    /** Entrega un archivo privado y deja rastro de quién lo bajó (salvo el alumno con sus propios archivos). */
    protected function entregar(Media $media): Response
    {
        // Vista previa (?ver=1): PDF e imágenes se muestran en el navegador; lo demás, una ficha con su descarga
        if (request()->boolean('ver')) {
            return $this->vistaPrevia($media);
        }

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

    private function vistaPrevia(Media $media): Response
    {
        $mime = (string) $media->mime_type;

        if ($mime === 'application/pdf' || str_starts_with($mime, 'image/')) {
            return response()->file($media->getPath(), [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.addcslashes($media->file_name, '"\\').'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->view('components.ui.sin-vista-previa', [
            'media' => $media,
            'descarga' => request()->fullUrlWithQuery(['ver' => null]),
        ]);
    }
}
