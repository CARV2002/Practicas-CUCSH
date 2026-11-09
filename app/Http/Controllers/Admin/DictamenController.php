<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Services\ConvenioWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DictamenController extends Controller
{
    public function __construct(private ConvenioWorkflow $workflow)
    {
    }

    /**
     * Dictámenes por carrera y convenio:
     *  - pendientes (por defecto), filtrables por carrera y estado del dictamen
     *  - convenios en los que todas las carreras dieron dictamen negativo
     */
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;
        $filtro = $request->string('dictamen')->toString() ?: Dictamen::Pendiente->value;

        $dictamenes = ConvenioCarrera::with(['convenio.empresa', 'carrera.division', 'dictaminador.user', 'media'])
            ->visiblesPara($personal)
            ->when($filtro !== 'todos', fn ($q) => $q->where('dictamen', $filtro))
            ->when($request->filled('carrera_id'), fn ($q) => $q->where('carrera_id', $request->integer('carrera_id')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('convenio.empresa', fn ($e) => $e
                ->where('razon_social', 'like', '%'.$request->string('q').'%')
                ->orWhere('rfc', 'like', '%'.$request->string('q').'%')))
            ->whereHas('convenio', fn ($c) => $filtro === Dictamen::Pendiente->value
                ? $c->where('estado', ConvenioEstado::EnValidacionAcademica->value)
                : $c)
            ->orderByRaw("case dictamen when 'pendiente' then 0 else 1 end")
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $todasNegativas = Convenio::with(['empresa', 'dictamenes.carrera'])
            ->visiblesPara($personal)
            ->whereHas('dictamenes')
            ->whereDoesntHave('dictamenes', fn ($d) => $d->where('dictamen', '!=', Dictamen::Negativo->value))
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.lista-dictamenes', [
            'dictamenes' => $dictamenes,
            'todasNegativas' => $todasNegativas,
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'resumen' => [
                'pendientes' => ConvenioCarrera::visiblesPara($personal)->pendientes()
                    ->whereHas('convenio', fn ($c) => $c->where('estado', ConvenioEstado::EnValidacionAcademica->value))
                    ->count(),
                'aprobatorios' => ConvenioCarrera::visiblesPara($personal)->where('dictamen', Dictamen::Aprobatorio->value)->count(),
                'negativos' => ConvenioCarrera::visiblesPara($personal)->where('dictamen', Dictamen::Negativo->value)->count(),
            ],
            'filtro' => $filtro,
        ]);
    }

    /** Captura del dictamen (aprobatorio/negativo) con observaciones y PDF escaneado. */
    public function registrar(Request $request, ConvenioCarrera $dictamen): RedirectResponse
    {
        $datos = $request->validate([
            'dictamen' => ['required', Rule::in([Dictamen::Aprobatorio->value, Dictamen::Negativo->value])],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $this->workflow->registrarDictamenCarrera(
            $dictamen,
            Dictamen::from($datos['dictamen']),
            $datos['observaciones'] ?? null,
            $request->user()->personal
        );

        if ($request->hasFile('documento')) {
            $dictamen->addMediaFromRequest('documento')->toMediaCollection('dictamen_pertinencia');
        }

        return back()->with('status', 'Dictamen registrado.');
    }

    /** Descarga del PDF escaneado del dictamen (disco privado). */
    public function descargar(ConvenioCarrera $dictamen, Media $media)
    {
        abort_unless(
            $media->model_type === ConvenioCarrera::class && (int) $media->model_id === (int) $dictamen->id,
            404
        );

        return $media->toResponse(request());
    }
}

