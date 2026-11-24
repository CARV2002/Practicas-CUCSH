<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Resultado de cada etapa de la bitácora del convenio: pendiente, aprobada, con observaciones o rechazada.
 */
enum SeguimientoEstado: string
{
    case Pendiente = 'pendiente';   // etapa abierta: alguien tiene "la pelota"
    case Aprobado = 'aprobado';
    case Observado = 'observado';   // se devuelve para corregir (p. ej. Jurídica regresa el Word)
    case Rechazado = 'rechazado';

    /** Familia de color pastel para la insignia de estatus (DESIGN_SYSTEM.md §1.4). */
    public function familia(): string
    {
        return match ($this) {
            self::Pendiente => 'ambar',
            self::Aprobado => 'menta',
            self::Observado => 'ambar',   // con observaciones: falta corregir (no es un rechazo)
            self::Rechazado => 'rosa',
        };
    }
}
