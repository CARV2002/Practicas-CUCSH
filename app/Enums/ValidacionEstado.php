<?php

declare(strict_types=1);

namespace App\Enums;

/** Para cartas, oficios y para el estado de cada archivo en custom_properties de medialibrary. */
enum ValidacionEstado: string
{
    case Pendiente = 'pendiente';
    case Validada = 'validada';
    case Rechazada = 'rechazada';

    /** Familia de color pastel para la insignia de estatus (DESIGN_SYSTEM.md §1.4). */
    public function familia(): string
    {
        return match ($this) {
            self::Pendiente => 'ambar',
            self::Validada => 'menta',
            self::Rechazada => 'rosa',
        };
    }
}
