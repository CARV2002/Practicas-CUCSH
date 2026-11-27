<?php

declare(strict_types=1);

namespace App\Http\Requests\Alumno;

use App\Support\ReglasArchivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/** Constancia de vigencia de derechos del IMSS (nueva, corregida o renovada) con su fecha de vigencia. */
class SubirImssRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // la ruta ya exige el rol de alumno
    }

    /** Un IMSS validado y vigente ya no se reemplaza; se renueva en sus últimos 30 días (Alumno::motivoSinCambioImss). */
    protected function prepareForValidation(): void
    {
        if ($motivo = $this->user()->alumno->motivoSinCambioImss()) {
            throw ValidationException::withMessages(['archivo' => $motivo]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo' => ReglasArchivo::pdfRequerido(estricto: true),
            'vigencia_imss' => ['required', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Elige el PDF o la imagen de tu constancia de vigencia de derechos.',
            'vigencia_imss.required' => 'Escribe la fecha hasta la que está vigente tu seguro (aparece en la constancia).',
            'vigencia_imss.after' => 'La vigencia debe ser posterior a hoy: si ya venció, descarga una constancia actualizada del IMSS.',
        ];
    }
}
