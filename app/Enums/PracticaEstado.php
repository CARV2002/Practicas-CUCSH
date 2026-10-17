<?php

namespace App\Enums;

enum PracticaEstado: string
{
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
}
