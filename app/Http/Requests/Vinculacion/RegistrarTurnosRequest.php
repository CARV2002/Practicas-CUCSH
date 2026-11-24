<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

/** Fechas en que el oficio pasó por Secretaría Académica y luego por la División (en ese orden). */
class RegistrarTurnosRequest extends GestionConvenioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $recepcion = $this->convenio()->fecha_recepcion->toDateString();

        return [
            'fecha_turno_secretaria' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:'.$recepcion],
            'fecha_turno_division' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:fecha_turno_secretaria'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_turno_division.after_or_equal' => 'La División recibe el turno después de Secretaría Académica.',
            'fecha_turno_secretaria.after_or_equal' => 'El turno no puede ser anterior a la recepción de la solicitud.',
        ];
    }
}
