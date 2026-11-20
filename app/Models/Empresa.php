<?php

namespace App\Models;

use App\Enums\TipoOrganismo;
use App\Models\Concerns\ValidaDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Concerns\Auditable;
use App\Enums\ConvenioEstado;

class Empresa extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes, ValidaDocumentos, Auditable;

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
    ];

    protected function casts(): array
    {
        return ['tipo_organismo' => TipoOrganismo::class, 'escritura_fecha' => 'date'];
    }

    /** Expediente: datos personales sensibles -> disco "local" (privado, storage/app/private). */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cv_instancia')
            ->useDisk('local')
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);

        foreach (self::coleccionesPdf() as $coleccion) {
            $this->addMediaCollection($coleccion)
                ->useDisk('local')
                ->acceptsMimeTypes(['application/pdf']);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convenios(): HasMany
    {
        return $this->hasMany(Convenio::class);
    }

    /** @return string[] */
    public static function coleccionesPdf(): array
    {
        return [
            'identificacion_representante',
            'acta_constitutiva',
            'constancia_situacion_fiscal',
            'comprobante_domicilio',
            'poder_notarial',   // opcional: pendiente de confirmar si se pide aparte del acta
        ];
    }

    /** Documentos que deben estar validados antes de turnar el convenio a las carreras. */
    /** Nombre legible de cada documento del expediente. */
    public static function nombreDocumento(string $coleccion): string
    {
        return [
            'cv_instancia' => 'Currículum de la instancia',
            'identificacion_representante' => 'Identificación oficial del representante',
            'acta_constitutiva' => 'Acta constitutiva o nombramiento legal',
            'constancia_situacion_fiscal' => 'Constancia de situación fiscal',
            'comprobante_domicilio' => 'Comprobante de domicilio',
            'poder_notarial' => 'Poder notarial (opcional)',
        ][$coleccion] ?? \Illuminate\Support\Str::headline($coleccion);
    }

    public static function coleccionesObligatorias(): array
    {
        return [
            'cv_instancia',
            'identificacion_representante',
            'acta_constitutiva',
            'constancia_situacion_fiscal',
            'comprobante_domicilio',
        ];
    }

    /** Lo que Lorena hoy revisa "a ojo" en el paso 1.1. */
    public function expedienteCompleto(): bool
    {
        foreach (self::coleccionesObligatorias() as $coleccion) {
            if (!$this->documentoValidado($coleccion)) {
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
            $vigente !== null && $vigente->fecha_fin_vigencia->lte(now()->addDays(60)) => ['por_vencer', 'Vence el ' . $vigente->fecha_fin_vigencia->format('d/m/Y')],
            $vigente !== null => ['vigente', 'Hasta el ' . $vigente->fecha_fin_vigencia->format('d/m/Y')],
            $convenios->contains(fn (Convenio $c) => !$c->estado->esFinal()) => ['en_tramite', $convenios->first(fn ($c) => !$c->estado->esFinal())->estado->label()],
            $ultimo?->estado === ConvenioEstado::Rechazado => ['rechazado', 'Desde el ' . $ultimo->updated_at->format('d/m/Y')],
            $convenios->contains(fn (Convenio $c) => $c->estado === ConvenioEstado::Formalizado) => ['vencido', 'Venció el ' . $convenios->where('estado', ConvenioEstado::Formalizado)->max('fecha_fin_vigencia')?->format('d/m/Y')],
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
    public function documentosBloqueados(): bool
    {
        return $this->convenios()
            ->whereNotIn('estado', [ConvenioEstado::Formalizado->value, ConvenioEstado::Rechazado->value])
            ->exists();
    }

}
