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
            self::Solicitado => 'En revisión de documentos',
            self::EnValidacionAcademica => 'En dictamen académico',
            self::EnRevisionJuridica => 'En revisión jurídica',
            self::EnFirmas => 'En firmas',
            self::Formalizado => 'Formalizado',
            self::Rechazado => 'Rechazado',
        };
    }

    /** Etiqueta para la empresa: en qué revisión va su solicitud. */
    public function etiquetaEmpresa(): string
    {
        return match ($this) {
            self::Solicitado => 'Revisión de documentos',
            self::EnValidacionAcademica => 'En revisión académica',
            self::EnRevisionJuridica => 'En revisión jurídica',
            self::EnFirmas => 'En firmas',
            self::Formalizado => 'Formalizado',
            self::Rechazado => 'Rechazado',
        };
    }

    /**
     * Qué esperar en cada etapa. No hay plazos estipulados para cada proceso, así que no se promete
     * una cifra: se dice quién tiene el trámite y que se responde lo más pronto posible.
     */
    public function estimadoEmpresa(): string
    {
        return match ($this) {
            self::Solicitado => 'Vinculación revisa tus documentos. Te responderemos lo más pronto posible y te avisaremos por correo si alguno necesita corrección.',
            self::EnValidacionAcademica => 'Las coordinaciones de las carreras que elegiste revisan tu propuesta. Te avisaremos en cuanto respondan.',
            self::EnRevisionJuridica => 'La Unidad Jurídica del CUCSH revisa el convenio. Te avisaremos en cuanto lo apruebe.',
            self::EnFirmas => 'Vinculación te avisará cuando los 3 juegos estén listos para que tu representante los firme; después pasan a firma de Rectoría.',
            self::Formalizado => 'Trámite concluido.',
            self::Rechazado => 'Trámite cerrado; para continuar inicia una nueva solicitud.',
        };
    }

    /** @return string[] Pasos que ve la empresa. */
    public static function pasosEmpresa(): array
    {
        return ['Revisión de documentos', 'Revisión académica', 'Revisión jurídica', 'Firmas', 'Formalizado'];
    }

    /** Índice del paso actual en pasosEmpresa(); -1 si fue rechazado. */
    public function pasoEmpresa(): int
    {
        return match ($this) {
            self::Solicitado => 0,
            self::EnValidacionAcademica => 1,
            self::EnRevisionJuridica => 2,
            self::EnFirmas => 3,
            self::Formalizado => 4,
            self::Rechazado => -1,
        };
    }

    /** Familia de color pastel para la insignia de estatus (DESIGN_SYSTEM.md §1.4). */
    public function familia(): string
    {
        return match ($this) {
            self::Solicitado => 'cielo',
            self::EnValidacionAcademica => 'lavanda',
            self::EnRevisionJuridica => 'indigo',
            self::EnFirmas => 'ambar',
            self::Formalizado => 'menta',
            self::Rechazado => 'rosa',
        };
    }
}
