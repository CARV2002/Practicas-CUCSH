<?php

namespace App\Enums;

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
