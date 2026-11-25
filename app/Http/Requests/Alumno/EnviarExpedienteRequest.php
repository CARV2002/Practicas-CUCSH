<?php

declare(strict_types=1);

namespace App\Http\Requests\Alumno;

use App\Models\Alumno;
use App\Support\ReglasArchivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Expediente del alumno (kardex + IMSS + % de créditos): se entrega junto a su coordinación
 * y debe quedar validado ANTES de postularse.
 */
class EnviarExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // la ruta ya exige el rol de alumno
    }

    /** Antes de revisar los campos: un expediente en revisión o ya validado no se vuelve a enviar. */
    protected function prepareForValidation(): void
    {
        $estado = $this->alumno()->estadoExpediente();

        if (in_array($estado, ['en_revision', 'validado'], true)) {
            throw ValidationException::withMessages(['expediente' => $estado === 'validado'
                ? 'Tu expediente ya está validado: ya puedes postularte.'
                : 'Tu expediente ya está en revisión con tu coordinación.']);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'porcentaje_creditos' => ['required', 'numeric', 'min:'.$this->minimoCreditos(), 'max:100'],
            'kardex' => ReglasArchivo::pdfRequerido(estricto: true),
            'imss' => ReglasArchivo::pdfRequerido(estricto: true),
            'vigencia_imss' => ['required', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'porcentaje_creditos.required' => 'Escribe tu porcentaje de créditos cursados (lo ves en tu kardex).',
            'porcentaje_creditos.min' => "Tu carrera requiere al menos el {$this->minimoCreditos()}% de créditos cursados para iniciar el trámite.",
            'kardex.required' => 'Adjunta tu kardex simple del ciclo actual.',
            'imss.required' => 'Adjunta tu constancia de vigencia de derechos del IMSS.',
            'vigencia_imss.required' => 'Escribe la fecha hasta la que está vigente tu seguro (aparece en la constancia).',
            'vigencia_imss.after' => 'La vigencia debe ser posterior a hoy: si ya venció, descarga una constancia actualizada del IMSS.',
        ];
    }

    public function alumno(): Alumno
    {
        return $this->user()->alumno->loadMissing('carrera');
    }

    /** % mínimo de créditos que pide la carrera del alumno. */
    private function minimoCreditos(): float
    {
        return (float) $this->alumno()->carrera->porcentaje_creditos_minimo;
    }
}
