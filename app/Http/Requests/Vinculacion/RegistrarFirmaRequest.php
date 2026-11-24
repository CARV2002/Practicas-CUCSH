<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

use App\Enums\EtapaConvenio;
use App\Support\ReglasArchivo;
use Illuminate\Validation\Rule;

/**
 * Firma de la empresa o de Rectoría. Sin el escaneo de los papeles firmados no se registra:
 * es la evidencia de que los juegos regresaron.
 */
class RegistrarFirmaRequest extends GestionConvenioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'etapa' => ['required', Rule::in([EtapaConvenio::FirmaEmpresa->value, EtapaConvenio::FirmaRectoria->value])],
            'comentario' => ['nullable', 'string', 'max:1000'],
            'evidencia' => ReglasArchivo::pdfRequerido(ReglasArchivo::MB_ESCANEO),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            // Lo que se pide escanear depende de quién firmó
            'evidencia.required' => $this->input('etapa') === EtapaConvenio::FirmaRectoria->value
                ? 'Sube el escaneo completo de un juego con las firmas y sellos de la empresa y de Rectoría.'
                : 'Sube el escaneo de la última hoja de los 3 juegos con la firma y el sello de la empresa.',
            'evidencia.mimes' => 'La evidencia debe ser un PDF.',
        ];
    }

    public function etapa(): EtapaConvenio
    {
        return EtapaConvenio::from($this->validated('etapa'));
    }
}
