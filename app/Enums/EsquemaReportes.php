<?php

namespace App\Enums;

/**
 * Periodicidad de reportes que define cada carrera en su reglamento interno.
 * Todas cierran con un reporte final; cambia lo que se entrega durante la práctica.
 */
enum EsquemaReportes: string
{
    case Mensual = 'mensual';
    case Bimestral = 'bimestral';
    case ParcialFinal = 'parcial_final';

    public function label(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual + final',
            self::Bimestral => 'Bimestral + final',
            self::ParcialFinal => 'Parcial (a la mitad de las horas) + final',
        };
    }

    /** @return ReporteTipo[] Tipos de reporte que el alumno puede entregar. */
    public function tipos(): array
    {
        return match ($this) {
            self::Mensual => [ReporteTipo::Mensual, ReporteTipo::Final],
            self::Bimestral => [ReporteTipo::Bimestral, ReporteTipo::Final],
            self::ParcialFinal => [ReporteTipo::Intermedio, ReporteTipo::Final],
        };
    }

    /** Tipos que se entregan una sola vez. */
    public function esUnico(ReporteTipo $tipo): bool
    {
        return $tipo === ReporteTipo::Final || ($this === self::ParcialFinal && $tipo === ReporteTipo::Intermedio);
    }
}
