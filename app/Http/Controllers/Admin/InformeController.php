<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\EtapaConvenio;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\Rol;
use App\Enums\SeguimientoEstado;
use App\Http\Controllers\Controller;
use App\Http\Requests\FiltrosInformeRequest;
use App\Informes\Informe;
use App\Informes\InformePracticas;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioSeguimiento;
use App\Models\Practica;
use App\Models\Programa;
use App\Services\Auditoria;
use App\Services\ExportadorCsv;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Informes: resumen en pantalla y exportación a CSV de cada informe (App\Informes).
 */
class InformeController extends Controller
{
    /** Resumen de prácticas por carrera, estado de los convenios y tiempos reales de cada etapa. */
    public function index(FiltrosInformeRequest $request): View
    {

        $personal = $request->user()->personal;
        $ids = $personal->carreraIdsEnAlcance();
        $completo = $this->esAdministrador($request);

        // --- Resumen de prácticas: es información académica, solo la ve el administrador
        // (Vinculación no opera el proceso del alumno; cada coordinación lo ve en su panel)
        $practicas = $completo ? (new InformePracticas($request, $personal))->consulta()->get() : collect();

        $porCarrera = $practicas
            ->groupBy(fn (Practica $p) => $p->postulacion->alumno->carrera_id)
            ->map(function ($grupo) {
                $carrera = $grupo->first()->postulacion->alumno->carrera;
                $requeridas = (int) $grupo->sum('horas_requeridas');
                $validadas = (int) $grupo->sum(fn (Practica $p) => (int) $p->horas_acumuladas - (int) $p->horas_reconocidas);

                return [
                    'clave' => $carrera->carrera_clave,
                    'nombre' => $carrera->nombre,
                    'total' => $grupo->count(),
                    'activas' => $grupo->filter(fn (Practica $p) => in_array($p->estado, PracticaEstado::activas(), true))->count(),
                    'acreditadas' => $grupo->filter(fn (Practica $p) => $p->estado === PracticaEstado::Acreditada)->count(),
                    'canceladas' => $grupo->filter(fn (Practica $p) => $p->estado === PracticaEstado::Cancelada)->count(),
                    'avance' => $requeridas > 0 ? (int) round($validadas / $requeridas * 100) : 0,
                ];
            })
            ->sortBy('nombre')
            ->values();

        $resumen = [
            'total' => $practicas->count(),
            'activas' => $porCarrera->sum('activas'),
            'acreditadas' => $porCarrera->sum('acreditadas'),
            'horas' => (int) $practicas->sum(fn (Practica $p) => (int) $p->horas_acumuladas - (int) $p->horas_reconocidas),
        ];

        // --- Convenios
        $convenios = fn () => Convenio::visiblesPara($personal);

        $conveniosResumen = [
            'vigentes' => $convenios()->vigentes()->count(),
            'por_vencer' => $convenios()->porVencer(60)->count(),
            'en_tramite' => $convenios()->whereNotIn('estado', [ConvenioEstado::Formalizado->value, ConvenioEstado::Rechazado->value])->count(),
        ];

        // --- Cuánto tarda de verdad cada etapa (etapas ya cerradas) frente al máximo documentado
        $cerradas = ConvenioSeguimiento::query()
            ->where('estado', '!=', SeguimientoEstado::Pendiente->value)
            ->whereNotNull('fecha_resolucion')
            ->when($ids !== null, fn ($q) => $q->whereHas('convenio', fn ($c) => $c->visiblesPara($personal)))
            ->get()
            ->groupBy(fn (ConvenioSeguimiento $s) => $s->etapa->value);

        $etapas = collect(EtapaConvenio::cases())
            ->filter(fn (EtapaConvenio $e) => $cerradas->has($e->value))
            ->map(fn (EtapaConvenio $e) => [
                'etapa' => $e->label(),
                'responsable' => $e->responsable(),
                'casos' => $cerradas[$e->value]->count(),
                'promedio' => round($cerradas[$e->value]->avg(fn (ConvenioSeguimiento $s) => $s->diasHabiles()), 1),
                'maximo' => $e->diasHabilesDocumentados()[1] ?? null,
            ])
            ->values();

        $nombres = fn (array $casos) => collect($casos)->mapWithKeys(fn ($e) => [
            $e->value => method_exists($e, 'label') ? $e->label() : ucfirst($e->value),
        ])->all();

        return view('admin.informes', [
            'completo' => $completo,
            'resumen' => $resumen,
            'porCarrera' => $porCarrera,
            'conveniosResumen' => $conveniosResumen,
            'etapas' => $etapas,
            'carreras' => Carrera::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'ciclos' => Programa::distinct()->orderByDesc('ciclo')->pluck('ciclo'),
            'estados' => [
                'practicas' => $nombres(PracticaEstado::cases()),
                'postulaciones' => $nombres(PostulacionEstado::cases()),
                'convenios' => $nombres(ConvenioEstado::cases()),
                'programas' => $nombres(ProgramaEstado::cases()),
            ],
        ]);
    }

    /** CSV de un informe (App\Informes\*), filtrado igual que la pantalla y limitado al alcance de quien lo pide. */
    public function exportar(FiltrosInformeRequest $request, string $tipo, ExportadorCsv $exportador): StreamedResponse
    {
        abort_unless(in_array($tipo, Informe::DE_VINCULACION, true) || $this->esAdministrador($request), 403);

        $informe = Informe::para($tipo, $request, $request->user()->personal);

        Auditoria::registrar(
            'informe.exportado',
            "Exportó el informe de {$tipo} (".$informe->consulta()->count().' filas)',
            cambios: ['filtros' => $request->only(['carrera_id', 'estado', 'ciclo', 'desde', 'hasta', 'vista', 'q'])],
            tipo: 'Informe',
        );

        return $exportador->descargar($informe, $tipo);
    }

    private function esAdministrador(Request $request): bool
    {
        return $request->user()->hasRole(Rol::Administrador->value);
    }
}
