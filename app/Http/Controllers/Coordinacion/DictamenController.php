<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinacion;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\ConvenioCarrera;
use App\Services\ConvenioWorkflow;
use App\Support\ReglasArchivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dictamen de pertinencia: la Coordinación de cada carrera decide si las actividades
 * propuestas por la empresa corresponden al perfil de egreso. Vinculación solo da seguimiento.
 */
class DictamenController extends Controller
{
    public function __construct(private ConvenioWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $personal = $request->user()->personal;
        $filtro = $request->string('dictamen')->toString() ?: Dictamen::Pendiente->value;

        $dictamenes = ConvenioCarrera::with(['convenio.empresa', 'convenio.dictamenes.carrera', 'carrera.division', 'dictaminador.user', 'media'])
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

        return view('admin.lista-dictamenes', [
            'modo' => 'coordinacion',
            'dictamenes' => $dictamenes,
            'todasNegativas' => collect(),
            'carreras' => Carrera::enAlcance($personal)->orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
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

    /** La coordinación emite su voto (aprobado/rechazado) con observaciones y su dictamen escaneado, firmado y sellado. */
    public function registrar(Request $request, ConvenioCarrera $dictamen): RedirectResponse
    {
        abort_unless($request->user()->personal->puedeVerCarrera((int) $dictamen->carrera_id), 403);

        $datos = $request->validate([
            'dictamen' => ['required', Rule::in([Dictamen::Aprobatorio->value, Dictamen::Negativo->value])],
            // Un dictamen negativo siempre explica por qué: la empresa recibe ese motivo para ajustar su propuesta
            'observaciones' => ['nullable', 'required_if:dictamen,'.Dictamen::Negativo->value, 'string', 'max:2000'],
            'documento' => ReglasArchivo::pdfRequerido(),
        ], [
            'documento.required' => 'Sube tu dictamen escaneado, firmado y sellado (PDF).',
            'observaciones.required_if' => 'Escribe el motivo del dictamen negativo: la empresa lo recibe para ajustar su propuesta.',
        ]);

        $this->workflow->registrarDictamenCarrera(
            $dictamen,
            Dictamen::from($datos['dictamen']),
            $datos['observaciones'] ?? null,
            $request->user()->personal
        );

        $dictamen->addMediaFromRequest('documento')->toMediaCollection('dictamen_pertinencia');

        return back()->with('status', 'Dictamen emitido. Vinculación fue notificada.');
    }

    public function descargar(ConvenioCarrera $dictamen, Media $media): Response
    {
        return $this->entregarDe($dictamen, $media);
    }
}
