<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\Auditable;

class Carrera extends Model
{
    use Auditable;
    protected $table = 'carreras';

    protected $attributes = ['esquema_reportes' => 'mensual'];

    /**
     * Reglas con que nace una carrera que da de alta el administrador. Desde ahí, solo su coordinación
     * las ajusta en «Reglas de mi carrera» (autonomía académica de cada licenciatura).
     */
    public const REGLAS_INICIALES = ['horas_requeridas' => 300, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8];

    protected $fillable = [
        'division_id',
        'carrera_clave',
        'nombre',
        'horas_requeridas',
        'porcentaje_creditos_minimo',
        'creditos_otorgados',
        'coordinacion_correo',
        'coordinacion_telefono',
        'esquema_reportes',
    ];

    protected function casts(): array
    {
        return [
            'horas_requeridas' => 'integer',
            'porcentaje_creditos_minimo' => 'decimal:2',
            'creditos_otorgados' => 'integer',
            'esquema_reportes' => \App\Enums\EsquemaReportes::class,
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

    /** Carreras dentro del alcance de ese personal (global ve todas). */
    public function scopeEnAlcance(\Illuminate\Database\Eloquent\Builder $query, Personal $personal): \Illuminate\Database\Eloquent\Builder
    {
        $ids = $personal->carreraIdsEnAlcance();

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }

    /** Coordinación de la carrera (personal con rol coordinador y esa carrera, o su división). */
    public function coordinadores(): \Illuminate\Support\Collection
    {
        return \App\Services\Avisos::coordinadores((int) $this->id);
    }

    /** Correo al que el alumno escribe: el de la coordinación o, si no lo ha capturado, el de su coordinador. */
    public function correoContacto(): ?string
    {
        return $this->coordinacion_correo ?: $this->coordinadores()->first()?->email;
    }

    public function auditoriaNombre(): ?string
    {
        return $this->carrera_clave;
    }

}
