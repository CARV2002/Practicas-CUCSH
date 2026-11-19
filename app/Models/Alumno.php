<?php

namespace App\Models;

use App\Enums\PracticaEstado;
use App\Models\Concerns\ValidaDocumentos;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Concerns\Auditable;

class Alumno extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos, Auditable;

    protected $table = 'alumnos';

    protected $fillable = ['user_id', 'carrera_id', 'num_control', 'grado', 'turno', 'vigencia_imss'];

    protected function casts(): array
    {
        return ['vigencia_imss' => 'date', 'grado' => 'integer', 'turno' => \App\Enums\Turno::class];
    }

    public function registerMediaCollections(): void
    {
        // Disco "local" = storage/app/private en Laravel 11+/12 (no público)
        $this->addMediaCollection('constancia_imss')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    public function practicas(): HasManyThrough
    {
        return $this->hasManyThrough(Practica::class, Postulacion::class, 'alumno_id', 'postulacion_id');
    }

    public function tienePracticaActiva(): bool
    {
        return $this->practicas()
            ->whereIn('practicas.estado', PracticaEstado::valores(PracticaEstado::activas()))
            ->exists();
    }

    public function tienePracticaCancelada(): bool
    {
        return $this->practicas()
            ->where('practicas.estado', PracticaEstado::Cancelada->value)
            ->exists();
    }

    /**
     * Baja sin decisión de la coordinación (cancelaciones anteriores al flujo formal):
     * el alumno espera a que su coordinación la resuelva antes de postularse de nuevo.
     */
    public function tieneBajaSinResolver(): bool
    {
        return $this->practicas()
            ->where('practicas.estado', PracticaEstado::Cancelada->value)
            ->whereNull('practicas.conserva_horas')
            ->exists();
    }

    /**
     * Alumnos del alcance de una coordinación con su constancia IMSS vigente por revisar,
     * se hayan postulado o no (el IMSS es requisito previo a la postulación).
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function conImssPorRevisar(Personal $personal): \Illuminate\Support\Collection
    {
        $ids = $personal->carreraIdsEnAlcance();

        return self::with(['user', 'carrera', 'media'])
            ->when($ids !== null, fn ($q) => $q->whereIn('carrera_id', $ids))
            ->whereHas('media', fn ($m) => $m->where('collection_name', 'constancia_imss'))
            ->get()
            ->filter(function (self $a) {
                $estado = $a->documentoVigente('constancia_imss')?->getCustomProperty('estado');

                return $estado === null || $estado === \App\Enums\ValidacionEstado::Pendiente->value;
            })
            ->values();
    }

    /** Horas validadas que la coordinación decidió conservar de la última baja (0 si se reiniciaron). */
    public function horasConservadas(): int
    {
        $ultima = $this->practicas()
            ->where('practicas.estado', PracticaEstado::Cancelada->value)
            ->latest('practicas.id')
            ->first();

        return $ultima && $ultima->conserva_horas ? $ultima->horas_acumuladas : 0;
    }

    public function tieneImssVigenteHasta(CarbonInterface $fecha): bool
    {
        return $this->vigencia_imss !== null
            && $this->vigencia_imss->toDateString() >= $fecha->toDateString();
    }

    public function yaAcreditado(): bool
    {
        return $this->practicas()->where('practicas.estado', PracticaEstado::Acreditada->value)->exists();
    }

    public function auditoriaNombre(): ?string
    {
        return $this->num_control;
    }

}
