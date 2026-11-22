<?php

namespace App\Http\Controllers\Empresa;

use App\Enums\ValidacionEstado;
use App\Enums\ConvenioEstado;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Str;
use App\Services\Avisos;
use App\Notifications\AvisoOperativo;

class DocumentoController extends Controller
{
    /** Expediente de la empresa con el estado de validación de cada documento. */
    public function index(Request $request): View
    {
        $empresa = $request->user()->empresa;
        $bloqueado = $empresa->documentosBloqueados();

        $documentos = collect($this->colecciones($empresa))->mapWithKeys(function ($col) use ($empresa, $bloqueado) {
            $media = $empresa->documentoVigente($col);
            $estado = $media ? ($media->getCustomProperty('estado') ?? ValidacionEstado::Pendiente->value) : null;

            return [
                $col => [
                    'media' => $media,
                    'obligatorio' => in_array($col, $empresa->obligatorias(), true),
                    // En revisión solo se cambia lo devuelto o se sube lo que falta; lo que Vinculación revisa queda fijo
                    'puedeSubir' => $estado !== ValidacionEstado::Validada->value
                        && (!$bloqueado || $media === null || $estado === ValidacionEstado::Rechazada->value),
                ]
            ];
        });

        return view('empresa.documentos', [
            'empresa' => $empresa,
            'documentos' => $documentos,
            'bloqueado' => $bloqueado,
            'expedienteCompleto' => $empresa->expedienteCompleto(),
            'expedienteEnOrden' => $empresa->expedienteEnOrden(),
        ]);
    }

    /**
     * Un solo envío para todo el expediente: cada archivo se valida por separado (solo PDF, máximo 5 MB).
     * La antigüedad de la constancia fiscal y del comprobante de domicilio (3 meses) la revisa Vinculación en el PDF.
     */
    public function subir(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;
        $aplicables = $this->colecciones($empresa);

        $reglas = ['archivos' => ['required', 'array', 'min:1'], 'archivos.*' => ['file']];
        $mensajes = ['archivos.required' => 'Elige al menos un documento para enviar.', 'archivos.min' => 'Elige al menos un documento para enviar.'];
        foreach ($aplicables as $col) {
            $nombre = Empresa::nombreDocumento($col);
            $reglas["archivos.{$col}"] = ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:' . Empresa::MAX_MB * 1024];
            $mensajes["archivos.{$col}.mimes"] = "«{$nombre}» debe ser un PDF.";
            $mensajes["archivos.{$col}.mimetypes"] = "«{$nombre}» debe ser un PDF.";
            $mensajes["archivos.{$col}.max"] = "«{$nombre}» pesa más de " . Empresa::MAX_MB . ' MB: comprímelo o escanéalo con menor resolución.';
        }
        $request->validate($reglas, $mensajes);

        $archivos = collect($request->file('archivos', []));
        if ($desconocidos = $archivos->keys()->diff($aplicables)->first()) {
            throw ValidationException::withMessages(['archivos' => 'Ese documento no corresponde a tu tipo de organización.']);
        }

        // Ninguno se guarda si alguno no se puede cambiar (validado o con la solicitud en revisión)
        $errores = [];
        foreach ($archivos->keys() as $col) {
            $vigente = $empresa->documentoVigente($col);
            $estado = $vigente?->getCustomProperty('estado');
            if ($estado === ValidacionEstado::Validada->value) {
                $errores["archivos.{$col}"] = '«' . Empresa::nombreDocumento($col) . '» ya fue validado: escribe a Vinculación si necesitas cambiarlo.';
            } elseif ($empresa->documentosBloqueados() && $vigente !== null && $estado !== ValidacionEstado::Rechazada->value) {
                $errores["archivos.{$col}"] = 'Tu convenio está en revisión: solo puedes subir lo que falta o reemplazar lo que Vinculación te devolvió.';
            }
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        foreach ($archivos as $col => $archivo) {
            $empresa->addMedia($archivo)->toMediaCollection($col);
        }

        $nombres = $archivos->keys()->map(fn ($c) => '«' . Empresa::nombreDocumento($c) . '»')->join(', ', ' y ');
        $solicitud = $empresa->convenios()->where('estado', ConvenioEstado::Solicitado->value)->first();
        if ($solicitud) {   // Lorena está esperando documentos: aviso claro de qué cambió y en qué organización
            $corregidos = $archivos->keys()->filter(fn ($c) => $empresa->media()->where('collection_name', $c)->count() > 1)->values();
            Avisos::enviar(Avisos::vinculacionDe($solicitud), new AvisoOperativo(
                mensaje: $corregidos->isNotEmpty()
                    ? "{$empresa->razon_social} corrigió " . $corregidos->map(fn ($c) => '«' . Empresa::nombreDocumento($c) . '»')->join(', ', ' y ') . ': ya puedes revisarlo.'
                    : "{$empresa->razon_social} subió {$nombres} a su expediente: ya puedes revisarlo.",
                url: route('admin.convenios.show', $solicitud, false) . '#expediente',
                detalle: $empresa->razon_social,
            ));
        }

        $mensaje = $archivos->count() === 1 ? "Enviamos {$nombres} a revisión." : "Enviamos {$archivos->count()} documentos a revisión: {$nombres}.";

        // Si la solicitud aún no se envía, el siguiente paso es enviarla desde «Mi convenio»
        if (!$empresa->documentosBloqueados() && !$empresa->convenios()->vigentes()->exists()) {
            return redirect()->route('empresa.convenio', ['seccion' => 'documentos'])
                ->with('status', $mensaje . ' Revisa que tu solicitud esté completa y envíala.');
        }

        return back()->with('status', $mensaje . ($solicitud ? ' Avisamos a Vinculación.' : ''));
    }
    /** Nombre del representante legal (firma el convenio). Se bloquea mientras el convenio está en revisión. */
    public function datosLegales(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;

        if ($empresa->documentosBloqueados()) {
            return back()->withErrors(['representante_legal' => 'Tu convenio está en revisión: no puedes cambiar tus datos legales.']);
        }

        // Declaraciones de la ENTIDAD del Formato de PP: van tal cual al convenio
        $request->merge([
            'tipo_organismo' => $empresa->tipo_organismo->value,
            'representante_legal' => \App\Support\Formatos::limpiar($request->input('representante_legal')),
            'escritura_numero' => \App\Support\Formatos::digitos($request->input('escritura_numero')) ?: null,
            'folio_mercantil' => strtoupper(trim((string) $request->input('folio_mercantil'))) ?: null,
        ]);
        $empresa->update(\App\Support\DatosNotariales::preparar($request->validate([
            'representante_legal' => ['required', 'string', 'min:5', 'max:255', 'regex:' . \App\Support\Formatos::NOMBRE_PERSONA],
            'representante_cargo' => ['required', 'string', 'min:3', 'max:255', 'regex:' . \App\Support\Formatos::TEXTO_LETRAS],
            'domicilio_fiscal' => ['required', 'string', 'min:10', 'max:500'],
            ...\App\Support\DatosNotariales::reglas(),
            'objeto_social' => ['required', 'string', 'max:1500'],
            'facultad_firmante' => ['required', 'string', 'max:1500'],
        ], [
            'representante_legal.required' => 'Escribe el nombre del representante legal.',
            'representante_cargo.required' => 'Escribe el cargo del representante legal.',
            'domicilio_fiscal.required' => 'Escribe el domicilio legal.',
            ...\App\Support\DatosNotariales::mensajes(),
            ...\App\Support\Formatos::mensajes(),
            'objeto_social.required' => 'Escribe el objeto social o las atribuciones.',
            'facultad_firmante.required' => 'Escribe la facultad de quien firma.',
        ]), $empresa->tipo_organismo));

        return back()->with('status', 'Datos legales actualizados.');
    }

    /** Descarga de un archivo propio (disco privado). */
    public function descargar(Request $request, int $media)
    {
        $archivo = $request->user()->empresa->media()->findOrFail($media);
        return $this->entregar($archivo);
    }

    /** @return string[] documentos que le aplican a esta organización (obligatorios y opcionales) */
    private function colecciones(Empresa $empresa): array
    {
        return $empresa->documentosAplicables();
    }
}