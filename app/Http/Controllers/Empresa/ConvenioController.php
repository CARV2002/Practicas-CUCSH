<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Services\ConvenioWorkflow;
use App\Support\SolicitudEmpresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConvenioController extends Controller
{
    public function __construct(private ConvenioWorkflow $workflow)
    {
    }

    /** Convenio vigente, trámite en curso (con su bitácora) e historial. */
    public function show(Request $request): View
    {
        $empresa = $request->user()->empresa;

        $convenios = $empresa->convenios()
            ->with(['dictamenes.carrera', 'seguimientos'])
            ->latest('id')
            ->get();

        $vigente = $convenios->first(fn(Convenio $c) => $c->estaVigente());
        $enTramite = $convenios->first(fn(Convenio $c) => !$c->estado->esFinal());

        $diasRestantes = $vigente
            ? (int) now()->startOfDay()->diffInDays($vigente->fecha_fin_vigencia, false)
            : null;

        return view('empresa.convenio', [
            'empresa' => $empresa,
            'vigente' => $vigente,
            'enTramite' => $enTramite,
            'historial' => $convenios->filter(fn(Convenio $c) => $c->estado->esFinal() && $c->isNot($vigente)),
            'diasRestantes' => $diasRestantes,
            'puedeRenovar' => $vigente !== null && $enTramite === null && $diasRestantes <= 60,
            'puedeSolicitar' => $vigente === null && $enTramite === null,
            'carreras' => Carrera::with('division')->orderBy('nombre')->get(),
            'expedienteCompleto' => $empresa->expedienteEnOrden(),
            // Motivo por el que el botón de enviar aparece deshabilitado (null = se puede enviar)
            'motivoSolicitud' => \App\Support\Requisitos::empresaSolicitarConvenio($empresa),
            // Solicitud por secciones con borrador (procedimiento de la empresa, etapa 2)
            'faltantes' => \App\Support\SolicitudEmpresa::faltantes($empresa),
            'borrador' => $empresa->solicitud_borrador ?? [],
            'seccion' => $request->query('seccion'),
        ]);
    }

    /**
     * Guarda una sección de la solicitud (avance). Solo revisa el formato de lo capturado:
     * la empresa puede guardar aunque le falte un dato notarial y terminar después.
     */
    public function guardarSeccion(Request $request, string $seccion): RedirectResponse
    {
        abort_unless(in_array($seccion, ['generales', 'legales', 'perfiles'], true), 404);
        $empresa = $request->user()->empresa;

        if ($seccion === 'legales' && $empresa->documentosBloqueados()) {
            return back()->withErrors(['representante_legal' => 'Tu convenio está en revisión: no puedes cambiar tus declaraciones legales.']);
        }

        $request->merge(SolicitudEmpresa::normalizar($request->all()));
        $datos = $request->validate(
            SolicitudEmpresa::reglas($seccion, $empresa, false),
            SolicitudEmpresa::mensajes(),
            SolicitudEmpresa::atributos(),
        );

        if ($seccion === 'perfiles') {
            $empresa->update(['solicitud_borrador' => array_merge($empresa->solicitud_borrador ?? [], $datos, [
                'carreras' => array_map('intval', $datos['carreras'] ?? []),
            ])]);
        } else {
            $empresa->update(\Illuminate\Support\Arr::only(
                SolicitudEmpresa::paraGuardar($seccion, $datos, $empresa),
                [...SolicitudEmpresa::CAMPOS[$seccion], 'domicilio_fiscal']
            ));
        }

        $faltan = SolicitudEmpresa::faltantes($empresa->fresh())[$seccion];
        $siguiente = ['generales' => 'legales', 'legales' => 'perfiles', 'perfiles' => 'documentos'][$seccion];

        return redirect()->route('empresa.convenio', ['seccion' => $faltan ? $seccion : $siguiente])
            ->with('status', $faltan
                ? 'Avance guardado. A «' . SolicitudEmpresa::SECCIONES[$seccion] . '» le faltan ' . count($faltan) . ' dato(s); puedes terminarla después.'
                : '«' . SolicitudEmpresa::SECCIONES[$seccion] . '» quedó completa.');
    }

    /** Ruta anterior de «Datos legales»: hoy es la sección «Declaraciones legales» de la solicitud. */
    public function datosLegales(Request $request): RedirectResponse
    {
        return $this->guardarSeccion($request, 'legales');
    }

    /** Envía la solicitud (inicial o renovación) con lo guardado en las secciones. */
    public function solicitar(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;

        $faltan = collect(SolicitudEmpresa::faltantes($empresa))->filter();
        if ($faltan->isNotEmpty()) {
            throw ValidationException::withMessages(['convenio' => 'Completa antes: ' . $faltan->keys()
                ->map(fn ($s) => SolicitudEmpresa::SECCIONES[$s] . ' (' . count($faltan[$s]) . ')')->join(', ') . '.']);
        }

        // Mismas reglas que deshabilitan el botón en la vista (App\Support\Requisitos)
        if ($motivo = \App\Support\Requisitos::empresaSolicitarConvenio($empresa)) {
            throw ValidationException::withMessages(['convenio' => $motivo]);
        }

        $borrador = $empresa->solicitud_borrador;
        $this->workflow->solicitar($empresa, $borrador, $borrador['carreras']);
        $empresa->update(['solicitud_borrador' => null]);

        return redirect()->route('empresa.convenio')
            ->with('status', 'Solicitud enviada a Vinculación. Te avisaremos por correo en cada cambio de etapa.');
    }

    /**
     * Colonias y estado de un código postal (para llenar el domicilio). Se consulta un servicio público
     * de códigos postales y se guarda en caché; si no responde, la empresa escribe su domicilio a mano.
     */
    public function codigoPostal(string $cp): JsonResponse
    {
        abort_unless(preg_match('/^\d{5}$/', $cp) === 1, 422);

        $datos = Cache::remember("cp:{$cp}", now()->addDays(30), function () use ($cp) {
            try {
                $r = Http::timeout(5)->acceptJson()->get("https://api.zippopotam.us/mx/{$cp}");
            } catch (\Throwable) {
                return null;
            }
            if (!$r->successful()) {
                return ['colonias' => [], 'estado' => null];
            }
            $lugares = collect($r->json('places', []));

            return [
                'colonias' => $lugares->pluck('place name')->filter()->unique()->sort()->values()->all(),
                'estado' => self::entidad((string) ($lugares->first()['state'] ?? '')),
            ];
        });

        return response()->json($datos ?? ['colonias' => [], 'estado' => null, 'sin_servicio' => true]);
    }

    /** Nombre del estado tal como está en la lista del formulario. */
    private static function entidad(string $estado): ?string
    {
        $equivalencias = ['Distrito Federal' => 'Ciudad de México', 'México' => 'Estado de México', 'Coahuila de Zaragoza' => 'Coahuila',
            'Michoacán de Ocampo' => 'Michoacán', 'Veracruz de Ignacio de la Llave' => 'Veracruz'];
        $estado = $equivalencias[$estado] ?? $estado;

        return in_array($estado, SolicitudEmpresa::ENTIDADES, true) ? $estado : null;
    }
    /** Descarga del convenio firmado (solo propio y formalizado; disco privado). */
    public function descargar(Request $request, Convenio $convenio)
    {
        $this->authorize('descargarFirmado', $convenio);

        $archivo = $convenio->getMedia('convenio_firmado')->last();
        abort_unless($archivo !== null, 404);

        return $this->entregar($archivo);
    }
}
