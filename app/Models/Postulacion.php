<?php

namespace App\Models;

use App\Enums\PostulacionEstado;
use App\Models\Concerns\ValidaDocumentos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Postulacion extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos;

    protected $table = 'postulaciones';

    protected $fillable = [
        'alumno_id', 'programa_id', 'validado_por', 'estado',
        'porcentaje_creditos_declarado', 'fecha_postulacion',
    ];

    protected function casts(): array
    {
        return [
            'estado' => PostulacionEstado::class,
            'porcentaje_creditos_declarado' => 'decimal:2',
            'fecha_postulacion' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Evidencia del alumno y cartas del trámite de asignación
        $this->addMediaCollection('kardex')->useDisk('local')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
        $this->addMediaCollection('carta_presentacion')->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
        $this->addMediaCollection('carta_aceptacion')->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'validado_por');
    }

    public function practica(): HasOne
    {
        return $this->hasOne(Practica::class);
    }

    /** Bandeja del coordinador/director: solo alumnos de sus carreras. */
    public function scopeVisiblesPara(Builder $query, Personal $personal): Builder
    {
        $ids = $personal->carreraIdsEnAlcance();

        return $ids === null
            ? $query
            : $query->whereHas('alumno', fn (Builder $q) => $q->whereIn('carrera_id', $ids));
    }
}
