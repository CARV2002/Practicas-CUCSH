<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\SeguimientoEstado;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioSeguimiento;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Services\Auditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InformeController extends Controller
{
    public function index(Request $request): View
    {
        $this->validar($request);

        $personal = $request->user()->personal;
        $ids = $personal->carreraIdsEnAlcance();
        $completo = $this->esAdministrador($request);

        // --- Resumen de prácticas: es información académica, solo la ve el administrador
        // (Vinculación no opera el proceso del alumno; cada coordinación lo ve en su panel)
        $practicas = $completo ? $this->practicas($request, $personal)->get() : collect();

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

    public function exportar(Request $request, string $tipo): StreamedResponse
    {
        // Vinculación solo exporta convenios; prácticas, postulaciones y programas son del ámbito académico
        abort_unless(in_array($tipo, ['convenios', 'empresas'], true) || $this->esAdministrador($request), 403);

        $this->validar($request);

        $personal = $request->user()->personal;

        [$encabezados, $consulta, $fila] = match ($tipo) {
            'practicas' => $this->exportarPracticas($request, $personal),
            'postulaciones' => $this->exportarPostulaciones($request, $personal),
            'convenios' => $this->exportarConvenios($request, $personal),
            'programas' => $this->exportarProgramas($request, $personal),
            'empresas' => $this->exportarEmpresas($request),
        };

        Auditoria::registrar(
            'informe.exportado',
            "Exportó el informe de {$tipo} (".(clone $consulta)->count().' filas)',
            cambios: ['filtros' => $request->only(['carrera_id', 'estado', 'ciclo', 'desde', 'hasta'])],
            tipo: 'Informe',
        );

        return response()->streamDownload(function () use ($encabezados, $consulta, $fila) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");   // BOM: Excel respeta los acentos
            fputcsv($salida, $encabezados, ',', '"', '');

            foreach ($consulta->lazyById(200) as $modelo) {
                $valores = $fila($modelo);
                if ($valores === null) {
                    continue;   // filtro calculado (p. ej. situación de la empresa)
                }
                fputcsv($salida, array_map([$this, 'limpiar'], $valores), ',', '"', '');
            }

            fclose($salida);
        }, $tipo.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ----------------------------- Consultas ----------------------------- */

    private function practicas(Request $request, Personal $personal): Builder
    {
        return Practica::with(['postulacion.alumno.user', 'postulacion.alumno.carrera', 'postulacion.programa.convenio.empresa', 'supervisor'])
            ->conHorasAcumuladas()
            ->visiblesPara($personal)
            ->when($request->filled('carrera_id'), fn ($q) => $q->whereHas('postulacion.alumno', fn ($a) => $a->where('carrera_id', $request->integer('carrera_id'))))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')->toString()))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_inicio', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_inicio', '<=', $request->date('hasta')));
    }

    /**
     * Empresas con su situación frente al CUCSH (la misma que usa el PDF de la bandeja).
     * El filtro «estado» acepta una clave de Empresa::SITUACIONES.
     *
     * @return array{0:string[],1:Builder,2:callable}
     */
    private function exportarEmpresas(Request $request): array
    {
        $situacion = $request->string('estado')->toString();
        $filtrar = isset(\App\Models\Empresa::SITUACIONES[$situacion]);

        return [
            ['Razón social', 'RFC', 'Tipo de organismo', 'Situación', 'Detalle', 'Representante legal', 'Cargo',
                'Contacto', 'Correo', 'Teléfono', 'Domicilio legal', 'Solicitudes', 'Registrada'],
            \App\Models\Empresa::with(['user', 'convenios'])->withCount('convenios')->orderBy('razon_social'),
            function (\App\Models\Empresa $e) use ($filtrar, $situacion) {
                $s = $e->situacion();
                if ($filtrar && $s['clave'] !== $situacion) {
                    return null;
                }

                return [
                    $e->razon_social, $e->rfc, $e->tipo_organismo?->label(), $s['texto'], $s['detalle'],
                    $e->representante_legal, $e->representante_cargo, $e->contacto_nombre, $e->user?->email,
                    $e->contacto_telefono, $e->domicilio_fiscal, $e->convenios_count, $e->created_at?->format('Y-m-d'),
                ];
            },
        ];
    }

    /** @return array{0:string[],1:Builder,2:callable} */
    private function exportarPracticas(Request $request, Personal $personal): array
    {
        return [
            ['Código de alumno', 'Alumno', 'Carrera', 'Empresa o proyecto', 'Programa', 'Ciclo', 'Inicio', 'Fin',
                'Horas validadas', 'Horas requeridas', 'Avance %', 'Estado', 'Supervisor'],
            $this->practicas($request, $personal),
            function (Practica $p) {
                $al = $p->postulacion->alumno;
                $pr = $p->postulacion->programa;
                $validadas = (int) $p->horas_acumuladas;

                return [
                    $al->num_control, $al->user->name, $al->carrera->carrera_clave,
                    $pr->convenio?->empresa->razon_social ?? 'Proyecto interno', $pr->titulo, $pr->ciclo,
                    $p->fecha_inicio->format('Y-m-d'), $p->fecha_fin->format('Y-m-d'),
                    $validadas, $p->horas_requeridas,
                    $p->horas_requeridas > 0 ? (int) round($validadas / $p->horas_requeridas * 100) : 0,
                    $p->estado->label(), $p->supervisor?->nombre,
                ];
            },
        ];
    }

    /** @return array{0:string[],1:Builder,2:callable} */
    private function exportarPostulaciones(Request $request, Personal $personal): array
    {
        $consulta = Postulacion::with(['alumno.user', 'alumno.carrera', 'programa.convenio.empresa'])
            ->visiblesPara($personal)
            ->when($request->filled('carrera_id'), fn ($q) => $q->whereHas('alumno', fn ($a) => $a->where('carrera_id', $request->integer('carrera_id'))))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')->toString()))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_postulacion', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_postulacion', '<=', $request->date('hasta')));

        return [
            ['Fecha', 'Código de alumno', 'Alumno', 'Carrera', 'Programa', 'Empresa o proyecto', '% créditos declarado', 'Estado'],
            $consulta,
            fn (Postulacion $po) => [
                $po->fecha_postulacion->format('Y-m-d'), $po->alumno->num_control, $po->alumno->user->name,
                $po->alumno->carrera->carrera_clave, $po->programa->titulo,
                $po->programa->convenio?->empresa->razon_social ?? 'Proyecto interno',
                (float) $po->porcentaje_creditos_declarado, $po->estado->label(),
            ],
        ];
    }

    /** @return array{0:string[],1:Builder,2:callable} */
    private function exportarConvenios(Request $request, Personal $personal): array
    {
        $consulta = Convenio::with(['empresa', 'dictamenes.carrera'])
            ->visiblesPara($personal)
            ->when($request->filled('carrera_id'), fn ($q) => $q->whereHas('dictamenes', fn ($d) => $d->where('carrera_id', $request->integer('carrera_id'))))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')->toString()))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_recepcion', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_recepcion', '<=', $request->date('hasta')));

        return [
            ['Folio', 'Empresa', 'RFC', 'Tipo de organismo', 'Estado', 'Recibido', 'Inicio de vigencia', 'Fin de vigencia',
                'Días para vencer', 'Carreras aprobadas', 'Plazas estimadas'],
            $consulta,
            fn (Convenio $c) => [
                $c->folio, $c->empresa->razon_social, $c->empresa->rfc, $c->empresa->tipo_organismo->label(), $c->estado->label(),
                $c->fecha_recepcion->format('Y-m-d'), $c->fecha_inicio_vigencia?->format('Y-m-d'), $c->fecha_fin_vigencia?->format('Y-m-d'),
                $c->estaVigente() ? (int) now()->startOfDay()->diffInDays($c->fecha_fin_vigencia, false) : null,
                $c->dictamenes->filter(fn ($d) => $d->dictamen === Dictamen::Aprobatorio)->map(fn ($d) => $d->carrera->carrera_clave)->join(' '),
                $c->plazas_estimadas,
            ],
        ];
    }

    /** @return array{0:string[],1:Builder,2:callable} */
    private function exportarProgramas(Request $request, Personal $personal): array
    {
        $ids = $personal->carreraIdsEnAlcance();

        $consulta = Programa::with(['convenio.empresa', 'carrera'])
            ->conCuposOcupados()
            ->when($ids !== null, fn ($q) => $q->whereIn('carrera_id', $ids))
            ->when($request->filled('carrera_id'), fn ($q) => $q->where('carrera_id', $request->integer('carrera_id')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')->toString()))
            ->when($request->filled('ciclo'), fn ($q) => $q->where('ciclo', $request->string('ciclo')->toString()));

        return [
            ['Programa', 'Empresa o proyecto', 'Carrera', 'Ciclo', 'Modalidad', 'Estado', 'Cupos totales', 'Cupos ocupados', 'Cupos disponibles', 'Sede', 'Horario'],
            $consulta,
            fn (Programa $pr) => [
                $pr->titulo, $pr->convenio?->empresa->razon_social ?? 'Proyecto interno', $pr->carrera->carrera_clave, $pr->ciclo,
                ucfirst($pr->modalidad->value), ucfirst($pr->estado->value),
                $pr->cupos_totales, (int) $pr->cupos_ocupados, $pr->cupos_disponibles, $pr->sede, $pr->horario,
            ],
        ];
    }

    /* ------------------------------- Helpers ------------------------------- */

    private function esAdministrador(Request $request): bool
    {
        return $request->user()->hasRole(\App\Enums\Rol::Administrador->value);
    }

    private function validar(Request $request): void
    {
        $request->validate([
            'carrera_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string', 'max:30'],
            'ciclo' => ['nullable', 'regex:/^\d{4}[AB]$/'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);
    }

    /** Neutraliza fórmulas: un texto que empiece con = + - @ no debe ejecutarse al abrirlo en Excel. */
    private function limpiar(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}