<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

use App\Models\Convenio;
use Illuminate\Validation\Rule;

/** Datos con los que el sistema redacta el borrador del convenio (plantilla Word del CUCSH). */
class GenerarBorradorRequest extends GestionConvenioRequest
{
    /**
     * Las declaraciones las capturó la empresa en su solicitud: si Lorena no las corrige en el formulario,
     * se toman tal cual de la empresa.
     */
    protected function prepareForValidation(): void
    {
        $empresa = $this->convenio()->empresa;

        $this->mergeIfMissing(array_filter([
            'representante_nombre' => $empresa->representante_legal,
            'representante_cargo' => $empresa->representante_cargo,
            'constitucion' => $empresa->constitucion,
            'atribuciones' => $empresa->objeto_social,
            'facultad_firmante' => $empresa->facultad_firmante,
            'domicilio_legal' => $empresa->domicilio_fiscal,
        ]));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vigencia_anios' => ['required', 'integer', Rule::in(Convenio::DURACIONES)],
            'representante_nombre' => ['required', 'string', 'max:255'],
            'representante_cargo' => ['required', 'string', 'max:255'],
            'constitucion' => ['required', 'string', 'max:1500'],
            'atribuciones' => ['required', 'string', 'max:1500'],
            'facultad_firmante' => ['required', 'string', 'max:1500'],
            'domicilio_legal' => ['required', 'string', 'max:500'],
            'lugar_firma' => ['required', 'string', 'max:100'],
            // Tentativa: Jurídica solo revisa cláusulas; la fecha real se fija al imprimir los juegos finales
            'fecha_firma' => ['nullable', 'date'],
            'apoyo_economico' => ['nullable', 'string', 'max:500'],
        ];
    }
}
