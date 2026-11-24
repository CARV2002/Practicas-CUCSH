<?php

declare(strict_types=1);

namespace App\Services;

use App\Informes\Informe;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga un informe como CSV que Excel abre bien: con BOM (para los acentos), por lotes de 200
 * registros (no carga todo en memoria) y sin fórmulas ejecutables.
 */
class ExportadorCsv
{
    public function descargar(Informe $informe, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($informe) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");   // BOM: Excel respeta los acentos
            fputcsv($salida, $informe->encabezados(), ',', '"', '');

            foreach ($informe->consulta()->lazyById(200) as $modelo) {
                $valores = $informe->fila($modelo);
                if ($valores === null) {
                    continue;   // filtro calculado (p. ej. situación de la empresa)
                }
                fputcsv($salida, array_map(self::limpiar(...), $valores), ',', '"', '');
            }

            fclose($salida);
        }, $nombre.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutraliza fórmulas: un texto que empiece con = + - @ no debe ejecutarse al abrirlo en Excel. */
    public static function limpiar(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}
