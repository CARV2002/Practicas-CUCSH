<?php

namespace App\Enums;

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
            self::Observado => 'cielo',
            self::Rechazado => 'rosa',
        };
    }
}
