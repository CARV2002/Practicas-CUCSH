<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Estatus;

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
        // Compatibilidad: mismas clases pastel que <x-ui.estatus>
        return Estatus::chip($this->familia()).' ring-1 ring-inset';
    }

    /** Familia de color pastel para la insignia de estatus (DESIGN_SYSTEM.md §1.4). */
    public function familia(): string
    {
        return match ($this) {
            self::Borrador => 'gris',
            self::PendienteAprobacion => 'ambar',
            self::Publicado => 'menta',
            self::Rechazado => 'rosa',
            self::Cerrado => 'piedra',
        };
    }
}
