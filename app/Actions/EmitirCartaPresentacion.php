<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PostulacionEstado;
use App\Enums\ProgramaEstado;
use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Programa;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use App\Services\ConvertidorPdf;
use App\Services\GeneradorCartaPresentacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * El coordinador valida la postulación y emite la carta de presentación (reserva el cupo).
 * Todo en UNA transacción con bloqueo de filas: dos validaciones simultáneas no sobrepasan
 * el cupo ni dejan a un alumno con dos empresas.
 */
class EmitirCartaPresentacion
{
    public function __construct(private GeneradorCartaPresentacion $generador) {}

    public function __invoke(Postulacion $postulacion, Personal $por): Postulacion
    {
        return DB::transaction(function () use ($postulacion, $por) {
            // Orden fijo de bloqueo (alumno -> programa -> postulación) para evitar deadlocks
            $alumno = Alumno::with('carrera')->lockForUpdate()->findOrFail($postulacion->alumno_id);
            $programa = Programa::with('convenio')->lockForUpdate()->findOrFail($postulacion->programa_id);
            $postulacion = Postulacion::lockForUpdate()->findOrFail($postulacion->id);

            // Cada coordinador solo valida alumnos de su carrera (el director, de su división)
            if (! $por->puedeVerCarrera((int) $alumno->carrera_id)) {
                $this->fallar('No tienes alcance sobre la carrera de este alumno.');
            }

            if ($postulacion->estado !== PostulacionEstado::Pendiente) {
                $this->fallar('Solo se puede validar una postulación pendiente.');
            }

            if ($programa->estado !== ProgramaEstado::Publicado) {
                $this->fallar('El programa no está publicado.');
            }

            if ((int) $programa->carrera_id !== (int) $alumno->carrera_id) {
                $this->fallar('El programa no está dirigido a la carrera del alumno.');
            }

            // Sin convenio vigente no hay oferta (salvo modalidad interna)
            if (! $programa->esInterno() && ! $programa->convenio?->estaVigente()) {
                $this->fallar('El convenio de la empresa no está vigente.');
            }

            if ((float) $postulacion->porcentaje_creditos_declarado < (float) $alumno->carrera->porcentaje_creditos_minimo) {
                $this->fallar("El alumno no cumple el {$alumno->carrera->porcentaje_creditos_minimo}% mínimo de créditos.");
            }

            if (! $alumno->tieneImssVigenteHasta(now())) {
                $this->fallar('El alumno no tiene seguro facultativo (IMSS) vigente.');
            }

            // Expediente (kardex + IMSS) validado por la coordinación antes de postularse
            if (! $alumno->expedienteValidado()) {
                $this->fallar('El expediente del alumno (kardex e IMSS) no está validado.');
            }

            // La carta se imprime con estos datos
            if ($alumno->grado === null || $alumno->turno === null) {
                $this->fallar('El alumno no ha capturado su grado y turno en su perfil.');
            }

            if ($programa->cuposOcupados() >= $programa->cupos_totales) {
                $this->fallar('El programa ya no tiene cupos disponibles.');
            }

            if ($alumno->yaAcreditado()) {
                $this->fallar('El alumno ya acreditó sus prácticas profesionales.');
            }

            // Un alumno, una empresa a la vez
            $otraEnTramite = Postulacion::where('alumno_id', $alumno->id)
                ->where('id', '!=', $postulacion->id)
                ->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::enTramite()))
                ->exists();

            if ($otraEnTramite || $alumno->tienePracticaActiva()) {
                $this->fallar('El alumno ya tiene una empresa asignada o en trámite.');
            }

            $postulacion->update([
                'estado' => PostulacionEstado::CartaPresentacionEmitida,
                'validado_por' => $por->id,
            ]);

            // Carta de Presentación (.docx) con carrera, grado y turno del alumno
            $ruta = $this->generador->generar($postulacion->setRelation('alumno', $alumno)->setRelation('programa', $programa), $por);

            // PDF listo para imprimir (LibreOffice); el Word queda por si la coordinación necesita ajustarlo
            $pdf = app(ConvertidorPdf::class)->convertir($ruta);

            $postulacion->addMedia($ruta)
                ->usingFileName("carta-presentacion-{$alumno->num_control}.docx")
                ->toMediaCollection('carta_presentacion');

            if ($pdf) {
                $postulacion->addMedia($pdf)
                    ->usingFileName("carta-presentacion-{$alumno->num_control}.pdf")
                    ->withCustomProperties(['generada' => true])
                    ->toMediaCollection('carta_presentacion');
                File::deleteDirectory(dirname($pdf));
            }

            $empresa = $programa->convenio?->empresa;

            Avisos::enviar($alumno->user, new AvisoOperativo(
                mensaje: "Tu postulación a «{$programa->titulo}» fue validada. Preséntate con ".($empresa->razon_social ?? 'el proyecto interno del CUCSH').' y sube tu carta de aceptación.',
                url: route('alumno.mis-documentos', absolute: false),
                detalle: $programa->titulo,
                correo: true,
                asunto: 'Tu carta de presentación está lista',
            ));

            Avisos::enviar($empresa?->user, new AvisoOperativo(
                mensaje: "{$alumno->user->name} ({$alumno->carrera->carrera_clave}) se presentará en «{$programa->titulo}». Si lo aceptas, entrégale su carta de aceptación sellada.",
                url: route('empresa.programas.postulantes', $programa, false),
                detalle: $programa->titulo,
            ));

            return $postulacion;
        });
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['postulacion' => $mensaje]);
    }
}
