<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filtros de la pantalla «Informes» y de sus exportaciones a CSV (todos opcionales). */
class FiltrosInformeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // la ruta ya exige personal; qué informe puede bajar cada rol lo decide InformeController
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'carrera_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'string', 'max:30'],
            'ciclo' => ['nullable', 'regex:/^\d{4}[AB]$/'],   // p. ej. 2027A
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }
}
