<?php

namespace App\Models;

use App\Enums\Modalidad;
use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\PracticaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Models\Concerns\Auditable;
use App\Enums\ValidacionEstado;

#[ObservedBy([PracticaObserver::class])]
class Practica extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'practicas';

    protected $fillable = [
        'postulacion_id',
        'autorizado_por',
        'profesor_supervisor_id',
        'modalidad',
        'fecha_inicio',
        'fecha_fin',
        'horas_requeridas',
        'motivo_baja',
        'tipo_baja',
        'fecha_baja',
        'detalle_baja',
        'conserva_horas',
        'horas_reconocidas',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'modalidad' => Modalidad::class,
            'estado' => PracticaEstado::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'horas_requeridas' => 'integer',
            'conserva_horas' => 'boolean',
            'fecha_baja' => 'datetime',
            'horas_reconocidas' => 'integer',
        ];
    }

    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function alumno(): HasOneThrough
    {
        return $this->hasOneThrough(Alumno::class, Postulacion::class, 'id', 'id', 'postulacion_id', 'alumno_id');
    }

    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'autorizado_por');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'profesor_supervisor_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }

    public function cartaTermino(): HasOne
    {
        return $this->hasOne(CartaTermino::class);
    }

    public function oficioAcreditacion(): HasOne
    {
        return $this->hasOne(OficioAcreditacion::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereIn('estado', PracticaEstado::valores(PracticaEstado::activas()));
    }

    /** Bandeja del coordinador/director: solo prácticas de alumnos de sus carreras. */
    public function scopeVisiblesPara(Builder $query, Personal $personal): Builder
    {
        $ids = $personal->carreraIdsEnAlcance();

        return $ids === null
            ? $query
            : $query->whereHas('postulacion.alumno', fn(Builder $q) => $q->whereIn('carrera_id', $ids));
    }

    /**
     * Horas acumuladas = suma de reportes VALIDADOS (no es columna, no se desincroniza).
     * En listados usa scopeConHorasAcumuladas() para evitar N+1.
     */
    protected function horasAcumuladas(): Attribute
    {
        // Las horas reconocidas vienen de una práctica anterior dada de baja con «conservar horas»
        return Attribute::get(function () {
            $reconocidas = (int) ($this->attributes['horas_reconocidas'] ?? 0);

            if (array_key_exists('horas_acumuladas', $this->attributes)) {
                return (int) $this->attributes['horas_acumuladas'] + $reconocidas;
            }

            return (int) $this->reportes()
                ->where('estado', ReporteEstado::Validado->value)
                ->sum('horas_reportadas') + $reconocidas;
        });
    }

    /** Motivos formales de baja o cambio de plaza (criterios de la Coordinación de Carrera). */
    public const MOTIVOS_BAJA = [
        'cierre_empresa' => 'Cierre o baja de la organización',
        'cambio_proyecto' => 'Cambio o término del proyecto',
        'mutuo_acuerdo' => 'Mutuo acuerdo',
        'cambio_plaza' => 'Cambio de plaza',
        'baja_temporal' => 'Baja temporal del alumno',
        'renuncia' => 'Renuncia del alumno con aviso',
        'despido' => 'Despido por la organización',
        'abandono' => 'Abandono injustificado',
        'falta_grave' => 'Falta grave o administrativa',
        'otro' => 'Otro motivo',
    ];

    /** Motivos que la coordinación sanciona: invalidan las horas y bloquean al alumno. */
    public const MOTIVOS_SANCION = ['despido', 'abandono', 'falta_grave'];

    /** Cómo quedó resuelta la baja. */
    public const TIPOS_BAJA = [
        'sin_reportes' => 'Baja sin reportes validados',
        'parcial_acreditada' => 'Baja parcial acreditada',
        'sancion' => 'Baja por sanción',
    ];

    public function motivoBajaTexto(): ?string
    {
        return $this->motivo_baja ? (self::MOTIVOS_BAJA[$this->motivo_baja] ?? $this->motivo_baja) : null;
    }

    public function tipoBajaTexto(): ?string
    {
        return $this->tipo_baja ? (self::TIPOS_BAJA[$this->tipo_baja] ?? $this->tipo_baja) : null;
    }

    /**
     * Resolución de la baja según el motivo y los reportes validados:
     * sanción → 0 horas; justificada con horas validadas → se conservan; sin reportes → se pierden.
     */
    public function resolverBaja(string $motivo): string
    {
        return match (true) {
            in_array($motivo, self::MOTIVOS_SANCION, true) => 'sancion',
            $this->horas_acumuladas > 0 => 'parcial_acreditada',
            default => 'sin_reportes',
        };
    }

    /** Horas que la baja deja al alumno para su siguiente plaza. */
    public function horasQueConserva(): int
    {
        return $this->tipo_baja === 'parcial_acreditada' || ($this->tipo_baja === null && $this->conserva_horas)
            ? (int) $this->horas_acumuladas
            : 0;
    }

    public function scopeConHorasAcumuladas(Builder $query): Builder
    {
        return $query->withSum(
            ['reportes as horas_acumuladas' => fn($q) => $q->where('estado', ReporteEstado::Validado->value)],
            'horas_reportadas'
        );
    }

    /** Texto para el panel del alumno: "240/480". */
    public function progresoTexto(): string
    {
        return $this->horas_acumuladas . '/' . $this->horas_requeridas;
    }

    public function horasCompletas(): bool
    {
        return $this->horas_acumuladas >= $this->horas_requeridas;
    }

    /**
     * Devuelve el motivo por el que el alumno aún NO puede subir su carta de término,
     * o null si ya puede hacerlo.
     */
    public function motivoSinCartaTermino(): ?string
    {
        if ($this->estado !== PracticaEstado::EnCurso) {
            return 'Tu carta de término ya no admite cambios.';
        }

        if ($this->cartaTermino?->estado_validacion === ValidacionEstado::Validada) {
            return 'Tu carta de término ya fue validada.';
        }

        $hayReportesSinResolver = $this->reportes()
            ->whereIn('estado', [ReporteEstado::Pendiente->value, ReporteEstado::Rechazado->value])
            ->exists();

        if ($hayReportesSinResolver) {
            return 'Tienes reportes pendientes de validación o rechazados. Resuélvelos antes de subir tu carta de término.';
        }

        if (!$this->horasCompletas()) {
            return "Aún no completas tus horas ({$this->progresoTexto()}). La carta de término se sube al terminar.";
        }

        return null;
    }

    public function auditoriaEtiqueta(): string
    {
        return 'Práctica';
    }

}
