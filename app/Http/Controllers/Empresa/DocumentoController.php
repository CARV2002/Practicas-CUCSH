<?php

namespace App\Http\Controllers\Empresa;

use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentoController extends Controller
{
    /** Expediente de la empresa con el estado de validación de cada documento. */
    public function index(Request $request): View
    {
        $empresa = $request->user()->empresa;

        $documentos = collect($this->colecciones())->mapWithKeys(fn ($col) => [$col => [
            'media' => $empresa->documentoVigente($col),
            'obligatorio' => in_array($col, Empresa::coleccionesObligatorias(), true),
        ]]);

        return view('empresa.documentos', [
            'empresa' => $empresa,
            'documentos' => $documentos,
            'expedienteCompleto' => $empresa->expedienteCompleto(),
        ]);
    }

    public function subir(Request $request): RedirectResponse
    {
        $empresa = $request->user()->empresa;

        $request->validate(['coleccion' => ['required', Rule::in($this->colecciones())]]);

        $coleccion = $request->input('coleccion');

        $request->validate([
            'archivo' => [
                'required', 'file', 'max:10240',
                $coleccion === 'cv_instancia' ? 'mimes:pdf,doc,docx' : 'mimes:pdf',
            ],
        ]);

        $vigente = $empresa->documentoVigente($coleccion);

        if ($vigente?->getCustomProperty('estado') === ValidacionEstado::Validada->value) {
            throw ValidationException::withMessages([
                'archivo' => 'Este documento ya fue validado. Contacta a la Coordinación si necesitas cambiarlo.',
            ]);
        }

        $empresa->addMediaFromRequest('archivo')->toMediaCollection($coleccion);

        return back()->with('status', 'Documento enviado a revisión.');
    }

    /** Descarga de un archivo propio (disco privado). */
    public function descargar(Request $request, int $media)
    {
        $archivo = $request->user()->empresa->media()->findOrFail($media);

        return $archivo->toResponse($request);
    }

    /** @return string[] */
    private function colecciones(): array
    {
        return array_values(array_unique([...Empresa::coleccionesObligatorias(), 'poder_notarial']));
    }
}