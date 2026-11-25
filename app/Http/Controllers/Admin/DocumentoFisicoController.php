<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\EstadoDocumentoFisico;
use App\Enums\TipoDocumentoFisico;
use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\DocumentoFisico;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Control de los papeles del trámite híbrido: dónde está cada oficio, dictamen y juego
 * firmado, desde cuándo y cuándo debe regresar.
 */
class DocumentoFisicoController extends Controller
{
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;
        $vista = $request->string('vista')->toString() ?: 'activos';
        $q = $request->string('q')->toString();

        // El oficio de turno académico ya no se rastrea en papel (el turno a las carreras es digital)
        $base = DocumentoFisico::query()
            ->where('tipo', '!=', TipoDocumentoFisico::OficioTurno->value)
            ->whereHas('convenio', fn ($c) => $c->visiblesPara($personal));

        $conteos = [
            'activos' => (clone $base)->activos()->count(),
            'fuera' => (clone $base)->fuera()->count(),
            'vencidos' => (clone $base)->vencidos()->count(),
            'casa' => (clone $base)->activos()->whereNotIn('estado', EstadoDocumentoFisico::fuera())->count(),
            'concluidos' => (clone $base)->whereIn('estado', EstadoDocumentoFisico::concluidos())->count(),
        ];

        $documentos = (clone $base)
            ->with(['convenio.empresa', 'actualizador.user', 'movimientos.registrador.user'])
            ->enVista($vista, $q)
            ->orderByRaw("CASE tipo WHEN 'oficio_turno' THEN 1 WHEN 'dictamen_juridico' THEN 2 ELSE 3 END")
            ->orderBy('juego')
            ->get()
            ->groupBy('convenio_id');

        // Convenios en trámite que aún no tienen su control físico (p. ej. anteriores a esta función)
        $sinControl = Convenio::visiblesPara($personal)
            ->with('empresa')
            ->whereIn('estado', [
                ConvenioEstado::EnRevisionJuridica->value, ConvenioEstado::EnFirmas->value, ConvenioEstado::Formalizado->value,
            ])
            ->whereDoesntHave('documentosFisicos')
            ->latest('id')
            ->limit(12)
            ->get();

        // Todos los papeles fuera de la oficina con fecha de regreso: alimentan la lista y el calendario
        $fuera = (clone $base)->fuera()->deTramiteVivo()->with('convenio.empresa')
            ->whereNotNull('fecha_compromiso')
            ->orderBy('fecha_compromiso')
            ->get();

        $calendario = $fuera->map(fn (DocumentoFisico $d) => [
            'fecha' => $d->fecha_compromiso->toDateString(),
            'papel' => $d->nombre(),
            'empresa' => $d->convenio->empresa->razon_social,
            'custodio' => $d->estado->custodio(),
            'estado' => $d->estado->label(),
            'tono' => $d->vencido() ? 'vencido' : ($d->porVencer() ? 'pronto' : 'normal'),
            'url' => route('admin.convenios.show', $d->convenio_id),
        ])->values();

        return view('admin.documentos-fisicos', [
            'grupos' => $documentos,
            'conteos' => $conteos,
            'vista' => $vista,
            'sinControl' => $sinControl,
            'proximos' => $fuera->take(6),
            'calendario' => $calendario,
        ]);
    }

    public function mover(Request $request, DocumentoFisico $documento): RedirectResponse
    {
        $this->authorize('gestionar', $documento->convenio);

        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_map(fn ($e) => $e->value, $documento->tipo->estados()))],
            'fecha' => ['nullable', 'date', 'before_or_equal:today'],
            'fecha_compromiso' => ['nullable', 'date', 'after_or_equal:fecha'],
            'nota' => ['nullable', 'string', 'max:1000'],
            'destino' => ['nullable', 'string', 'max:255'],
            'juntos' => ['nullable', 'boolean'],
        ], [
            'fecha_compromiso.after_or_equal' => 'La fecha de regreso no puede ser anterior al movimiento.',
        ]);

        $estado = EstadoDocumentoFisico::from($datos['estado']);

        if ($bloqueo = $documento->bloqueoPara($estado)) {
            return back()->withErrors(['estado' => $bloqueo])->withInput();
        }

        if (in_array($estado, [EstadoDocumentoFisico::ConObservaciones, EstadoDocumentoFisico::Extraviado], true) && blank($datos['nota'] ?? null)) {
            return back()->withErrors(['nota' => 'Explica brevemente qué pasó con el documento.'])->withInput();
        }

        if (array_key_exists('destino', $datos) && $documento->juego) {
            $documento->update(['destino' => $datos['destino']]);
        }

        // Los 3 juegos viajan juntos (a firma de la empresa, luego a Rectoría): con «juntos» se mueven también
        // los juegos de este convenio que están en el mismo lugar. El destino de cada juego no se toca.
        $papeles = collect([$documento]);
        if ($request->boolean('juntos') && $documento->juego) {
            $papeles = $papeles->merge($documento->hermanosEnMismoEstado());
        }

        $fecha = isset($datos['fecha']) ? Carbon::parse($datos['fecha']) : today();
        // Se calcula una sola vez: los juegos que se mueven juntos comparten exactamente la misma fecha de regreso
        $plazo = $estado->plazoHabiles();
        $compromiso = isset($datos['fecha_compromiso']) ? Carbon::parse($datos['fecha_compromiso']) : ($plazo ? $fecha->copy()->addWeekdays($plazo) : null);

        DB::transaction(fn () => $papeles->each(fn (DocumentoFisico $d) => $d->mover($estado, $request->user()->personal, $datos['nota'] ?? null, $compromiso, $fecha)));

        $nombres = $papeles->count() > 1
            ? 'Juegos '.$papeles->pluck('juego')->sort()->join(', ', ' y ')
            : $documento->nombre();

        return back()->with('status', "{$nombres}: {$estado->label()}.");
    }

    /** Da de alta los papeles de un convenio que no tenía control físico. */
    public function preparar(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $convenio->prepararDocumentosFisicos($request->user()->personal);

        return back()->with('status', "Control físico abierto para {$convenio->empresa->razon_social}.");
    }

    /** Recordatorio manual a la empresa para que devuelva los juegos firmados. */
    public function recordarEmpresa(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $juegos = $convenio->documentosFisicos()->where('estado', EstadoDocumentoFisico::ConEmpresa->value)->get();

        if ($juegos->isEmpty()) {
            return back()->withErrors(['convenio' => 'La empresa no tiene juegos pendientes de firma.']);
        }

        Avisos::enviar($convenio->empresa->user, new AvisoOperativo(
            mensaje: DocumentoFisico::mensajeJuegosEmpresa($juegos),
            url: route('empresa.convenio', absolute: false),
            detalle: 'Convenio con el CUCSH',
            correo: true,
            asunto: 'Recordatorio: devuelve los juegos firmados de tu convenio',
        ));

        return back()->with('status', "Recordatorio enviado a {$convenio->empresa->razon_social}.");
    }
}
