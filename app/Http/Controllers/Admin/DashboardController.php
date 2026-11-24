<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\EstadoDocumentoFisico;
use App\Enums\EtapaConvenio;
use App\Enums\PracticaEstado;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\ConvenioSeguimiento;
use App\Models\Division;
use App\Models\DocumentoFisico;
use App\Models\Empresa;
use App\Models\Practica;
use App\Support\Pendientes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Panel de Vinculación: solo el trámite de convenios (lo académico vive en coordinacion/). */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $porEstado = Convenio::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');
        $conteo = fn (ConvenioEstado $e) => (int) ($porEstado[$e->value] ?? 0);

        // Etapas abiertas con su avance contra el máximo documentado de días hábiles
        $abiertas = ConvenioSeguimiento::with('convenio.empresa')
            ->abiertos()
            ->get()
            ->map(function (ConvenioSeguimiento $s) {
                $max = $s->etapa->diasHabilesDocumentados()[1] ?? null;
                $dias = $s->diasHabiles();

                return (object) [
                    'seguimiento' => $s,
                    'dias' => $dias,
                    'max' => $max,
                    'avance' => $max ? min(100, (int) round($dias / max(1, $max) * 100)) : null,
                    'excede' => $s->excedeEspera(),
                    'por_vencer' => $max !== null && ! $s->excedeEspera() && $dias >= $max * 0.8,
                ];
            });

        $excedidasPorEstado = $abiertas->where('excede', true)
            ->groupBy(fn ($a) => $a->seguimiento->convenio->estado->value)
            ->map->count();

        // Atención prioritaria: primero lo que excede, luego lo más cercano a su límite
        $prioritarios = $abiertas
            ->sortByDesc(fn ($a) => [$a->excede ? 1 : 0, $a->avance ?? 0, $a->dias])
            ->take(6)
            ->values();

        return view('admin.dashboard', [
            'nombre' => explode(' ', trim($request->user()->name))[0],
            // Mismo total que «Por atender» de la campana
            'totalPendientes' => Pendientes::total($request->user()),
            'esAdministrador' => $esAdmin = $request->user()->hasRole(Rol::Administrador->value),
            // Panorama global (solo lectura) para la Coordinación de Servicios Académicos
            'panorama' => $esAdmin ? $this->panorama() : null,
            'etapas' => [
                ['estado' => ConvenioEstado::Solicitado, 'etiqueta' => 'En revisión de documentos', 'icono' => 'documento', 'tono' => 'cielo'],
                ['estado' => ConvenioEstado::EnValidacionAcademica, 'etiqueta' => 'En dictamen por carrera', 'icono' => 'academico', 'tono' => 'lavanda'],
                ['estado' => ConvenioEstado::EnRevisionJuridica, 'etiqueta' => 'En Unidad Jurídica', 'icono' => 'juridico', 'tono' => 'indigo'],
                ['estado' => ConvenioEstado::EnFirmas, 'etiqueta' => 'Por firmar', 'icono' => 'firma', 'tono' => 'ambar'],
            ],
            'conteo' => collect(ConvenioEstado::cases())->mapWithKeys(fn ($e) => [$e->value => $conteo($e)])->all(),
            'excedidasPorEstado' => $excedidasPorEstado,
            'kpis' => [
                'vigentes' => Convenio::vigentes()->count(),
                'en_tramite' => $conteo(ConvenioEstado::Solicitado) + $conteo(ConvenioEstado::EnValidacionAcademica)
                    + $conteo(ConvenioEstado::EnRevisionJuridica) + $conteo(ConvenioEstado::EnFirmas),
                'empresas' => Empresa::count(),
                'empresas_con_convenio' => Empresa::whereHas('convenios', fn ($c) => $c->vigentes())->count(),
                'por_vencer_60' => Convenio::porVencer(60)->count(),
                'por_vencer_30' => Convenio::porVencer(30)->count(),
                'excedidas' => $abiertas->where('excede', true)->count(),
            ],
            'pendientes' => [
                'dictamenes' => ConvenioCarrera::pendientes()
                    ->whereHas('convenio', fn ($c) => $c->where('estado', ConvenioEstado::EnValidacionAcademica->value))
                    ->count(),
                'juridica' => ConvenioSeguimiento::abiertos()
                    ->where('etapa', EtapaConvenio::DictamenJuridico->value)->count(),
            ],
            'prioritarios' => $prioritarios,
            'fisicos' => [
                'fuera' => DocumentoFisico::deTramiteVivo()->fuera()->count(),
                'vencidos' => DocumentoFisico::deTramiteVivo()->vencidos()->count(),
                'por_imprimir' => DocumentoFisico::deTramiteVivo()->where('estado', EstadoDocumentoFisico::PorElaborar->value)->count(),
            ],
            'convenios_recientes' => Convenio::with('empresa')->latest('id')->limit(5)->get(),
            'convenios_por_vencer' => Convenio::with('empresa')
                ->porVencer(60)
                ->orderBy('fecha_fin_vigencia')
                ->limit(5)
                ->get(),
            'convenios_por_estado' => collect(ConvenioEstado::cases())->map(fn ($e) => [
                'estado' => $e,
                'total' => $conteo($e),
            ]),
        ]);
    }

    /**
     * Métricas de todo el CUCSH para el administrador: alumnos y prácticas por división y estado de las coordinaciones.
     *
     * @return array{alumnos:int, activas:int, acreditadas:int, en_cierre:int, bajas:int, divisiones:Collection, sin_coordinacion:Collection}
     */
    private function panorama(): array
    {
        $porEstado = Practica::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $n = fn (PracticaEstado $e) => (int) ($porEstado[$e->value] ?? 0);

        $activas = Practica::query()
            ->whereIn('practicas.estado', PracticaEstado::valores(PracticaEstado::activas()))
            ->join('postulaciones', 'postulaciones.id', '=', 'practicas.postulacion_id')
            ->join('alumnos', 'alumnos.id', '=', 'postulaciones.alumno_id')
            ->join('carreras', 'carreras.id', '=', 'alumnos.carrera_id')
            ->selectRaw('carreras.division_id, count(*) as total')->groupBy('carreras.division_id')->pluck('total', 'carreras.division_id');
        $alumnos = Alumno::query()->join('carreras', 'carreras.id', '=', 'alumnos.carrera_id')
            ->selectRaw('carreras.division_id, count(*) as total')->groupBy('carreras.division_id')->pluck('total', 'carreras.division_id');

        $carreras = Carrera::with('division')->orderBy('nombre')->get();

        return [
            'alumnos' => Alumno::count(),
            'activas' => $n(PracticaEstado::EnCurso),
            'acreditadas' => $n(PracticaEstado::Acreditada),
            'en_cierre' => $n(PracticaEstado::EnCierre),
            'bajas' => $n(PracticaEstado::Cancelada),
            'divisiones' => Division::withCount('carreras')->orderBy('nombre')->get()->map(fn ($d) => (object) [
                'nombre' => $d->nombre,
                'carreras' => $d->carreras_count,
                'alumnos' => (int) ($alumnos[$d->id] ?? 0),
                'activas' => (int) ($activas[$d->id] ?? 0),
            ]),
            'sin_coordinacion' => $carreras->filter(fn ($c) => $c->coordinadores()->isEmpty()),
        ];
    }
}
