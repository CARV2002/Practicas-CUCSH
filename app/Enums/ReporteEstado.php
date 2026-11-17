<?php

namespace App\Enums;

use App\Enums\Concerns\TieneTransiciones;
enum ReporteEstado: string
{
    use TieneTransiciones;

    case Pendiente = 'pendiente';
    case Validado = 'validado';
    case Rechazado = 'rechazado';
    case Anulado = 'anulado';        // la práctica se canceló con este reporte sin resolver

    public function transiciones(): array
    {
        return match ($this) {
            self::Pendiente => [self::Validado, self::Rechazado, self::Anulado],
            self::Validado => [self::Rechazado, self::Anulado],            // corrección mientras la práctica sigue en curso
            self::Rechazado => [self::Pendiente, self::Validado, self::Anulado],   // el alumno lo reenvía
            self::Anulado => [],
        };
    }

    /** Familia de color pastel para la insignia de estatus (DESIGN_SYSTEM.md §1.4). */
    public function familia(): string
    {
        return match ($this) {
            self::Pendiente => 'ambar',
            self::Validado => 'menta',
            self::Rechazado => 'rosa',
            self::Anulado => 'piedra',
        };
    }
}
