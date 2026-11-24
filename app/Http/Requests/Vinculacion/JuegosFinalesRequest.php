<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

/** Día real de la firma: se imprime en los juegos finales y será el inicio de vigencia al formalizar. */
class JuegosFinalesRequest extends GestionConvenioRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha_firma' => ['required', 'date', 'after_or_equal:'.$this->convenio()->fecha_recepcion->toDateString()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_firma.required' => 'Indica el día en que se firmarán los juegos.',
            'fecha_firma.after_or_equal' => 'La fecha de firma no puede ser anterior a la solicitud del convenio.',
        ];
    }
}
