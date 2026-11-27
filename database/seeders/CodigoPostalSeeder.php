<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\CatalogoPostal;
use Illuminate\Database\Seeder;

/**
 * Catálogo de códigos postales de SEPOMEX (los 32 estados). Lee el archivo oficial de storage/app/sepomex/;
 * si el catálogo ya está cargado no lo repite (para actualizarlo: php artisan practicas:importar-cp).
 */
class CodigoPostalSeeder extends Seeder
{
    public function run(): void
    {
        if (CatalogoPostal::cargado()) {
            return;
        }

        $archivo = CatalogoPostal::archivoLocal();
        if ($archivo === null) {
            $this->command->warn('Catálogo de códigos postales sin cargar: guarda CPdescarga.txt de SEPOMEX en storage/app/sepomex/ y corre php artisan practicas:importar-cp. Mientras, la empresa escribe colonia y estado a mano.');

            return;
        }

        $total = CatalogoPostal::importar($archivo);
        $this->command->info("Catálogo de códigos postales: {$total} colonias.");
    }
}
