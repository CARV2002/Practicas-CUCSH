<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RFC con el formato reglamentario del SAT:
 *  - Persona moral (empresas, asociaciones, dependencias): 12 caracteres = 3 letras + fecha AAMMDD + 3 de homoclave.
 *  - Persona física con actividad empresarial: 13 caracteres = 4 letras + fecha AAMMDD + 3 de homoclave.
 * La fecha debe existir y no se aceptan los RFC genéricos (público en general y extranjeros).
 * Se espera ya normalizado (mayúsculas, sin espacios ni guiones): ver Rfc::normalizar().
 */
class Rfc implements ValidationRule
{
    public const GENERICOS = ['XAXX010101000', 'XEXX010101000'];

    public static function normalizar(?string $rfc): string
    {
        return mb_strtoupper(preg_replace('/[\s\-_.]+/u', '', (string) $rfc));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rfc = (string) $value;

        if (in_array($rfc, self::GENERICOS, true)) {
            $fail('Ese es un RFC genérico del SAT: escribe el RFC propio de tu organización.');

            return;
        }

        if (! preg_match('/^([A-ZÑ&]{3,4})(\d{2})(\d{2})(\d{2})([A-Z\d]{3})$/u', $rfc, $m)) {
            $fail(mb_strlen($rfc) < 12 || mb_strlen($rfc) > 13
                ? 'El RFC lleva 12 caracteres si es persona moral (ej. ABC010101XYZ) o 13 si es persona física (ej. ABCD010101XYZ).'
                : 'El RFC no tiene el formato del SAT: letras iniciales, 6 dígitos de fecha (AAMMDD) y 3 de homoclave.');

            return;
        }

        [, , $aa, $mm, $dd] = $m;
        if (! checkdate((int) $mm, (int) $dd, 2000 + (int) $aa) && ! checkdate((int) $mm, (int) $dd, 1900 + (int) $aa)) {
            $fail("La fecha del RFC ({$aa}{$mm}{$dd}) no existe: revisa los 6 dígitos de fecha (AAMMDD).");
        }
    }
}
