<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinacion;

use App\Actions\ActivarPractica;
use App\Actions\DarBajaPractica;
use App\Actions\EmitirCartaPresentacion;
use App\Actions\RegistrarCartaAceptacion;
use App\Actions\RevisarExpedienteAlumno;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinacion\ActivarPracticaRequest;
use App\Http\Requests\Coordinacion\DarBajaPracticaRequest;
use App\Http\Requests\Coordinacion\DesbloquearAlumnoRequest;
use App\Http\Requests\Coordinacion\RevisarExpedienteRequest;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use App\Services\ConvertidorPdf;
use App\Services\GeneradorCartaPresentacion;
use App\Support\ReglasArchivo;
use App\Support\Requisitos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Practicantes de la coordinación: expedientes por validar, postulaciones, cartas, activación,
 * supervisor, bajas y desbloqueos.
 */
class PracticanteController extends Controller
{
    /** Prácticas (con horas acumuladas) y postulaciones en trámite. */
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;

        $practicas = Practica::with(['postulacion.alumno.user', 'postulacion.alumno.carrera', 'postulacion.programa.convenio.empresa', 'supervisor'])
            ->conHorasAcumuladas()
            ->visiblesPara($personal)
            ->when($request->filled('q'), fn ($q) => $q->whereHas('postulacion.alumno', fn ($a) => $a
                ->where('num_control', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->string('q').'%'))))
            ->when($request->filled('carrera_id'), fn ($q) => $q->whereHas('postulacion.alumno', fn ($a) => $a->where('carrera_id', $request->integer('carrera_id'))))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->latest('id')
            ->paginate(15, ['*'], 'practicas')
            ->withQueryString();

        $postulaciones = Postulacion::with(['alumno.user', 'alumno.carrera', 'alumno.media', 'programa.convenio.empresa', 'media'])
            ->visiblesPara($personal)
            ->whereIn('estado', [
                PostulacionEstado::Pendiente->value,
                PostulacionEstado::CartaPresentacionEmitida->value,
                PostulacionEstado::CartaAceptacionRecibida->value,
            ])
            ->oldest('fecha_postulacion')
            ->paginate(10, ['*'], 'postulaciones')
            ->withQueryString();

        return view('coordinacion.lista-practicantes', [
            // Kardex e IMSS se revisan juntos ANTES de que el alumno pueda postularse
            'expedientesPorRevisar' => Alumno::conExpedientePorRevisar($personal),
            // Alumnos con bloqueo por sanción o por límite de bajas: solo un desbloqueo excepcional los libera
            'bloqueados' => Alumno::bloqueadosPara($personal),
            'practicas' => $practicas,
            'postulaciones' => $postulaciones,
            // Qué impide emitir la carta o activar la práctica de cada postulación (id => motivo(s))
            'bloqueosCarta' => $postulaciones->getCollection()
                ->filter(fn (Postulacion $po) => $po->estado === PostulacionEstado::Pendiente)
                ->mapWithKeys(fn (Postulacion $po) => [$po->id => Requisitos::emitirCarta($po)])
                ->all(),
            'bloqueosActivar' => $postulaciones->getCollection()
                ->filter(fn (Postulacion $po) => in_array($po->estado, [PostulacionEstado::CartaPresentacionEmitida, PostulacionEstado::CartaAceptacionRecibida], true))
                ->mapWithKeys(fn (Postulacion $po) => [$po->id => Requisitos::activarPractica($po)])
                ->all(),
            // Bajas que ya lleva el alumno de cada práctica activa: el modal de baja avisa si llega al límite
            'bajasPrevias' => $practicas->getCollection()
                ->filter(fn (Practica $pr) => in_array($pr->estado, PracticaEstado::activas(), true))
                ->mapWithKeys(fn (Practica $pr) => [$pr->id => $pr->postulacion->alumno->bajasVigentes()->count()])
                ->all(),
            'carreras' => Carrera::enAlcance($personal)->orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'estados' => PracticaEstado::cases(),
            'profesores' => Profesor::with('division')->activos()
                ->whereIn('division_id', Carrera::enAlcance($personal)->select('division_id'))
                ->orderBy('nombre')->get(),
        ]);
    }

    /** Emite la carta de presentación: la coordinación sube el escaneo firmado y sellado; el alumno lo descarga. */
    public function emitirCarta(Request $request, Postulacion $postulacion, EmitirCartaPresentacion $emitir): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);

        $request->validate(['carta' => ReglasArchivo::pdfRequerido()], [
            'carta.required' => 'Sube la carta de presentación firmada y sellada (PDF).',
        ]);

        $emitir($postulacion, $request->user()->personal, $request->file('carta'));

        return back()->with('status', 'Carta de presentación emitida: el alumno ya puede descargarla para llevarla a la organización.');
    }

    /** Formato de la carta de presentación con los datos del alumno, para imprimirlo, firmarlo y sellarlo. */
    public function formatoCarta(Request $request, Postulacion $postulacion, GeneradorCartaPresentacion $generador, ConvertidorPdf $convertidor): BinaryFileResponse
    {
        $this->authorize('gestionar', $postulacion);

        $word = $generador->generar($postulacion, $request->user()->personal);
        $pdf = $convertidor->convertir($word);
        $nombre = 'carta-presentacion-'.$postulacion->alumno->num_control;

        // Sin LibreOffice se entrega el Word para imprimirlo desde ahí
        return $pdf
            ? response()->download($pdf, "{$nombre}.pdf")->deleteFileAfterSend()
            : response()->download($word, "{$nombre}.docx")->deleteFileAfterSend();
    }

    /** Kardex e IMSS se revisan juntos: se autorizan ambos o se devuelve el que esté mal (RevisarExpedienteAlumno). */
    public function revisarExpediente(RevisarExpedienteRequest $request, Alumno $alumno, RevisarExpedienteAlumno $revisar): RedirectResponse
    {
        if ($request->devuelve()) {
            $nombres = $revisar->devolver($alumno, $request->validated('documentos'), $request->validated('motivo'), $request->user()->id);

            return back()->with('status', 'Expediente devuelto al alumno para que reemplace '.implode(' y ', $nombres).'.');
        }

        $revisar->autorizar($alumno, $request->user()->id);

        return back()->with('status', "Expediente de {$alumno->user->name} validado: ya puede postularse.");
    }

    public function rechazarPostulacion(Request $request, Postulacion $postulacion): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);

        if (! $postulacion->estado->puedePasarA(PostulacionEstado::Rechazada)) {
            return back()->withErrors(['postulacion' => "Una postulación «{$postulacion->estado->label()}» ya no se puede rechazar."]);
        }

        $postulacion->update(['estado' => PostulacionEstado::Rechazada]);

        Avisos::enviar($postulacion->alumno->user, new AvisoOperativo(
            mensaje: "Tu postulación a «{$postulacion->programa->titulo}» fue rechazada. Puedes postularte a otro programa de tu carrera.",
            url: route('alumno.oferta-programas', absolute: false),
            correo: true,
            asunto: 'Actualización de tu postulación',
        ));

        return back()->with('status', 'Postulación rechazada.');
    }

    public function subirCartaAceptacion(Request $request, Postulacion $postulacion, RegistrarCartaAceptacion $registrar): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);

        $ya = $postulacion->media()->where('collection_name', 'carta_aceptacion')->exists();

        $request->validate(['archivo' => $ya ? ReglasArchivo::pdfOpcional() : ReglasArchivo::pdfRequerido()]);

        if ($request->hasFile('archivo')) {
            $postulacion->addMediaFromRequest('archivo')->toMediaCollection('carta_aceptacion');
        }

        $registrar($postulacion);

        return back()->with('status', 'Carta de aceptación registrada; la práctica puede activarse.');
    }

    /**
     * Modalidad interna: adjunta el Oficio de Aceptación Interno firmado por el Jefe de Departamento
     * o Director de División del área receptora. Es requisito para activar la práctica.
     */
    public function subirOficioInterno(Request $request, Postulacion $postulacion): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);

        if (! $postulacion->programa->esInterno()) {
            return back()->withErrors(['postulacion' => 'El oficio de aceptación interno solo aplica a proyectos internos.']);
        }

        if (! in_array($postulacion->estado, [PostulacionEstado::CartaPresentacionEmitida, PostulacionEstado::CartaAceptacionRecibida], true)) {
            return back()->withErrors(['postulacion' => 'Primero emite la carta de presentación.']);
        }

        $request->validate(['archivo' => ReglasArchivo::pdfRequerido()], [
            'archivo.required' => 'Adjunta el oficio firmado en PDF.',
        ]);

        $media = $postulacion->addMediaFromRequest('archivo')->toMediaCollection('oficio_aceptacion_interno');
        // Lo sube la propia coordinación con la firma del área receptora: queda validado
        $postulacion->resolverDocumento($media, ValidacionEstado::Validada, null, $request->user()->id);

        return back()->with('status', 'Oficio de aceptación interno registrado.');
    }

    public function activar(ActivarPracticaRequest $request, Postulacion $postulacion, ActivarPractica $activar): RedirectResponse
    {
        $datos = $request->validated();

        $supervisor = isset($datos['profesor_id']) ? Profesor::find($datos['profesor_id']) : null;
        if ($supervisor && ! $supervisor->activo) {
            return back()->withErrors(['profesor_id' => 'Ese profesor está desactivado en el catálogo: elige otro o actívalo en «Profesores supervisores».']);
        }

        $activar(
            $postulacion,
            $request->user()->personal,
            Carbon::parse($datos['fecha_inicio']),
            Carbon::parse($datos['fecha_fin']),
            $supervisor
        );

        return back()->with('status', 'Práctica activada.');
    }

    /** Asigna o cambia el supervisor (debe ser de la división de la carrera del alumno). */
    public function asignarSupervisor(Request $request, Practica $practica): RedirectResponse
    {
        $this->authorize('gestionar', $practica);

        if (! in_array($practica->estado, PracticaEstado::activas(), true)) {
            return back()->withErrors(['practica' => 'Solo se asigna supervisor a prácticas activas.']);
        }
        $datos = $request->validate(['profesor_id' => ['required', 'integer', Rule::exists('profesores', 'id')]]);

        $profesor = Profesor::findOrFail($datos['profesor_id']);
        $carrera = $practica->postulacion->alumno->carrera;

        if (! $profesor->activo) {
            return back()->withErrors(['practica' => 'Ese profesor está desactivado en el catálogo: elige otro o actívalo en «Profesores supervisores».']);
        }

        abort_unless($request->user()->personal->puedeVerCarrera((int) $carrera->id), 403);

        if (! $profesor->puedeSupervisar($carrera)) {
            return back()->withErrors(['practica' => 'El profesor no pertenece a la división de la carrera del alumno.']);
        }

        $practica->update(['profesor_supervisor_id' => $profesor->id]);

        Avisos::enviar(Avisos::alumnoDe($practica), new AvisoOperativo(
            mensaje: "Tu profesor supervisor es {$profesor->nombre}.",
            url: route('alumno.mis-practicas', absolute: false),
        ));

        return back()->with('status', 'Supervisor asignado.');
    }

    /** Baja de una práctica en curso; el tipo de baja y los avisos los resuelve DarBajaPractica. */
    public function cancelar(DarBajaPracticaRequest $request, Practica $practica, DarBajaPractica $darBaja): RedirectResponse
    {
        if (! in_array($practica->estado, PracticaEstado::activas(), true)) {
            return back()->withErrors(['practica' => 'Solo se cancelan prácticas activas.']);
        }

        $baja = $darBaja($practica, $request->validated('motivo_baja'), $request->validated('detalle_baja'));

        if ($baja === null) {
            return back()->withErrors(['practica' => 'Esta práctica ya se dio de baja.']);
        }

        $resumen = match ($baja['tipo']) {
            'parcial_acreditada' => "baja parcial acreditada: el alumno conserva {$baja['horas']} horas.",
            'sancion' => 'baja por sanción: las horas se invalidan.',
            default => 'baja sin reportes validados: las horas no se conservan.',
        };

        return back()->with('status', 'Baja registrada como '.$resumen
            .($baja['bloqueado'] ? ' El alumno quedó bloqueado para postularse.' : ''));
    }

    /** Desbloqueo excepcional: la coordinación levanta la restricción con una justificación. */
    public function desbloquear(DesbloquearAlumnoRequest $request, Alumno $alumno): RedirectResponse
    {
        $datos = $request->validated();

        if (! $alumno->estaBloqueado()) {
            return back()->withErrors(['desbloqueo' => 'Este alumno no está bloqueado.']);
        }

        $alumno->update([
            'desbloqueado_at' => now(),
            'desbloqueo_justificacion' => $datos['justificacion'],
            'desbloqueado_por' => $request->user()->id,
        ]);

        Avisos::enviar($alumno->user, new AvisoOperativo(
            mensaje: 'Tu coordinación levantó la restricción de tu perfil (desbloqueo excepcional). Ya puedes postularte a una vacante de tu carrera.',
            url: route('alumno.oferta-programas', absolute: false),
            correo: true,
            asunto: 'Ya puedes postularte de nuevo',
        ));

        return back()->with('status', "Desbloqueaste a {$alumno->user->name}: ya puede postularse de nuevo.");
    }

    /** Descarga de archivos de una postulación (kardex, cartas). */
    public function descargar(Request $request, Postulacion $postulacion, Media $media): Response
    {
        return $this->entregarDe($postulacion, $media);
    }
}
