<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Papeles que siguen viajando con firmas autógrafas y sellos de tinta, y que Vinculación rastrea a mano:
 *  1. El expediente / borrador que se lleva en carpeta a la Unidad Jurídica (regresa con el dictamen).
 *  2 y 3. Los 3 juegos originales del convenio: con la empresa (firma al calce y sello) y en Rectoría
 *     (firma del Rector y reparto final).
 * OficioTurno queda solo por compatibilidad: el turno a las carreras es digital y ya no se rastrea.
 */
enum TipoDocumentoFisico: string
{
    /** @deprecated El turno a las coordinaciones es digital; ya no se crea ni se muestra. */
    case OficioTurno = 'oficio_turno';
    case DictamenJuridico = 'dictamen_juridico';
    case JuegoConvenio = 'juego_convenio';

    public function label(): string
    {
        return match ($this) {
            self::OficioTurno => 'Oficio de turno académico',
            self::DictamenJuridico => 'Expediente / borrador en Unidad Jurídica',
            self::JuegoConvenio => 'Juego original del convenio',
        };
    }

    /** Recorrido típico del papel, en una línea. */
    public function descripcion(): string
    {
        return match ($this) {
            self::OficioTurno => 'Secretaría Académica → División → acuse de recibo',
            self::DictamenJuridico => 'Carpeta física a revisión legal → regresa con el dictamen de viabilidad',
            self::JuegoConvenio => 'Con la empresa (firma al calce y sello) → Rectoría (firma del Rector) → reparto',
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

    /** Destino habitual de cada juego (editable): uno se queda en Vinculación, otro a la empresa y otro al archivo de Jurídica. */
    public static function destinoJuego(int $juego): string
    {
        return match ($juego) {
            1 => 'Archivo de Vinculación (CUCSH)',
            2 => 'Empresa',
            default => 'Archivo de la Unidad Jurídica',
        };
    }

    /** Cómo termina cada juego al formalizar: el de Vinculación se archiva; los otros dos se entregan a su destino. */
    public static function estadoFinalJuego(int $juego): EstadoDocumentoFisico
    {
        return $juego === 1 ? EstadoDocumentoFisico::Archivado : EstadoDocumentoFisico::Entregado;
    }
}
