<?php

declare(strict_types=1);

namespace App\Http\Controllers\Empresa;

use App\Enums\ConvenioEstado;
use App\Enums\EstadoDocumentoFisico;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\DocumentoFisico;
use App\Models\Empresa;
use App\Models\Practica;
use App\Services\ConvenioWorkflow;
use App\Support\Requisitos;
use App\Support\SolicitudEmpresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Mi convenio» de la organización: solicitud por secciones (con borrador), seguimiento, renovación y descarga.
 */
class ConvenioController extends Controller
{
    public function __construct(private ConvenioWorkflow $workflow) {}

    /** Convenio vigente, trámite en curso (con su bitácora) e historial. */
    public function show(Request $request): View
    {
        $empresa = $request->user()->empresa;

        $convenios = $empresa->convenios()
            ->with(['dictamenes.carrera', 'seguimientos'])
            ->latest('id')
            ->get();

        $vigente = $convenios->first(fn (Convenio $c) => $c->estaVigente());
        $enTramite = $convenios->first(fn (Convenio $c) => ! $c->estado->esFinal());

        $diasRestantes = $vigente
            ? (int) now()->startOfDay()->diffInDays($vigente->fecha_fin_vigencia, false)
            : null;

        $puedeRenovar = $vigente !== null && $enTramite === null && $diasRestantes <= Empresa::DIAS_RENOVACION;

        return view('empresa.convenio', [
            'empresa' => $empresa,
            // Juegos originales que la empresa tiene en su poder para firmar
            'juegosPendientes' => DocumentoFisico::where('estado', EstadoDocumentoFisico::ConEmpresa->value)
                ->whereHas('convenio', fn ($c) => $c->where('empresa_id', $empresa->id))
                ->get(),
            // Renovación: practicantes recibidos con el convenio que vence (precarga «Experiencia previa»)
            'recibidosVigente' => $puedeRenovar
                ? Practica::whereHas('postulacion.programa', fn ($q) => $q->where('convenio_id', $vigente->id))->count()
                : 0,
            'vigente' => $vigente,
            'enTramite' => $enTramite,
            'historial' => $convenios->filter(fn (Convenio $c) => $c->estado->esFinal() && $c->isNot($vigente)),
            'diasRestantes' => $diasRestantes,
            'puedeRenovar' => $puedeRenovar,
            'puedeSolicitar' => $vigente === null && $enTramite === null,
            'carreras' => Carrera::with('division')->orderBy('nombre')->get(),
            'expedienteCompleto' => $empresa->expedienteEnOrden(),
            // Motivo por el que el botón de enviar aparece deshabilitado (null = se puede enviar)
            'motivoSolicitud' => Requisitos::empresaSolicitarConvenio($empresa),
            // Solicitud por secciones con borrador (procedimiento de la empresa, etapa 2)
            'faltantes' => SolicitudEmpresa::faltantes($empresa),
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

        // En revisión o con convenio vigente (fuera de la renovación) la solicitud es de solo lectura: el formulario
        // ni se muestra, así que un envío directo se rechaza sin tocar nada
        if ($motivo = $empresa->motivoSolicitudBloqueada()) {
            throw ValidationException::withMessages(['convenio' => $motivo]);
        }

        // Pasos en orden: no se guarda una sección si la anterior sigue incompleta
        $faltantes = SolicitudEmpresa::faltantes($empresa);
        foreach (['generales', 'legales'] as $anterior) {
            if ($anterior === $seccion) {
                break;
            }
            if (! empty($faltantes[$anterior])) {
                throw ValidationException::withMessages(['convenio' => 'Primero completa «'.SolicitudEmpresa::SECCIONES[$anterior].'»: los pasos van en orden.']);
            }
        }

        return $this->guardar($request, $seccion, $empresa);
    }

    private function guardar(Request $request, string $seccion, Empresa $empresa): RedirectResponse
    {
        $request->merge(SolicitudEmpresa::normalizar($request->all()));
        $datos = $request->validate(
            SolicitudEmpresa::reglas($seccion, $empresa, false),
            SolicitudEmpresa::mensajes(),
            SolicitudEmpresa::atributos(),
        );

        if ($seccion === 'perfiles') {
            $empresa->update(['solicitud_borrador' => array_merge($empresa->solicitud_borrador ?? [], $datos, [
                'carreras' => array_map('intval', $datos['carreras'] ?? []),
                // Cuándo se corrigió: si fue después de un rechazo, el aviso rojo ya no se muestra
                'perfiles_guardado_at' => now()->toIso8601String(),
            ])]);
        } else {
            $empresa->update(Arr::only(
                SolicitudEmpresa::paraGuardar($seccion, $datos, $empresa),
                [...SolicitudEmpresa::CAMPOS[$seccion], 'domicilio_fiscal']
            ));
        }

        // Secciones revisadas en esta solicitud (en una renovación, todas deben revisarse antes de enviar)
        $borrador = $empresa->fresh()->solicitud_borrador ?? [];
        $borrador['revisadas'] = array_values(array_unique([...($borrador['revisadas'] ?? []), $seccion]));
        $empresa->update(['solicitud_borrador' => $borrador]);

        $faltan = SolicitudEmpresa::faltantes($empresa->fresh())[$seccion];
        $siguiente = ['generales' => 'legales', 'legales' => 'perfiles', 'perfiles' => 'documentos'][$seccion];

        return redirect()->route('empresa.convenio', ['seccion' => $faltan ? $seccion : $siguiente])
            ->with('status', $faltan
                ? 'Avance guardado. A «'.SolicitudEmpresa::SECCIONES[$seccion].'» le faltan '.count($faltan).' dato(s); puedes terminarla después.'
                : '«'.SolicitudEmpresa::SECCIONES[$seccion].'» quedó completa.');
    }

    /** Ruta anterior de «Datos legales»: hoy es la sección «Declaraciones legales» de la solicitud. */
    public function datosLegales(Request $request): RedirectResponse
    {
        // Mismas reglas que la sección: solo editable sin trámite en revisión ni convenio vigente (fuera de la renovación)
        return $this->guardarSeccion($request, 'legales');
    }

    /**
     * Corrige los datos que Vinculación devolvió: solo esos campos se reciben y se validan con las reglas de su
     * sección; lo demás de la solicitud sigue en solo lectura.
     */
    public function corregir(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;
        $convenio = $empresa->convenios()->where('estado', ConvenioEstado::Solicitado->value)->latest('id')->first();
        $pendientes = $convenio?->correccionesPendientes() ?? [];
        if ($convenio === null || $pendientes === []) {
            throw ValidationException::withMessages(['convenio' => 'Vinculación no te ha pedido corregir datos.']);
        }

        $request->merge(SolicitudEmpresa::normalizar($request->all()));
        $reglas = [];
        foreach ($pendientes as $campo => $c) {
            $reglas[$campo] = SolicitudEmpresa::reglas($c['seccion'], $empresa, true)[$campo] ?? ['required'];
        }
        $datos = $request->validate($reglas, SolicitudEmpresa::mensajes(), SolicitudEmpresa::atributos());

        $this->workflow->registrarCorreccion($convenio, $datos);

        return redirect()->route('empresa.convenio')->with('status', 'Corrección enviada. Avisamos a Vinculación para que la revise.');
    }

    /** Envía la solicitud (inicial o renovación) con lo guardado en las secciones. */
    public function solicitar(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;

        $faltan = collect(SolicitudEmpresa::faltantes($empresa))->filter();
        if ($faltan->isNotEmpty()) {
            throw ValidationException::withMessages(['convenio' => 'Completa antes: '.$faltan->keys()
                ->map(fn ($s) => SolicitudEmpresa::SECCIONES[$s].' ('.count($faltan[$s]).')')->join(', ').'.']);
        }

        // Mismas reglas que deshabilitan el botón en la vista (App\Support\Requisitos)
        if ($motivo = Requisitos::empresaSolicitarConvenio($empresa)) {
            throw ValidationException::withMessages(['convenio' => $motivo]);
        }

        $borrador = $empresa->solicitud_borrador;
        $this->workflow->solicitar($empresa, $borrador, $borrador['carreras']);
        $empresa->update(['solicitud_borrador' => null]);

        return redirect()->route('empresa.convenio')
            ->with('status', 'Solicitud enviada a Vinculación. Te avisaremos por correo en cada cambio de etapa.');
    }

    /** Descarga del convenio firmado (solo propio y formalizado; disco privado). */
    public function descargar(Request $request, Convenio $convenio): Response
    {
        $this->authorize('descargarFirmado', $convenio);

        $archivo = $convenio->getMedia('convenio_firmado')->last();
        abort_unless($archivo !== null, 404);

        return $this->entregar($archivo);
    }
}
