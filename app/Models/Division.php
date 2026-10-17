<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $table = 'divisiones';

    protected $fillable = ['nombre'];

    public function carreras(): HasMany
    {
        return $this->hasMany(Carrera::class);
    }

    public function profesores(): HasMany
    {
        return $this->hasMany(Profesor::class);
    }

    /** Directores de esta división. */
    public function personal(): HasMany
    {
        return $this->hasMany(Personal::class);
    }
}
