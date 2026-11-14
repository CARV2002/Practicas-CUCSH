<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PracticaEstado;
use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Models\CartaTermino;
use App\Models\OficioAcreditacion;
use App\Models\Practica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Services\Avisos;
use App\Notifications\AvisoOperativo;

/** Cierre de la práctica: carta de término -> oficio -> Control Escolar -> SIIAU. */
class CierreController extends Controller
{
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;
        $todas = $request->string('ver')->toString() === 'todas';

        $estados = PracticaEstado::valores($todas
            ? [...PracticaEstado::activas(), PracticaEstado::Acreditada]
            : PracticaEstado::activas());

        $practicas = Practica::with([
            'postulacion.alumno.user',
            'postulacion.alumno.carrera',
            'postulacion.programa.convenio.empresa',
            'cartaTermino.media',
            'oficioAcreditacion.media',
        ])
            ->conHorasAcumuladas()
            ->visiblesPara($personal)
            ->whereHas('cartaTermino')
            ->whereIn('estado', $estados)
            ->when($request->filled('q'), fn($q) => $q->whereHas('postulacion.alumno', fn($a) => $a
                ->where('num_control', 'like', '%' . $request->string('q') . '%')
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', '%' . $request->string('q') . '%'))))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.lista-cierres', ['practicas' => $practicas, 'ver' => $todas ? 'todas' : 'pendientes']);
    }

    /** Valida o rechaza la carta de término. Validarla exige horas completas (lo aplica CartaTerminoObserver). */
    public function resolverCarta(Request $request, Practica $practica): RedirectResponse
    {
        $this->autorizar($request, $practica);

        $datos = $request->validate([
            'estado' => ['required', Rule::in([ValidacionEstado::Validada->value, ValidacionEstado::Rechazada->value])],
            'motivo' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('estado') === ValidacionEstado::Rechazada->value)],
        ], ['motivo.required' => 'Explica el motivo del rechazo para que el alumno pueda corregir.']);

        $carta = $practica->cartaTermino;
        $archivo = $carta?->documentoVigente('carta_termino');

        if ($archivo === null) {
            return back()->withErrors(['carta' => 'El alumno aún no sube su carta de término.']);
        }

        if ($practica->estado !== PracticaEstado::EnCurso) {
            return back()->withErrors(['carta' => 'Solo se revisa la carta de una práctica en curso.']);
        }

        $estado = ValidacionEstado::from($datos['estado']);

        DB::transaction(function () use ($carta, $archivo, $estado, $datos, $request) {
            $carta->update(['estado_validacion' => $estado]);   // si faltan horas, el observer lanza ValidationException
            $carta->resolverDocumento($archivo, $estado, $datos['motivo'] ?? null, $request->user()->id);
        });

        $valida = $estado === ValidacionEstado::Validada;

        Avisos::enviar(Avisos::alumnoDe($practica), new AvisoOperativo(
            mensaje: $valida
            ? 'Tu carta de término fue validada. Solo falta que la Coordinación registre tu acreditación; no tienes que hacer nada más.'
            : 'Tu carta de término fue rechazada: ' . $datos['motivo'] . ' Sube una corregida.',
            url: route($valida ? 'alumno.mis-practicas' : 'alumno.mis-documentos', absolute: false),
            correo: true,
            asunto: 'Actualización de tu carta de término',
        ));

        return back()->with('status', $estado === ValidacionEstado::Validada
            ? 'Carta validada: la práctica pasó a cierre.'
            : 'Carta rechazada; el alumno puede subir otra.');
    }

    /** La Coordinación sube el oficio de acreditación (PDF firmado). */
    public function emitirOficio(Request $request, Practica $practica): RedirectResponse
    {
        $this->autorizar($request, $practica);

        if ($practica->estado !== PracticaEstado::EnCierre) {
            return back()->withErrors(['oficio' => 'La práctica aún no está en cierre (falta validar la carta de término).']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $existente = $practica->oficioAcreditacion;

        if ($existente?->enviado_control_escolar_at !== null) {
            return back()->withErrors(['oficio' => 'El oficio ya se envió a Control Escolar; no se puede reemplazar.']);
        }

        DB::transaction(function () use ($practica, $request) {
            $oficio = OficioAcreditacion::firstOrCreate(
                ['practica_id' => $practica->id],
                ['emitido_por' => $request->user()->personal->id, 'estado_validacion' => ValidacionEstado::Validada]
            );

            $oficio->addMediaFromRequest('archivo')->toMediaCollection('oficio_acreditacion');
        });

        return back()->with('status', 'Oficio de acreditación registrado.');
    }

    public function enviarControlEscolar(Request $request, Practica $practica): RedirectResponse
    {
        $this->autorizar($request, $practica);

        $oficio = $practica->oficioAcreditacion;

        if ($oficio === null || $oficio->getMedia('oficio_acreditacion')->isEmpty()) {
            return back()->withErrors(['oficio' => 'Primero registra el oficio de acreditación.']);
        }

        if ($oficio->enviado_control_escolar_at !== null) {
            return back()->withErrors(['oficio' => 'El oficio ya estaba marcado como enviado.']);
        }

        $oficio->update(['enviado_control_escolar_at' => now()]);   // el observer exige oficio validado y práctica en cierre

        return back()->with('status', 'Oficio marcado como enviado a Control Escolar.');
    }

    /** Control Escolar no usa el sistema: la Coordinación registra aquí cuando confirman la captura en SIIAU. */
    public function capturarSiiau(Request $request, Practica $practica): RedirectResponse
    {
        $this->autorizar($request, $practica);

        $oficio = $practica->oficioAcreditacion;

        if ($oficio === null) {
            return back()->withErrors(['oficio' => 'Esta práctica aún no tiene oficio.']);
        }

        if ($oficio->capturado_siiau_at !== null) {
            return back()->withErrors(['oficio' => 'La captura en SIIAU ya estaba registrada.']);
        }

        $oficio->update(['capturado_siiau_at' => now()]);   // el observer exige que ya se haya enviado y acredita la práctica

        $titulo = $practica->postulacion->programa->titulo;

        Avisos::enviar(Avisos::alumnoDe($practica), new AvisoOperativo(
            mensaje: "¡Tu práctica profesional en «{$titulo}» quedó acreditada! Ya puedes ver tu acreditación en el sistema.",
            url: route('alumno.mis-practicas', absolute: false),
            correo: true,
            asunto: '¡Tu práctica quedó acreditada!',
        ));

        Avisos::enviar(Avisos::empresaDe($practica), new AvisoOperativo(
            mensaje: "{$practica->postulacion->alumno->user->name} concluyó y acreditó su práctica en «{$titulo}». Gracias por recibirle.",
            url: route('empresa.practicantes.show', $practica, false),
        ));

        return back()->with('status', 'Captura en SIIAU registrada: la práctica quedó acreditada.');
    }

    public function descargar(Request $request, Practica $practica, Media $media)
    {
        $this->autorizar($request, $practica);

        $cartaId = $practica->cartaTermino?->id;
        $oficioId = $practica->oficioAcreditacion?->id;

        abort_unless(
            ($media->model_type === CartaTermino::class && $cartaId !== null && (int) $media->model_id === (int) $cartaId)
            || ($media->model_type === OficioAcreditacion::class && $oficioId !== null && (int) $media->model_id === (int) $oficioId),
            404
        );

        $this->authorize('download', $media);

        return $media->toResponse($request);
    }

    private function autorizar(Request $request, Practica $practica): void
    {
        $this->authorize('gestionar', $practica);
    }
}