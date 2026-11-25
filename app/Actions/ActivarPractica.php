<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use App\Models\Programa;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La coordinación valida la carta de aceptación que subió el alumno y, en ese mismo paso, nace la práctica:
 * queda «en curso» para reportar horas y ocupa 1 lugar del cupo de la vacante. Congela horas_requeridas desde
 * la carrera, exige que convenio e IMSS cubran TODO el periodo y cancela las demás postulaciones abiertas del alumno.
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
            // Orden fijo de bloqueo (alumno -> programa -> postulación), el mismo de la carta de presentación
            $alumno = Alumno::with('carrera')->lockForUpdate()->findOrFail($postulacion->alumno_id);
            $programa = Programa::with('convenio')->lockForUpdate()->findOrFail($postulacion->programa_id);
            $postulacion = Postulacion::lockForUpdate()->findOrFail($postulacion->id)->setRelation('programa', $programa);

            if (! $por->puedeVerCarrera((int) $alumno->carrera_id)) {
                $this->fallar('No tienes alcance sobre la carrera de este alumno.');
            }

            if (! in_array($postulacion->estado, [PostulacionEstado::CartaPresentacionEmitida, PostulacionEstado::CartaAceptacionRecibida], true)) {
                $this->fallar('Primero debe emitirse la carta de presentación.');
            }
            $aceptacion = $postulacion->media()->where('collection_name', 'carta_aceptacion')->latest('id')->first();
            if ($aceptacion === null) {
                $this->fallar('El alumno aún no sube la carta de aceptación de la organización.');
            }
            // Dos alumnos pueden traer carta de presentación para la misma vacante: el lugar es de quien se activa primero
            if ($programa->cuposOcupados() >= $programa->cupos_totales) {
                $this->fallar('La vacante ya no tiene cupos disponibles.');
            }

            if ($fechaFin->lessThanOrEqualTo($fechaInicio)) {
                $this->fallar('La fecha de fin debe ser posterior a la de inicio.');
            }

            // Si se asigna supervisor, debe ser de la misma DIVISIÓN que la carrera del alumno
            if ($supervisor !== null && ! $supervisor->puedeSupervisar($alumno->carrera)) {
                $this->fallar('El profesor supervisor no pertenece a la división de la carrera del alumno.');
            }

            // Modalidad interna (sin convenio): el área receptora de la UdeG firma un Oficio de Aceptación Interno
            if ($programa->esInterno() && ! $postulacion->media()->where('collection_name', 'oficio_aceptacion_interno')->exists()) {
                $this->fallar('Falta el Oficio de Aceptación Interno firmado por el Jefe de Departamento o Director de División.');
            }

            // Un convenio que vence a mitad de la práctica invalida las horas
            if (! $programa->esInterno() && ! $programa->convenio?->cubreHasta($fechaFin)) {
                $this->fallar('El convenio no está vigente durante todo el periodo de la práctica.');
            }

            // Nadie llega a la práctica sin su expediente: kardex e IMSS validados por la coordinación
            if (! $alumno->documentoValidado('kardex') || ! $alumno->documentoValidado('constancia_imss')) {
                $this->fallar('El expediente del alumno (kardex e IMSS) no está validado.');
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
                // Cambio de plaza: horas validadas que la coordinación decidió conservar
                'horas_reconocidas' => min($alumno->horasConservadas(), $alumno->carrera->horas_requeridas),
                'estado' => PracticaEstado::EnCurso,
            ]);

            // La carta de aceptación queda validada y la postulación aceptada en el mismo paso
            $postulacion->resolverDocumento($aceptacion, ValidacionEstado::Validada, null, $por->user_id);
            if ($postulacion->estado === PostulacionEstado::CartaPresentacionEmitida) {
                $postulacion->update(['estado' => PostulacionEstado::CartaAceptacionRecibida]);
            }
            $postulacion->update(['estado' => PostulacionEstado::Aceptada]);

            // "Un alumno, una empresa": las demás postulaciones abiertas se cancelan solas
            Postulacion::where('alumno_id', $alumno->id)
                ->where('id', '!=', $postulacion->id)
                ->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::abiertas()))
                ->get()
                ->each->update(['estado' => PostulacionEstado::Cancelada]);

            $empresa = $programa->convenio?->empresa;

            Avisos::enviar($alumno->user, new AvisoOperativo(
                mensaje: "Tu coordinación validó tu carta de aceptación: tu práctica en «{$programa->titulo}» quedó activa del {$fechaInicio->format('d/m/Y')} al {$fechaFin->format('d/m/Y')}. Ya puedes registrar tus reportes: debes completar {$practica->horas_requeridas} horas.",
                url: route('alumno.mis-practicas', absolute: false),
                detalle: $programa->titulo,
                correo: true,
                asunto: 'Tu práctica profesional está activa',
            ));

            Avisos::enviar($empresa?->user, new AvisoOperativo(
                mensaje: "{$alumno->user->name} inició su práctica en «{$programa->titulo}».",
                url: route('empresa.practicantes.show', $practica, false),
            ));

            return $practica;
        });
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['practica' => $mensaje]);
    }
}
