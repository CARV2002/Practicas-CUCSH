<?php

namespace App\Http\Controllers\Empresa;

use App\Enums\PracticaEstado;
use App\Http\Controllers\Controller;
use App\Models\Programa;
use App\Models\Practica;
use App\Models\Reporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PracticanteController extends Controller
{
    /** Practicantes asignados a los programas de la empresa, con horas validadas. */
    public function index(Request $request): View
    {
        $empresa = $request->user()->empresa;

        $practicas = $this->propias($empresa->id)
            ->with(['postulacion.alumno.user', 'postulacion.alumno.carrera', 'postulacion.programa', 'supervisor'])
            ->conHorasAcumuladas()
            ->when($request->filled('q'), fn($q) => $q->whereHas('postulacion.alumno', fn($a) => $a
                ->where('num_control', 'like', '%' . $request->string('q') . '%')
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', '%' . $request->string('q') . '%'))))
            ->when($request->filled('programa_id'), fn($q) => $q->whereHas('postulacion', fn($p) => $p->where('programa_id', $request->integer('programa_id'))))
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->string('estado')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('empresa.lista-practicantes', [
            'practicas' => $practicas,
            'programas' => Programa::whereHas('convenio', fn($c) => $c->where('empresa_id', $empresa->id))
                ->orderBy('titulo')->get(['id', 'titulo', 'ciclo']),
            'estados' => PracticaEstado::cases(),
            'resumen' => [
                'activos' => $this->propias($empresa->id)->activas()->count(),
                'cierre' => $this->propias($empresa->id)->where('estado', PracticaEstado::EnCierre->value)->count(),
                'acreditados' => $this->propias($empresa->id)->where('estado', PracticaEstado::Acreditada->value)->count(),
            ],
        ]);
    }

    /** Detalle de un practicante: horas y reportes (la empresa aporta retroalimentación). */
    public function show(Request $request, Practica $practica): View
    {
        $this->autorizar($request, $practica);

        $practica->load(['postulacion.alumno.user', 'postulacion.alumno.carrera', 'postulacion.programa', 'supervisor', 'reportes.media']);

        return view('empresa.practicante-show', [
            'practica' => $practica,
            'alumno' => $practica->postulacion->alumno,
            'reportes' => $practica->reportes->sortByDesc('periodo_fin')->values(),
        ]);
    }

    /** La empresa solo escribe su retroalimentación; la validación de horas es de la Coordinación. */
    public function retroalimentar(Request $request, Reporte $reporte): RedirectResponse
    {
        $this->authorize('retroalimentar', $reporte);

        $datos = $request->validate([
            'retroalimentacion_empresa' => ['required', 'string', 'max:2000'],
        ]);

        $reporte->update($datos);

        return back()->with('status', 'Retroalimentación guardada.');
    }

    public function descargarReporte(Request $request, Reporte $reporte, Media $media)
    {
        abort_unless($media->model_type === Reporte::class && (int) $media->model_id === (int) $reporte->id, 404);
        $this->authorize('download', $media);

        return $media->toResponse($request);
    }

    private function propias(int $empresaId)
    {
        return Practica::whereHas('postulacion.programa.convenio', fn($c) => $c->where('empresa_id', $empresaId));
    }

    private function autorizar(Request $request, Practica $practica): void
    {
        $this->authorize('view', $practica);
    }
}