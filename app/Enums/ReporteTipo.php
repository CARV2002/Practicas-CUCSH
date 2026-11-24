<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Periodicidad del reporte de horas (mensual, bimestral, intermedio o final), según el esquema de la carrera.
 */
enum ReporteTipo: string
{
    case Mensual = 'mensual';
    case Bimestral = 'bimestral';
    case Intermedio = 'intermedio';   // "parcial": a la mitad de las horas
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual',
            self::Bimestral => 'Bimestral',
            self::Intermedio => 'Parcial',
            self::Final => 'Final',
        };
    }
}
