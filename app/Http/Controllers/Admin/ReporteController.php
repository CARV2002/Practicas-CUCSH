<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ReporteTipo;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Practica;
use App\Models\Reporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ReporteController extends Controller
{
    /**
     * Reportes por práctica (horas y estado de validación),
     * avance de cada practicante y prácticas próximas a cerrar.
     */
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;

        $reportes = Reporte::with(['practica.postulacion.alumno.user', 'practica.postulacion.alumno.carrera', 'practica.postulacion.programa.convenio.empresa', 'media'])
            ->whereHas('practica', fn ($p) => $p->visiblesPara($personal))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')))
            ->when($request->filled('carrera_id'), fn ($q) => $q->whereHas('practica.postulacion.alumno', fn ($a) => $a->where('carrera_id', $request->integer('carrera_id'))))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('practica.postulacion.alumno', fn ($a) => $a
                ->where('num_control', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->string('q').'%'))))
            ->orderByRaw("case estado when 'pendiente' then 0 else 1 end")
            ->latest('periodo_fin')
            ->paginate(15, ['*'], 'reportes')
            ->withQueryString();

        $avance = Practica::with(['postulacion.alumno.user', 'postulacion.alumno.carrera'])
            ->conHorasAcumuladas()
            ->visiblesPara($personal)
            ->activas()
            ->orderBy('fecha_fin')
            ->paginate(10, ['*'], 'avance')
            ->withQueryString();

        // Próximas a cerrar: terminan en 30 días o ya cubren las horas requeridas
        $porCerrar = Practica::with(['postulacion.alumno.user', 'postulacion.alumno.carrera'])
            ->conHorasAcumuladas()
            ->visiblesPara($personal)
            ->activas()
            ->get()
            ->filter(fn ($p) => $p->fecha_fin->lessThanOrEqualTo(now()->addDays(30)) || $p->horasCompletas())
            ->sortBy('fecha_fin')
            ->values();

        return view('admin.lista-reportes', [
            'reportes' => $reportes,
            'avance' => $avance,
            'porCerrar' => $porCerrar,
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'estados' => ReporteEstado::cases(),
            'tipos' => ReporteTipo::cases(),
            'pendientes' => Reporte::where('estado', ReporteEstado::Pendiente->value)
                ->whereHas('practica', fn ($p) => $p->visiblesPara($personal))
                ->count(),
        ]);
    }

    /** Valida o rechaza un reporte de horas; las horas validadas son las que cuentan. */
    public function resolver(Request $request, Reporte $reporte): RedirectResponse
    {
        abort_unless(
            $request->user()->personal->puedeVerCarrera((int) $reporte->practica->postulacion->alumno->carrera_id),
            403
        );

        $datos = $request->validate([
            'estado' => ['required', Rule::in([ReporteEstado::Validado->value, ReporteEstado::Rechazado->value])],
            'evaluacion_profesor' => ['nullable', 'string', 'max:2000'],
            'retroalimentacion_empresa' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! in_array($reporte->practica->estado, PracticaEstado::activas(), true)) {
            return back()->withErrors(['reporte' => 'La práctica ya no está activa.']);
        }

        $reporte->update($datos);

        return back()->with('status', 'Reporte '.($datos['estado'] === ReporteEstado::Validado->value ? 'validado' : 'rechazado').'.');
    }

    /** Descarga del reporte firmado (disco privado). */
    public function descargar(Request $request, Reporte $reporte, Media $media)
    {
        abort_unless(
            $media->model_type === Reporte::class
            && (int) $media->model_id === (int) $reporte->id
            && $request->user()->personal->puedeVerCarrera((int) $reporte->practica->postulacion->alumno->carrera_id),
            404
        );

        return $media->toResponse($request);
    }
}
