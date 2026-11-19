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
use App\Observers\PostulacionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Models\Concerns\Auditable;

#[ObservedBy([PostulacionObserver::class])]
class Postulacion extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos, Auditable;

    protected $table = 'postulaciones';

    protected $fillable = [
        'alumno_id',
        'programa_id',
        'validado_por',
        'estado',
        'porcentaje_creditos_declarado',
        'fecha_postulacion',
        'siiau_confirmado_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => PostulacionEstado::class,
            'porcentaje_creditos_declarado' => 'decimal:2',
            'fecha_postulacion' => 'date',
            'siiau_confirmado_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Evidencia del alumno y cartas del trámite de asignación
        // La genera el sistema en Word (editable) o se sube firmada en PDF
        $this->addMediaCollection('carta_presentacion')->useDisk('local')
            ->acceptsMimeTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']);
        // Modalidad interna: oficio firmado por el Jefe de Departamento / Director de División receptor
        $this->addMediaCollection('oficio_aceptacion_interno')->useDisk('local')
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
            : $query->whereHas('alumno', fn(Builder $q) => $q->whereIn('carrera_id', $ids));
    }

    public function auditoriaEtiqueta(): string
    {
        return 'Postulación';
    }

}
