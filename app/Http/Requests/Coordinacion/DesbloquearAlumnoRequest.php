<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinacion;

use App\Models\Alumno;
use Illuminate\Foundation\Http\FormRequest;

/** Desbloqueo excepcional: queda registrada la causa de fuerza mayor. */
class DesbloquearAlumnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Alumno $alumno */
        $alumno = $this->route('alumno');

        return (bool) $this->user()->personal?->puedeVerCarrera((int) $alumno->carrera_id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'justificacion' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'justificacion.required' => 'Escribe la justificación del desbloqueo.',
            'justificacion.min' => 'Explica con más detalle la causa de fuerza mayor.',
        ];
    }
}
