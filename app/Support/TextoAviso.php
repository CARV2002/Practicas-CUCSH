<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Parte un aviso largo en «título» (lo que pasó, en una línea) y «detalle» (motivo, qué hacer…),
 * para que la campana y la lista de notificaciones se lean de un vistazo.
 *
 *   «Tu coordinación te devolvió el reporte del 01/11 al 30/11. Motivo: «…». Qué hacer: …»
 *    → título «Tu coordinación te devolvió el reporte del 01/11 al 30/11»
 *    → detalle «Motivo: «…». Qué hacer: …»
 */
final class TextoAviso
{
    private const MAX_TITULO = 95;

    /** @return array{titulo: string, detalle: ?string} */
    public static function partes(?string $mensaje, ?string $titulo = null): array
    {
        $mensaje = trim((string) $mensaje) ?: 'Notificación';

        if (filled($titulo)) {
            return ['titulo' => $titulo, 'detalle' => $mensaje === $titulo ? null : $mensaje];
        }

        // 1. Formato estándar de correcciones: «… . Motivo: …» / «… . Qué hacer: …» / «… . Observaciones …»
        if (preg_match('/^(.{10,160}?)[.:]\s+((?:Motivo|Qué hacer|Observaciones)\b.*)$/su', $mensaje, $m)) {
            return ['titulo' => self::cortar($m[1]), 'detalle' => trim($m[2])];
        }

        // 2. Primera oración (o lo que va antes de «:») como título, si es corta
        if (preg_match('/^(.{10,'.self::MAX_TITULO.'}?)(?:[.!?]|:)\s+(\S.*)$/su', $mensaje, $m)) {
            return ['titulo' => $m[1], 'detalle' => trim($m[2])];
        }

        // 3. Una sola oración larga: título recortado y el texto completo como detalle
        if (mb_strlen($mensaje) > self::MAX_TITULO) {
            return ['titulo' => self::cortar($mensaje), 'detalle' => $mensaje];
        }

        return ['titulo' => rtrim($mensaje, '.'), 'detalle' => null];
    }

    private static function cortar(string $texto): string
    {
        $texto = rtrim(trim($texto), '.');

        return mb_strlen($texto) > self::MAX_TITULO ? Str::limit($texto, self::MAX_TITULO - 1, '…') : $texto;
    }
}
