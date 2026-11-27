<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConvenioEstado;
use App\Enums\TipoOrganismo;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ValidaDocumentos;
use App\Support\DatosNotariales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Organización (empresa, dependencia o asociación) con su cuenta, expediente, declaraciones legales y convenios.
 */
class Empresa extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia, SoftDeletes, ValidaDocumentos;

    protected $table = 'empresas';

    protected $fillable = [
        'user_id',
        'rfc',
        'razon_social',
        'tipo_organismo',
        'domicilio_fiscal',
        'representante_legal',
        'representante_cargo',
        'constitucion',
        'escritura_numero', 'escritura_fecha', 'notario_nombre', 'notaria_numero', 'notaria_ciudad', 'folio_mercantil',
        'objeto_social',
        'facultad_firmante',
        'contacto_nombre',
        'contacto_cargo',
        'contacto_telefono',
        'descripcion',
        'proyectos_principales',
        'tipo_organismo_otro', 'giro', 'calle', 'numero_exterior', 'numero_interior', 'colonia', 'codigo_postal', 'municipio', 'entidad',
        'facultad_origen', 'poder_escritura_numero', 'poder_fecha', 'poder_notario_nombre', 'poder_notaria_numero', 'poder_notaria_ciudad',
        'solicitud_borrador',
    ];

    protected function casts(): array
    {
        return ['tipo_organismo' => TipoOrganismo::class, 'escritura_fecha' => 'date', 'poder_fecha' => 'date', 'solicitud_borrador' => 'array'];
    }

    /** Expediente: datos personales sensibles -> disco "local" (privado, storage/app/private). */
    public function registerMediaCollections(): void
    {
        foreach (self::coleccionesPdf() as $coleccion) {
            $this->addMediaCollection($coleccion)
                ->useDisk('local')
                ->acceptsMimeTypes(['application/pdf']);
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Convenio, $this> */
    public function convenios(): HasMany
    {
        return $this->hasMany(Convenio::class);
    }

    /** @return string[] */
    public static function coleccionesPdf(): array
    {
        return [
            'acta_constitutiva',
            'documento_creacion',
            'nombramiento_representante',
            'constancia_situacion_fiscal',
            'identificacion_representante',
            'comprobante_domicilio',
            'poder_notarial',
            'cv_instancia',
        ];
    }

    /** Documentos que deben estar validados antes de turnar el convenio a las carreras. */
    /** Nombre legible de cada documento del expediente. */
    public static function nombreDocumento(string $coleccion): string
    {
        return [
            'cv_instancia' => 'Currículum de la instancia',
            'identificacion_representante' => 'Identificación oficial del representante (INE o pasaporte)',
            'acta_constitutiva' => 'Acta constitutiva',
            'documento_creacion' => 'Documento de creación (ley, decreto o acuerdo)',
            'nombramiento_representante' => 'Nombramiento oficial vigente del titular',
            'constancia_situacion_fiscal' => 'Constancia de situación fiscal',
            'comprobante_domicilio' => 'Comprobante de domicilio',
            'poder_notarial' => 'Poder notarial del representante',
        ][$coleccion] ?? Str::headline($coleccion);
    }

    /** Peso máximo de cada PDF del expediente (MB): suficiente para un escaneo legible. */
    public const MAX_MB = 5;

    /** Documentos que no pueden tener más de 3 meses de antigüedad al subirse. */
    public const CON_VIGENCIA = ['constancia_situacion_fiscal', 'comprobante_domicilio'];

    /**
     * Documentos obligatorios según el tipo de organismo (sin argumento: los de una empresa privada).
     *  Privada / sociedad civil: acta constitutiva, CSF, identificación del representante y comprobante de domicilio
     *                            (+ poder notarial si sus facultades vienen de un poder).
     *  Pública / otro:           documento de creación y nombramiento vigente del titular, en lugar del acta.
     */
    public static function coleccionesObligatorias(TipoOrganismo|string|null $tipo = null, ?string $facultadOrigen = null): array
    {
        $tipo = $tipo instanceof TipoOrganismo ? $tipo : (TipoOrganismo::tryFrom((string) $tipo) ?? TipoOrganismo::Privado);
        $escritura = DatosNotariales::usaEscritura($tipo);

        return array_values(array_filter([
            $escritura ? 'acta_constitutiva' : 'documento_creacion',
            $escritura ? null : 'nombramiento_representante',
            'constancia_situacion_fiscal',
            'identificacion_representante',
            'comprobante_domicilio',
            'cv_instancia',   // «Anexar un breve CV de la instancia» (formulario oficial)
            $escritura && $facultadOrigen === 'poder' ? 'poder_notarial' : null,
        ]));
    }

    /** Documentos obligatorios de ESTA organización. */
    public function obligatorias(): array
    {
        return self::coleccionesObligatorias($this->tipo_organismo, $this->facultad_origen);
    }

    /** Documentos que la organización puede subir (obligatorios + opcionales que le aplican). */
    public function documentosAplicables(): array
    {
        $escritura = DatosNotariales::usaEscritura($this->tipo_organismo);

        return array_values(array_unique([...$this->obligatorias(), ...($escritura ? ['poder_notarial'] : [])]));
    }

    /** Domicilio fiscal en una línea (para el convenio), armado con sus partes. */
    public function domicilioCompleto(): ?string
    {
        if (blank($this->calle)) {
            return $this->domicilio_fiscal;
        }

        return trim($this->calle.' '.$this->numero_exterior.($this->numero_interior ? ' int. '.$this->numero_interior : '')
            .', col. '.$this->colonia.', C.P. '.$this->codigo_postal.', '.$this->municipio.', '.$this->entidad);
    }

    /** Lo que Lorena hoy revisa "a ojo" en el paso 1.1. */
    public function expedienteCompleto(): bool
    {
        foreach ($this->obligatorias() as $coleccion) {
            if (! $this->documentoValidado($coleccion)) {
                return false;
            }
        }

        return true;
    }

    /** Situaciones de una organización frente al CUCSH (filtros, PDF y exportación usan las mismas). */
    public const SITUACIONES = [
        'vigente' => ['Convenio vigente', 'menta'],
        'por_vencer' => ['Por vencer (60 días)', 'ambar'],
        'en_tramite' => ['Convenio en trámite', 'cielo'],
        'vencido' => ['Convenio vencido', 'piedra'],
        'rechazado' => ['Solicitud rechazada', 'rosa'],
        'sin_solicitud' => ['Sin solicitud', 'gris'],
        'inactiva' => ['Cuenta inactiva', 'gris'],
    ];

    /** @return array{clave:string, texto:string, familia:string, detalle:?string} (requiere convenios cargados) */
    public function situacion(): array
    {
        $convenios = $this->relationLoaded('convenios') ? $this->convenios : $this->convenios()->get();
        $vigente = $convenios->first(fn (Convenio $c) => $c->estaVigente());
        $ultimo = $convenios->sortByDesc('id')->first();

        [$clave, $detalle] = match (true) {
            $this->user && $this->user->activo === false => ['inactiva', null],
            $vigente !== null && $vigente->fecha_fin_vigencia->lte(now()->addDays(60)) => ['por_vencer', 'Vence el '.$vigente->fecha_fin_vigencia->format('d/m/Y')],
            $vigente !== null => ['vigente', 'Hasta el '.$vigente->fecha_fin_vigencia->format('d/m/Y')],
            $convenios->contains(fn (Convenio $c) => ! $c->estado->esFinal()) => ['en_tramite', $convenios->first(fn ($c) => ! $c->estado->esFinal())->estado->label()],
            $ultimo?->estado === ConvenioEstado::Rechazado => ['rechazado', 'Desde el '.$ultimo->updated_at->format('d/m/Y')],
            $convenios->contains(fn (Convenio $c) => $c->estado === ConvenioEstado::Formalizado) => ['vencido', 'Venció el '.$convenios->where('estado', ConvenioEstado::Formalizado)->max('fecha_fin_vigencia')?->format('d/m/Y')],
            default => ['sin_solicitud', null],
        };

        return ['clave' => $clave, 'texto' => self::SITUACIONES[$clave][0], 'familia' => self::SITUACIONES[$clave][1], 'detalle' => $detalle];
    }

    public function auditoriaNombre(): ?string
    {
        return $this->razon_social;
    }

    /** Con convenio vigente el expediente ya fue validado: no se marca como incompleto. */
    public function expedienteEnOrden(): bool
    {
        return $this->convenios()->vigentes()->exists() || $this->expedienteCompleto();
    }

    /** Mientras el convenio está en trámite la empresa no cambia documentos (salvo los rechazados). */
    /** Días antes del vencimiento en que se abre la renovación (y con ella la edición de la solicitud). */
    public const DIAS_RENOVACION = 60;

    /**
     * Por qué la solicitud del convenio (datos generales, declaraciones y perfiles) está en solo lectura; null = editable.
     * Lo enviado a revisión no cambia mientras dura el trámite, y lo firmado no cambia mientras el convenio está
     * vigente: se actualiza al renovarlo. Es la misma regla que muestra u oculta el formulario en «Mi convenio».
     */
    public function motivoSolicitudBloqueada(): ?string
    {
        $convenios = $this->convenios()->get();

        if ($convenios->contains(fn (Convenio $c) => ! $c->estado->esFinal())) {
            return 'Tu convenio está en revisión: los datos que enviaste quedan fijos hasta que termine el trámite.';
        }

        $vigente = $convenios->first(fn (Convenio $c) => $c->estaVigente());
        if ($vigente && (int) now()->startOfDay()->diffInDays($vigente->fecha_fin_vigencia, false) > self::DIAS_RENOVACION) {
            return 'Tu convenio está vigente con estos datos: podrás actualizarlos al renovarlo, '.self::DIAS_RENOVACION.' días antes de que venza.';
        }

        return null;
    }

    /** Renovación abierta: tiene convenio vigente y ya está dentro de los últimos DIAS_RENOVACION días, sin trámite. */
    public function enRenovacion(): bool
    {
        return $this->motivoSolicitudBloqueada() === null && $this->convenios()->get()->contains(fn (Convenio $c) => $c->estaVigente());
    }

    public function documentosBloqueados(): bool
    {
        return $this->convenios()
            ->whereNotIn('estado', [ConvenioEstado::Formalizado->value, ConvenioEstado::Rechazado->value])
            ->exists();
    }
}
