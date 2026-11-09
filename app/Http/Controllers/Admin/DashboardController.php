<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\SeguimientoEstado;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\ConvenioSeguimiento;
use App\Models\Empresa;
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
        $personal = $request->user()->personal;

        // Ocupación de plazas: cupos ocupados / cupos totales de programas publicados
        $publicados = Programa::conCuposOcupados()
            ->where('estado', ProgramaEstado::Publicado->value)
            ->get(['id', 'cupos_totales']);

        $totalCupos = (int) $publicados->sum('cupos_totales');
        $ocupados = (int) $publicados->sum('cupos_ocupados');

        // Etapas abiertas que exceden la espera documentada (cuellos de botella)
        $excedidas = ConvenioSeguimiento::with('convenio.empresa')
            ->abiertos()
            ->get()
            ->filter(fn ($s) => $s->excedeEspera())
            ->sortByDesc(fn ($s) => $s->diasHabiles())
            ->values();

        return view('admin.dashboard', [
            'kpis' => [
                'alumnos' => Alumno::count(),
                'practicas_activas' => Practica::activas()->count(),
                'empresas' => Empresa::count(),
                'empresas_con_convenio' => Empresa::whereHas('convenios', fn ($c) => $c->vigentes())->count(),
                'por_vencer_60' => Convenio::porVencer(60)->count(),
                'por_vencer_30' => Convenio::porVencer(30)->count(),
                'ocupacion' => $totalCupos > 0 ? (int) round($ocupados / $totalCupos * 100) : 0,
                'cupos_ocupados' => $ocupados,
                'cupos_totales' => $totalCupos,
            ],
            'pendientes' => [
                'convenios_en_tramite' => Convenio::whereNotIn('estado', [
                    ConvenioEstado::Formalizado->value,
                    ConvenioEstado::Rechazado->value,
                ])->count(),
                'solicitudes' => Convenio::where('estado', ConvenioEstado::Solicitado->value)->count(),
                'dictamenes' => ConvenioCarrera::pendientes()
                    ->whereHas('convenio', fn ($c) => $c->where('estado', ConvenioEstado::EnValidacionAcademica->value))
                    ->count(),
                'juridica' => ConvenioSeguimiento::abiertos()
                    ->where('etapa', EtapaConvenio::DictamenJuridico->value)->count(),
                'firmas' => ConvenioSeguimiento::abiertos()
                    ->whereIn('etapa', [EtapaConvenio::FirmaEmpresa->value, EtapaConvenio::FirmaRectoria->value])->count(),
                'postulaciones' => Postulacion::visiblesPara($personal)
                    ->where('estado', PostulacionEstado::Pendiente->value)->count(),
                'reportes' => Reporte::where('estado', ReporteEstado::Pendiente->value)
                    ->whereHas('practica', fn ($p) => $p->visiblesPara($personal))->count(),
            ],
            'cuellos' => $excedidas->take(5),
            'convenios_recientes' => Convenio::with('empresa')->latest('id')->limit(5)->get(),
            'convenios_por_vencer' => Convenio::with('empresa')
                ->porVencer(60)
                ->orderBy('fecha_fin_vigencia')
                ->limit(5)
                ->get(),
            'programas_recientes' => Programa::with(['convenio.empresa', 'carrera'])
                ->conCuposOcupados()
                ->latest('id')
                ->limit(5)
                ->get(),
            'practicas_por_estado' => collect(PracticaEstado::cases())->mapWithKeys(fn ($e) => [
                $e->label() => Practica::where('estado', $e->value)->count(),
            ]),
        ]);
    }
}
