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

    // ---- Reglas del recorrido ----------------------------------------------------------

    /** ¿La Unidad Jurídica ya dio el dictamen favorable? (el convenio está en firmas o formalizado) */
    private function juridicaAprobo(): bool
    {
        return in_array($this->convenio?->estado, [\App\Enums\ConvenioEstado::EnFirmas, \App\Enums\ConvenioEstado::Formalizado], true);
    }

    /**
     * Por qué este papel no puede pasar a ese estado (null si sí puede).
     *  - Expediente devuelto por Jurídica: solo puede volver a la Unidad Jurídica hasta que haya dictamen favorable.
     *  - Los 3 juegos no salen a firma de la empresa ni a Rectoría sin el dictamen favorable.
     */
    public function bloqueoPara(EstadoDocumentoFisico $estado): ?string
    {
        if ($this->juridicaAprobo() || $estado === $this->estado || $estado === EstadoDocumentoFisico::Extraviado) {
            return null;
        }

        if ($this->tipo === TipoDocumentoFisico::DictamenJuridico
            && !in_array($estado, [EstadoDocumentoFisico::EnJuridica, EstadoDocumentoFisico::ConObservaciones], true)) {
            return 'Jurídica aún no emite el dictamen favorable: el expediente solo puede volver a la Unidad Jurídica.';
        }

        if ($this->tipo === TipoDocumentoFisico::JuegoConvenio && in_array($estado, [
            EstadoDocumentoFisico::ConEmpresa, EstadoDocumentoFisico::FirmadoEmpresa, EstadoDocumentoFisico::EnRectoria,
            EstadoDocumentoFisico::Firmado, EstadoDocumentoFisico::Entregado,
        ], true)) {
            return 'Los 3 juegos no pueden salir a firma hasta que Jurídica emita el dictamen favorable.';
        }

        return null;
    }

    /** @return EstadoDocumentoFisico[] estados a los que sí puede pasar ahora */
    public function estadosDisponibles(): array
    {
        return array_values(array_filter($this->tipo->estados(), fn ($e) => $this->bloqueoPara($e) === null));
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

    /** Papeles de trámites que siguen vivos: un convenio rechazado ya no genera recordatorios. */
    public function scopeDeTramiteVivo(Builder $q): Builder
    {
        return $q->whereHas('convenio', fn ($c) => $c->where('estado', '!=', \App\Enums\ConvenioEstado::Rechazado->value));
    }

    /** Texto del recordatorio a la empresa (manual o automático). */
    public static function mensajeJuegosEmpresa(\Illuminate\Support\Collection $juegos): string
    {
        $n = $juegos->count();
        $fecha = $juegos->min('fecha_compromiso');
        $cuando = $fecha ? ' a más tardar el ' . \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') : '';

        return "Tienes {$n} " . ($n === 1 ? 'juego original' : 'juegos originales') . " del convenio por firmar. Fírmalos al calce y entrégalos en Vinculación{$cuando}.";
    }

    /**
     * Filtro de la pantalla «Documentos físicos» (y de su informe):
     * activos · fuera · vencidos · casa (en la oficina de Vinculación) · concluidos; y búsqueda por organización o folio.
     */
    public function scopeEnVista(Builder $q, string $vista, string $buscar = ''): Builder
    {
        return $q
            ->when($vista === 'activos', fn ($d) => $d->activos())
            ->when($vista === 'fuera', fn ($d) => $d->fuera())
            ->when($vista === 'vencidos', fn ($d) => $d->vencidos())
            ->when($vista === 'casa', fn ($d) => $d->activos()->whereNotIn('estado', EstadoDocumentoFisico::fuera()))
            ->when($vista === 'concluidos', fn ($d) => $d->whereIn('estado', EstadoDocumentoFisico::concluidos()))
            ->when($buscar !== '', fn ($d) => $d->whereHas('convenio', fn ($c) => $c
                ->where('folio', 'like', "%{$buscar}%")
                ->orWhereHas('empresa', fn ($e) => $e->where('razon_social', 'like', "%{$buscar}%"))));
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
