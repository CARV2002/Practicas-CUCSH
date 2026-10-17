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

class Practica extends Model
{
    use SoftDeletes;

    protected $table = 'practicas';

    protected $fillable = [
        'postulacion_id', 'autorizado_por', 'profesor_supervisor_id',
        'modalidad', 'fecha_inicio', 'fecha_fin', 'horas_requeridas', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'modalidad' => Modalidad::class,
            'estado' => PracticaEstado::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'horas_requeridas' => 'integer',
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
            : $query->whereHas('postulacion.alumno', fn (Builder $q) => $q->whereIn('carrera_id', $ids));
    }

    /**
     * Horas acumuladas = suma de reportes VALIDADOS (no es columna, no se desincroniza).
     * En listados usa scopeConHorasAcumuladas() para evitar N+1.
     */
    protected function horasAcumuladas(): Attribute
    {
        return Attribute::get(function () {
            if (array_key_exists('horas_acumuladas', $this->attributes)) {
                return (int) $this->attributes['horas_acumuladas'];
            }

            return (int) $this->reportes()
                ->where('estado', ReporteEstado::Validado->value)
                ->sum('horas_reportadas');
        });
    }

    public function scopeConHorasAcumuladas(Builder $query): Builder
    {
        return $query->withSum(
            ['reportes as horas_acumuladas' => fn ($q) => $q->where('estado', ReporteEstado::Validado->value)],
            'horas_reportadas'
        );
    }

    /** Texto para el panel del alumno: "240/480". */
    public function progresoTexto(): string
    {
        return $this->horas_acumuladas.'/'.$this->horas_requeridas;
    }

    public function horasCompletas(): bool
    {
        return $this->horas_acumuladas >= $this->horas_requeridas;
    }
}
