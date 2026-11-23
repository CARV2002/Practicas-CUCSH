<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes del sistema por clave (sin migración por cada uno).
 *   correo_juridica → buzón institucional de la Unidad Jurídica que recibe los borradores turnados (opcional)
 */
class Configuracion extends Model
{
    public const CORREO_JURIDICA = 'correo_juridica';

    protected $table = 'configuraciones';
    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['clave', 'valor', 'actualizado_por'];

    public static function valor(string $clave, ?string $porDefecto = null): ?string
    {
        return static::find($clave)?->valor ?? $porDefecto;
    }

    public static function guardar(string $clave, ?string $valor, ?int $userId = null): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor, 'actualizado_por' => $userId]);
    }
}
