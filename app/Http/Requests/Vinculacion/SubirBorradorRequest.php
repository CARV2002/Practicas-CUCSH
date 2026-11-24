<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

use App\Models\Convenio;
use App\Support\ReglasArchivo;
use Illuminate\Validation\Rule;

/** Borrador redactado fuera del sistema (Word o PDF), con los años de vigencia de su cláusula UNDÉCIMA. */
class SubirBorradorRequest extends GestionConvenioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Word para que Jurídica pueda corregir con control de cambios, o PDF ya cerrado
            'borrador' => ['required', 'file', 'mimes:pdf,docx', 'max:'.(ReglasArchivo::MB * 1024)],
            // La vigencia que dice SU cláusula UNDÉCIMA: es la que se usará al formalizar
            'vigencia_anios' => ['required', 'integer', Rule::in(Convenio::DURACIONES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['vigencia_anios.required' => 'Indica los años de vigencia que dice la cláusula UNDÉCIMA de este borrador.'];
    }
}
