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
use Illuminate\Support\Str;
use App\Services\Avisos;
use App\Notifications\AvisoOperativo;

class ConvenioController extends Controller
{
    public function __construct(private ConvenioWorkflow $workflow)
    {
    }

    public function index(Request $request): View
    {
        $convenios = Convenio::with(['empresa.media', 'seguimientos', 'dictamenes', 'documentosFisicos'])
            ->visiblesPara($request->user()->personal)
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('q'), fn($q) => $q->whereHas('empresa', fn($e) => $e
                ->where('razon_social', 'like', '%' . $request->string('q') . '%')
                ->orWhere('rfc', 'like', '%' . $request->string('q') . '%')))
            ->latest('fecha_recepcion')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $conteos = Convenio::visiblesPara($request->user()->personal)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('admin.lista-convenios', [
            'convenios' => $convenios,
            'estados' => ConvenioEstado::cases(),
            'conteos' => $conteos,
            'porVencer' => Convenio::porVencer(60)->count(),
        ]);
    }

    public function show(Convenio $convenio): View
    {
        $this->authorize('view', $convenio);

        $convenio->load([
            'empresa.user',
            'dictamenes.carrera',
            'dictamenes.dictaminador.user',
            'seguimientos.registrador.user',
            'seguimientos.media',
            'documentosFisicos',
        ]);

        return view('admin.convenio-show', [
            'convenio' => $convenio,
            'empresa' => $convenio->empresa,
            'colecciones' => $convenio->empresa->documentosAplicables(),
            'etapaAbierta' => $convenio->etapasAbiertas()->latest('id')->first(),
            'borradores' => $convenio->getMedia('borrador_convenio'),
            'firmado' => $convenio->getMedia('convenio_firmado')->last(),
        ]);
    }

    /** Valida o rechaza un documento del expediente de la empresa. */
    public function resolverDocumento(Request $request, Convenio $convenio, Media $media): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        if ($convenio->estado !== ConvenioEstado::Solicitado) {
            return back()->withErrors(['convenio' => 'Los documentos solo se validan mientras la solicitud está en revisión de documentos.']);
        }

        abort_unless(
            $media->model_type === Empresa::class && (int) $media->model_id === (int) $convenio->empresa_id,
            404
        );

        $datos = $request->validate([
            'estado' => ['required', Rule::in([ValidacionEstado::Validada->value, ValidacionEstado::Rechazada->value])],
            'motivo' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('estado') === ValidacionEstado::Rechazada->value)],
        ], [
            'motivo.required' => 'Escribe el motivo para que la empresa sepa qué corregir.',
        ]);

        // Solo se revisa lo que está pendiente: lo validado ya no se rechaza y lo devuelto espera su reemplazo
        if ($media->getCustomProperty('estado') === ValidacionEstado::Validada->value) {
            return back()->withErrors(['documento' => 'Ese documento ya está validado: no se puede rechazar.']);
        }
        if ($media->getCustomProperty('estado') === ValidacionEstado::Rechazada->value) {
            return back()->withErrors(['documento' => 'Ese documento ya se devolvió: espera a que la empresa suba el corregido.']);
        }

        $convenio->empresa->resolverDocumento(
            $media,
            ValidacionEstado::from($datos['estado']),
            $datos['motivo'] ?? null,
            $request->user()->id
        );

        $empresa = $convenio->empresa;

        if ($datos['estado'] === ValidacionEstado::Rechazada->value) {
            Avisos::enviar($empresa->user, new AvisoOperativo(
                mensaje: 'Vinculación te devolvió «' . Empresa::nombreDocumento($media->collection_name) . '» de tu expediente: ' . $datos['motivo'] . ' Súbelo de nuevo para continuar con tu convenio.',
                url: route('empresa.documentos', absolute: false),
                correo: true,
                asunto: 'Documento de tu expediente rechazado',
            ));
        } elseif ($empresa->fresh()->expedienteCompleto()) {
            // Con el último documento aprobado, la plataforma turna sola la propuesta a las coordinaciones
            // de las carreras solicitadas (y le avisa a la empresa): Lorena ya no tiene que hacerlo a mano.
            $this->workflow->enviarAValidacionAcademica($convenio, $request->user()->personal);

            return back()->with('status', 'Documentos aprobados. La propuesta se envió a las coordinaciones de las carreras solicitadas para su dictamen académico.');
        }

        return back()->with('status', $datos['estado'] === ValidacionEstado::Rechazada->value
            ? 'Documento rechazado. Avisamos a la empresa por correo para que suba solo ese documento corregido.'
            : 'Documento aprobado.');
    }

    /** Valida de una vez los documentos que Lorena marcó (en cualquier orden). */
    public function validarDocumentos(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        if ($convenio->estado !== ConvenioEstado::Solicitado) {
            return back()->withErrors(['convenio' => 'Los documentos solo se validan mientras la solicitud está en revisión de documentos.']);
        }

        $datos = $request->validate(
            ['documentos' => ['required', 'array', 'min:1'], 'documentos.*' => ['integer']],
            ['documentos.required' => 'Marca al menos un documento para validar.']
        );

        $empresa = $convenio->empresa;
        $medios = $empresa->media()->whereIn('id', $datos['documentos'])->get();
        abort_if($medios->count() !== count(array_unique($datos['documentos'])), 404);

        $validados = 0;
        foreach ($medios as $media) {
            // Lo ya validado o ya devuelto no cambia
            if (in_array($media->getCustomProperty('estado'), [null, ValidacionEstado::Pendiente->value], true)) {
                $empresa->resolverDocumento($media, ValidacionEstado::Validada, null, $request->user()->id);
                $validados++;
            }
        }

        if ($empresa->fresh()->expedienteCompleto()) {
            $this->workflow->enviarAValidacionAcademica($convenio, $request->user()->personal);

            return back()->with('status', "Validaste {$validados} documento(s) y el expediente quedó completo: la propuesta se envió a las coordinaciones de las carreras solicitadas.");
        }

        return back()->with('status', "Validaste {$validados} documento(s).");
    }

    public function enviarAValidacionAcademica(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $this->workflow->enviarAValidacionAcademica($convenio, $request->user()->personal);

        return back()->with('status', 'Convenio turnado a las carreras.');
    }

    public function subirBorrador(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        if (!$convenio->etapasAbiertas()->where('etapa', EtapaConvenio::RedaccionBorrador->value)->exists()) {
            return back()->withErrors(['convenio' => 'El borrador solo se adjunta cuando la etapa de redacción está abierta.']);
        }

        $request->validate([
            'borrador' => ['required', 'file', 'mimes:pdf,docx', 'max:10240'],
        ]);

        $convenio->addMediaFromRequest('borrador')->toMediaCollection('borrador_convenio');

        return back()->with('status', 'Borrador adjuntado.');
    }

    public function turnarAJuridica(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $this->workflow->turnarAJuridica($convenio, $request->user()->personal);

        return back()->with('status', 'Borrador turnado a la Unidad Jurídica.');
    }

    public function registrarDictamenJuridico(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $datos = $request->validate([
            'resultado' => [
                'required',
                Rule::in([
                    SeguimientoEstado::Aprobado->value,
                    SeguimientoEstado::Observado->value,
                    SeguimientoEstado::Rechazado->value,
                ])
            ],
            'comentario' => ['nullable', 'string', 'max:2000'],
            // Fecha real en que la Unidad Jurídica otorgó (u observó) el dictamen: mide los tiempos de Jurídica
            'fecha_dictamen' => ['nullable', 'date', 'before_or_equal:today'],
            'documento' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $this->workflow->registrarDictamenJuridico(
            $convenio,
            SeguimientoEstado::from($datos['resultado']),
            $datos['comentario'] ?? null,
            $request->user()->personal
        );

        $seguimiento = $convenio->seguimientos()
            ->where('etapa', EtapaConvenio::DictamenJuridico->value)
            ->latest('id')
            ->first();

        if (!empty($datos['fecha_dictamen'])) {
            $seguimiento?->update(['fecha_resolucion' => $datos['fecha_dictamen']]);
        }

        if ($request->hasFile('documento')) {
            $seguimiento?->addMediaFromRequest('documento')->toMediaCollection('documento_etapa');
        }

        return back()->with('status', match ($datos['resultado']) {
            SeguimientoEstado::Aprobado->value => 'Dictamen de viabilidad registrado. Imprime los 3 juegos del convenio definitivo y avisa a la empresa para que pase a firmarlos.',
            SeguimientoEstado::Observado->value => 'Observaciones registradas. Corrige los datos, genera de nuevo el borrador y vuelve a turnarlo a Jurídica.',
            default => 'Jurídica rechazó el convenio: el trámite quedó cerrado y la empresa fue notificada.',
        });
    }

    public function registrarFirma(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $datos = $request->validate([
            'etapa' => ['required', Rule::in([EtapaConvenio::FirmaEmpresa->value, EtapaConvenio::FirmaRectoria->value])],
            'comentario' => ['nullable', 'string', 'max:1000'],
            // Evidencia de que los papeles regresaron firmados: sin ella no se registra la firma
            'evidencia' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'evidencia.required' => $request->input('etapa') === EtapaConvenio::FirmaRectoria->value
                ? 'Sube el escaneo completo de un juego con las firmas y sellos de la empresa y de Rectoría.'
                : 'Sube el escaneo de la última hoja de los 3 juegos con la firma y el sello de la empresa.',
            'evidencia.mimes' => 'La evidencia debe ser un PDF.',
        ]);

        $etapa = EtapaConvenio::from($datos['etapa']);
        $this->workflow->registrarFirma($convenio, $etapa, $request->user()->personal, $datos['comentario'] ?? null);

        // La evidencia queda en la bitácora de esa etapa
        $convenio->seguimientos()->where('etapa', $etapa->value)->latest('id')->first()
            ?->addMedia($request->file('evidencia'))->preservingOriginal()->toMediaCollection('documento_etapa');

        // El juego completo firmado por Rectoría ES el convenio firmado: ya no se vuelve a pedir al formalizar
        if ($etapa === EtapaConvenio::FirmaRectoria) {
            $convenio->clearMediaCollection('convenio_firmado');
            $convenio->addMedia($request->file('evidencia'))->toMediaCollection('convenio_firmado');
        }

        return back()->with('status', $etapa === EtapaConvenio::FirmaEmpresa
            ? 'Firma de la empresa registrada con su evidencia. Lleva los 3 juegos a Rectoría.'
            : 'Firma de Rectoría registrada. El escaneo quedó como convenio firmado: solo falta capturar folio y vigencia para formalizar.');
    }

    public function formalizar(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $datos = $request->validate([
            'folio' => ['required', 'string', 'max:30'],
            'fecha_inicio_vigencia' => ['required', 'date'],
            // La duración pactada en la cláusula UNDÉCIMA calcula el fin; una fecha explícita la sustituye
            'vigencia_anios' => ['nullable', 'integer', Rule::in(Convenio::DURACIONES)],
            'fecha_fin_vigencia' => ['nullable', 'date', 'after:fecha_inicio_vigencia'],
            'convenio_firmado' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('convenio_firmado')) {
            $convenio->clearMediaCollection('convenio_firmado');   // un reintento no deja versiones duplicadas
            $convenio->addMediaFromRequest('convenio_firmado')->toMediaCollection('convenio_firmado');
        }

        if (!empty($datos['vigencia_anios'])) {
            $convenio->update(['vigencia_anios' => (int) $datos['vigencia_anios']]);
        }

        $inicio = \Illuminate\Support\Carbon::parse($datos['fecha_inicio_vigencia']);
        $fin = isset($datos['fecha_fin_vigencia'])
            ? \Illuminate\Support\Carbon::parse($datos['fecha_fin_vigencia'])
            : $inicio->copy()->addYears($convenio->duracionAnios())->subDay();

        $this->workflow->formalizar($convenio, $request->user()->personal, $datos['folio'], $inicio, $fin, $datos['comentario'] ?? null);

        $aviso = $this->workflow->advertenciaVigencia($inicio, $fin);

        return back()->with('status', 'Convenio formalizado.' . ($aviso ? " Aviso: {$aviso}" : ''));
    }

    /** Fechas en que el oficio pasó por Secretaría Académica y por la División (solo canales). */
    public function registrarTurnos(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $datos = $request->validate([
            'fecha_turno_secretaria' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:' . $convenio->fecha_recepcion->toDateString()],
            'fecha_turno_division' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:fecha_turno_secretaria'],
        ], [
            'fecha_turno_division.after_or_equal' => 'La División recibe el turno después de Secretaría Académica.',
            'fecha_turno_secretaria.after_or_equal' => 'El turno no puede ser anterior a la recepción de la solicitud.',
        ]);

        $this->workflow->registrarTurnos(
            $convenio,
            $request->user()->personal,
            isset($datos['fecha_turno_secretaria']) ? \Illuminate\Support\Carbon::parse($datos['fecha_turno_secretaria']) : null,
            isset($datos['fecha_turno_division']) ? \Illuminate\Support\Carbon::parse($datos['fecha_turno_division']) : null,
        );

        return back()->with('status', 'Fechas de turno registradas.');
    }

    public function rechazar(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        $this->workflow->rechazar($convenio, $request->user()->personal, $datos['motivo']);

        return back()->with('status', 'Convenio rechazado.');
    }

    /** Descarga de archivos privados (disco local) de un convenio o del expediente de su empresa. */
    public function descargar(Convenio $convenio, Media $media)
    {
        $pertenece = ($media->model_type === Convenio::class && (int) $media->model_id === (int) $convenio->id)
            || ($media->model_type === Empresa::class && (int) $media->model_id === (int) $convenio->empresa_id)
            || ($media->model_type === \App\Models\ConvenioSeguimiento::class
                && \App\Models\ConvenioSeguimiento::whereKey($media->model_id)->where('convenio_id', $convenio->id)->exists());

        abort_unless($pertenece, 404);

        $this->authorize('download', $media);

        return $this->entregar($media);
    }

    public function generarBorrador(Request $request, Convenio $convenio, GeneradorBorradorConvenio $generador, \App\Services\ConvertidorPdf $convertidor): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $enRedaccion = $convenio->etapasAbiertas()->where('etapa', EtapaConvenio::RedaccionBorrador->value)->exists();

        if (!$enRedaccion || !$convenio->puedePasarAJuridica()) {
            return back()->withErrors(['convenio' => 'El borrador solo se genera cuando la etapa de redacción está abierta y hay al menos una carrera aprobada.']);
        }

        // Las declaraciones las capturó la empresa: Lorena solo las revisa (y corrige si hace falta)
        $empresa = $convenio->empresa;
        $request->mergeIfMissing(array_filter([
            'representante_nombre' => $empresa->representante_legal,
            'representante_cargo' => $empresa->representante_cargo,
            'constitucion' => $empresa->constitucion,
            'atribuciones' => $empresa->objeto_social,
            'facultad_firmante' => $empresa->facultad_firmante,
            'domicilio_legal' => $empresa->domicilio_fiscal,
        ]));

        $datos = $request->validate([
            'vigencia_anios' => ['required', 'integer', Rule::in(Convenio::DURACIONES)],
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

        $convenio->update(['vigencia_anios' => (int) $datos['vigencia_anios']]);
        $convenio->load('empresa');

        $ruta = $generador->generar($convenio, $datos);
        $version = $convenio->getMedia('borrador_convenio')->count() + 1;

        // PDF limpio para imprimir los 3 juegos (LibreOffice); el .docx queda para las correcciones de Jurídica
        $pdf = $convertidor->convertir($ruta);

        $convenio->addMedia($ruta)
            ->usingFileName("borrador-convenio-v{$version}.docx")
            ->withCustomProperties(['version' => $version])
            ->toMediaCollection('borrador_convenio');

        if ($pdf) {
            $convenio->addMedia($pdf)
                ->usingFileName("convenio-v{$version}.pdf")
                ->withCustomProperties(['version' => $version])
                ->toMediaCollection('borrador_pdf');
            \Illuminate\Support\Facades\File::deleteDirectory(dirname($pdf));   // carpeta temporal de LibreOffice
        }

        return back()->with('status', "Borrador v{$version} generado ({$datos['vigencia_anios']} años de vigencia)."
            . ($pdf ? ' Ya puedes descargar el PDF para imprimir.' : ' No se pudo crear el PDF: revisa que LibreOffice esté instalado; el Word sí quedó adjunto.'));
    }
}