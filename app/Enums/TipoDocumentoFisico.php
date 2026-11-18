<?php

namespace App\Enums;

/**
 * Papeles del trámite híbrido que Vinculación controla a mano: el oficio de turno, el
 * Dictamen de Viabilidad Jurídica en original y los 3 juegos del convenio que firman
 * la empresa y Rectoría.
 */
enum TipoDocumentoFisico: string
{
    case OficioTurno = 'oficio_turno';
    case DictamenJuridico = 'dictamen_juridico';
    case JuegoConvenio = 'juego_convenio';

    public function label(): string
    {
        return match ($this) {
            self::OficioTurno => 'Oficio de turno académico',
            self::DictamenJuridico => 'Dictamen de viabilidad jurídica',
            self::JuegoConvenio => 'Juego original del convenio',
        };
    }

    /** Recorrido típico del papel, en una línea. */
    public function descripcion(): string
    {
        return match ($this) {
            self::OficioTurno => 'Secretaría Académica → División → acuse de recibo',
            self::DictamenJuridico => 'Original que devuelve la Unidad Jurídica',
            self::JuegoConvenio => 'Firma de la empresa → firma de Rectoría → entrega',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::OficioTurno => 'enviar',
            self::DictamenJuridico => 'juridico',
            self::JuegoConvenio => 'firma',
        };
    }

    /** @return EstadoDocumentoFisico[] Camino de ese papel, en orden. */
    public function estados(): array
    {
        return match ($this) {
            self::OficioTurno => [
                EstadoDocumentoFisico::PorElaborar, EstadoDocumentoFisico::EnVinculacion,
                EstadoDocumentoFisico::EnSecretaria, EstadoDocumentoFisico::EnDivision,
                EstadoDocumentoFisico::AcuseRecibido, EstadoDocumentoFisico::Archivado,
                EstadoDocumentoFisico::Extraviado,
            ],
            self::DictamenJuridico => [
                EstadoDocumentoFisico::EnJuridica, EstadoDocumentoFisico::ConObservaciones,
                EstadoDocumentoFisico::EnVinculacion, EstadoDocumentoFisico::Archivado,
                EstadoDocumentoFisico::Extraviado,
            ],
            self::JuegoConvenio => [
                EstadoDocumentoFisico::PorElaborar, EstadoDocumentoFisico::EnVinculacion,
                EstadoDocumentoFisico::ConEmpresa, EstadoDocumentoFisico::FirmadoEmpresa,
                EstadoDocumentoFisico::EnRectoria, EstadoDocumentoFisico::Firmado,
                EstadoDocumentoFisico::ConObservaciones, EstadoDocumentoFisico::Entregado,
                EstadoDocumentoFisico::Archivado, EstadoDocumentoFisico::Extraviado,
            ],
        };
    }

    /** Destino habitual de cada juego (editable). */
    public static function destinoJuego(int $juego): string
    {
        return match ($juego) {
            1 => 'Archivo de Vinculación (CUCSH)',
            2 => 'Empresa',
            default => 'Rectoría',
        };
    }
}
