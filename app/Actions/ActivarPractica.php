<?php

namespace App\Actions;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nace la práctica. Congela horas_requeridas desde la carrera, exige que convenio e IMSS
 * cubran TODO el periodo y cancela las demás postulaciones abiertas del alumno.
 */
class ActivarPractica
{
    public function __invoke(
        Postulacion $postulacion,
        Personal $por,
        CarbonInterface $fechaInicio,
        CarbonInterface $fechaFin,
        ?Profesor $supervisor = null,   // opcional: si nadie administra profesores aún, se asigna después
    ): Practica {
        return DB::transaction(function () use ($postulacion, $por, $supervisor, $fechaInicio, $fechaFin) {
            $alumno = Alumno::with('carrera')->lockForUpdate()->findOrFail($postulacion->alumno_id);
            $postulacion = Postulacion::with('programa.convenio')->lockForUpdate()->findOrFail($postulacion->id);
            $programa = $postulacion->programa;

            if (! $por->puedeVerCarrera((int) $alumno->carrera_id)) {
                $this->fallar('No tienes alcance sobre la carrera de este alumno.');
            }

            if ($postulacion->estado !== PostulacionEstado::CartaAceptacionRecibida) {
                $this->fallar('Falta registrar la carta de aceptación de la empresa.');
            }

            if ($fechaFin->lessThanOrEqualTo($fechaInicio)) {
                $this->fallar('La fecha de fin debe ser posterior a la de inicio.');
            }

            // Si se asigna supervisor, debe ser de la misma DIVISIÓN que la carrera del alumno
            if ($supervisor !== null && ! $supervisor->puedeSupervisar($alumno->carrera)) {
                $this->fallar('El profesor supervisor no pertenece a la división de la carrera del alumno.');
            }

            // Un convenio que vence a mitad de la práctica invalida las horas
            if (! $programa->esInterno() && ! $programa->convenio?->cubreHasta($fechaFin)) {
                $this->fallar('El convenio no está vigente durante todo el periodo de la práctica.');
            }

            if (! $alumno->tieneImssVigenteHasta($fechaFin)) {
                $this->fallar('El seguro IMSS del alumno vence antes de terminar la práctica.');
            }

            if ($alumno->tienePracticaActiva()) {
                $this->fallar('El alumno ya tiene una práctica activa.');
            }

            $practica = Practica::create([
                'postulacion_id' => $postulacion->id,
                'autorizado_por' => $por->id,
                'profesor_supervisor_id' => $supervisor?->id,
                'modalidad' => $programa->modalidad,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'horas_requeridas' => $alumno->carrera->horas_requeridas,   // copia congelada
                'estado' => PracticaEstado::EnCurso,
            ]);

            $postulacion->update(['estado' => PostulacionEstado::Aceptada]);

            // "Un alumno, una empresa": las demás postulaciones abiertas se cancelan solas
            Postulacion::where('alumno_id', $alumno->id)
                ->where('id', '!=', $postulacion->id)
                ->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::abiertas()))
                ->update(['estado' => PostulacionEstado::Cancelada->value]);

            return $practica;
        });
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['practica' => $mensaje]);
    }
}
