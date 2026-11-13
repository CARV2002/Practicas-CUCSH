<?php

namespace App\Http\Controllers;

use App\Enums\EtapaConvenio;
use App\Enums\PracticaEstado;
use App\Enums\TipoOrganismo;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Practica;
use App\Models\Programa;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Páginas públicas informativas. Los datos salen de la base de datos y de los
 * enums del dominio para que lo que se muestra coincida con lo que realmente
 * opera el sistema.
 */
class HomeController extends Controller
{
    /** Máximo de organizaciones que se muestran en la cuadrícula de inicio. */
    private const ORGANIZACIONES_VISIBLES = 6;

    /** Máximo de organizaciones que se muestran en la franja de la página de empresas. */
    private const ORGANIZACIONES_EN_FRANJA = 5;

    public function inicio(): View
    {
        $vigentes = $this->conveniosVigentes();

        return view('pages.home.inicio', [
            'organizaciones' => $vigentes->take(self::ORGANIZACIONES_VISIBLES)->pluck('empresa'),
            'totalOrganizaciones' => $vigentes->count(),
            'programasAbiertos' => Programa::abiertos()->count(),
            'totalCarreras' => Carrera::count(),
        ]);
    }

    public function estudiantes(): View
    {
        return view('pages.home.estudiantes', [
            'divisiones' => $this->divisionesConCarreras(),
            'organizaciones' => $this->conveniosVigentes()->count(),
            'programasAbiertos' => Programa::abiertos()->count(),
            ...$this->conteosDePracticas(),
        ]);
    }

    public function empresas(): View
    {
        $vigentes = $this->conveniosVigentes();

        return view('pages.home.empresas', [
            'organizaciones' => $vigentes->take(self::ORGANIZACIONES_EN_FRANJA)->pluck('empresa'),
            'totalOrganizaciones' => $vigentes->count(),
            'programasAbiertos' => Programa::abiertos()->count(),
            'divisiones' => $this->divisionesConCarreras(),
            // Mismos valores que acepta el registro de empresa.
            'tiposOrganismo' => collect(TipoOrganismo::cases())->map(fn (TipoOrganismo $t) => $t->label())->all(),
            // Plazos documentados por etapa del convenio, en días hábiles [mínimo, máximo].
            'plazos' => collect(EtapaConvenio::cases())
                ->mapWithKeys(fn (EtapaConvenio $e) => [$e->value => $e->diasHabilesDocumentados()])
                ->all(),
            ...$this->conteosDePracticas(),
        ]);
    }

    /**
     * Convenios formalizados y vigentes hoy, de empresas con cuenta activa;
     * uno por empresa, los más recientes primero.
     */
    private function conveniosVigentes(): Collection
    {
        return Convenio::vigentes()
            ->whereHas('empresa.user', fn ($q) => $q->where('activo', true))
            ->with('empresa')
            ->latest('fecha_inicio_vigencia')
            ->get()
            ->unique('empresa_id');
    }

    /** Divisiones con sus carreras (horas y % de créditos que valida el sistema). */
    private function divisionesConCarreras(): Collection
    {
        return Division::with(['carreras' => fn ($q) => $q->orderBy('nombre')])
            ->orderBy('id')
            ->get()
            ->filter(fn (Division $d) => $d->carreras->isNotEmpty());
    }

    /** @return array{practicasEnCurso:int, practicasAcreditadas:int} */
    private function conteosDePracticas(): array
    {
        return [
            'practicasEnCurso' => Practica::where('estado', PracticaEstado::EnCurso->value)->count(),
            'practicasAcreditadas' => Practica::where('estado', PracticaEstado::Acreditada->value)->count(),
        ];
    }
}
