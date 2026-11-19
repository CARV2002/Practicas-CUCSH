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
use App\Models\Concerns\Auditable;

class Alumno extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos, Auditable;

    protected $table = 'alumnos';

    protected $fillable = ['user_id', 'carrera_id', 'num_control', 'grado', 'turno', 'vigencia_imss', 'porcentaje_creditos'];

    protected function casts(): array
    {
        return ['vigencia_imss' => 'date', 'grado' => 'integer', 'turno' => \App\Enums\Turno::class, 'porcentaje_creditos' => 'decimal:2'];
    }

    public function registerMediaCollections(): void
    {
        // Disco "local" = storage/app/private en Laravel 11+/12 (no público)
        $this->addMediaCollection('constancia_imss')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);

        // Expediente: el kardex se entrega junto con el IMSS y se valida antes de postularse
        $this->addMediaCollection('kardex')
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
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function conExpedientePorRevisar(Personal $personal): \Illuminate\Support\Collection
    {
        $ids = $personal->carreraIdsEnAlcance();
        $pendiente = fn ($m) => $m !== null && in_array($m->getCustomProperty('estado'), [null, \App\Enums\ValidacionEstado::Pendiente->value], true);

        return self::with(['user', 'carrera', 'media'])
            ->when($ids !== null, fn ($q) => $q->whereIn('carrera_id', $ids))
            ->whereHas('media', fn ($m) => $m->whereIn('collection_name', ['constancia_imss', 'kardex']))
            ->get()
            ->filter(fn (self $a) => $pendiente($a->documentoVigente('kardex')) || $pendiente($a->documentoVigente('constancia_imss')))
            ->values();
    }

    /** @deprecated Usa conExpedientePorRevisar(): el IMSS se revisa junto con el kardex. */
    public static function conImssPorRevisar(Personal $personal): \Illuminate\Support\Collection
    {
        return self::conExpedientePorRevisar($personal);
    }

    /** Horas validadas que la coordinación decidió conservar de la última baja (0 si se reiniciaron). */
    public function horasConservadas(): int
    {
        $ultima = $this->practicas()
            ->where('practicas.estado', PracticaEstado::Cancelada->value)
            ->latest('practicas.id')
            ->first();

        return $ultima && $ultima->conserva_horas ? $ultima->horas_acumuladas : 0;
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
            $imss === 'validada' && !$this->tieneImssVigenteHasta(now()) => 'imss_vencido',
            $kardex === 'validada' && $imss === 'validada' => 'validado',
            $hayKardex && $hayImss => 'en_revision',
            default => 'sin_enviar',
        };
    }

    /** Kardex e IMSS validados por su coordinación (y el IMSS sigue vigente): ya puede postularse. */
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
