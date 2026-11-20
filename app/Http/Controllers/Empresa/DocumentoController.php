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

        $documentos = collect($this->colecciones())->mapWithKeys(function ($col) use ($empresa, $bloqueado) {
            $media = $empresa->documentoVigente($col);
            $estado = $media ? ($media->getCustomProperty('estado') ?? ValidacionEstado::Pendiente->value) : null;

            return [
                $col => [
                    'media' => $media,
                    'obligatorio' => in_array($col, Empresa::coleccionesObligatorias(), true),
                    'puedeSubir' => $estado !== ValidacionEstado::Validada->value
                        && (!$bloqueado || $estado === ValidacionEstado::Rechazada->value),
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

    public function subir(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;

        $request->validate(['coleccion' => ['required', Rule::in($this->colecciones())]]);

        $coleccion = $request->input('coleccion');

        $request->validate([
            'archivo' => [
                'required',
                'file',
                'max:10240',
                $coleccion === 'cv_instancia' ? 'mimes:pdf,doc,docx' : 'mimes:pdf',
            ],
        ]);

        $vigente = $empresa->documentoVigente($coleccion);

        if (
            $empresa->documentosBloqueados()
            && $vigente?->getCustomProperty('estado') !== ValidacionEstado::Rechazada->value
        ) {
            throw ValidationException::withMessages([
                'archivo' => 'Tu convenio está en revisión. Podrás modificar tus documentos si Vinculación rechaza alguno o rechaza la solicitud.',
            ]);
        }

        if ($vigente?->getCustomProperty('estado') === ValidacionEstado::Validada->value) {
            throw ValidationException::withMessages([
                'archivo' => 'Este documento ya fue validado. Contacta a Vinculación si necesitas cambiarlo.',
            ]);
        }

        $empresa->addMediaFromRequest('archivo')->toMediaCollection($coleccion);

        $solicitud = $empresa->convenios()->where('estado', ConvenioEstado::Solicitado->value)->first();

        if ($solicitud) {   // solo avisa si Lorena está esperando documentos
            Avisos::enviar(Avisos::vinculacionDe($solicitud), new AvisoOperativo(
                mensaje: "{$empresa->razon_social} subió «" . Str::headline($coleccion) . '» a su expediente.',
                url: route('admin.convenios.show', $solicitud, false),
                detalle: $empresa->razon_social,
            ));
        }

        return back()->with('status', 'Documento enviado a revisión.');
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

    /** @return string[] */
    private function colecciones(): array
    {
        return array_values(array_unique([...Empresa::coleccionesObligatorias(), 'poder_notarial']));
    }
}