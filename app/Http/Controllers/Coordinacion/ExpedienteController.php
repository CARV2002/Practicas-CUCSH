<?php

namespace App\Http\Controllers\Coordinacion;

use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Postulacion;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Expediente del alumno que revisa la Coordinación de su carrera:
 * constancia de vigencia IMSS (del alumno) y kardex (de cada postulación).
 */
class ExpedienteController extends Controller
{
    /** Colecciones que la coordinación puede validar, por tipo de dueño. */
    private const REVISABLES = [
        Alumno::class => ['constancia_imss' => 'constancia IMSS'],
        Postulacion::class => ['kardex' => 'kardex', 'oficio_aceptacion_interno' => 'oficio de aceptación interno'],
    ];

    public function resolver(Request $request, Media $media): RedirectResponse
    {
        $nombre = self::REVISABLES[$media->model_type][$media->collection_name] ?? null;
        abort_if($nombre === null, 404);

        $dueno = $media->model;
        $alumno = $dueno instanceof Alumno ? $dueno : $dueno?->alumno;
        abort_if($alumno === null, 404);

        // Solo la coordinación de la carrera del alumno
        abort_unless($request->user()->personal->puedeVerCarrera((int) $alumno->carrera_id), 403);

        $datos = $request->validate([
            'estado' => ['required', Rule::in([ValidacionEstado::Validada->value, ValidacionEstado::Rechazada->value])],
            'motivo' => ['nullable', 'required_if:estado,' . ValidacionEstado::Rechazada->value, 'string', 'max:500'],
        ], [
            'motivo.required_if' => 'Explica al alumno por qué se rechaza el documento.',
        ]);

        $estado = ValidacionEstado::from($datos['estado']);
        $dueno->resolverDocumento($media, $estado, $datos['motivo'] ?? null, $request->user()->id);

        $rechazado = $estado === ValidacionEstado::Rechazada;

        Avisos::enviar($alumno->user, new AvisoOperativo(
            mensaje: $rechazado
                ? "Tu coordinación rechazó tu {$nombre}: {$datos['motivo']}. Súbelo de nuevo."
                : "Tu coordinación validó tu {$nombre}.",
            url: route('alumno.mis-documentos', absolute: false),
            correo: $rechazado,
            asunto: $rechazado ? 'Documento rechazado' : null,
        ));

        return back()->with('status', ucfirst($nombre) . ($rechazado ? ' rechazado.' : ' validado.'));
    }

    /** Descarga de la constancia IMSS del alumno. */
    public function descargarAlumno(Alumno $alumno, Media $media)
    {
        abort_unless($media->model_type === Alumno::class && (int) $media->model_id === (int) $alumno->id, 404);
        $this->authorize('download', $media);

        return $this->entregar($media);
    }
}
