<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

use App\Enums\SeguimientoEstado;
use App\Support\ReglasArchivo;
use Illuminate\Validation\Rule;

/** Resultado del dictamen de la Unidad Jurídica: aprobado, con observaciones o rechazado. */
class RegistrarDictamenJuridicoRequest extends GestionConvenioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resultado' => ['required', Rule::in([
                SeguimientoEstado::Aprobado->value,
                SeguimientoEstado::Observado->value,
                SeguimientoEstado::Rechazado->value,
            ])],
            'comentario' => ['nullable', 'string', 'max:2000'],
            // Fecha real en que Jurídica otorgó (u observó) el dictamen: con ella se miden sus tiempos de respuesta
            'fecha_dictamen' => ['nullable', 'date', 'before_or_equal:today'],
            'documento' => ReglasArchivo::pdfOpcional(),
        ];
    }
}
