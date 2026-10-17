<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Personal institucional. El rol (vinculación, coordinador, director, jurídico...) está en Spatie;
 * esta tabla solo define el ALCANCE:
 *   carrera_id  -> coordinador de esa carrera
 *   division_id -> director de esa división (ve todas sus carreras)
 *   ninguno     -> alcance global
 */
class Personal extends Model
{
    protected $table = 'personal';

    protected $fillable = ['user_id', 'division_id', 'carrera_id', 'puesto'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /** Convenios que lleva como gestor (Vinculación). */
    public function convenios(): HasMany
    {
        return $this->hasMany(Convenio::class, 'gestionado_por');
    }

    public function dictamenes(): HasMany
    {
        return $this->hasMany(ConvenioCarrera::class, 'dictaminado_por');
    }

    public function tieneAlcanceGlobal(): bool
    {
        return $this->carrera_id === null && $this->division_id === null;
    }

    /** @return int[]|null null = todas las carreras */
    public function carreraIdsEnAlcance(): ?array
    {
        if ($this->carrera_id !== null) {
            return [(int) $this->carrera_id];
        }

        if ($this->division_id !== null) {
            return Carrera::where('division_id', $this->division_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return null;
    }

    public function puedeVerCarrera(int $carreraId): bool
    {
        $ids = $this->carreraIdsEnAlcance();

        return $ids === null || in_array($carreraId, $ids, true);
    }
}
