<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de profesores supervisores, adscritos a una división (sin login en esta fase). */
class Profesor extends Model
{
    protected $table = 'profesores';

    protected $fillable = ['division_id', 'nombre', 'email'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function practicas(): HasMany
    {
        return $this->hasMany(Practica::class, 'profesor_supervisor_id');
    }

    /** Puede supervisar a alumnos de esa carrera si pertenece a su misma división. */
    public function puedeSupervisar(Carrera $carrera): bool
    {
        return (int) $this->division_id === (int) $carrera->division_id;
    }
}
