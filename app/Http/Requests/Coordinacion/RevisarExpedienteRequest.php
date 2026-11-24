<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinacion;

use App\Models\Alumno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Decisión de la coordinación sobre el expediente del alumno (kardex + IMSS):
 * «autorizar» ambos, o «devolver» los que estén mal con un motivo.
 */
class RevisarExpedienteRequest extends FormRequest
{
    /** Solo la coordinación de la carrera del alumno. */
    public function authorize(): bool
    {
        return (bool) $this->user()->personal?->puedeVerCarrera((int) $this->alumno()->carrera_id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['autorizar', 'devolver'])],
            'documentos' => ['exclude_unless:decision,devolver', 'required', 'array', 'min:1'],
            'documentos.*' => [Rule::in(['kardex', 'imss'])],
            'motivo' => ['exclude_unless:decision,devolver', 'required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'documentos.required' => 'Marca qué documento debe corregir el alumno.',
            'motivo.required' => 'Explica al alumno qué está mal para que sepa qué subir (p. ej. «el kardex es del ciclo anterior»).',
        ];
    }

    public function alumno(): Alumno
    {
        /** @var Alumno */
        return $this->route('alumno');
    }

    public function devuelve(): bool
    {
        return $this->validated('decision') === 'devolver';
    }
}
