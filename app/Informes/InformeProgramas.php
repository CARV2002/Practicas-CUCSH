<?php

declare(strict_types=1);

namespace App\Informes;

use App\Models\Programa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Programas y vacantes con sus cupos (filtra por carrera, estado y ciclo). */
class InformeProgramas extends Informe
{
    public function encabezados(): array
    {
        return ['Programa', 'Empresa o proyecto', 'Carrera', 'Ciclo', 'Modalidad', 'Estado', 'Cupos totales', 'Cupos ocupados', 'Cupos disponibles', 'Sede', 'Horario'];
    }

    /** @return Builder<Programa> */
    public function consulta(): Builder
    {
        $f = $this->filtros;
        $ids = $this->personal->carreraIdsEnAlcance();

        return Programa::with(['convenio.empresa', 'carrera'])
            ->conCuposOcupados()
            ->when($ids !== null, fn ($q) => $q->whereIn('carrera_id', $ids))
            ->when($f->filled('carrera_id'), fn ($q) => $q->where('carrera_id', $f->integer('carrera_id')))
            ->when($f->filled('estado'), fn ($q) => $q->where('estado', $this->filtro('estado')))
            ->when($f->filled('ciclo'), fn ($q) => $q->where('ciclo', $this->filtro('ciclo')));
    }

    /** @param  Programa  $modelo */
    public function fila(Model $modelo): array
    {
        return [
            $modelo->titulo, $modelo->convenio?->empresa->razon_social ?? 'Proyecto interno', $modelo->carrera->carrera_clave, $modelo->ciclo,
            ucfirst($modelo->modalidad->value), ucfirst($modelo->estado->value),
            $modelo->cupos_totales, (int) $modelo->cupos_ocupados, $modelo->cupos_disponibles, $modelo->sede, $modelo->horario,
        ];
    }
}
