<?php

declare(strict_types=1);

namespace App\Http\Requests\Alumno;

use App\Enums\EsquemaReportes;
use App\Enums\ReporteTipo;
use App\Models\Practica;
use App\Support\ReglasArchivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reporte de horas que el alumno redacta en pantalla (el sistema genera el formato en PDF)
 * o, si su carrera usa un formato propio, que sube ya firmado.
 */
class CrearReporteRequest extends FormRequest
{
    /** Solo el alumno dueño de la práctica (PracticaPolicy::entregarDocumentos). */
    public function authorize(): bool
    {
        return $this->user()->can('entregarDocumentos', $this->practica());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $practica = $this->practica();
        // El periodo no puede ir más allá de hoy ni del fin de la práctica
        $tope = today()->min($practica->fecha_fin)->toDateString();

        return [
            'tipo' => ['required', Rule::in(array_map(fn (ReporteTipo $t) => $t->value, $this->esquema()->tipos()))],
            'periodo_inicio' => ['required', 'date', 'after_or_equal:'.$practica->fecha_inicio->toDateString(), 'before_or_equal:'.$tope],
            'periodo_fin' => ['required', 'date', 'after_or_equal:periodo_inicio', 'before_or_equal:'.$tope],
            'horas_reportadas' => ['required', 'integer', 'min:1', 'max:1000'],
            // Lo que redacta en pantalla: con esto el sistema genera el formato estándar en PDF
            'actividades' => ['required_without:archivo', 'nullable', 'string', 'min:30', 'max:5000'],
            'logros' => ['nullable', 'string', 'max:3000'],
            // Opcional: el formato propio de su carrera ya firmado (los datos siguen contando las horas)
            'archivo' => ReglasArchivo::pdfOpcional(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'actividades.required_without' => 'Describe las actividades que realizaste en el periodo (o sube el formato propio de tu carrera ya firmado).',
            'actividades.min' => 'Describe con más detalle tus actividades (al menos 30 caracteres).',
            'periodo_inicio.after_or_equal' => 'El periodo no puede empezar antes de tu práctica.',
            'periodo_fin.before_or_equal' => 'El periodo no puede terminar en el futuro ni después de tu práctica.',
            'tipo.in' => "Tu carrera pide reportes con el esquema «{$this->esquema()->label()}».",
        ];
    }

    public function practica(): Practica
    {
        /** @var Practica */
        return $this->route('practica');
    }

    /** Cada carrera define la periodicidad de sus reportes (mensual, bimestral o parcial + final). */
    public function esquema(): EsquemaReportes
    {
        return $this->user()->alumno->carrera->esquema_reportes ?? EsquemaReportes::Mensual;
    }
}
