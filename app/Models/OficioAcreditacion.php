<?php

namespace App\Models;

use App\Enums\ValidacionEstado;
use App\Models\Concerns\ValidaDocumentos;
use App\Observers\OficioAcreditacionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy([OficioAcreditacionObserver::class])]
class OficioAcreditacion extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos;

    protected $table = 'oficios_acreditacion';

    protected $fillable = [
        'practica_id', 'emitido_por', 'estado_validacion',
        'enviado_control_escolar_at', 'capturado_siiau_at',
    ];

    protected function casts(): array
    {
        return [
            'estado_validacion' => ValidacionEstado::class,
            'enviado_control_escolar_at' => 'datetime',
            'capturado_siiau_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('oficio_acreditacion')->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'emitido_por');
    }
}
