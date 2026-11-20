<?php

namespace App\Http\Controllers\Coordinacion;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Panel de la Coordinación de carrera: solo su alcance (carrera o división). */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;
        $ids = $personal->carreraIdsEnAlcance();
        $enAlcance = fn($q) => $ids === null ? $q : $q->whereIn('carrera_id', $ids);

        $publicados = $enAlcance(Programa::conCuposOcupados())
            ->where('estado', ProgramaEstado::Publicado->value)
            ->get(['id', 'cupos_totales']);

        $totalCupos = (int) $publicados->sum('cupos_totales');
        $ocupados = (int) $publicados->sum('cupos_ocupados');

        return view('coordinacion.dashboard', [
            // Cuentas institucionales («Coordinación de …») no se saludan por «nombre»
            'nombre' => preg_match('/^coordinaci[oó]n\b/iu', trim($request->user()->name)) ? null : explode(' ', trim($request->user()->name))[0],
            'carreras' => Carrera::enAlcance($personal)->orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'kpis' => [
                'alumnos' => $enAlcance(Alumno::query())->count(),
                'practicas_activas' => Practica::visiblesPara($personal)->activas()->count(),
                'programas_publicados' => $publicados->count(),
                'ocupacion' => $totalCupos > 0 ? (int) round($ocupados / $totalCupos * 100) : 0,
                'cupos_ocupados' => $ocupados,
                'cupos_totales' => $totalCupos,
            ],
            // Misma fuente que la campana: el saludo y las tarjetas siempre coinciden
            'pendientes' => $pendientes = \App\Support\Pendientes::conteosCoordinacion($personal),
            'totalPendientes' => \App\Support\Pendientes::totalCoordinacion($pendientes),
            'programas_recientes' => $enAlcance(Programa::with(['convenio.empresa', 'carrera'])->conCuposOcupados())
                ->latest('id')
                ->limit(5)
                ->get(),
            'practicas_por_estado' => collect(PracticaEstado::cases())->mapWithKeys(fn($e) => [
                $e->label() => Practica::visiblesPara($personal)->where('estado', $e->value)->count(),
            ]),
        ]);
    }
}
