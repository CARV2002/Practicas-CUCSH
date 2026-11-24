<?php

declare(strict_types=1);

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
use Symfony\Component\HttpFoundation\Response;

/**
 * Expediente del alumno que revisa la Coordinación de su carrera:
 * constancia de vigencia IMSS (del alumno) y kardex (de cada postulación).
 */
class ExpedienteController extends Controller
{
    /** Colecciones que la coordinación puede validar, por tipo de dueño. */
    private const REVISABLES = [
        Alumno::class => ['constancia_imss' => 'constancia IMSS', 'kardex' => 'kardex'],
        Postulacion::class => ['oficio_aceptacion_interno' => 'oficio de aceptación interno'],
    ];

    public function resolver(Request $request, Media $media): RedirectResponse
    {
        $nombre = self::REVISABLES[$media->model_type][$media->collection_name] ?? null;
        abort_if($nombre === null, 404);

        $dueno = $media->model;
        if (! $dueno instanceof Alumno && ! $dueno instanceof Postulacion) {
            abort(404);
        }
        $alumno = $dueno instanceof Alumno ? $dueno : $dueno->alumno;

        // Solo la coordinación de la carrera del alumno
        abort_unless($request->user()->personal->puedeVerCarrera((int) $alumno->carrera_id), 403);

        $datos = $request->validate([
            'estado' => ['required', Rule::in([ValidacionEstado::Validada->value, ValidacionEstado::Rechazada->value])],
            'motivo' => ['nullable', 'required_if:estado,'.ValidacionEstado::Rechazada->value, 'string', 'max:500'],
        ], [
            'motivo.required_if' => 'Explica al alumno por qué se rechaza el documento.',
        ]);

        $estado = ValidacionEstado::from($datos['estado']);
        $dueno->resolverDocumento($media, $estado, $datos['motivo'] ?? null, $request->user()->id);

        $rechazado = $estado === ValidacionEstado::Rechazada;
        $completo = ! $rechazado && $alumno->fresh()->expedienteValidado();   // con esto ya puede postularse

        Avisos::enviar($alumno->user, new AvisoOperativo(
            mensaje: $rechazado
                ? "Tu coordinación te devolvió tu {$nombre}. Motivo: «{$datos['motivo']}». Qué hacer: entra a «Mis documentos › Tu expediente», busca la tarjeta marcada en rojo y sube el archivo corregido."
                : ($completo
                    ? 'Tu coordinación validó tu kardex y tu constancia IMSS. ¡Ya puedes postularte a una vacante de tu carrera!'
                    : "Tu coordinación validó tu {$nombre}."),
            url: route('alumno.mis-documentos', absolute: false),
            correo: $rechazado || $completo,
            asunto: $rechazado ? 'Corrige un documento de tu expediente' : ($completo ? 'Ya puedes postularte a prácticas profesionales' : null),
        ));

        return back()->with('status', ucfirst($nombre).($rechazado ? ' rechazado.' : ' validado.'));
    }

    /** Descarga de la constancia IMSS del alumno. */
    public function descargarAlumno(Alumno $alumno, Media $media): Response
    {
        return $this->entregarDe($alumno, $media);
    }
}
