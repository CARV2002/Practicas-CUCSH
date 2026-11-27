<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatos de captura de los registros (alumno, Google y empresa). Las mismas reglas viven en el navegador
 * (atributos pattern, maxlength y data-solo de resources/js/validacion.js), así el usuario ve el error al escribir
 * y el servidor lo vuelve a revisar al enviar.
 */
class Formatos
{
    /** Largo máximo del nombre completo de una persona (alumno, contacto, representante, notario). */
    public const MAX_NOMBRE_PERSONA = 80;

    /** Largo máximo de la razón social (la del SAT rara vez pasa de 120). */
    public const MAX_RAZON_SOCIAL = 150;

    /** Código postal de México: 5 dígitos. */
    public const CODIGO_POSTAL = '/^\d{5}$/';

    /** Código de alumno UdG: exactamente 9 dígitos. */
    public const CODIGO_ALUMNO = '/^\d{9}$/';

    public const CODIGO_ALUMNO_HTML = '\d{9}';

    /** Nombre de persona: letras (con acentos), espacios, punto, guion y apóstrofo; al menos nombre y apellido. */
    public const NOMBRE_PERSONA = "/^[\\pL'.-]+(?:\\s+[\\pL'.-]+)+$/u";

    public const NOMBRE_PERSONA_HTML = "[A-Za-zÁÉÍÓÚÜÑáéíóúüñ'.\\-]+(\\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ'.\\-]+)+";

    /** Cargo, ciudad: letras, espacios y signos básicos (sin números). */
    public const TEXTO_LETRAS = "/^[\\pL'.,\\s-]+$/u";

    /**
     * Teléfono de México: 10 dígitos, sin empezar con 0 o 1 (no existen así) y sin repetir el mismo número
     * 7 veces seguidas (descarta 0000000000, 3300000000, 5555555555…).
     */
    public const TELEFONO = '/^(?!\d*(\d)\1{6})[2-9]\d{9}$/';

    /** Mismo criterio para el atributo pattern del navegador. */
    public const TELEFONO_HTML = '(?![0-9]*([0-9])\1{6})[2-9][0-9]{9}';

    /** Extensión telefónica (conmutador UdG): de 1 a 6 números. */
    public const EXTENSION = '/^\d{1,6}$/';

    /** Une teléfono y extensión en un solo texto para mostrar: «3312345678 ext. 1234». */
    public static function telefonoConExtension(?string $telefono, ?string $extension): ?string
    {
        $telefono = self::digitos($telefono);
        $extension = self::digitos($extension);

        return trim($telefono.($extension !== '' ? ($telefono !== '' ? ' ' : '').'ext. '.$extension : '')) ?: null;
    }

    /**
     * Separa lo guardado en teléfono y extensión para precargar el formulario.
     *
     * @return array{0: string, 1: string}
     */
    public static function separarTelefono(?string $valor): array
    {
        $valor = (string) $valor;
        $partes = preg_split('/\s*ext\.?\s*/i', $valor, 2);
        $telefono = preg_replace('/\D+/', '', $partes[0] ?? '');
        $extension = preg_replace('/\D+/', '', $partes[1] ?? '');

        // Solo una extensión corta guardada sin «ext.» (p. ej. «1234»)
        if ($extension === '' && strlen($telefono) > 0 && strlen($telefono) <= 6) {
            return ['', $telefono];
        }

        return [$telefono, $extension];
    }

    /** RFC de persona moral (12) o física (13). */
    public const RFC = '/^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/u';

    public const RFC_HTML = '[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}';

    /** Números de escritura y de notaría. */
    public const NUMERO = '/^\d{1,10}$/';

    /**
     * Número exterior del domicilio (ya en mayúsculas): 976 · 12-B · 1500 BIS · S/N · KM 12.5.
     * Evita texto libre como «988-Lorem Ipsum» en un dato que va impreso en el convenio.
     */
    public const NUMERO_EXTERIOR = '/^(S\/N|SN|KM ?\d{1,4}(\.\d{1,2})?|\d{1,6}( ?- ?| )?([A-Z]{1,2}|BIS)?)$/u';

    /** Mismo formato para el atributo pattern del navegador (sin delimitadores; el navegador lo ancla solo). */
    public const NUMERO_EXTERIOR_HTML = 'S\/N|SN|KM ?[0-9]{1,4}(\.[0-9]{1,2})?|[0-9]{1,6}( ?- ?| )?([A-Z]{1,2}|BIS)?';

    /** Número interior: 3 · 3-A · B · LOCAL 2 · DEPTO 4 · PISO 2 (siempre con un número, salvo una o dos letras). */
    public const NUMERO_INTERIOR = '/^((DEPTO|DEP|LOCAL|PISO|INT|OF|OFICINA|EDIF|TORRE|BODEGA|CASA)\.? ?[A-Z0-9-]{1,4}|[A-Z]{1,2}|\d{1,5}( ?- ?)?[A-Z]{0,2})$/u';

    public const NUMERO_INTERIOR_HTML = '(DEPTO|DEP|LOCAL|PISO|INT|OF|OFICINA|EDIF|TORRE|BODEGA|CASA)\.? ?[A-Z0-9\-]{1,4}|[A-Z]{1,2}|[0-9]{1,5}( ?- ?)?[A-Z]{0,2}';

    /** Folio mercantil: letras mayúsculas, números y guiones. */
    public const FOLIO = '/^[A-Z0-9-]{3,60}$/';

    /** Quita espacios dobles y de los extremos. */
    public static function limpiar(?string $v): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $v));
    }

    /**
     * Quita los separadores de un número pegado («215-123 456», «33 1234 5678», «12,345»).
     * Las letras se conservan para que la validación diga claramente que solo van números.
     */
    public static function digitos(?string $v): string
    {
        return preg_replace('/[\s.,()\-]+/u', '', trim((string) $v));
    }

    /** @return array<string, string> mensajes claros para cada formato */
    public static function mensajes(): array
    {
        return [
            'num_control.regex' => 'El código de alumno son 9 números (ej. 000000000).',
            'name.regex' => 'Escribe tu nombre completo (nombre y apellido), solo con letras.',
            'representante_legal.regex' => 'Escribe nombre y apellido del representante, solo con letras.',
            'representante_cargo.regex' => 'El cargo solo lleva letras (ej. «Director General»).',
            'contacto_cargo.regex' => 'El cargo solo lleva letras (ej. «Coordinadora de RH»).',
            'contacto_telefono.regex' => 'Escribe un teléfono real de 10 números (ej. 3312345678).',
            'telefono.regex' => 'Escribe un teléfono real de 10 números (ej. 3312345678).',
            'extension.regex' => 'La extensión son de 1 a 6 números (ej. 1234).',
            'coordinacion_telefono.regex' => 'Escribe un teléfono real de 10 números (ej. 3312345678).',
            'coordinacion_extension.regex' => 'La extensión son de 1 a 6 números (ej. 1234).',
            'folio.regex' => 'El folio lleva mayúsculas, números y guiones (ej. CV-2026-001).',
            'rfc.regex' => 'El RFC son 12 o 13 caracteres (ej. ABC010101XYZ).',
            'notario_nombre.regex' => 'Escribe el nombre completo del notario, solo con letras.',
            'notaria_ciudad.regex' => 'La ciudad solo lleva letras (ej. «Guadalajara, Jalisco»).',
            'escritura_numero.regex' => 'El número de escritura solo lleva números.',
            'notaria_numero.regex' => 'El número de notaría solo lleva números.',
            'folio_mercantil.regex' => 'El folio mercantil lleva mayúsculas, números y guiones.',
        ];
    }
}
