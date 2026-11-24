<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * División académica del CUCSH: agrupa carreras y profesores supervisores.
 */
class Division extends Model
{
    use Auditable;

    protected $table = 'divisiones';

    protected $fillable = ['nombre'];

    /** @return HasMany<Carrera, $this> */
    public function carreras(): HasMany
    {
        return $this->hasMany(Carrera::class);
    }

    /** @return HasMany<Profesor, $this> */
    public function profesores(): HasMany
    {
        return $this->hasMany(Profesor::class);
    }

    /**
     * Directores de esta división.
     *
     * @return HasMany<Personal, $this>
     */
    public function personal(): HasMany
    {
        return $this->hasMany(Personal::class);
    }

    public function auditoriaEtiqueta(): string
    {
        return 'División';
    }

    public function auditoriaNombre(): ?string
    {
        return $this->nombre;
    }
}
