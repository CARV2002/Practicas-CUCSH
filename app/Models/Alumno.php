<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PracticaEstado;
use App\Enums\Turno;
use App\Enums\ValidacionEstado;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ValidaDocumentos;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Alumno: datos escolares, expediente (kardex + IMSS), postulaciones, prácticas y bloqueos por bajas.
 */
class Alumno extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia, ValidaDocumentos;

    protected $table = 'alumnos';

    protected $fillable = ['user_id', 'carrera_id', 'num_control', 'grado', 'turno', 'vigencia_imss', 'porcentaje_creditos',
        'desbloqueado_at', 'desbloqueo_justificacion', 'desbloqueado_por'];

    /** Al llegar a este número de bajas el alumno ya no puede postularse por su cuenta. */
    public const LIMITE_BAJAS = 2;

    protected function casts(): array
    {
        return ['vigencia_imss' => 'date', 'grado' => 'integer', 'turno' => Turno::class, 'porcentaje_creditos' => 'decimal:2',
            'desbloqueado_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        // Disco "local" = storage/app/private en Laravel 11+/12 (no público)
        $this->addMediaCollection('constancia_imss')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);

        // Expediente: el kardex se entrega junto con el IMSS y se valida antes de postularse
        $this->addMediaCollection('kardex')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Carrera, $this> */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /** @return HasMany<Postulacion, $this> */
    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    /** @return HasManyThrough<Practica, Postulacion, $this> */
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
     * Alumnos del alcance de una coordinación con algo del expediente por revisar: kardex o IMSS
     * recién enviados o reemplazados (también renovaciones de IMSS durante la práctica).
     *
     * @return Collection<int, self>
     */
    public static function conExpedientePorRevisar(Personal $personal): Collection
    {
        $ids = $personal->carreraIdsEnAlcance();
        $pendiente = fn ($m) => $m !== null && in_array($m->getCustomProperty('estado'), [null, ValidacionEstado::Pendiente->value], true);

        return self::with(['user', 'carrera', 'media'])
            ->when($ids !== null, fn ($q) => $q->whereIn('carrera_id', $ids))
            ->whereHas('media', fn ($m) => $m->whereIn('collection_name', ['constancia_imss', 'kardex']))
            ->get()
            // Devuelto: la coordinación ya lo revisó y espera la corrección del alumno
            ->filter(fn (self $a) => $a->estadoExpediente() !== 'devuelto'
                && ($pendiente($a->documentoVigente('kardex')) || $pendiente($a->documentoVigente('constancia_imss'))))
            ->values();
    }

    /** @deprecated Usa conExpedientePorRevisar(): el IMSS se revisa junto con el kardex. */
    public static function conImssPorRevisar(Personal $personal): Collection
    {
        return self::conExpedientePorRevisar($personal);
    }

    /** Horas validadas que conserva de su última baja (solo «baja parcial acreditada»; 0 en los demás casos). */
    public function horasConservadas(): int
    {
        return $this->ultimaBaja()?->horasQueConserva() ?? 0;
    }

    public function ultimaBaja(): ?Practica
    {
        return $this->practicas()
            ->where('practicas.estado', PracticaEstado::Cancelada->value)
            ->latest('practicas.id')
            ->first();
    }

    /** Bajas que cuentan para el bloqueo: las posteriores al último desbloqueo excepcional. */
    public function bajasVigentes(): Collection
    {
        return $this->practicas()
            ->where('practicas.estado', PracticaEstado::Cancelada->value)
            ->when($this->desbloqueado_at, fn ($q) => $q->where(fn ($w) => $w
                ->where('practicas.fecha_baja', '>', $this->desbloqueado_at)
                ->orWhereNull('practicas.fecha_baja')))
            ->get();
    }

    /**
     * Motivo por el que el alumno no puede postularse por su cuenta (null si no está bloqueado):
     *  'sancion' → baja por despido, abandono o falta grave
     *  'limite'  → alcanzó el límite de bajas
     */
    public function motivoBloqueo(): ?string
    {
        $bajas = $this->bajasVigentes();

        return match (true) {
            $bajas->contains(fn (Practica $p) => $p->tipo_baja === 'sancion') => 'sancion',
            $bajas->count() >= self::LIMITE_BAJAS => 'limite',
            default => null,
        };
    }

    public function estaBloqueado(): bool
    {
        return $this->motivoBloqueo() !== null;
    }

    /** Texto para el alumno (y la coordinación) que explica el bloqueo. */
    public function textoBloqueo(): ?string
    {
        return match ($this->motivoBloqueo()) {
            'sancion' => 'Tu última práctica se dio de baja por sanción: tus horas no se conservan y no puedes postularte hasta que tu coordinación levante la restricción. Acude en persona a tu Coordinación de Carrera.',
            'limite' => 'Llegaste al límite de '.self::LIMITE_BAJAS.' bajas de práctica, así que ya no puedes postularte por tu cuenta. Si tuviste una causa de fuerza mayor, acude en persona a tu Coordinación de Carrera para solicitar un desbloqueo excepcional.',
            default => null,
        };
    }

    /** Alumnos bloqueados del alcance de una coordinación (para el desbloqueo excepcional). */
    public static function bloqueadosPara(Personal $personal): Collection
    {
        $ids = $personal->carreraIdsEnAlcance();

        return self::with(['user', 'carrera'])
            ->when($ids !== null, fn ($q) => $q->whereIn('carrera_id', $ids))
            ->whereHas('practicas', fn ($q) => $q->where('practicas.estado', PracticaEstado::Cancelada->value))
            ->get()
            ->filter(fn (self $a) => $a->estaBloqueado())
            ->values();
    }

    /**
     * Estado del expediente (kardex + IMSS), que se entrega y se revisa en un solo momento:
     *  sin_enviar · en_revision · devuelto · validado · imss_vencido
     */
    public function estadoExpediente(): string
    {
        $kardex = $this->documentoVigente('kardex')?->getCustomProperty('estado');
        $imss = $this->documentoVigente('constancia_imss')?->getCustomProperty('estado');
        $hayKardex = $this->documentoVigente('kardex') !== null;
        $hayImss = $this->documentoVigente('constancia_imss') !== null;

        return match (true) {
            $kardex === 'rechazada' || $imss === 'rechazada' => 'devuelto',
            // Un IMSS validado que ya venció se renueva antes de seguir, esté como esté el kardex
            $imss === 'validada' && ! $this->tieneImssVigenteHasta(now()) => 'imss_vencido',
            // Créditos por debajo del mínimo de su carrera (p. ej. la coordinación subió el mínimo): no puede postularse
            $hayKardex && $this->creditosInsuficientes() => 'no_cumple',
            $kardex === 'validada' && $imss === 'validada' => 'validado',
            $hayKardex && $hayImss => 'en_revision',
            default => 'sin_enviar',
        };
    }

    /** Su % de créditos declarado no alcanza el mínimo de su carrera (el mismo criterio que la postulación). */
    public function creditosInsuficientes(): bool
    {
        return (float) $this->porcentaje_creditos < (float) $this->carrera?->porcentaje_creditos_minimo;
    }

    /** Kardex e IMSS validados por su coordinación, IMSS vigente y créditos suficientes: ya puede postularse. */
    public function expedienteValidado(): bool
    {
        return $this->estadoExpediente() === 'validado';
    }

    /** Ya entregó un IMSS que la coordinación validó y sigue vigente: no se le vuelve a pedir. */
    public function tieneImssValidadoVigente(): bool
    {
        return $this->documentoValidado('constancia_imss') && $this->tieneImssVigenteHasta(now());
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
