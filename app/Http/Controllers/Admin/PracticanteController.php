<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ValidacionEstado;
use App\Actions\ActivarPractica;
use App\Actions\EmitirCartaPresentacion;
use App\Actions\RegistrarCartaAceptacion;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

        $postulaciones = Postulacion::with(['alumno.user', 'alumno.carrera', 'programa.convenio.empresa', 'media'])
            ->visiblesPara($personal)
            ->whereIn('estado', [
                PostulacionEstado::Pendiente->value,
                PostulacionEstado::CartaPresentacionEmitida->value,
                PostulacionEstado::CartaAceptacionRecibida->value,
            ])
            ->oldest('fecha_postulacion')
            ->paginate(10, ['*'], 'postulaciones')
            ->withQueryString();

        return view('admin.lista-practicantes', [
            'practicas' => $practicas,
            'postulaciones' => $postulaciones,
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'estados' => PracticaEstado::cases(),
            'profesores' => Profesor::with('division')->orderBy('nombre')->get(),
        ]);
    }

    public function emitirCarta(Request $request, Postulacion $postulacion, EmitirCartaPresentacion $emitir): RedirectResponse
    {
        $emitir($postulacion, $request->user()->personal);

        return back()->with('status', 'Carta de presentación emitida; cupo reservado.');
    }

    public function rechazarPostulacion(Request $request, Postulacion $postulacion): RedirectResponse
    {
        abort_unless($request->user()->personal->puedeVerCarrera((int) $postulacion->alumno->carrera_id), 403);

        if (! in_array($postulacion->estado, [PostulacionEstado::Pendiente, PostulacionEstado::CartaPresentacionEmitida], true)) {
            return back()->withErrors(['postulacion' => 'Solo se rechazan postulaciones pendientes o con carta emitida.']);
        }

        $postulacion->update(['estado' => PostulacionEstado::Rechazada]);

        return back()->with('status', 'Postulación rechazada.');
    }

    public function subirCartaAceptacion(Request $request, Postulacion $postulacion, RegistrarCartaAceptacion $registrar): RedirectResponse
    {
        abort_unless($request->user()->personal->puedeVerCarrera((int) $postulacion->alumno->carrera_id), 403);

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $postulacion->addMediaFromRequest('archivo')->toMediaCollection('carta_aceptacion');
        $registrar($postulacion);

        return back()->with('status', 'Carta de aceptación registrada.');
    }

    public function activar(Request $request, Postulacion $postulacion, ActivarPractica $activar): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'profesor_id' => ['nullable', 'integer', Rule::exists('profesores', 'id')],
        ]);

        $supervisor = isset($datos['profesor_id']) ? Profesor::find($datos['profesor_id']) : null;

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
        $datos = $request->validate(['profesor_id' => ['required', 'integer', Rule::exists('profesores', 'id')]]);

        $profesor = Profesor::findOrFail($datos['profesor_id']);
        $carrera = $practica->postulacion->alumno->carrera;

        abort_unless($request->user()->personal->puedeVerCarrera((int) $carrera->id), 403);

        if (! $profesor->puedeSupervisar($carrera)) {
            return back()->withErrors(['practica' => 'El profesor no pertenece a la división de la carrera del alumno.']);
        }

        $practica->update(['profesor_supervisor_id' => $profesor->id]);

        return back()->with('status', 'Supervisor asignado.');
    }

    public function cancelar(Request $request, Practica $practica): RedirectResponse
    {
        abort_unless(
            $request->user()->personal->puedeVerCarrera((int) $practica->postulacion->alumno->carrera_id),
            403
        );

        if (! in_array($practica->estado, PracticaEstado::activas(), true)) {
            return back()->withErrors(['practica' => 'Solo se cancelan prácticas activas.']);
        }

        $practica->update(['estado' => PracticaEstado::Cancelada]);

        return back()->with('status', 'Práctica cancelada.');
    }

    /** Descarga de archivos de una postulación (kardex, cartas). */
    public function descargar(Request $request, Postulacion $postulacion, Media $media)
    {
        abort_unless(
            $media->model_type === Postulacion::class
            && (int) $media->model_id === (int) $postulacion->id
            && $request->user()->personal->puedeVerCarrera((int) $postulacion->alumno->carrera_id),
            404
        );

        return $media->toResponse($request);
    }
}