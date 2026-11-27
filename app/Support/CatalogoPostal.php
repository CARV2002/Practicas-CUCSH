<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CodigoPostal;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Catálogo local de códigos postales a partir del archivo oficial y gratuito de SEPOMEX (Correos de México):
 * «CPdescarga.txt» (texto separado por «|», en ISO-8859-1) o el .zip que lo contiene, con los 32 estados.
 * Se importa una vez (y cuando SEPOMEX publique una versión nueva); las consultas ya no dependen de internet.
 */
class CatalogoPostal
{
    /** Dónde busca el archivo el seeder (y el comando, si no se le indica otro). */
    public const RUTA = 'app/sepomex';

    /** Nombres de SEPOMEX → nombres de la lista de estados del sistema (SolicitudEmpresa::ENTIDADES). */
    private const ESTADOS = [
        'Coahuila de Zaragoza' => 'Coahuila',
        'Michoacán de Ocampo' => 'Michoacán',
        'Veracruz de Ignacio de la Llave' => 'Veracruz',
        'México' => 'Estado de México',
        'Distrito Federal' => 'Ciudad de México',
    ];

    /** Archivo del catálogo disponible en storage/app/sepomex (txt o zip), o null si aún no se descarga. */
    public static function archivoLocal(): ?string
    {
        foreach (['CPdescarga.txt', 'CPdescarga.zip'] as $nombre) {
            $ruta = storage_path(self::RUTA.'/'.$nombre);
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    public static function cargado(): bool
    {
        return CodigoPostal::query()->exists();
    }

    /**
     * Reemplaza el catálogo con el archivo indicado. Devuelve cuántos asentamientos quedaron.
     *
     * @param  (callable(int): void)|null  $avance  se llama cada bloque guardado con el total acumulado
     */
    public static function importar(string $ruta, ?callable $avance = null): int
    {
        $lineas = self::lineas($ruta);

        // La primera línea es una nota de SEPOMEX; los nombres de columna vienen en la que empieza con d_codigo
        $columnas = null;
        foreach ($lineas as $linea) {
            if (str_starts_with(trim($linea), 'd_codigo')) {
                $columnas = array_flip(array_map(fn ($c) => strtolower(trim($c)), explode('|', $linea)));
                break;
            }
        }
        if ($columnas === null) {
            throw new RuntimeException('El archivo no tiene el formato del catálogo de SEPOMEX (falta la fila de encabezados «d_codigo|…»).');
        }
        foreach (['d_codigo', 'd_asenta', 'd_mnpio', 'd_estado'] as $requerida) {
            if (! isset($columnas[$requerida])) {
                throw new RuntimeException("Al archivo le falta la columna «{$requerida}».");
            }
        }

        $total = 0;
        DB::transaction(function () use ($lineas, $columnas, $avance, &$total) {
            CodigoPostal::query()->delete();
            $bloque = [];

            // El generador sigue justo después de la fila de encabezados
            $lineas->next();
            for (; $lineas->valid(); $lineas->next()) {
                $linea = (string) $lineas->current();
                if (trim($linea) === '') {
                    continue;
                }
                $c = explode('|', $linea);
                $cp = str_pad(trim($c[$columnas['d_codigo']] ?? ''), 5, '0', STR_PAD_LEFT);
                $colonia = trim($c[$columnas['d_asenta']] ?? '');
                if (preg_match('/^\d{5}$/', $cp) !== 1 || $colonia === '') {
                    continue;
                }

                $bloque[] = [
                    'cp' => $cp,
                    'colonia' => mb_substr($colonia, 0, 255),
                    'tipo_asentamiento' => isset($columnas['d_tipo_asenta']) ? (trim($c[$columnas['d_tipo_asenta']] ?? '') ?: null) : null,
                    'municipio' => mb_substr(trim($c[$columnas['d_mnpio']] ?? ''), 0, 255),
                    'estado' => self::estado(trim($c[$columnas['d_estado']] ?? '')),
                ];

                if (count($bloque) === 1000) {
                    CodigoPostal::insert($bloque);
                    $total += count($bloque);
                    $bloque = [];
                    $avance && $avance($total);
                }
            }

            if ($bloque) {
                CodigoPostal::insert($bloque);
                $total += count($bloque);
                $avance && $avance($total);
            }
        });

        return $total;
    }

    /**
     * Estado, municipio y colonias de un código postal; null si no existe en el catálogo.
     *
     * @return array{cp:string, estado:string, municipio:string, colonias:list<string>}|null
     */
    public static function buscar(string $cp): ?array
    {
        $filas = CodigoPostal::query()->where('cp', $cp)->orderBy('colonia')->get(['colonia', 'municipio', 'estado']);
        if ($filas->isEmpty()) {
            return null;
        }

        return [
            'cp' => $cp,
            'estado' => (string) $filas->first()->estado,
            // Casi siempre es uno; si el CP cruza municipios, el que más colonias tiene
            'municipio' => (string) $filas->countBy('municipio')->sortDesc()->keys()->first(),
            'colonias' => $filas->pluck('colonia')->unique()->values()->all(),
        ];
    }

    public static function estado(string $estado): string
    {
        return self::ESTADOS[$estado] ?? $estado;
    }

    /**
     * Líneas del catálogo en UTF-8, una por una, desde el .txt o desde el .zip que lo trae.
     *
     * @return \Generator<int, string>
     */
    private static function lineas(string $ruta): \Generator
    {
        if (! is_file($ruta)) {
            throw new RuntimeException("No existe el archivo {$ruta}.");
        }

        $origen = $ruta;
        if (str_ends_with(strtolower($ruta), '.zip')) {
            $zip = new ZipArchive;
            if ($zip->open($ruta) !== true) {
                throw new RuntimeException('No se pudo abrir el .zip del catálogo.');
            }
            $interno = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = (string) $zip->getNameIndex($i);
                if (str_ends_with(strtolower($nombre), '.txt')) {
                    $interno = $nombre;
                    break;
                }
            }
            $zip->close();
            if ($interno === null) {
                throw new RuntimeException('El .zip no contiene el archivo de texto del catálogo.');
            }
            $origen = 'zip://'.$ruta.'#'.$interno;
        }

        $manejador = fopen($origen, 'rb');
        if ($manejador === false) {
            throw new RuntimeException('No se pudo leer el catálogo.');
        }

        try {
            while (($linea = fgets($manejador)) !== false) {
                $linea = rtrim($linea, "\r\n");
                // SEPOMEX publica en ISO-8859-1; si ya viene en UTF-8 se deja igual
                yield mb_check_encoding($linea, 'UTF-8') ? $linea : mb_convert_encoding($linea, 'UTF-8', 'ISO-8859-1');
            }
        } finally {
            fclose($manejador);
        }
    }
}
