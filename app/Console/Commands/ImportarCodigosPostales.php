<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CatalogoPostal;
use Illuminate\Console\Command;
use Throwable;

/**
 * Carga (o actualiza) el catálogo local de códigos postales con el archivo oficial de SEPOMEX.
 *   php artisan practicas:importar-cp                      → usa storage/app/sepomex/CPdescarga.txt (o .zip)
 *   php artisan practicas:importar-cp C:\ruta\CPdescarga.zip
 */
class ImportarCodigosPostales extends Command
{
    protected $signature = 'practicas:importar-cp {archivo? : Ruta al CPdescarga.txt o .zip de SEPOMEX}';

    protected $description = 'Importa el catálogo oficial de códigos postales de SEPOMEX (reemplaza el actual)';

    public function handle(): int
    {
        $ruta = $this->argument('archivo') ?: CatalogoPostal::archivoLocal();

        if (! is_string($ruta) || $ruta === '') {
            $this->error('No encontré el catálogo. Descarga «CPdescarga.txt» (todos los estados) de Correos de México y guárdalo en storage/app/sepomex/.');

            return self::FAILURE;
        }

        $this->info("Importando {$ruta}…");

        try {
            $total = CatalogoPostal::importar($ruta, function (int $n) {
                if ($n % 20000 === 0) {
                    $this->line("  {$n} colonias…");
                }
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Listo: {$total} colonias en el catálogo.");

        return self::SUCCESS;
    }
}
