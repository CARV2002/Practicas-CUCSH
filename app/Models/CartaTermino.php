<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValidacionEstado;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ValidaDocumentos;
use App\Observers\CartaTerminoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Carta de término: la emite la organización al cubrir el alumno sus horas (hoja membretada, firmada y sellada).
 * El alumno sube el escaneo; su coordinación la valida para pasar a cierre y emitir el oficio de acreditación.
 */
#[ObservedBy([CartaTerminoObserver::class])]
class CartaTermino extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia, ValidaDocumentos;

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

    /** @return BelongsTo<Practica, $this> */
    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }

    public function auditoriaEtiqueta(): string
    {
        return 'Carta de término';
    }
}
