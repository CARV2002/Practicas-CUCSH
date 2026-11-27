<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CodigoPostal;
use App\Models\Empresa;
use App\Rules\UnicoNormalizado;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Solicitud de convenio por secciones (Etapa 2 del procedimiento de la empresa; sustituye al Google Form):
 *   1. generales  Datos generales y fiscales: razón social, giro, a qué se dedica, domicilio fiscal desglosado y enlace de RH.
 *   2. legales    Declaraciones legales: acta constitutiva (o documento de creación) y representante que firma.
 *   3. perfiles   Perfiles requeridos: carreras, objetivos, actividades, plazas, cómo se enteró y experiencia previa.
 *   4. documentos Expediente en PDF (se sube en «Documentos» con un solo botón).
 *
 * Al GUARDAR AVANCE solo se revisa el formato de lo capturado (la empresa puede terminar después);
 * al ENVIAR la solicitud todo debe estar completo.
 */
final class SolicitudEmpresa
{
    public const SECCIONES = [
        'generales' => 'Datos generales y fiscales',
        'legales' => 'Declaraciones legales',
        'perfiles' => 'Perfiles requeridos',
        'documentos' => 'Documentos en PDF',
    ];

    /** Campos de la empresa que se guardan en cada sección (perfiles vive en el borrador). */
    public const CAMPOS = [
        'generales' => ['razon_social', 'giro', 'descripcion', 'proyectos_principales', 'calle', 'numero_exterior', 'numero_interior',
            'colonia', 'codigo_postal', 'municipio', 'entidad', 'contacto_nombre', 'contacto_cargo', 'contacto_telefono'],
        'legales' => ['representante_legal', 'representante_cargo', 'objeto_social', 'facultad_origen', 'facultad_firmante',
            ...DatosNotariales::CAMPOS, 'constitucion',
            'poder_escritura_numero', 'poder_fecha', 'poder_notario_nombre', 'poder_notaria_numero', 'poder_notaria_ciudad'],
        'perfiles' => ['carreras', 'objetivos', 'actividades_propuestas', 'plazas_estimadas', 'medio_conocimiento', 'experiencia_previa'],
    ];

    /**
     * Campos que Vinculación puede devolver para corrección (sección => campos). Las carreras no: cambiarlas
     * es otra propuesta (se rechaza y la empresa envía una nueva).
     */
    public const CORREGIBLES = [
        'generales' => ['razon_social', 'giro', 'descripcion', 'proyectos_principales', 'calle', 'numero_exterior', 'numero_interior',
            'colonia', 'codigo_postal', 'municipio', 'entidad', 'contacto_nombre', 'contacto_cargo', 'contacto_telefono'],
        'legales' => ['representante_legal', 'representante_cargo', 'objeto_social', 'facultad_firmante', 'escritura_numero', 'escritura_fecha',
            'notario_nombre', 'notaria_numero', 'notaria_ciudad', 'folio_mercantil', 'constitucion'],
        'perfiles' => ['objetivos', 'actividades_propuestas', 'plazas_estimadas', 'medio_conocimiento', 'experiencia_previa'],
    ];

    /** Sección a la que pertenece un campo corregible (null si no se puede devolver). */
    public static function seccionDe(string $campo): ?string
    {
        foreach (self::CORREGIBLES as $seccion => $campos) {
            if (in_array($campo, $campos, true)) {
                return $seccion;
            }
        }

        return null;
    }

    public const ENTIDADES = ['Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche', 'Chiapas', 'Chihuahua', 'Ciudad de México',
        'Coahuila', 'Colima', 'Durango', 'Estado de México', 'Guanajuato', 'Guerrero', 'Hidalgo', 'Jalisco', 'Michoacán', 'Morelos', 'Nayarit',
        'Nuevo León', 'Oaxaca', 'Puebla', 'Querétaro', 'Quintana Roo', 'San Luis Potosí', 'Sinaloa', 'Sonora', 'Tabasco', 'Tamaulipas',
        'Tlaxcala', 'Veracruz', 'Yucatán', 'Zacatecas'];

    /**
     * Datos del código postal enviado si está en el catálogo local de SEPOMEX (null si no hay CP válido o no se encontró).
     *
     * @return array{cp:string, estado:string, municipio:string, colonias:list<string>}|null
     */
    private static function cpDelCatalogo(string $cp): ?array
    {
        return preg_match('/^\d{5}$/', $cp) === 1 ? CatalogoPostal::buscar($cp) : null;
    }

    /** Regla: el municipio o el estado enviado corresponde al código postal según el catálogo. */
    private static function coincideConCp(string $campo, string $cp): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fallar) use ($campo, $cp) {
            if (blank($valor)) {
                return;
            }

            // Mayúsculas y acentos no cuentan (la columna compara sin distinguirlos)
            if (! CodigoPostal::where('cp', $cp)->where($campo, trim((string) $valor))->exists()) {
                $correcto = CatalogoPostal::buscar($cp)[$campo] ?? '';
                $fallar($campo === 'estado'
                    ? "El estado no corresponde al código postal {$cp} ({$correcto})."
                    : "El municipio no corresponde al código postal {$cp} ({$correcto}).");
            }
        };
    }

    /** Normaliza lo que llega del formulario antes de validar (espacios, mayúsculas, números pegados con separadores). */
    public static function normalizar(array $datos): array
    {
        foreach (['razon_social', 'contacto_nombre', 'representante_legal', 'notario_nombre', 'poder_notario_nombre', 'calle', 'colonia', 'municipio', 'giro'] as $c) {
            if (array_key_exists($c, $datos)) {
                $datos[$c] = Formatos::limpiar($datos[$c]) ?: null;
            }
        }
        foreach (['contacto_telefono', 'codigo_postal', 'escritura_numero', 'poder_escritura_numero'] as $c) {
            if (array_key_exists($c, $datos)) {
                $datos[$c] = Formatos::digitos($datos[$c]) ?: null;
            }
        }
        foreach (['numero_exterior', 'numero_interior'] as $c) {
            if (array_key_exists($c, $datos)) {
                $datos[$c] = preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $datos[$c]))) ?: null;
            }
        }
        if (array_key_exists('folio_mercantil', $datos)) {
            $datos['folio_mercantil'] = mb_strtoupper(trim((string) $datos['folio_mercantil'])) ?: null;
        }

        return $datos;
    }

    /** @return array<string, array<int, mixed>> */
    public static function reglas(string $seccion, Empresa $empresa, bool $estricto): array
    {
        $req = fn (bool $cuando = true) => $estricto && $cuando ? 'required' : 'nullable';
        $escritura = DatosNotariales::usaEscritura($empresa->tipo_organismo);
        $poder = request('facultad_origen') === 'poder';
        // CP con el que se revisan municipio y estado: el que se envía o, al revisar lo guardado, el de la organización
        $cp = (string) (request('codigo_postal') ?? $empresa->codigo_postal);

        return match ($seccion) {
            'generales' => [
                'razon_social' => ['required', 'string', 'min:3', 'max:'.Formatos::MAX_RAZON_SOCIAL,
                    new UnicoNormalizado('empresas', 'razon_social', 'Ya hay otra organización registrada con esa razón social.', $empresa->id)],
                'giro' => [$req(), 'string', 'min:3', 'max:120'],
                'descripcion' => [$req(), 'string', 'min:30', 'max:1500'],
                'proyectos_principales' => [$req(), 'string', 'min:10', 'max:1500'],
                'calle' => [$req(), 'string', 'min:3', 'max:120'],
                'numero_exterior' => [$req(), 'string', 'max:15', 'regex:'.Formatos::NUMERO_EXTERIOR],
                'numero_interior' => ['nullable', 'string', 'max:15', 'regex:'.Formatos::NUMERO_INTERIOR],
                'colonia' => [$req(), 'string', 'min:3', 'max:120'],
                'codigo_postal' => [$req(), 'regex:'.Formatos::CODIGO_POSTAL],
                // Con el CP en el catálogo de SEPOMEX, municipio y estado deben ser los suyos (el catálogo manda,
                // aunque el municipio oficial lleve números, p. ej. «Ahualulco del Sonido 13»); sin él, se escriben a mano
                'municipio' => [$req(), 'string', 'min:3', 'max:120', ...(self::cpDelCatalogo($cp) ? [self::coincideConCp('municipio', $cp)] : ['regex:'.Formatos::TEXTO_LETRAS])],
                'entidad' => [$req(), Rule::in(self::ENTIDADES), ...(self::cpDelCatalogo($cp) ? [self::coincideConCp('estado', $cp)] : [])],
                'contacto_nombre' => ['required', 'string', 'min:5', 'max:'.Formatos::MAX_NOMBRE_PERSONA, 'regex:'.Formatos::NOMBRE_PERSONA],
                'contacto_cargo' => [$req(), 'string', 'min:3', 'max:120', 'regex:'.Formatos::TEXTO_LETRAS],
                'contacto_telefono' => [$req(), 'regex:'.Formatos::TELEFONO],
            ],
            'legales' => [
                'representante_legal' => [$req(), 'string', 'min:5', 'max:'.Formatos::MAX_NOMBRE_PERSONA, 'regex:'.Formatos::NOMBRE_PERSONA],
                'representante_cargo' => [$req(), 'string', 'min:3', 'max:120', 'regex:'.Formatos::TEXTO_LETRAS],
                'objeto_social' => [$req(), 'string', 'min:20', 'max:1500'],
                ...self::reglasCreacion($escritura, $estricto),
                // Facultades de quien firma: del acta o de un poder (sociedades); de su nombramiento (dependencias)
                'facultad_origen' => [$req(), Rule::in($escritura ? ['acta', 'poder'] : ['nombramiento'])],
                'facultad_firmante' => [$req(! $escritura), 'nullable', 'string', 'min:15', 'max:1500'],
                'poder_escritura_numero' => [$req($escritura && $poder), 'nullable', 'regex:'.Formatos::NUMERO],
                'poder_fecha' => [$req($escritura && $poder), 'nullable', 'date', 'before_or_equal:today'],
                'poder_notario_nombre' => [$req($escritura && $poder), 'nullable', 'string', 'min:5', 'max:'.Formatos::MAX_NOMBRE_PERSONA, 'regex:'.Formatos::NOMBRE_PERSONA],
                'poder_notaria_numero' => [$req($escritura && $poder), 'nullable', 'regex:'.Formatos::NUMERO],
                'poder_notaria_ciudad' => [$req($escritura && $poder), 'nullable', 'string', 'max:120', 'regex:'.Formatos::TEXTO_LETRAS],
            ],
            'perfiles' => [
                'carreras' => [$req(), 'array', $estricto ? 'min:1' : 'min:0'],
                'carreras.*' => ['integer', Rule::exists('carreras', 'id')],
                'objetivos' => [$req(), 'string', 'min:20', 'max:3000'],
                'actividades_propuestas' => [$req(), 'string', 'min:30', 'max:3000'],
                'plazas_estimadas' => [$req(), 'integer', 'min:1', 'max:500'],
                'medio_conocimiento' => [$req(), 'string', 'min:3', 'max:255'],
                'experiencia_previa' => [$req(), 'string', 'min:2', 'max:3000'],
            ],
            default => [],
        };
    }

    /** Escritura pública (sociedades) o documento de creación (dependencias). */
    private static function reglasCreacion(bool $escritura, bool $estricto): array
    {
        $req = fn (bool $cuando) => $estricto && $cuando ? 'required' : 'nullable';

        return [
            'escritura_numero' => [$req($escritura), 'nullable', 'regex:'.Formatos::NUMERO],
            'escritura_fecha' => [$req($escritura), 'nullable', 'date', 'before_or_equal:today'],
            'notario_nombre' => [$req($escritura), 'nullable', 'string', 'min:5', 'max:'.Formatos::MAX_NOMBRE_PERSONA, 'regex:'.Formatos::NOMBRE_PERSONA],
            'notaria_numero' => [$req($escritura), 'nullable', 'regex:'.Formatos::NUMERO],
            'notaria_ciudad' => [$req($escritura), 'nullable', 'string', 'max:120', 'regex:'.Formatos::TEXTO_LETRAS],
            // El folio mercantil se pide a las sociedades mercantiles (privadas)
            'folio_mercantil' => ['nullable', 'regex:'.Formatos::FOLIO],
            'constitucion' => [$req(! $escritura), 'nullable', 'string', 'min:15', 'max:1500'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return [
            'required' => 'Falta «:attribute».',
            'min' => '«:attribute» es muy corto: escribe al menos :min caracteres.',
            'max' => '«:attribute» no puede pasar de :max caracteres.',
            'carreras.required' => 'Elige al menos una carrera.',
            'carreras.min' => 'Elige al menos una carrera.',
            'codigo_postal.regex' => 'El código postal son 5 números.',
            'numero_exterior.regex' => 'Escribe solo el número y, si lleva, su letra (ej. 976, 12-B o S/N).',
            'numero_interior.regex' => 'Escribe el número interior (ej. 3, 3-A, Local 2 o Depto 4).',
            'entidad.in' => 'Elige el estado de la lista.',
            'poder_escritura_numero.regex' => 'El número de escritura del poder solo lleva números.',
            'poder_notaria_numero.regex' => 'El número de notaría solo lleva números.',
            'poder_notario_nombre.regex' => 'Escribe el nombre completo del notario, solo con letras.',
            'poder_notaria_ciudad.regex' => 'La ciudad solo lleva letras.',
            ...DatosNotariales::mensajes(),
            ...Formatos::mensajes(),
        ];
    }

    /** @return array<string, string> */
    public static function atributos(): array
    {
        return [
            'razon_social' => 'razón social', 'giro' => 'giro o actividad', 'descripcion' => 'a qué se dedica la organización',
            'proyectos_principales' => 'principales proyectos o clientes', 'calle' => 'calle', 'numero_exterior' => 'número exterior',
            'numero_interior' => 'número interior', 'colonia' => 'colonia', 'codigo_postal' => 'código postal', 'municipio' => 'municipio',
            'entidad' => 'estado', 'contacto_nombre' => 'nombre del contacto', 'contacto_cargo' => 'cargo del contacto',
            'contacto_telefono' => 'teléfono del contacto', 'representante_legal' => 'representante legal', 'representante_cargo' => 'cargo del representante',
            'objeto_social' => 'objeto social o atribuciones', 'facultad_origen' => 'de dónde vienen las facultades de quien firma',
            'facultad_firmante' => 'datos del nombramiento', 'escritura_numero' => 'número de escritura', 'escritura_fecha' => 'fecha de la escritura',
            'notario_nombre' => 'notario', 'notaria_numero' => 'número de notaría', 'notaria_ciudad' => 'ciudad de la notaría',
            'folio_mercantil' => 'folio mercantil', 'constitucion' => 'documento de creación',
            'poder_escritura_numero' => 'número de escritura del poder', 'poder_fecha' => 'fecha del poder', 'poder_notario_nombre' => 'notario del poder',
            'poder_notaria_numero' => 'notaría del poder', 'poder_notaria_ciudad' => 'ciudad de la notaría del poder',
            'carreras' => 'carreras', 'objetivos' => 'objetivos', 'actividades_propuestas' => 'actividades', 'plazas_estimadas' => 'plazas',
            'medio_conocimiento' => 'cómo se enteraron', 'experiencia_previa' => 'practicantes que han recibido',
        ];
    }

    /**
     * Prepara lo validado de una sección para guardarlo en la empresa: redacta las declaraciones del convenio
     * (constitución y facultades) y arma el domicilio en una línea.
     */
    public static function paraGuardar(string $seccion, array $datos, Empresa $empresa): array
    {
        if ($seccion === 'generales') {
            $e = (clone $empresa)->fill(Arr::only($datos, self::CAMPOS['generales']));
            $datos['domicilio_fiscal'] = filled($e->calle) && filled($e->codigo_postal) ? $e->domicilioCompleto() : $empresa->domicilio_fiscal;

            return $datos;
        }

        if ($seccion === 'legales') {
            $escritura = DatosNotariales::usaEscritura($empresa->tipo_organismo);
            if ($escritura) {
                if (filled($datos['escritura_numero'] ?? null) && filled($datos['escritura_fecha'] ?? null)
                    && filled($datos['notario_nombre'] ?? null) && filled($datos['notaria_numero'] ?? null) && filled($datos['notaria_ciudad'] ?? null)) {
                    $datos['constitucion'] = DatosNotariales::redactar($datos);
                }
                $datos['facultad_firmante'] = match ($datos['facultad_origen'] ?? null) {
                    'acta' => 'las facultades que le otorga la escritura constitutiva citada en la declaración I',
                    'poder' => filled($datos['poder_escritura_numero'] ?? null) && filled($datos['poder_fecha'] ?? null)
                        ? self::redactarPoder($datos) : null,
                    default => null,
                };
                if (($datos['facultad_origen'] ?? null) !== 'poder') {
                    foreach (['poder_escritura_numero', 'poder_fecha', 'poder_notario_nombre', 'poder_notaria_numero', 'poder_notaria_ciudad'] as $c) {
                        $datos[$c] = null;
                    }
                }
            } else {
                $datos = array_merge($datos, array_fill_keys(DatosNotariales::CAMPOS, null));
                $datos['facultad_origen'] = 'nombramiento';
            }
        }

        return $datos;
    }

    /** Declaración III: el poder notarial con el que firma el representante. */
    public static function redactarPoder(array $d): string
    {
        $fecha = Carbon::parse($d['poder_fecha'])->locale('es')->translatedFormat('j \d\e F \d\e Y');
        $notario = preg_replace('/^\s*lic(enciad[oa])?\.?\s+/iu', '', (string) ($d['poder_notario_nombre'] ?? ''));

        return "el poder notarial contenido en la escritura pública número {$d['poder_escritura_numero']} de fecha {$fecha}"
            .($notario ? ", otorgada ante la fe del Lic. {$notario}, Notario Público número {$d['poder_notaria_numero']} de {$d['poder_notaria_ciudad']}" : '')
            .', mismo que no le ha sido revocado ni limitado';
    }

    /**
     * Qué le falta a cada sección para poder enviar la solicitud.
     *
     * @return array<string, string[]> sección => campos faltantes (vacío = completa)
     */
    public static function faltantes(Empresa $empresa): array
    {
        return array_map(fn ($errores) => array_keys($errores), self::revisar($empresa));
    }

    /**
     * Lo que impide enviar la solicitud, separado: «vacios» (falta llenarlos, ámbar) y «corregir» (están llenos pero
     * mal escritos, rojo, con el mensaje de qué corregir).
     *
     * @return array<string, array{vacios: list<string>, corregir: array<string, string>}>
     */
    public static function estadoCampos(Empresa $empresa): array
    {
        $borrador = $empresa->solicitud_borrador ?? [];
        $resultado = [];

        foreach (self::revisar($empresa) as $seccion => $errores) {
            $resultado[$seccion] = ['vacios' => [], 'corregir' => []];
            foreach ($errores as $campo => $mensajes) {
                $base = preg_replace('/\.\d+$/', '', (string) $campo);
                $valor = match ($seccion) {
                    'perfiles' => $borrador[$base] ?? null,
                    'documentos' => null,
                    default => $empresa->{$base},
                };
                blank($valor)
                    ? $resultado[$seccion]['vacios'][] = (string) $campo
                    : $resultado[$seccion]['corregir'][(string) $campo] = (string) ($mensajes[0] ?? '');
            }
        }

        return $resultado;
    }

    /** @return array<string, array<string, list<string>>> errores de validación por sección (campo => mensajes) */
    private static function revisar(Empresa $empresa): array
    {
        $borrador = $empresa->solicitud_borrador ?? [];
        $faltan = [];

        // Las reglas leen del request (facultad_origen, codigo_postal): para revisar lo GUARDADO se ponen los valores
        // de la empresa y al final se regresan los que traía el request, para no alterar un envío en curso
        $contexto = ['facultad_origen', 'codigo_postal'];
        $originales = request()->only($contexto);
        request()->merge(['facultad_origen' => $empresa->facultad_origen, 'codigo_postal' => $empresa->codigo_postal]);

        try {
            foreach (['generales', 'legales'] as $seccion) {
                $datos = $empresa->only(self::CAMPOS[$seccion]);
                $datos['poder_fecha'] = $empresa->poder_fecha?->toDateString();
                $datos['escritura_fecha'] = $empresa->escritura_fecha?->toDateString();
                $v = validator($datos, self::reglas($seccion, $empresa, true), self::mensajes(), self::atributos());
                $faltan[$seccion] = $v->errors()->messages();
            }
        } finally {
            foreach ($contexto as $clave) {
                array_key_exists($clave, $originales)
                    ? request()->merge([$clave => $originales[$clave]])
                    : request()->request->remove($clave);
            }
        }

        $v = validator($borrador, self::reglas('perfiles', $empresa, true), self::mensajes(), self::atributos());
        $faltan['perfiles'] = $v->errors()->messages();

        $faltan['documentos'] = collect($empresa->obligatorias())
            ->filter(fn ($c) => $empresa->documentoVigente($c) === null)
            ->mapWithKeys(fn ($c) => [$c => ['Falta subirlo.']])->all();

        return $faltan;
    }

    public static function completa(Empresa $empresa): bool
    {
        return collect(self::faltantes($empresa))->flatten()->isEmpty();
    }
}
