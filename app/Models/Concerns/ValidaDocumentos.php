<?php

namespace App\Models\Concerns;

use App\Enums\ValidacionEstado;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait ValidaDocumentos
{
    public function resolverDocumento(Media $media, ValidacionEstado $estado, ?string $motivo, int $revisorUserId): void
    {
        $media->setCustomProperty('estado', $estado->value);
        $media->setCustomProperty('motivo', $motivo);
        $media->setCustomProperty('revisado_por', $revisorUserId);
        $media->setCustomProperty('revisado_en', now()->toDateTimeString());
        $media->save();
    }

    /** Último archivo subido de una colección (el vigente). */
    public function documentoVigente(string $coleccion): ?Media
    {
        return $this->getMedia($coleccion)->sortByDesc('created_at')->first();
    }

    public function documentoValidado(string $coleccion): bool
    {
        return $this->documentoVigente($coleccion)?->getCustomProperty('estado') === ValidacionEstado::Validada->value;
    }
}
