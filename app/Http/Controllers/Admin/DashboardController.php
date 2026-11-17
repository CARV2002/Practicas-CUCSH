<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\EtapaConvenio;
use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\ConvenioSeguimiento;
use App\Models\Empresa;
use Illuminate\View\View;

/** Panel de Vinculación: solo el trámite de convenios (lo académico vive en coordinacion/). */
class DashboardController extends Controller
{
    public function index(): View
    {
        $cerrados = [ConvenioEstado::Formalizado->value, ConvenioEstado::Rechazado->value];

        // Etapas abiertas que exceden la espera documentada (cuellos de botella)
        $excedidas = ConvenioSeguimiento::with('convenio.empresa')
            ->abiertos()
            ->get()
            ->filter(fn($s) => $s->excedeEspera())
            ->sortByDesc(fn($s) => $s->diasHabiles())
            ->values();

        return view('admin.dashboard', [
            'kpis' => [
                'vigentes' => Convenio::vigentes()->count(),
                'en_tramite' => Convenio::whereNotIn('estado', $cerrados)->count(),
                'empresas' => Empresa::count(),
                'empresas_con_convenio' => Empresa::whereHas('convenios', fn($c) => $c->vigentes())->count(),
                'por_vencer_60' => Convenio::porVencer(60)->count(),
                'por_vencer_30' => Convenio::porVencer(30)->count(),
            ],
            'pendientes' => [
                'solicitudes' => Convenio::where('estado', ConvenioEstado::Solicitado->value)->count(),
                'dictamenes' => ConvenioCarrera::pendientes()
                    ->whereHas('convenio', fn($c) => $c->where('estado', ConvenioEstado::EnValidacionAcademica->value))
                    ->count(),
                'juridica' => ConvenioSeguimiento::abiertos()
                    ->where('etapa', EtapaConvenio::DictamenJuridico->value)->count(),
                'firmas' => ConvenioSeguimiento::abiertos()
                    ->whereIn('etapa', [EtapaConvenio::FirmaEmpresa->value, EtapaConvenio::FirmaRectoria->value])->count(),
            ],
            'cuellos' => $excedidas->take(5),
            'convenios_recientes' => Convenio::with('empresa')->latest('id')->limit(5)->get(),
            'convenios_por_vencer' => Convenio::with('empresa')
                ->porVencer(60)
                ->orderBy('fecha_fin_vigencia')
                ->limit(5)
                ->get(),
            'convenios_por_estado' => collect(ConvenioEstado::cases())->mapWithKeys(fn($e) => [
                $e->label() => Convenio::where('estado', $e->value)->count(),
            ]),
        ]);
    }
}
