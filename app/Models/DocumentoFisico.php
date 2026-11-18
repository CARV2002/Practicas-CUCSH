<?php

namespace App\Models;

use App\Enums\EstadoDocumentoFisico;
use App\Enums\TipoDocumentoFisico;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Un papel del trámite híbrido (oficio, dictamen jurídico o juego del convenio) y dónde está.
 * Cada cambio queda en documento_fisico_movimientos para reconstruir su recorrido.
 */
class DocumentoFisico extends Model
{
    protected $table = 'documentos_fisicos';

    protected $fillable = [
        'convenio_id', 'tipo', 'juego', 'destino', 'estado', 'fecha_estado', 'fecha_compromiso', 'notas', 'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoFisico::class,
            'estado' => EstadoDocumentoFisico::class,
            'juego' => 'integer',
            'fecha_estado' => 'date',
            'fecha_compromiso' => 'date',
        ];
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'actualizado_por');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(DocumentoFisicoMovimiento::class)->latest('fecha')->latest('id');
    }

    public function nombre(): string
    {
        return $this->tipo === TipoDocumentoFisico::JuegoConvenio
            ? "Juego {$this->juego} de 3"
            : $this->tipo->label();
    }

    // ---- Plazos ------------------------------------------------------------------------

    public function diasEnEstado(): int
    {
        return (int) abs($this->fecha_estado->diffInWeekdays(today()));
    }

    /** Fuera de la oficina y ya pasó la fecha en que debía regresar. */
    public function vencido(): bool
    {
        return $this->estado->fueraDeOficina()
            && $this->fecha_compromiso !== null
            && $this->fecha_compromiso->lt(today());
    }

    /** Regresa hoy o en los próximos 2 días hábiles. */
    public function porVencer(): bool
    {
        return $this->estado->fueraDeOficina()
            && $this->fecha_compromiso !== null
            && !$this->vencido()
            && $this->fecha_compromiso->lte(today()->addWeekdays(2));
    }

    public function scopeFuera(Builder $q): Builder
    {
        return $q->whereIn('estado', EstadoDocumentoFisico::fuera());
    }

    public function scopeVencidos(Builder $q): Builder
    {
        return $q->fuera()->whereDate('fecha_compromiso', '<', today()->toDateString());
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->whereNotIn('estado', EstadoDocumentoFisico::concluidos());
    }

    // ---- Movimientos -------------------------------------------------------------------

    /** Registra que el papel cambió de lugar o de situación. */
    public function mover(
        EstadoDocumentoFisico $estado,
        ?Personal $por,
        ?string $nota = null,
        ?CarbonInterface $compromiso = null,
        ?CarbonInterface $fecha = null,
    ): self {
        if (!in_array($estado, $this->tipo->estados(), true)) {
            throw ValidationException::withMessages([
                'estado' => "«{$estado->label()}» no aplica a {$this->tipo->label()}.",
            ]);
        }

        $fecha ??= today();
        $plazo = $estado->plazoHabiles();
        $compromiso = $plazo === null ? null : ($compromiso ?? $fecha->copy()->addWeekdays($plazo));

        return DB::transaction(function () use ($estado, $por, $nota, $compromiso, $fecha) {
            $this->update([
                'estado' => $estado,
                'fecha_estado' => $fecha->toDateString(),
                'fecha_compromiso' => $compromiso?->toDateString(),
                'actualizado_por' => $por?->id ?? $this->actualizado_por,
            ]);

            $this->movimientos()->create([
                'estado' => $estado,
                'nota' => $nota,
                'fecha' => $fecha->toDateString(),
                'registrado_por' => $por?->id,
            ]);

            return $this;
        });
    }
}
