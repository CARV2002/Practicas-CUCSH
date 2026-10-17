<?php

namespace App\Enums;

enum ConvenioEstado: string
{
    case Solicitado = 'solicitado';                         // Fase 1: revisión de documentos
    case EnValidacionAcademica = 'en_validacion_academica'; // Fase 2: dictámenes por carrera
    case EnRevisionJuridica = 'en_revision_juridica';       // Fase 3: borrador + Unidad Jurídica
    case EnFirmas = 'en_firmas';                            // Fase 4: empresa y Rectoría
    case Formalizado = 'formalizado';
    case Rechazado = 'rechazado';

    // "Vencido" NO es un estado: se calcula con Convenio::scopeVigentes().

    public function esFinal(): bool
    {
        return in_array($this, [self::Formalizado, self::Rechazado], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Solicitado => 'Solicitado (revisión de documentos)',
            self::EnValidacionAcademica => 'En validación académica',
            self::EnRevisionJuridica => 'En revisión jurídica',
            self::EnFirmas => 'En firmas',
            self::Formalizado => 'Formalizado',
            self::Rechazado => 'Rechazado',
        };
    }
}
