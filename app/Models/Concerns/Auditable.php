<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Auditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra en la bitácora cada alta, cambio o baja del modelo, con su nombre legible y sin campos sensibles.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => Auditoria::modelo($m, 'creado'));
        static::updated(fn (Model $m) => Auditoria::modelo($m, 'actualizado'));
        static::deleted(fn (Model $m) => Auditoria::modelo($m, 'eliminado'));
    }

    /** Nombre legible; también arma la clave de la acción (Práctica → practica.actualizado). */
    public function auditoriaEtiqueta(): string
    {
        return class_basename($this);
    }

    /** Referencia corta para la descripción (folio, razón social...). */
    public function auditoriaNombre(): ?string
    {
        return null;
    }

    /** @return string[] campos que no se registran */
    public function auditoriaOmitir(): array
    {
        return [];
    }
}
