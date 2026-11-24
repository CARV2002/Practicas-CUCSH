<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

use App\Enums\ValidacionEstado;
use Illuminate\Validation\Rule;

/** Vinculación valida o devuelve un documento del expediente de la empresa (con motivo si lo devuelve). */
class ResolverDocumentoEmpresaRequest extends GestionConvenioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in([ValidacionEstado::Validada->value, ValidacionEstado::Rechazada->value])],
            'motivo' => ['nullable', 'string', 'max:1000', Rule::requiredIf($this->input('estado') === ValidacionEstado::Rechazada->value)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['motivo.required' => 'Escribe el motivo para que la empresa sepa qué corregir.'];
    }

    public function rechazado(): bool
    {
        return $this->validated('estado') === ValidacionEstado::Rechazada->value;
    }
}
