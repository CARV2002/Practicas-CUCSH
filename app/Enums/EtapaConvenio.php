<?php

namespace App\Enums;

/**
 * Etapas del proceso real de Vinculación. La columna es string: agregar una etapa
 * es agregar un caso aquí, sin migración.
 */
enum EtapaConvenio: string
{
    case RevisionDocumentos = 'revision_documentos';
    case TurnoAcademico = 'turno_academico';        // Secretaría -> División -> Coordinación
    case RedaccionBorrador = 'redaccion_borrador';
    case DictamenJuridico = 'dictamen_juridico';
    case FirmaEmpresa = 'firma_empresa';            // 3 juegos, firma al calce
    case FirmaRectoria = 'firma_rectoria';
    case ArchivoDistribucion = 'archivo_distribucion';

    public function label(): string
    {
        return match ($this) {
            self::RevisionDocumentos => 'Revisión de documentos',
            self::TurnoAcademico => 'Turno académico y dictámenes por carrera',
            self::RedaccionBorrador => 'Redacción del borrador',
            self::DictamenJuridico => 'Dictamen de viabilidad jurídica',
            self::FirmaEmpresa => 'Firma de la empresa (3 juegos)',
            self::FirmaRectoria => 'Firma de Rectoría',
            self::ArchivoDistribucion => 'Registro, archivo y entrega de juegos',
        };
    }

    /** Quién tiene la pelota en esa etapa. */
    public function responsable(): string
    {
        return match ($this) {
            self::RevisionDocumentos, self::RedaccionBorrador, self::ArchivoDistribucion => 'Vinculación',
            self::TurnoAcademico => 'Secretaría, Divisiones y Coordinaciones',
            self::DictamenJuridico => 'Unidad Jurídica',
            self::FirmaEmpresa => 'Empresa',
            self::FirmaRectoria => 'Rectoría',
        };
    }

    /**
     * Espera documentada en días hábiles [mínimo, máximo], según el proceso de Lorena.
     * null = el documento no da cifra (el turno académico es el gran punto ciego).
     *
     * @return array{0:int,1:int}|null
     */
    public function diasHabilesDocumentados(): ?array
    {
        return match ($this) {
            self::RevisionDocumentos => [1, 3],
            self::DictamenJuridico => [5, 15],
            self::FirmaEmpresa => [3, 7],
            self::FirmaRectoria => [5, 10],
            default => null,
        };
    }
}
