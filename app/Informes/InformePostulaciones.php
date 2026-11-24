<?php

declare(strict_types=1);

namespace App\Informes;

use App\Models\Postulacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Postulaciones de los alumnos (filtra por carrera, estado y fecha de postulación). */
class InformePostulaciones extends Informe
{
    public function encabezados(): array
    {
        return ['Fecha', 'Código de alumno', 'Alumno', 'Carrera', 'Programa', 'Empresa o proyecto', '% créditos declarado', 'Estado'];
    }

    /** @return Builder<Postulacion> */
    public function consulta(): Builder
    {
        $f = $this->filtros;

        return Postulacion::with(['alumno.user', 'alumno.carrera', 'programa.convenio.empresa'])
            ->visiblesPara($this->personal)
            ->when($f->filled('carrera_id'), fn ($q) => $q->whereHas('alumno', fn ($a) => $a->where('carrera_id', $f->integer('carrera_id'))))
            ->when($f->filled('estado'), fn ($q) => $q->where('estado', $this->filtro('estado')))
            ->when($f->filled('desde'), fn ($q) => $q->whereDate('fecha_postulacion', '>=', $f->date('desde')))
            ->when($f->filled('hasta'), fn ($q) => $q->whereDate('fecha_postulacion', '<=', $f->date('hasta')));
    }

    /** @param  Postulacion  $modelo */
    public function fila(Model $modelo): array
    {
        return [
            $modelo->fecha_postulacion->format('Y-m-d'), $modelo->alumno->num_control, $modelo->alumno->user->name,
            $modelo->alumno->carrera->carrera_clave, $modelo->programa->titulo,
            $modelo->programa->convenio?->empresa->razon_social ?? 'Proyecto interno',
            (float) $modelo->porcentaje_creditos_declarado, $modelo->estado->label(),
        ];
    }
}
