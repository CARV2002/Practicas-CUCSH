<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\SeguimientoEstado;
use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\Empresa;
use App\Services\ConvenioWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Services\GeneradorBorradorConvenio;

class ConvenioController extends Controller
{
    public function __construct(private ConvenioWorkflow $workflow)
    {
    }

    public function index(Request $request): View
    {
        $convenios = Convenio::with(['empresa', 'seguimientos'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('empresa', fn ($e) => $e
                ->where('razon_social', 'like', '%'.$request->string('q').'%')
                ->orWhere('rfc', 'like', '%'.$request->string('q').'%')))
            ->latest('fecha_recepcion')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.lista-convenios', [
            'convenios' => $convenios,
            'estados' => ConvenioEstado::cases(),
            'porVencer' => Convenio::porVencer(60)->count(),
        ]);
    }

    public function show(Convenio $convenio): View
    {
        $convenio->load([
            'empresa.user',
            'dictamenes.carrera',
            'dictamenes.dictaminador.user',
            'seguimientos.registrador.user',
        ]);

        return view('admin.convenio-show', [
            'convenio' => $convenio,
            'empresa' => $convenio->empresa,
            'colecciones' => Empresa::coleccionesObligatorias(),
            'etapaAbierta' => $convenio->etapasAbiertas()->latest('id')->first(),
            'borradores' => $convenio->getMedia('borrador_convenio'),
            'firmado' => $convenio->getMedia('convenio_firmado')->last(),
        ]);
    }

    /** Valida o rechaza un documento del expediente de la empresa. */
    public function resolverDocumento(Request $request, Convenio $convenio, Media $media): RedirectResponse
    {
        abort_unless(
            $media->model_type === Empresa::class && (int) $media->model_id === (int) $convenio->empresa_id,
            404
        );

        $datos = $request->validate([
            'estado' => ['required', Rule::in([ValidacionEstado::Validada->value, ValidacionEstado::Rechazada->value])],
            'motivo' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('estado') === ValidacionEstado::Rechazada->value)],
        ]);

        $convenio->empresa->resolverDocumento(
            $media,
            ValidacionEstado::from($datos['estado']),
            $datos['motivo'] ?? null,
            $request->user()->id
        );

        return back()->with('status', 'Documento actualizado.');
    }

    public function enviarAValidacionAcademica(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->workflow->enviarAValidacionAcademica($convenio, $request->user()->personal);

        return back()->with('status', 'Convenio turnado a las carreras.');
    }

    public function subirBorrador(Request $request, Convenio $convenio): RedirectResponse
    {
        $request->validate([
            'borrador' => ['required', 'file', 'mimes:pdf,docx', 'max:10240'],
        ]);

        $convenio->addMediaFromRequest('borrador')->toMediaCollection('borrador_convenio');

        return back()->with('status', 'Borrador adjuntado.');
    }

    public function turnarAJuridica(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->workflow->turnarAJuridica($convenio, $request->user()->personal);

        return back()->with('status', 'Borrador turnado a la Unidad Jurídica.');
    }

    public function registrarDictamenJuridico(Request $request, Convenio $convenio): RedirectResponse
    {
        $datos = $request->validate([
            'resultado' => ['required', Rule::in([
                SeguimientoEstado::Aprobado->value,
                SeguimientoEstado::Observado->value,
                SeguimientoEstado::Rechazado->value,
            ])],
            'comentario' => ['nullable', 'string', 'max:2000'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $this->workflow->registrarDictamenJuridico(
            $convenio,
            SeguimientoEstado::from($datos['resultado']),
            $datos['comentario'] ?? null,
            $request->user()->personal
        );

        if ($request->hasFile('documento')) {
            $seguimiento = $convenio->seguimientos()
                ->where('etapa', EtapaConvenio::DictamenJuridico->value)
                ->latest('id')
                ->first();

            $seguimiento?->addMediaFromRequest('documento')->toMediaCollection('documento_etapa');
        }

        return back()->with('status', 'Dictamen jurídico registrado.');
    }

    public function registrarFirma(Request $request, Convenio $convenio): RedirectResponse
    {
        $datos = $request->validate([
            'etapa' => ['required', Rule::in([EtapaConvenio::FirmaEmpresa->value, EtapaConvenio::FirmaRectoria->value])],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->workflow->registrarFirma(
            $convenio,
            EtapaConvenio::from($datos['etapa']),
            $request->user()->personal,
            $datos['comentario'] ?? null
        );

        return back()->with('status', 'Firma registrada.');
    }

    public function formalizar(Request $request, Convenio $convenio): RedirectResponse
    {
        $datos = $request->validate([
            'folio' => ['required', 'string', 'max:30'],
            'fecha_inicio_vigencia' => ['required', 'date'],
            'fecha_fin_vigencia' => ['required', 'date', 'after:fecha_inicio_vigencia'],
            'convenio_firmado' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('convenio_firmado')) {
            $convenio->addMediaFromRequest('convenio_firmado')->toMediaCollection('convenio_firmado');
        }

        $inicio = \Illuminate\Support\Carbon::parse($datos['fecha_inicio_vigencia']);
        $fin = \Illuminate\Support\Carbon::parse($datos['fecha_fin_vigencia']);

        $this->workflow->formalizar($convenio, $request->user()->personal, $datos['folio'], $inicio, $fin, $datos['comentario'] ?? null);

        $aviso = $this->workflow->advertenciaVigencia($inicio, $fin);

        return back()->with('status', 'Convenio formalizado.'.($aviso ? " Aviso: {$aviso}" : ''));
    }

    public function rechazar(Request $request, Convenio $convenio): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        $this->workflow->rechazar($convenio, $request->user()->personal, $datos['motivo']);

        return back()->with('status', 'Convenio rechazado.');
    }

    /** Descarga de archivos privados (disco local) de un convenio o del expediente de su empresa. */
    public function descargar(Convenio $convenio, Media $media)
    {
        $pertenece = ($media->model_type === Convenio::class && (int) $media->model_id === (int) $convenio->id)
            || ($media->model_type === Empresa::class && (int) $media->model_id === (int) $convenio->empresa_id);

        abort_unless($pertenece, 404);

        return $media->toResponse(request());
    }

    public function generarBorrador(Request $request, Convenio $convenio, GeneradorBorradorConvenio $generador): RedirectResponse
{
    $enRedaccion = $convenio->etapasAbiertas()->where('etapa', EtapaConvenio::RedaccionBorrador->value)->exists();

    if (! $enRedaccion || ! $convenio->puedePasarAJuridica()) {
        return back()->withErrors(['convenio' => 'El borrador solo se genera cuando la etapa de redacción está abierta y hay al menos una carrera aprobada.']);
    }

    $datos = $request->validate([
        'representante_nombre' => ['required', 'string', 'max:255'],
        'representante_cargo' => ['required', 'string', 'max:255'],
        'constitucion' => ['required', 'string', 'max:1500'],
        'atribuciones' => ['required', 'string', 'max:1500'],
        'facultad_firmante' => ['required', 'string', 'max:1500'],
        'domicilio_legal' => ['required', 'string', 'max:500'],
        'lugar_firma' => ['required', 'string', 'max:100'],
        'fecha_firma' => ['required', 'date'],
        'apoyo_economico' => ['nullable', 'string', 'max:500'],
    ]);

    $convenio->load('empresa');

    $ruta = $generador->generar($convenio, $datos);
    $version = $convenio->getMedia('borrador_convenio')->count() + 1;

    $convenio->addMedia($ruta)
        ->usingFileName("borrador-convenio-v{$version}.docx")
        ->toMediaCollection('borrador_convenio');

    return back()->with('status', "Borrador v{$version} generado y adjuntado. Revísalo en Word antes de turnarlo a Jurídica.");
}
}