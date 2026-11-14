<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Services\ConvenioWorkflow;
use Illuminate\Http\RedirectResponse;
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
            'expedienteCompleto' => $empresa->expedienteCompleto(),
        ]);
    }

    /** Solicitud inicial o renovación (se permite renovar 60 días antes del vencimiento). */
    public function solicitar(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;
        $convenios = $empresa->convenios()->get();

        if ($convenios->contains(fn(Convenio $c) => !$c->estado->esFinal())) {
            throw ValidationException::withMessages(['convenio' => 'Ya tienes una solicitud de convenio en trámite.']);
        }

        $vigente = $convenios->first(fn(Convenio $c) => $c->estaVigente());

        if ($vigente && now()->diffInDays($vigente->fecha_fin_vigencia, false) > 60) {
            throw ValidationException::withMessages([
                'convenio' => 'Tu convenio sigue vigente; podrás renovarlo 60 días antes de su vencimiento.',
            ]);
        }

        $datos = $request->validate([
            'objetivos' => ['required', 'string', 'max:3000'],
            'actividades_propuestas' => ['required', 'string', 'max:3000'],
            'plazas_estimadas' => ['required', 'integer', 'min:1', 'max:500'],
            'medio_conocimiento' => ['nullable', 'string', 'max:255'],
            'experiencia_previa' => ['nullable', 'string', 'max:3000'],
            'carreras' => ['required', 'array', 'min:1'],
            'carreras.*' => ['integer', Rule::exists('carreras', 'id')],
        ], [
            'carreras.required' => 'Selecciona al menos una carrera.',
        ]);

        $this->workflow->solicitar($empresa, $datos, $datos['carreras']);

        return redirect()->route('empresa.convenio')
            ->with('status', 'Solicitud enviada. Te avisaremos en cada cambio de etapa.');
    }

    /** Descarga del convenio firmado (solo propio y formalizado; disco privado). */
    public function descargar(Request $request, Convenio $convenio)
    {
        $this->authorize('descargarFirmado', $convenio);

        $archivo = $convenio->getMedia('convenio_firmado')->last();
        abort_unless($archivo !== null, 404);

        return $archivo->toResponse($request);
    }
}
