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

    /** Teléfono de México: 10 dígitos. */
    public const TELEFONO = '/^\d{10}$/';

    /** Extensión telefónica (conmutador UdG): de 1 a 6 números. */
    public const EXTENSION = '/^\d{1,6}$/';

    /** Une teléfono y extensión en un solo texto para mostrar: «3338193300 ext. 23549». */
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

        // Solo una extensión corta guardada sin «ext.» (p. ej. «23549»)
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
            'num_control.regex' => 'El código de alumno son exactamente 9 números (por ejemplo 219123456).',
            'name.regex' => 'Escribe tu nombre completo (nombre y apellido), solo con letras.',
            'representante_legal.regex' => 'Escribe el nombre completo del representante (nombre y apellido), solo con letras.',
            'representante_cargo.regex' => 'El cargo solo lleva letras, por ejemplo «Director General».',
            'contacto_cargo.regex' => 'El cargo solo lleva letras, por ejemplo «Coordinadora de Recursos Humanos».',
            'contacto_telefono.regex' => 'El teléfono son 10 números, sin espacios ni guiones (por ejemplo 3312345678).',
            'telefono.regex' => 'El teléfono son 10 números, sin espacios ni guiones (por ejemplo 3312345678).',
            'extension.regex' => 'La extensión solo lleva números (de 1 a 6), por ejemplo 23549.',
            'coordinacion_telefono.regex' => 'El teléfono son 10 números, sin espacios ni guiones (por ejemplo 3338193300).',
            'coordinacion_extension.regex' => 'La extensión solo lleva números (de 1 a 6), por ejemplo 23549.',
            'folio.regex' => 'El folio solo lleva letras mayúsculas, números y guiones (por ejemplo CV-2026-014).',
            'rfc.regex' => 'El RFC son 12 o 13 caracteres: letras, 6 números de la fecha y la homoclave (por ejemplo ABC010203XY1).',
            'notario_nombre.regex' => 'Escribe el nombre completo del notario, solo con letras.',
            'notaria_ciudad.regex' => 'La ciudad solo lleva letras, por ejemplo «Guadalajara, Jalisco».',
            'escritura_numero.regex' => 'El número de escritura solo lleva números, sin comas ni puntos.',
            'notaria_numero.regex' => 'El número de notaría solo lleva números.',
            'folio_mercantil.regex' => 'El folio mercantil solo lleva letras mayúsculas, números y guiones.',
        ];
    }
}
