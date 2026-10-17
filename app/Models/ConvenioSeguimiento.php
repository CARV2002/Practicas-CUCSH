<?php

namespace App\Models;

use App\Enums\EtapaConvenio;
use App\Enums\SeguimientoEstado;
use App\Models\Concerns\ValidaDocumentos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ConvenioSeguimiento extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos;

    protected $table = 'convenio_seguimientos';

    protected $fillable = [
        'convenio_id', 'registrado_por', 'etapa', 'estado', 'comentario', 'fecha_registro', 'fecha_resolucion',
    ];

    protected function casts(): array
    {
        return [
            'etapa' => EtapaConvenio::class,
            'estado' => SeguimientoEstado::class,
            'fecha_registro' => 'date',
            'fecha_resolucion' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Acuses y dictámenes escaneados de la etapa (p. ej. el Dictamen de Viabilidad Jurídica)
        $this->addMediaCollection('documento_etapa')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'registrado_por');
    }

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('estado', SeguimientoEstado::Pendiente->value);
    }

    /** Días hábiles (lun-vie, sin festivos) que lleva o llevó la etapa. */
    public function diasHabiles(): int
    {
        $fin = $this->fecha_resolucion ?? today();

        return (int) abs($this->fecha_registro->diffInWeekdays($fin));
    }

    /** ¿Rebasa el máximo documentado? Alimenta el tablero de cuellos de botella. */
    public function excedeEspera(): bool
    {
        $rango = $this->etapa->diasHabilesDocumentados();

        return $rango !== null && $this->diasHabiles() > $rango[1];
    }
}
