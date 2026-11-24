<?php

declare(strict_types=1);

namespace App\Support;

/** Formatos de presentación reutilizables en las vistas. */
final class Formato
{
    /**
     * Porcentaje sin ceros decimales sobrantes: 50.00 → «50», 72.50 → «72.5».
     * (Quitar ceros con rtrim directo convierte «50» en «5»: por eso se normaliza primero a dos decimales.)
     */
    public static function porcentaje(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');
    }
}
