<?php

namespace App\Models;

use App\Enums\ValidacionEstado;
use App\Models\Concerns\ValidaDocumentos;
use App\Observers\CartaTerminoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy([CartaTerminoObserver::class])]
class CartaTermino extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos;

    protected $table = 'cartas_termino';

    protected $fillable = ['practica_id', 'estado_validacion'];

    protected function casts(): array
    {
        return ['estado_validacion' => ValidacionEstado::class];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('carta_termino')->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }
}
