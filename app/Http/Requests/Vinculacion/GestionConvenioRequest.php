<?php

declare(strict_types=1);

namespace App\Http\Requests\Vinculacion;

use App\Models\Convenio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de los formularios con los que Vinculación mueve un convenio de etapa.
 * Autoriza ANTES de validar (ConvenioPolicy::gestionar): quien solo puede leer, como el administrador,
 * recibe un 403 y nunca ve los mensajes de validación del formulario.
 */
abstract class GestionConvenioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionar', $this->convenio());
    }

    /** Convenio de la URL. */
    public function convenio(): Convenio
    {
        /** @var Convenio */
        return $this->route('convenio');
    }
}
