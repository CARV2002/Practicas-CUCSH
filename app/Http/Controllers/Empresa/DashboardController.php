<?php

namespace App\Http\Controllers\Empresa;

use App\Enums\Dictamen;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $empresa = $request->user()->empresa;
        $id = $empresa->id;

        $convenios = $empresa->convenios()
            ->with(['dictamenes' => fn ($d) => $d->where('dictamen', Dictamen::Aprobatorio->value)->with('carrera')])
            ->latest('id')
            ->get();

        $vigente = $convenios->first(fn (Convenio $c) => $c->estaVigente());
        $enTramite = $convenios->first(fn (Convenio $c) => ! $c->estado->esFinal());

        $programas = Programa::conCuposOcupados()
            ->whereHas('convenio', fn ($c) => $c->where('empresa_id', $id))
            ->get(['id', 'titulo', 'estado', 'cupos_totales']);

        $publicados = $programas->where('estado', ProgramaEstado::Publicado);

        $practicas = fn () => Practica::whereHas('postulacion.programa.convenio', fn ($c) => $c->where('empresa_id', $id));

        return view('empresa.dashboard', [
            'empresa' => $empresa,
            'vigente' => $vigente,
            'enTramite' => $enTramite,
            'diasRestantes' => $vigente ? (int) now()->startOfDay()->diffInDays($vigente->fecha_fin_vigencia, false) : null,
            'expedienteCompleto' => $empresa->expedienteCompleto(),
            'kpis' => [
                'practicantes_activos' => $practicas()->activas()->count(),
                'acreditados' => $practicas()->where('estado', PracticaEstado::Acreditada->value)->count(),
                'programas_publicados' => $publicados->count(),
                'cupos_totales' => (int) $publicados->sum('cupos_totales'),
                'cupos_ocupados' => (int) $publicados->sum('cupos_ocupados'),
                'cupos_libres' => max(0, (int) $publicados->sum('cupos_totales') - (int) $publicados->sum('cupos_ocupados')),
            ],
            'reportesPendientes' => Reporte::whereHas('practica', fn ($p) => $p
                ->activas()
                ->whereHas('postulacion.programa.convenio', fn ($c) => $c->where('empresa_id', $id)))
                ->where('estado', ReporteEstado::Pendiente->value)
                ->whereNull('retroalimentacion_empresa')
                ->with('practica.postulacion.alumno.user')
                ->latest('periodo_fin')
                ->limit(5)
                ->get(),
            'candidatos' => Postulacion::with(['alumno.user', 'alumno.carrera', 'programa'])
                ->whereHas('programa.convenio', fn ($c) => $c->where('empresa_id', $id))
                ->where('estado', PostulacionEstado::CartaPresentacionEmitida->value)
                ->latest('id')
                ->limit(5)
                ->get(),
            'programasSinPublicar' => $programas->where('estado', ProgramaEstado::Borrador)->count(),
        ]);
    }
}