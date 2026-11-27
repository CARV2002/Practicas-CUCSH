<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

/** Fechas en que el oficio pasó por Secretaría Académica y luego por la División (en ese orden). */
class RegistrarTurnosRequest extends GestionConvenioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $envio = $this->convenio()->fechaEnvioAcademico()->toDateString();
        $tope = $this->convenio()->topeTurnoAcademico()->toDateString();

        // Fechas reales (sin 31 de febrero), desde el envío a las carreras; pueden ser estimadas (a futuro) hasta el
        // tope, porque solo miden tiempos. La División va el mismo día o después de Secretaría.
        return [
            'fecha_turno_secretaria' => ['nullable', 'required_with:fecha_turno_division', 'date_format:Y-m-d', 'after_or_equal:'.$envio, 'before_or_equal:'.$tope],
            'fecha_turno_division' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_turno_secretaria', 'before_or_equal:'.$tope],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_turno_secretaria.required_with' => 'Anota primero la fecha de Secretaría Académica: la División lo recibe después.',
            'fecha_turno_secretaria.after_or_equal' => 'Secretaría Académica no pudo recibirlo antes de que lo enviaras a las carreras (:date).',
            'fecha_turno_division.after_or_equal' => 'La División lo recibe el mismo día o después que Secretaría Académica.',
            'fecha_turno_secretaria.before_or_equal' => 'Puede ser una fecha estimada, pero no más allá de :date (90 días después del envío).',
            'fecha_turno_division.before_or_equal' => 'Puede ser una fecha estimada, pero no más allá de :date (90 días después del envío).',
            'fecha_turno_secretaria.date_format' => 'Esa fecha no existe: elígela en el calendario.',
            'fecha_turno_division.date_format' => 'Esa fecha no existe: elígela en el calendario.',
        ];
    }
}
