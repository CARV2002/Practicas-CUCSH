<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\ValidacionEstado;
use App\Models\User;
use App\Services\Auditoria;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Documentos con revisión (pendiente, validado o rechazado con motivo) para modelos con archivos de Media Library.
 */
trait ValidaDocumentos
{
    public function resolverDocumento(Media $media, ValidacionEstado $estado, ?string $motivo, int $revisorUserId): void
    {
        // Regla de todos los paneles: lo validado ya no se rechaza ni se «des-valida».
        // Si cambia algo real (p. ej. un nuevo apoderado), el dueño sube un documento nuevo y ese se revisa.
        if ($media->getCustomProperty('estado') === ValidacionEstado::Validada->value && $estado !== ValidacionEstado::Validada) {
            throw ValidationException::withMessages([
                'documento' => '«'.$media->file_name.'» ya está validado: no se puede rechazar. Si cambió algo, pide que se suba un documento nuevo.',
            ]);
        }

        $media->setCustomProperty('estado', $estado->value);
        $media->setCustomProperty('motivo', $motivo);
        $media->setCustomProperty('revisado_por', $revisorUserId);
        $media->setCustomProperty('revisado_en', now()->toDateTimeString());
        $media->save();

        Auditoria::registrar(
            "documento.{$estado->value}",
            "Documento «{$media->collection_name}» ({$media->file_name}) marcado como {$estado->value}".($motivo ? ": {$motivo}" : ''),
            $this,
            ['coleccion' => $media->collection_name, 'archivo' => $media->file_name, 'motivo' => $motivo],
            User::find($revisorUserId),
        );
    }

    /** Último archivo subido de una colección (el vigente). */
    public function documentoVigente(string $coleccion): ?Media
    {
        // Por id, no por fecha: un reemplazo subido en el mismo segundo que el anterior debe ganar
        return $this->getMedia($coleccion)->sortByDesc('id')->first();
    }

    public function documentoValidado(string $coleccion): bool
    {
        return $this->documentoVigente($coleccion)?->getCustomProperty('estado') === ValidacionEstado::Validada->value;
    }
}
