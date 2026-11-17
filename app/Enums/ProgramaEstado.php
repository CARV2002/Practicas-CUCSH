<?php

namespace App\Enums;

/**
 * Ciclo de una vacante:
 *  - empresa:      crea -> PendienteAprobacion -> (Coordinación) Publicado | Rechazado; puede cerrar la publicada
 *  - coordinación: sus programas internos nacen en Borrador y ella misma los publica
 * Solo Publicado es visible y postulable para el alumno.
 */
enum ProgramaEstado: string
{
    case Borrador = 'borrador';
    case PendienteAprobacion = 'pendiente_aprobacion';
    case Publicado = 'publicado';
    case Rechazado = 'rechazado';
    case Cerrado = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::PendienteAprobacion => 'Pendiente de aprobación',
            self::Publicado => 'Publicado',
            self::Rechazado => 'Rechazado',
            self::Cerrado => 'Cerrado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Publicado => 'bg-green-50 text-green-700',
            self::PendienteAprobacion => 'bg-yellow-50 text-yellow-700',
            self::Rechazado, self::Cerrado => 'bg-red-50 text-red-700',
            self::Borrador => 'bg-gray-100 text-gray-600',
        };
    }
}
