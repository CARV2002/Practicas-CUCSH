<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Division;
use Illuminate\Database\Seeder;

class CarreraSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = [
            'División de Estudios Jurídicos' => [
                ['ABOG', 'Licenciatura en Abogado (Escolarizado)', 350, 50.00, 8],
                ['ABOS', 'Licenciatura en Abogado (Semiescolarizado)', 350, 50.00, 8],
                ['CRIM', 'Licenciatura en Criminología', 300, 60.00, 10],
            ],
            'División de Estudios Políticos y Sociales' => [
                ['LREI', 'Licenciatura en Relaciones Internacionales', 240, 60.00, 10],
                ['EPYG', 'Licenciatura en Estudios Políticos y Gobierno', 200, 50.00, 8],
                ['SOCI', 'Licenciatura en Sociología', 300, 55.00, 10],
            ],
            'División de Estudios Históricos y Humanos' => [
                ['HIST', 'Licenciatura en Historia', 480, 50.00, 10],
                ['LETR', 'Licenciatura en Letras Hispánicas', 120, 40.00, 6],
                ['FILO', 'Licenciatura en Filosofía', 200, 50.00, 8],
                ['ANTR', 'Licenciatura en Antropología', 250, 50.00, 10],
            ],
            'División de Estudios de Estado y Sociedad' => [
                ['GEOG', 'Licenciatura en Geografía', 250, 60.00, 10],
                ['COMP', 'Licenciatura en Comunicación Pública', 300, 60.00, 10],
            ],
            'División de Cultura Internacional' => [
                ['DIFL', 'Licenciatura en Didáctica del Francés como Lengua Extranjera', 200, 50.00, 8],
                ['DILE', 'Licenciatura en Docencia del Inglés como Lengua Extranjera', 200, 50.00, 8],
                ['ESCR', 'Licenciatura en Escritura Creativa', 150, 40.00, 6],
            ],
        ];

        foreach ($catalogo as $nombreDivision => $carreras) {
            $division = Division::firstOrCreate(['nombre' => $nombreDivision]);

            foreach ($carreras as [$clave, $nombre, $horas, $porcentaje, $creditos]) {
                Carrera::firstOrCreate(
                    ['carrera_clave' => $clave],
                    [
                        'division_id' => $division->id,
                        'nombre' => $nombre,
                        'horas_requeridas' => $horas,
                        'porcentaje_creditos_minimo' => $porcentaje,
                        'creditos_otorgados' => $creditos,
                    ]
                );
            }
        }
    }
}