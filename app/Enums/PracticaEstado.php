<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TieneTransiciones;

/**
 * Estado de la práctica: en curso, en cierre, acreditada o cancelada (baja).
 */
enum PracticaEstado: string
{
    use TieneTransiciones;
    case EnCurso = 'en_curso';
    case EnCierre = 'en_cierre';     // carta de término validada
    case Acreditada = 'acreditada';  // oficio enviado a Control Escolar
    case Cancelada = 'cancelada';

    public static function activas(): array
    {
        return [self::EnCurso, self::EnCierre];
    }

    /** @return string[] */
    public static function valores(array $casos): array
    {
        return array_map(fn (self $c) => $c->value, $casos);
    }

    public function label(): string
    {
        return match ($this) {
            self::EnCurso => 'En curso',
            self::EnCierre => 'En cierre',
            self::Acreditada => 'Acreditada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function transiciones(): array
    {
        return match ($this) {
            self::EnCurso => [self::EnCierre, self::Cancelada],
            self::EnCierre => [self::Acreditada, self::Cancelada],
            self::Acreditada, self::Cancelada => [],
        };
    }

    /** Familia de color pastel para la insignia de estatus (DESIGN_SYSTEM.md §1.4). */
    public function familia(): string
    {
        return match ($this) {
            self::EnCurso => 'turquesa',
            self::EnCierre => 'ambar',
            self::Acreditada => 'menta',
            self::Cancelada => 'piedra',
        };
    }
}
