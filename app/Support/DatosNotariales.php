<?php

namespace App\Support;

use App\Enums\TipoOrganismo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Datos notariales de la empresa (paso 1 del trámite de Vinculación).
 *
 * Las sociedades y asociaciones se constituyen ante notario: capturan escritura, fecha, notario, notaría
 * y folio mercantil por separado y el sistema redacta la declaración del convenio. Las dependencias públicas
 * (y «otro») se crean por ley o decreto: escriben su fundamento en texto libre.
 */
class DatosNotariales
{
    public const CAMPOS = ['escritura_numero', 'escritura_fecha', 'notario_nombre', 'notaria_numero', 'notaria_ciudad', 'folio_mercantil'];

    /** Tipos de organismo que acreditan su existencia con escritura pública. */
    public static function usaEscritura(TipoOrganismo|string|null $tipo): bool
    {
        $tipo = $tipo instanceof TipoOrganismo ? $tipo : TipoOrganismo::tryFrom((string) $tipo);

        return in_array($tipo, [TipoOrganismo::Privado, TipoOrganismo::SociedadCivil], true);
    }

    /** @return array<string, array<int, mixed>> */
    public static function reglas(string $campoTipo = 'tipo_organismo'): array
    {
        $escritura = [TipoOrganismo::Privado->value, TipoOrganismo::SociedadCivil->value];

        return [
            'escritura_numero' => [Rule::requiredIf(fn () => in_array(request($campoTipo), $escritura, true)), 'nullable', 'regex:' . Formatos::NUMERO],
            'escritura_fecha' => [Rule::requiredIf(fn () => in_array(request($campoTipo), $escritura, true)), 'nullable', 'date', 'before_or_equal:today'],
            'notario_nombre' => [Rule::requiredIf(fn () => in_array(request($campoTipo), $escritura, true)), 'nullable', 'string', 'min:5', 'max:255', 'regex:' . Formatos::NOMBRE_PERSONA],
            'notaria_numero' => [Rule::requiredIf(fn () => in_array(request($campoTipo), $escritura, true)), 'nullable', 'regex:' . Formatos::NUMERO],
            'notaria_ciudad' => [Rule::requiredIf(fn () => in_array(request($campoTipo), $escritura, true)), 'nullable', 'string', 'max:120', 'regex:' . Formatos::TEXTO_LETRAS],
            'folio_mercantil' => ['nullable', 'regex:' . Formatos::FOLIO],
            'constitucion' => [Rule::requiredIf(fn () => !in_array(request($campoTipo), $escritura, true)), 'nullable', 'string', 'min:15', 'max:1500'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return [
            'escritura_numero.required' => 'Escribe el número de la escritura pública (acta constitutiva).',
            'escritura_fecha.required' => 'Indica la fecha de la escritura pública.',
            'escritura_fecha.before_or_equal' => 'La fecha de la escritura no puede ser futura.',
            'notario_nombre.required' => 'Escribe el nombre del notario que dio fe.',
            'notaria_numero.required' => 'Escribe el número de la notaría.',
            'notaria_ciudad.required' => 'Escribe la ciudad de la notaría.',
            'constitucion.required' => 'Escribe la ley o el decreto de creación de tu institución.',
        ];
    }

    /**
     * Normaliza lo validado: con escritura se redacta la declaración «constitucion»; sin ella se limpian los campos notariales.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function preparar(array $datos, TipoOrganismo|string|null $tipo): array
    {
        if (!self::usaEscritura($tipo)) {
            return array_merge($datos, array_fill_keys(self::CAMPOS, null));
        }

        $datos['folio_mercantil'] = $datos['folio_mercantil'] ?? null;
        $datos['constitucion'] = self::redactar($datos);

        return $datos;
    }

    /** Texto de la declaración I de la ENTIDAD, a partir de los datos notariales. */
    public static function redactar(array $d): string
    {
        // «Lic.» lo agrega la redacción: si la empresa lo escribió en el nombre, no se duplica
        $d['notario_nombre'] = preg_replace('/^\s*lic(enciad[oa])?\.?\s+/iu', '', (string) $d['notario_nombre']);
        $fecha = Carbon::parse($d['escritura_fecha'])->locale('es')->translatedFormat('j \d\e F \d\e Y');
        $texto = "la escritura pública número {$d['escritura_numero']} de fecha {$fecha}, otorgada ante la fe del Lic. {$d['notario_nombre']}, "
            . "Notario Público número {$d['notaria_numero']} de {$d['notaria_ciudad']}";

        return $texto . (filled($d['folio_mercantil'] ?? null)
            ? ", inscrita en el Registro Público de Comercio bajo el folio mercantil {$d['folio_mercantil']}"
            : '');
    }
}
