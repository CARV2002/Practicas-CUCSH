<?php

declare(strict_types=1);

namespace App\Informes;

use App\Enums\Dictamen;
use App\Models\Convenio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Convenios con su vigencia y las carreras aprobadas (filtra por carrera dictaminada, estado y fecha de recepción). */
class InformeConvenios extends Informe
{
    public function encabezados(): array
    {
        return ['Folio', 'Empresa', 'RFC', 'Tipo de organismo', 'Estado', 'Recibido', 'Inicio de vigencia', 'Fin de vigencia',
            'Días para vencer', 'Carreras aprobadas', 'Plazas estimadas'];
    }

    /** @return Builder<Convenio> */
    public function consulta(): Builder
    {
        $f = $this->filtros;

        return Convenio::with(['empresa', 'dictamenes.carrera'])
            ->visiblesPara($this->personal)
            ->when($f->filled('carrera_id'), fn ($q) => $q->whereHas('dictamenes', fn ($d) => $d->where('carrera_id', $f->integer('carrera_id'))))
            ->when($f->filled('estado'), fn ($q) => $q->where('estado', $this->filtro('estado')))
            ->when($f->filled('desde'), fn ($q) => $q->whereDate('fecha_recepcion', '>=', $f->date('desde')))
            ->when($f->filled('hasta'), fn ($q) => $q->whereDate('fecha_recepcion', '<=', $f->date('hasta')));
    }

    /** @param  Convenio  $modelo */
    public function fila(Model $modelo): array
    {
        return [
            $modelo->folio, $modelo->empresa->razon_social, $modelo->empresa->rfc, $modelo->empresa->tipo_organismo->label(), $modelo->estado->label(),
            $modelo->fecha_recepcion->format('Y-m-d'), $modelo->fecha_inicio_vigencia?->format('Y-m-d'), $modelo->fecha_fin_vigencia?->format('Y-m-d'),
            $modelo->estaVigente() ? (int) now()->startOfDay()->diffInDays($modelo->fecha_fin_vigencia, false) : null,
            $modelo->dictamenes->filter(fn ($d) => $d->dictamen === Dictamen::Aprobatorio)->map(fn ($d) => $d->carrera->carrera_clave)->join(' '),
            $modelo->plazas_estimadas,
        ];
    }
}
