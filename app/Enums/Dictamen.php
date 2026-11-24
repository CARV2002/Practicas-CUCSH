<?php

declare(strict_types=1);

namespace App\Enums;

/** Dictamen Técnico de Pertinencia Académica de UNA carrera sobre UN convenio. */
enum Dictamen: string
{
    case Pendiente = 'pendiente';
    case Aprobatorio = 'aprobatorio';
    case Negativo = 'negativo';

    /** Familia de color pastel para la insignia de estatus (DESIGN_SYSTEM.md §1.4). */
    public function familia(): string
    {
        return match ($this) {
            self::Pendiente => 'ambar',
            self::Aprobatorio => 'menta',
            self::Negativo => 'rosa',
        };
    }
}
