<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Bitácora de auditoría: solo se inserta. */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id', 'actor_nombre', 'actor_rol', 'accion', 'entidad_tipo', 'entidad_id',
        'descripcion', 'cambios', 'ip', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return ['cambios' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La bitácora de auditoría es de solo lectura.'));
        static::deleting(fn () => throw new LogicException('La bitácora de auditoría no se puede borrar.'));
    }
}