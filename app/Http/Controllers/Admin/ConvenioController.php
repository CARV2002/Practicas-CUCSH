<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\SeguimientoEstado;
use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vinculacion\FormalizarConvenioRequest;
use App\Http\Requests\Vinculacion\GenerarBorradorRequest;
use App\Http\Requests\Vinculacion\JuegosFinalesRequest;
use App\Http\Requests\Vinculacion\RegistrarDictamenJuridicoRequest;
use App\Http\Requests\Vinculacion\RegistrarFirmaRequest;
use App\Http\Requests\Vinculacion\RegistrarTurnosRequest;
use App\Http\Requests\Vinculacion\ResolverDocumentoEmpresaRequest;
use App\Http\Requests\Vinculacion\SubirBorradorRequest;
use App\Models\Configuracion;
use App\Models\Convenio;
use App\Models\ConvenioSeguimiento;
use App\Models\Empresa;
use App\Notifications\AvisoOperativo;
use App\Notifications\BorradorTurnadoJuridica;
use App\Services\Auditoria;
use App\Services\Avisos;
use App\Services\BorradoresConvenio;
use App\Services\ConvenioWorkflow;
use App\Support\Requisitos;
use App\Support\SolicitudEmpresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bandeja de convenios de Vinculación: revisión del expediente de la empresa, borradores, Jurídica,
 * firmas y formalización. El administrador la ve en modo lectura.
 */
class ConvenioController extends Controller
{
    public function __construct(private ConvenioWorkflow $workflow) {}

    public function index(Request $request): View
    {
        // «Por vencer» es una pestaña más de la bandeja: formalizados que vencen en los próximos 60 días
        $porVencerFiltro = $request->string('estado')->toString() === self::FILTRO_POR_VENCER;

        $convenios = Convenio::with(['empresa.media', 'seguimientos', 'dictamenes', 'documentosFisicos'])
            ->visiblesPara($request->user()->personal)
            ->when($porVencerFiltro, fn ($q) => $q->porVencer(60)->orderBy('fecha_fin_vigencia'))
            ->when(! $porVencerFiltro && $request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('empresa', fn ($e) => $e
                ->where('razon_social', 'like', '%'.$request->string('q').'%')
                ->orWhere('rfc', 'like', '%'.$request->string('q').'%')))
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
            'porVencer' => Convenio::visiblesPara($request->user()->personal)->porVencer(60)->count(),
            'porVencerIds' => Convenio::porVencer(60)->pluck('id')->flip(),
            // ¿La empresa ya pidió la renovación? (tiene otra solicitud en trámite)
            'enRenovacion' => Convenio::whereNotIn('estado', [ConvenioEstado::Formalizado->value, ConvenioEstado::Rechazado->value])
                ->pluck('empresa_id')->flip(),
        ]);
    }

    public const FILTRO_POR_VENCER = 'por_vencer';

    /** Ligas anteriores (avisos ya enviados): ahora es una pestaña de la bandeja. */
    public function porVencer(): RedirectResponse
    {
        return redirect()->route('admin.lista-convenios', ['estado' => self::FILTRO_POR_VENCER]);
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
            // Por qué todavía no se puede enviar a dictamen académico (null = ya se puede)
            'faltaTurnar' => Requisitos::turnarAValidacionAcademica($convenio),
            // A quién le llega el correo al turnar a Jurídica
            'correoJuridica' => Configuracion::valor(Configuracion::CORREO_JURIDICA),
        ]);
    }

    /** Valida o rechaza un documento del expediente de la empresa. */
    public function resolverDocumento(ResolverDocumentoEmpresaRequest $request, Convenio $convenio, Media $media): RedirectResponse
    {
        if ($convenio->estado !== ConvenioEstado::Solicitado) {
            return back()->withErrors(['convenio' => 'Los documentos solo se validan mientras la solicitud está en revisión de documentos.']);
        }

        abort_unless(
            $media->model_type === Empresa::class && (int) $media->model_id === (int) $convenio->empresa_id,
            404
        );

        $datos = $request->validated();

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

        if ($request->rechazado()) {
            Avisos::enviar($empresa->user, new AvisoOperativo(
                mensaje: 'Vinculación te devolvió «'.Empresa::nombreDocumento($media->collection_name).'» de tu expediente. Motivo: «'.$datos['motivo'].'». Qué hacer: entra a «Documentos», busca la tarjeta marcada en rojo y sube el archivo corregido para continuar con tu convenio.',
                url: route('empresa.documentos', absolute: false),
                correo: true,
                asunto: 'Documento de tu expediente rechazado',
            ));
        } elseif ($empresa->fresh()->expedienteCompleto() && ! $convenio->fresh()->tieneCorreccionesPendientes()) {
            // Con el último documento aprobado, la plataforma turna sola la propuesta a las coordinaciones
            // de las carreras solicitadas (y le avisa a la empresa): Lorena ya no tiene que hacerlo a mano.
            $this->workflow->enviarAValidacionAcademica($convenio, $request->user()->personal);

            return back()->with('status', 'Documentos aprobados. La propuesta se envió a las coordinaciones de las carreras solicitadas para su dictamen académico.');
        }

        return back()->with('status', $request->rechazado()
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

        if ($empresa->fresh()->expedienteCompleto() && ! $convenio->fresh()->tieneCorreccionesPendientes()) {
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

    /** Borrador redactado fuera del sistema (p. ej. el Word que Jurídica devolvió con control de cambios). */
    public function subirBorrador(SubirBorradorRequest $request, Convenio $convenio, BorradoresConvenio $borradores): RedirectResponse
    {
        if (! $convenio->etapasAbiertas()->where('etapa', EtapaConvenio::RedaccionBorrador->value)->exists()) {
            return back()->withErrors(['convenio' => 'El borrador solo se adjunta cuando la etapa de redacción está abierta.']);
        }

        $anios = (int) $request->validated('vigencia_anios');
        $nuevo = $borradores->adjuntar($convenio, $request->file('borrador'), $anios);

        return back()->with('status', "Borrador v{$nuevo['version']} adjuntado ({$anios} años de vigencia)."
            .($nuevo['pdf'] ? '' : ' No se pudo crear el PDF: imprime desde el Word.'));
    }

    /**
     * Turna la versión vigente a la Unidad Jurídica: congela el borrador (ya no se edita hasta el dictamen),
     * lo anota en la bitácora y, si hay un correo de Jurídica configurado, se lo envía con los anexos.
     */
    public function turnarAJuridica(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $correo = Configuracion::valor(Configuracion::CORREO_JURIDICA);
        $enviar = $correo && $request->boolean('enviar_correo', true);
        $vigente = $convenio->versionesBorrador()->last();
        $version = $vigente['version'] ?? 1;

        $this->workflow->turnarAJuridica($convenio, $request->user()->personal,
            "Se turnó la versión v{$version} del borrador".($enviar ? " y se envió por correo a {$correo}." : ' (entrega por correo propio o en carpeta física).'));

        if (! $enviar) {
            return back()->with('status', "Borrador v{$version} turnado a la Unidad Jurídica. Descarga el archivo para enviarlo o llevarlo impreso.");
        }

        try {
            Notification::route('mail', $correo)->notify(new BorradorTurnadoJuridica(
                $convenio->fresh('empresa'),
                $version,
                collect([$vigente['archivo'] ?? null, $vigente['pdf'] ?? null])->filter()->unique('id')->values()->all(),
                $request->user()->name,
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('status', "Borrador v{$version} turnado, pero no se pudo enviar el correo a {$correo}: descarga el archivo y envíalo desde tu correo.");
        }

        return back()->with('status', "Borrador v{$version} turnado a la Unidad Jurídica y enviado por correo a {$correo} con sus anexos.");
    }

    /** Correo institucional de la Unidad Jurídica que recibe los borradores turnados (opcional). */
    public function configurarJuridica(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('operar-convenios'), 403);

        $request->merge(['correo_juridica' => Str::lower(trim((string) $request->input('correo_juridica')))]);
        $datos = $request->validate(['correo_juridica' => ['nullable', 'email', 'max:255']], [
            'correo_juridica.email' => 'Escribe un correo válido, por ejemplo usuario@dominio.com.',
        ]);

        Configuracion::guardar(Configuracion::CORREO_JURIDICA, $datos['correo_juridica'] ?: null, $request->user()->id);
        Auditoria::registrar('configuracion.correo_juridica', 'Correo de la Unidad Jurídica: '.($datos['correo_juridica'] ?: 'sin correo (solo registro interno)'));

        return back()->with('status', $datos['correo_juridica']
            ? "Listo: los borradores turnados se enviarán a {$datos['correo_juridica']}."
            : 'Sin correo de Jurídica: al turnar solo se registra en la bitácora.');
    }

    public function registrarDictamenJuridico(RegistrarDictamenJuridicoRequest $request, Convenio $convenio): RedirectResponse
    {
        $datos = $request->validated();

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

        if (! empty($datos['fecha_dictamen'])) {
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

    public function registrarFirma(RegistrarFirmaRequest $request, Convenio $convenio): RedirectResponse
    {
        $etapa = $request->etapa();
        $this->workflow->registrarFirma($convenio, $etapa, $request->user()->personal, $request->validated('comentario'));

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

    public function formalizar(FormalizarConvenioRequest $request, Convenio $convenio): RedirectResponse
    {
        $datos = $request->validated();

        // Los años quedaron impresos en la cláusula UNDÉCIMA del último borrador que se firmó con tinta:
        // aquí ya no se eligen (ni se acepta una fecha de fin distinta), para que el registro no contradiga al contrato.
        if ($convenio->vigencia_anios === null) {
            return back()->withErrors(['convenio' => 'El convenio no tiene años de vigencia registrados en su borrador. Genera o adjunta el borrador indicando la vigencia pactada.']);
        }

        if ($request->hasFile('convenio_firmado')) {
            $convenio->clearMediaCollection('convenio_firmado');   // un reintento no deja versiones duplicadas
            $convenio->addMediaFromRequest('convenio_firmado')->toMediaCollection('convenio_firmado');
        }

        $inicio = Carbon::parse($datos['fecha_inicio_vigencia']);

        // Lo impreso en la última hoja de los juegos y lo registrado deben coincidir
        if (($impresa = $convenio->fechaFirmaImpresa()) && ! $impresa->isSameDay($inicio)) {
            return back()->withInput()->withErrors(['fecha_inicio_vigencia' => 'Los juegos firmados dicen '.$impresa->format('d/m/Y').': el inicio de vigencia debe ser esa misma fecha. Si se firmó otro día, vuelve a generar los juegos finales con la fecha correcta.']);
        }

        $fin = $inicio->copy()->addYears($convenio->duracionAnios())->subDay();

        $this->workflow->formalizar($convenio, $request->user()->personal, $datos['folio'], $inicio, $fin, $datos['comentario'] ?? null, $datos['juegos_pendientes'] ?? []);

        $aviso = $this->workflow->advertenciaVigencia($inicio, $fin);

        return back()->with('status', 'Convenio formalizado.'.($aviso ? " Aviso: {$aviso}" : ''));
    }

    /** Fechas en que el oficio pasó por Secretaría Académica y por la División (solo canales). */
    public function registrarTurnos(RegistrarTurnosRequest $request, Convenio $convenio): RedirectResponse
    {
        $datos = $request->validated();

        $this->workflow->registrarTurnos(
            $convenio,
            $request->user()->personal,
            isset($datos['fecha_turno_secretaria']) ? Carbon::parse($datos['fecha_turno_secretaria']) : null,
            isset($datos['fecha_turno_division']) ? Carbon::parse($datos['fecha_turno_division']) : null,
        );

        return back()->with('status', 'Fechas de turno registradas.');
    }

    /** Devuelve datos de la solicitud a la empresa para corregir (solo esos quedan abiertos para ella). */
    public function pedirCorreccion(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $corregibles = array_merge(...array_values(SolicitudEmpresa::CORREGIBLES));
        $datos = $request->validate([
            'campos' => ['required', 'array', 'min:1'],
            'campos.*' => ['string', Rule::in($corregibles)],
            'motivo_correccion' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'campos.required' => 'Marca al menos un dato para que la empresa lo corrija.',
            'motivo_correccion.required' => 'Escribe qué debe corregir: la empresa lo verá en su aviso.',
            'motivo_correccion.min' => 'Explica un poco más qué debe corregir.',
        ]);

        $this->workflow->pedirCorreccion($convenio, $datos['campos'], $datos['motivo_correccion'], $request->user()->personal);

        return back()->with('status', 'Pediste corregir '.count($datos['campos']).' dato(s). Avisamos a la empresa por correo; solo esos campos quedan abiertos para ella.');
    }

    public function rechazar(Request $request, Convenio $convenio): RedirectResponse
    {
        $this->authorize('gestionar', $convenio);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        $this->workflow->rechazar($convenio, $request->user()->personal, $datos['motivo']);

        return back()->with('status', 'Convenio rechazado.');
    }

    /** Descarga de archivos privados (disco local) de un convenio o del expediente de su empresa. */
    public function descargar(Convenio $convenio, Media $media): Response
    {
        $pertenece = ($media->model_type === Convenio::class && (int) $media->model_id === (int) $convenio->id)
            || ($media->model_type === Empresa::class && (int) $media->model_id === (int) $convenio->empresa_id)
            || ($media->model_type === ConvenioSeguimiento::class
                && ConvenioSeguimiento::whereKey($media->model_id)->where('convenio_id', $convenio->id)->exists());

        abort_unless($pertenece, 404);

        $this->authorize('download', $media);

        // Solo la versión vigente se imprime: el PDF de una versión anterior ya no se entrega (el Word sí, como historial)
        if ($convenio->esImpresionObsoleta($media)) {
            return back()->withErrors(['convenio' => 'Esa versión del borrador ya es obsoleta: imprime la versión vigente.']);
        }

        return $this->entregar($media);
    }

    /**
     * Juegos finales para firma: el mismo borrador que aprobó Jurídica, ahora con la fecha real de firma impresa.
     * Esa fecha será el inicio de vigencia al formalizar.
     */
    public function juegosFinales(JuegosFinalesRequest $request, Convenio $convenio, BorradoresConvenio $borradores): RedirectResponse
    {
        if ($convenio->estado !== ConvenioEstado::EnFirmas) {
            return back()->withErrors(['convenio' => 'Los juegos finales se imprimen cuando Jurídica ya dio el dictamen favorable y el convenio está en firmas.']);
        }

        $base = $convenio->datosBorradorVigente();
        if ($base === null) {
            return back()->withErrors(['fecha_firma' => 'El borrador vigente lo adjuntaste tú (no se generó en el sistema): escribe la fecha a mano en la última hoja y captúrala igual al formalizar.']);
        }

        $fechaFirma = $request->validated('fecha_firma');
        $version = $borradores->juegosFinales($convenio, $base, $fechaFirma);
        $fecha = Carbon::parse($fechaFirma)->format('d/m/Y');

        return back()->with('status', "Juegos finales (v{$version}) listos con fecha de firma {$fecha}. Imprime los 3 juegos: esa fecha será el inicio de vigencia al formalizar.");
    }

    /** El sistema redacta el borrador con la plantilla del CUCSH y las declaraciones que capturó la empresa. */
    public function generarBorrador(GenerarBorradorRequest $request, Convenio $convenio, BorradoresConvenio $borradores): RedirectResponse
    {
        $enRedaccion = $convenio->etapasAbiertas()->where('etapa', EtapaConvenio::RedaccionBorrador->value)->exists();

        if (! $enRedaccion || ! $convenio->puedePasarAJuridica()) {
            return back()->withErrors(['convenio' => 'El borrador solo se genera cuando la etapa de redacción está abierta y hay al menos una carrera aprobada.']);
        }

        $datos = $request->validated();
        $nuevo = $borradores->generar($convenio, $datos);

        return back()->with('status', "Borrador v{$nuevo['version']} generado ({$datos['vigencia_anios']} años de vigencia)."
            .($nuevo['pdf'] ? ' Ya puedes descargar el PDF para imprimir.' : ' No se pudo crear el PDF: revisa que LibreOffice esté instalado; el Word sí quedó adjunto.'));
    }
}
