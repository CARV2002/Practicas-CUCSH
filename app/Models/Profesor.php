<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de profesores supervisores, adscritos a una división (sin login en esta fase). */
class Profesor extends Model
{
    use Auditable;

    protected $table = 'profesores';

    protected $fillable = ['division_id', 'nombre', 'email', 'telefono', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /** Solo los activos se pueden asignar a una práctica nueva. */
    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    /** @return BelongsTo<Division, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** @return HasMany<Practica, $this> */
    public function practicas(): HasMany
    {
        return $this->hasMany(Practica::class, 'profesor_supervisor_id');
    }

    /** Puede supervisar a alumnos de esa carrera si pertenece a su misma división. */
    public function puedeSupervisar(Carrera $carrera): bool
    {
        return (int) $this->division_id === (int) $carrera->division_id;
    }

    public function auditoriaNombre(): ?string
    {
        return $this->nombre;
    }
}
