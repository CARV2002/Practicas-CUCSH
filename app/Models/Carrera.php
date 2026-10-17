<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrera extends Model
{
    protected $table = 'carreras';

    protected $fillable = [
        'division_id', 'carrera_clave', 'nombre',
        'horas_requeridas', 'porcentaje_creditos_minimo', 'creditos_otorgados',
    ];

    protected function casts(): array
    {
        return [
            'horas_requeridas' => 'integer',
            'porcentaje_creditos_minimo' => 'decimal:2',
            'creditos_otorgados' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }

    /** Coordinadores de esta carrera. */
    public function personal(): HasMany
    {
        return $this->hasMany(Personal::class);
    }

    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class);
    }

    /** Un dictamen por cada convenio que solicitó esta carrera. */
    public function dictamenes(): HasMany
    {
        return $this->hasMany(ConvenioCarrera::class);
    }

    public function convenios(): BelongsToMany
    {
        return $this->belongsToMany(Convenio::class, 'convenio_carrera')
            ->withPivot(['id', 'dictamen', 'observaciones', 'fecha_dictamen'])
            ->withTimestamps();
    }
}
