<?php

namespace App\Enums;

/**
 * Dónde está y en qué va un documento físico. Los estados "fuera de la oficina" tienen
 * un plazo de regreso (días hábiles) que alimenta los recordatorios automáticos.
 */
enum EstadoDocumentoFisico: string
{
    case PorElaborar = 'por_elaborar';
    case EnVinculacion = 'en_vinculacion';
    case EnSecretaria = 'en_secretaria';
    case EnDivision = 'en_division';
    case AcuseRecibido = 'acuse_recibido';
    case EnJuridica = 'en_juridica';
    case ConEmpresa = 'con_empresa';
    case FirmadoEmpresa = 'firmado_empresa';
    case EnRectoria = 'en_rectoria';
    case Firmado = 'firmado';
    case ConObservaciones = 'con_observaciones';
    case Entregado = 'entregado';
    case Archivado = 'archivado';
    case Extraviado = 'extraviado';

    public function label(): string
    {
        return match ($this) {
            self::PorElaborar => 'Por imprimir',
            self::EnVinculacion => 'En la oficina de Vínculos',
            self::EnSecretaria => 'En Secretaría Académica',
            self::EnDivision => 'En la División',
            self::AcuseRecibido => 'Acuse recibido',
            self::EnJuridica => 'En Unidad Jurídica',
            self::ConEmpresa => 'Con la empresa para firma',
            self::FirmadoEmpresa => 'Firmado por la empresa',
            self::EnRectoria => 'En Rectoría para firma',
            self::Firmado => 'Firmado por ambas partes',
            self::ConObservaciones => 'Devuelto con observaciones',
            self::Entregado => 'Entregado a su destino',
            self::Archivado => 'Archivado',
            self::Extraviado => 'Extraviado',
        };
    }

    public function familia(): string
    {
        return match ($this) {
            self::PorElaborar => 'gris',
            self::EnVinculacion, self::AcuseRecibido => 'cielo',
            self::EnSecretaria, self::EnDivision => 'lavanda',
            self::EnJuridica => 'indigo',
            self::ConEmpresa, self::EnRectoria => 'ambar',
            self::FirmadoEmpresa => 'turquesa',
            self::Firmado, self::Entregado => 'menta',
            self::Archivado => 'piedra',
            self::ConObservaciones, self::Extraviado => 'rosa',
        };
    }

    /** Quién tiene el papel en la mano. */
    public function custodio(): string
    {
        return match ($this) {
            self::EnSecretaria => 'Secretaría Académica',
            self::EnDivision => 'División',
            self::EnJuridica => 'Unidad Jurídica',
            self::ConEmpresa => 'Empresa',
            self::EnRectoria => 'Rectoría',
            self::Entregado => 'Destinatario',
            self::Extraviado => 'Sin ubicar',
            default => 'Vinculación',
        };
    }

    /** Plazo de regreso en días hábiles (según el proceso documentado); null = está en casa. */
    public function plazoHabiles(): ?int
    {
        return match ($this) {
            self::EnSecretaria, self::EnDivision => 5,
            self::EnJuridica => 15,
            self::ConEmpresa => 7,
            self::EnRectoria => 10,
            default => null,
        };
    }

    public function fueraDeOficina(): bool
    {
        return $this->plazoHabiles() !== null;
    }

    /** Ya no requiere seguimiento. */
    public function concluido(): bool
    {
        return in_array($this, [self::Entregado, self::Archivado, self::AcuseRecibido], true);
    }

    /** @return string[] */
    public static function fuera(): array
    {
        return array_map(fn (self $e) => $e->value, array_filter(self::cases(), fn (self $e) => $e->fueraDeOficina()));
    }

    /** @return string[] */
    public static function concluidos(): array
    {
        return array_map(fn (self $e) => $e->value, array_filter(self::cases(), fn (self $e) => $e->concluido()));
    }
}
