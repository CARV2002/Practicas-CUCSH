<?php

namespace App\Models;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\SeguimientoEstado;
use App\Models\Concerns\ValidaDocumentos;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Convenio extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes, ValidaDocumentos;

    protected $table = 'convenios';

    protected $fillable = [
        'empresa_id', 'gestionado_por', 'folio', 'estado', 'fecha_recepcion',
        'objetivos', 'actividades_propuestas', 'plazas_estimadas',
        'medio_conocimiento', 'experiencia_previa',
        'fecha_inicio_vigencia', 'fecha_fin_vigencia',
    ];

    protected function casts(): array
    {
        return [
            'estado' => ConvenioEstado::class,
            'fecha_recepcion' => 'date',
            'fecha_inicio_vigencia' => 'date',
            'fecha_fin_vigencia' => 'date',
            'plazas_estimadas' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Borradores: SIN singleFile(), cada versión que Jurídica devuelve queda guardada.
        $this->addMediaCollection('borrador_convenio')
            ->useDisk('local')
            ->acceptsMimeTypes([
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);

        // Escaneo de los 3 juegos originales firmados por ambas partes
        $this->addMediaCollection('convenio_firmado')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function gestor(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'gestionado_por');
    }

    /** Un dictamen por carrera solicitada. */
    public function dictamenes(): HasMany
    {
        return $this->hasMany(ConvenioCarrera::class);
    }

    public function carreras(): BelongsToMany
    {
        return $this->belongsToMany(Carrera::class, 'convenio_carrera')
            ->withPivot(['id', 'dictamen', 'observaciones', 'fecha_dictamen', 'dictaminado_por'])
            ->withTimestamps();
    }

    /** Las carreras que quedan en el contrato (dictamen aprobatorio). */
    public function carrerasAprobadas(): BelongsToMany
    {
        return $this->carreras()->wherePivot('dictamen', Dictamen::Aprobatorio->value);
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(ConvenioSeguimiento::class)->orderBy('fecha_registro')->orderBy('id');
    }

    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class);
    }

    // ---- Reglas de la fase académica -------------------------------------------------

    public function autorizaCarrera(int $carreraId): bool
    {
        return $this->dictamenes()
            ->where('carrera_id', $carreraId)
            ->where('dictamen', Dictamen::Aprobatorio->value)
            ->exists();
    }

    public function dictamenesPendientes(): int
    {
        return $this->dictamenes()->where('dictamen', Dictamen::Pendiente->value)->count();
    }

    public function tieneAprobatorio(): bool
    {
        return $this->dictamenes()->where('dictamen', Dictamen::Aprobatorio->value)->exists();
    }

    /** Todas respondieron y ninguna aprobó: "el proceso muere". */
    public function todasNegativas(): bool
    {
        return $this->dictamenes()->exists()
            && $this->dictamenesPendientes() === 0
            && ! $this->tieneAprobatorio();
    }

    /** Sin pendientes y con al menos una carrera aprobatoria. */
    public function puedePasarAJuridica(): bool
    {
        return $this->dictamenesPendientes() === 0 && $this->tieneAprobatorio();
    }

    /** Etapas abiertas = quién tiene "la pelota" ahora mismo. */
    public function etapasAbiertas(): HasMany
    {
        return $this->seguimientos()->where('estado', SeguimientoEstado::Pendiente->value);
    }

    // ---- Vigencia (se calcula, no se guarda) -----------------------------------------

    public function scopeVigentes(Builder $query, ?CarbonInterface $en = null): Builder
    {
        $fecha = ($en ?? now())->toDateString();

        return $query->where('estado', ConvenioEstado::Formalizado->value)
            ->whereDate('fecha_inicio_vigencia', '<=', $fecha)
            ->whereDate('fecha_fin_vigencia', '>=', $fecha);
    }

    /** Convenios que vencen en los próximos N días (avisos a 60 y 30 días). */
    public function scopePorVencer(Builder $query, int $dias): Builder
    {
        return $query->vigentes()
            ->whereDate('fecha_fin_vigencia', '<=', now()->addDays($dias)->toDateString());
    }

    /** Alcance: global ve todo; coordinador/director solo convenios que solicitaron sus carreras. */
    public function scopeVisiblesPara(Builder $query, Personal $personal): Builder
    {
        $ids = $personal->carreraIdsEnAlcance();

        return $ids === null
            ? $query
            : $query->whereHas('dictamenes', fn (Builder $q) => $q->whereIn('carrera_id', $ids));
    }

    public function estaVigente(?CarbonInterface $en = null): bool
    {
        $fecha = ($en ?? now())->toDateString();

        return $this->estado === ConvenioEstado::Formalizado
            && $this->fecha_inicio_vigencia !== null
            && $this->fecha_fin_vigencia !== null
            && $this->fecha_inicio_vigencia->toDateString() <= $fecha
            && $this->fecha_fin_vigencia->toDateString() >= $fecha;
    }

    /** Vigente hoy y hasta la fecha dada (que cubra TODA la práctica). */
    public function cubreHasta(CarbonInterface $fecha): bool
    {
        return $this->estaVigente()
            && $this->fecha_fin_vigencia->toDateString() >= $fecha->toDateString();
    }
}
