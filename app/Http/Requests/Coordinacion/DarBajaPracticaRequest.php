<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinacion;

use App\Models\Practica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Baja de una práctica en curso: motivo del catálogo y qué pasó. */
class DarBajaPracticaRequest extends FormRequest
{
    /** La práctica debe ser de una carrera de esta coordinación. */
    public function authorize(): bool
    {
        $practica = $this->practica();

        return $this->user()->can('gestionar', $practica)
            && (bool) $this->user()->personal?->puedeVerCarrera((int) $practica->postulacion->alumno->carrera_id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo_baja' => ['required', Rule::in(array_keys(Practica::MOTIVOS_BAJA))],
            'detalle_baja' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo_baja.required' => 'Indica el motivo de la baja.',
            'detalle_baja.required' => 'Describe brevemente lo que pasó.',
        ];
    }

    public function practica(): Practica
    {
        /** @var Practica */
        return $this->route('practica');
    }
}
