<?php

declare(strict_types=1);

namespace App\Informes;

use App\Models\Practica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Prácticas con sus horas validadas y su avance (filtra por carrera, estado y fecha de inicio). */
class InformePracticas extends Informe
{
    public function encabezados(): array
    {
        return ['Código de alumno', 'Alumno', 'Carrera', 'Empresa o proyecto', 'Programa', 'Ciclo', 'Inicio', 'Fin',
            'Horas validadas', 'Horas requeridas', 'Avance %', 'Estado', 'Supervisor'];
    }

    /** @return Builder<Practica> */
    public function consulta(): Builder
    {
        $f = $this->filtros;

        return Practica::with(['postulacion.alumno.user', 'postulacion.alumno.carrera', 'postulacion.programa.convenio.empresa', 'supervisor'])
            ->conHorasAcumuladas()
            ->visiblesPara($this->personal)
            ->when($f->filled('carrera_id'), fn ($q) => $q->whereHas('postulacion.alumno', fn ($a) => $a->where('carrera_id', $f->integer('carrera_id'))))
            ->when($f->filled('estado'), fn ($q) => $q->where('estado', $this->filtro('estado')))
            ->when($f->filled('desde'), fn ($q) => $q->whereDate('fecha_inicio', '>=', $f->date('desde')))
            ->when($f->filled('hasta'), fn ($q) => $q->whereDate('fecha_inicio', '<=', $f->date('hasta')));
    }

    /** @param  Practica  $modelo */
    public function fila(Model $modelo): array
    {
        $al = $modelo->postulacion->alumno;
        $pr = $modelo->postulacion->programa;
        $validadas = (int) $modelo->horas_acumuladas;

        return [
            $al->num_control, $al->user->name, $al->carrera->carrera_clave,
            $pr->convenio?->empresa->razon_social ?? 'Proyecto interno', $pr->titulo, $pr->ciclo,
            $modelo->fecha_inicio->format('Y-m-d'), $modelo->fecha_fin->format('Y-m-d'),
            $validadas, $modelo->horas_requeridas,
            $modelo->horas_requeridas > 0 ? (int) round($validadas / $modelo->horas_requeridas * 100) : 0,
            $modelo->estado->label(), $modelo->supervisor?->nombre,
        ];
    }
}
