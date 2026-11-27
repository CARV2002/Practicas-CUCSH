<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un asentamiento (colonia) del catálogo oficial de SEPOMEX. Solo lectura para la aplicación:
 * se reemplaza completo al importar una versión nueva del catálogo (App\Support\CatalogoPostal).
 *
 * @property string $cp
 * @property string $colonia
 * @property string|null $tipo_asentamiento
 * @property string $municipio
 * @property string $estado
 */
class CodigoPostal extends Model
{
    protected $table = 'codigos_postales';

    public $timestamps = false;

    protected $fillable = ['cp', 'colonia', 'tipo_asentamiento', 'municipio', 'estado'];
}
