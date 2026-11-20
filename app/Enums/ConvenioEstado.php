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

    /** Etiqueta general para la empresa (el detalle por etapa es del admin). */
    public function etiquetaEmpresa(): string
    {
        return match ($this) {
            self::Solicitado => 'Revisión de documentos',
            self::EnValidacionAcademica, self::EnRevisionJuridica => 'En revisión',
            self::EnFirmas => 'En firmas',
            self::Formalizado => 'Formalizado',
            self::Rechazado => 'Rechazado',
        };
    }

    /**
     * Estimado de respuesta que ve la empresa (general, con los plazos documentados de EtapaConvenio).
     * Vinculación ve el detalle exacto por etapa en su bandeja.
     */
    public function estimadoEmpresa(): string
    {
        return match ($this) {
            self::Solicitado => 'de 1 a 3 días hábiles para revisar tu expediente, una vez que esté completo.',
            self::EnValidacionAcademica => 'sin plazo fijo: depende de que cada carrera emita su dictamen de pertinencia. Te avisaremos en cuanto respondan.',
            self::EnRevisionJuridica => 'de 5 a 15 días hábiles para el dictamen de la Unidad Jurídica.',
            self::EnFirmas => 'de 8 a 17 días hábiles: de 3 a 7 para tu firma de los 3 juegos y de 5 a 10 para la firma de Rectoría.',
            self::Formalizado => 'trámite concluido.',
            self::Rechazado => 'trámite cerrado; para continuar inicia una nueva solicitud.',
        };
    }

    /** @return string[] Pasos generales que ve la empresa. */
    public static function pasosEmpresa(): array
    {
        return ['Revisión de documentos', 'En revisión', 'En firmas', 'Formalizado'];
    }

    /** Índice del paso actual en pasosEmpresa(); -1 si fue rechazado. */
    public function pasoEmpresa(): int
    {
        return match ($this) {
            self::Solicitado => 0,
            self::EnValidacionAcademica, self::EnRevisionJuridica => 1,
            self::EnFirmas => 2,
            self::Formalizado => 3,
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
