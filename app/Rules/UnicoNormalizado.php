<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Único sin importar mayúsculas ni espacios de más: «División  de Estudios Jurídicos» y
 * «división de estudios jurídicos» son el mismo registro. Evita duplicados que Rule::unique deja pasar
 * en motores sensibles a mayúsculas o cuando alguien agrega espacios.
 */
class UnicoNormalizado implements ValidationRule
{
    public function __construct(
        private string $tabla,
        private string $columna,
        private string $mensaje,
        private ?int $ignorarId = null,
        private bool $conBorrados = false,
    ) {}

    public static function normalizar(?string $valor): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $valor)));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $buscado = self::normalizar((string) $value);

        $existe = DB::table($this->tabla)
            ->when($this->ignorarId !== null, fn ($q) => $q->where('id', '!=', $this->ignorarId))
            ->when(! $this->conBorrados && Schema::hasColumn($this->tabla, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->pluck($this->columna)
            ->contains(fn ($v) => self::normalizar($v) === $buscado);

        if ($existe) {
            $fail($this->mensaje);
        }
    }
}
