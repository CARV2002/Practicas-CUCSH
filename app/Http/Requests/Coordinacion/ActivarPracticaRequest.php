<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinacion;

use App\Models\Postulacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Fechas de la práctica y, si ya se sabe, su profesor supervisor. */
class ActivarPracticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Postulacion $postulacion */
        $postulacion = $this->route('postulacion');

        return $this->user()->can('gestionar', $postulacion);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'profesor_id' => ['nullable', 'integer', Rule::exists('profesores', 'id')],
        ];
    }
}
