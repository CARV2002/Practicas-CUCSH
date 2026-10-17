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

class Alumno extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos;

    protected $table = 'alumnos';

    protected $fillable = ['user_id', 'carrera_id', 'num_control', 'vigencia_imss'];

    protected function casts(): array
    {
        return ['vigencia_imss' => 'date'];
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

    public function tieneImssVigenteHasta(CarbonInterface $fecha): bool
    {
        return $this->vigencia_imss !== null
            && $this->vigencia_imss->toDateString() >= $fecha->toDateString();
    }
}
