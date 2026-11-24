<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Dictamen;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ValidaDocumentos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Dictamen Técnico de Pertinencia Académica: una carrera opina sobre un convenio. */
class ConvenioCarrera extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia, ValidaDocumentos;

    protected $table = 'convenio_carrera';

    protected $fillable = [
        'convenio_id',
        'carrera_id',
        'dictaminado_por',
        'dictamen',
        'observaciones',
        'fecha_dictamen',
    ];

    protected function casts(): array
    {
        return [
            'dictamen' => Dictamen::class,
            'fecha_dictamen' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('dictamen_pertinencia')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    /** @return BelongsTo<Convenio, $this> */
    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    /** @return BelongsTo<Carrera, $this> */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /** @return BelongsTo<Personal, $this> */
    public function dictaminador(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'dictaminado_por');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('dictamen', Dictamen::Pendiente->value);
    }

    /** Bandeja del coordinador/director: solo los dictámenes de sus carreras. */
    public function scopeVisiblesPara(Builder $query, Personal $personal): Builder
    {
        $ids = $personal->carreraIdsEnAlcance();

        return $ids === null ? $query : $query->whereIn('carrera_id', $ids);
    }

    public function auditoriaEtiqueta(): string
    {
        return 'Dictamen';
    }
}
