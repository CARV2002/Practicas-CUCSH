<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

use App\Support\ReglasArchivo;
use Illuminate\Support\Str;

/** Cierre del trámite: folio institucional, inicio de vigencia y (si no se subió con la firma de Rectoría) el convenio firmado. */
class FormalizarConvenioRequest extends GestionConvenioRequest
{
    /** El folio se captura como venga («cv 2026 014 ») y se guarda limpio («CV2026014»). */
    protected function prepareForValidation(): void
    {
        $this->merge(['folio' => Str::upper(preg_replace('/\s+/', '', (string) $this->input('folio')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'folio' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\/-]{3,30}$/'],
            'fecha_inicio_vigencia' => ['required', 'date'],
            'convenio_firmado' => ReglasArchivo::pdfOpcional(ReglasArchivo::MB_ESCANEO),
            'comentario' => ['nullable', 'string', 'max:1000'],
            // Juegos que aún no se entregan físicamente al formalizar (siguen en Documentos físicos)
            'juegos_pendientes' => ['nullable', 'array'],
            'juegos_pendientes.*' => ['integer', 'in:1,2,3'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['folio.regex' => 'El folio solo lleva letras mayúsculas, números, guiones o diagonales (por ejemplo CV-2026-014).'];
    }
}
