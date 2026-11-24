<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reglas de validación de los archivos que se suben al sistema, en un solo lugar: si cambia el límite
 * de tamaño o el tipo permitido, se cambia aquí y no en cada formulario.
 */
class ReglasArchivo
{
    /** Tamaño máximo de un PDF normal (expediente, cartas, reportes). */
    public const MB = 10;

    /** Escaneos completos con firmas y sellos (juegos del convenio): pesan más. */
    public const MB_ESCANEO = 20;

    /**
     * PDF de hasta $mb megas. Con $estricto además revisa el contenido real del archivo (no solo la extensión):
     * se usa en lo que sube el alumno, que suele renombrar fotos a «.pdf».
     *
     * @return list<string>
     */
    public static function pdf(int $mb = self::MB, bool $estricto = false): array
    {
        return array_values(array_filter([
            'file',
            'mimes:pdf',
            $estricto ? 'mimetypes:application/pdf' : null,
            'max:'.($mb * 1024),
        ]));
    }

    /** @return list<string> */
    public static function pdfRequerido(int $mb = self::MB, bool $estricto = false): array
    {
        return ['required', ...self::pdf($mb, $estricto)];
    }

    /** @return list<string> */
    public static function pdfOpcional(int $mb = self::MB, bool $estricto = false): array
    {
        return ['nullable', ...self::pdf($mb, $estricto)];
    }
}
