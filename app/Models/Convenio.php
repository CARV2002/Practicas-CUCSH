<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EstadoDocumentoFisico;
use App\Enums\EtapaConvenio;
use App\Enums\SeguimientoEstado;
use App\Enums\TipoDocumentoFisico;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ValidaDocumentos;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Convenio de una organización con el CUCSH: etapas, dictámenes por carrera, borradores, papeles físicos y vigencia.
 */
class Convenio extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia, SoftDeletes, ValidaDocumentos;

    protected $table = 'convenios';

    protected $fillable = [
        'empresa_id',
        'gestionado_por',
        'folio',
        'estado',
        'fecha_recepcion',
        'objetivos',
        'actividades_propuestas',
        'plazas_estimadas',
        'medio_conocimiento',
        'experiencia_previa',
        'fecha_turno_secretaria',
        'fecha_turno_division',
        'vigencia_anios',
        'fecha_inicio_vigencia',
        'fecha_fin_vigencia',
    ];

    protected function casts(): array
    {
        return [
            'estado' => ConvenioEstado::class,
            'fecha_recepcion' => 'date',
            'fecha_inicio_vigencia' => 'date',
            'fecha_fin_vigencia' => 'date',
            'fecha_turno_secretaria' => 'date',
            'fecha_turno_division' => 'date',
            'plazas_estimadas' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Borradores: SIN singleFile(), cada versión que Jurídica devuelve queda guardada.
        $this->addMediaCollection('borrador_convenio')
            ->useDisk('local')
            ->acceptsFile(function (File $file) {
                $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));

                return match ($ext) {
                    'pdf' => $file->mimeType === 'application/pdf',
                    // Según el servidor, un .docx se detecta como Word, zip u octet-stream
                    'docx' => in_array($file->mimeType, [
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/zip',
                        'application/octet-stream',
                    ], true),
                    default => false,
                };
            });

        // Escaneo de los 3 juegos originales firmados por ambas partes
        $this->addMediaCollection('convenio_firmado')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);

        // PDF limpio de cada versión generada (para imprimir los 3 juegos)
        $this->addMediaCollection('borrador_pdf')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Personal, $this> */
    public function gestor(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'gestionado_por');
    }

    /**
     * Un dictamen por carrera solicitada.
     *
     * @return HasMany<ConvenioCarrera, $this>
     */
    public function dictamenes(): HasMany
    {
        return $this->hasMany(ConvenioCarrera::class);
    }

    /** @return BelongsToMany<Carrera, $this> */
    public function carreras(): BelongsToMany
    {
        return $this->belongsToMany(Carrera::class, 'convenio_carrera')
            ->withPivot(['id', 'dictamen', 'observaciones', 'fecha_dictamen', 'dictaminado_por'])
            ->withTimestamps();
    }

    /**
     * Las carreras que quedan en el contrato (dictamen aprobatorio).
     *
     * @return BelongsToMany<Carrera, $this>
     */
    public function carrerasAprobadas(): BelongsToMany
    {
        return $this->carreras()->wherePivot('dictamen', Dictamen::Aprobatorio->value);
    }

    /** @return HasMany<ConvenioSeguimiento, $this> */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(ConvenioSeguimiento::class)->orderBy('fecha_registro')->orderBy('id');
    }

    /** @return HasMany<Programa, $this> */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class);
    }

    // ---- Reglas de la fase académica -------------------------------------------------

    public function autorizaCarrera(int $carreraId): bool
    {
        return $this->dictamenes()
            ->where('carrera_id', $carreraId)
            ->where('dictamen', Dictamen::Aprobatorio->value)
            ->exists();
    }

    public function dictamenesPendientes(): int
    {
        return $this->dictamenes()->where('dictamen', Dictamen::Pendiente->value)->count();
    }

    public function tieneAprobatorio(): bool
    {
        return $this->dictamenes()->where('dictamen', Dictamen::Aprobatorio->value)->exists();
    }

    /** Todas respondieron y ninguna aprobó: "el proceso muere". */
    public function todasNegativas(): bool
    {
        return $this->dictamenes()->exists()
            && $this->dictamenesPendientes() === 0
            && ! $this->tieneAprobatorio();
    }

    /** Sin pendientes y con al menos una carrera aprobatoria. */
    public function puedePasarAJuridica(): bool
    {
        return $this->dictamenesPendientes() === 0 && $this->tieneAprobatorio();
    }

    /**
     * Etapas abiertas = quién tiene "la pelota" ahora mismo.
     *
     * @return HasMany<ConvenioSeguimiento, $this>
     */
    public function etapasAbiertas(): HasMany
    {
        return $this->seguimientos()->where('estado', SeguimientoEstado::Pendiente->value);
    }

    // ---- Vigencia (se calcula, no se guarda) -----------------------------------------

    public function scopeVigentes(Builder $query, ?CarbonInterface $en = null): Builder
    {
        $fecha = ($en ?? now())->toDateString();

        return $query->where('estado', ConvenioEstado::Formalizado->value)
            ->whereDate('fecha_inicio_vigencia', '<=', $fecha)
            ->whereDate('fecha_fin_vigencia', '>=', $fecha);
    }

    /**
     * Convenios que vencen en los próximos N días (avisos a 60 y 30 días).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePorVencer(Builder $query, int $dias): Builder
    {
        return $query->vigentes()
            ->whereDate('fecha_fin_vigencia', '<=', now()->addDays($dias)->toDateString());
    }

    /** Alcance: global ve todo; coordinador/director solo convenios que solicitaron sus carreras. */
    public function scopeVisiblesPara(Builder $query, Personal $personal): Builder
    {
        $ids = $personal->carreraIdsEnAlcance();

        return $ids === null
            ? $query
            : $query->whereHas('dictamenes', fn (Builder $q) => $q->whereIn('carrera_id', $ids));
    }

    /**
     * Días de vigencia que debe tener el convenio para recibir postulaciones nuevas
     * (aprox. un semestre: lo que dura una práctica). Evita que las horas queden en riesgo.
     */
    public const DIAS_MINIMOS_POSTULACION = 180;

    /** Duraciones que se pueden pactar en la cláusula UNDÉCIMA. */
    public const DURACIONES = [1, 2, 3, 4, 5];

    public function admitePostulaciones(): bool
    {
        return $this->estaVigente()
            && $this->fecha_fin_vigencia->gte(now()->startOfDay()->addDays(self::DIAS_MINIMOS_POSTULACION));
    }

    /**
     * Versiones del borrador (v1, v2…), de la más antigua a la vigente. Cada una con su archivo editable o
     * el PDF que se adjuntó, y el PDF limpio para imprimir (generado con LibreOffice, o el mismo si ya era PDF).
     *
     * @return Collection<int, array{version:int, archivo:Media, pdf:?Media, vigente:bool}>
     */
    public function versionesBorrador(): Collection
    {
        $pdfs = $this->getMedia('borrador_pdf');
        $versiones = $this->getMedia('borrador_convenio')->sortBy('id')->values()
            ->map(function ($archivo, $i) use ($pdfs) {
                $version = (int) ($archivo->getCustomProperty('version') ?? $i + 1);
                $pdf = $archivo->mime_type === 'application/pdf'
                    ? $archivo
                    : $pdfs->first(fn ($p) => (int) $p->getCustomProperty('version') === $version);

                return ['version' => $version, 'archivo' => $archivo, 'pdf' => $pdf, 'vigente' => false];
            });

        if ($versiones->isNotEmpty()) {
            $versiones->put($versiones->count() - 1, ['vigente' => true] + $versiones->last());
        }

        return $versiones;
    }

    /**
     * Fecha de firma impresa en los juegos finales (la que Lorena fija al imprimirlos). El borrador para Jurídica
     * la deja en blanco; si los juegos ya llevan fecha, la vigencia debe empezar ese mismo día.
     */
    public function fechaFirmaImpresa(): ?Carbon
    {
        $final = $this->getMedia('borrador_convenio')->sortByDesc('id')->first(fn ($m) => $m->getCustomProperty('final'));
        $fecha = $final?->getCustomProperty('fecha_firma');

        return $fecha ? Carbon::parse($fecha) : null;
    }

    /** Datos con que se generó el borrador vigente (null si el vigente lo adjuntó Lorena y no se puede regenerar). */
    public function datosBorradorVigente(): ?array
    {
        $vigente = $this->versionesBorrador()->last()['archivo'] ?? null;

        return $vigente?->getCustomProperty('datos');
    }

    /** Número que tendrá el próximo borrador (generado o adjuntado). */
    public function siguienteVersionBorrador(): int
    {
        return ($this->versionesBorrador()->max('version') ?? 0) + 1;
    }

    /** ¿Este archivo es el PDF para imprimir de una versión que ya no es la vigente? */
    public function esImpresionObsoleta(Media $media): bool
    {
        if (! in_array($media->collection_name, ['borrador_pdf', 'borrador_convenio'], true) || $media->mime_type !== 'application/pdf') {
            return false;
        }

        $vigente = $this->versionesBorrador()->last();

        return $vigente !== null && $vigente['pdf']?->id !== $media->id;
    }

    /** Duración pactada en años (la del borrador, o 3 si no se definió, como el Formato de PP). */
    public function duracionAnios(): int
    {
        return (int) ($this->vigencia_anios ?: 3);
    }

    public function estaVigente(?CarbonInterface $en = null): bool
    {
        $fecha = ($en ?? now())->toDateString();

        return $this->estado === ConvenioEstado::Formalizado
            && $this->fecha_inicio_vigencia !== null
            && $this->fecha_fin_vigencia !== null
            && $this->fecha_inicio_vigencia->toDateString() <= $fecha
            && $this->fecha_fin_vigencia->toDateString() >= $fecha;
    }

    /** Vigente hoy y hasta la fecha dada (que cubra TODA la práctica). */
    public function cubreHasta(CarbonInterface $fecha): bool
    {
        return $this->estaVigente()
            && $this->fecha_fin_vigencia->toDateString() >= $fecha->toDateString();
    }

    public function auditoriaNombre(): ?string
    {
        return $this->folio;
    }

    // ---- Documentos físicos (trámite híbrido) ------------------------------------------

    /** @return HasMany<DocumentoFisico, $this> */
    public function documentosFisicos(): HasMany
    {
        return $this->hasMany(DocumentoFisico::class)->orderByRaw("CASE tipo WHEN 'oficio_turno' THEN 1 WHEN 'dictamen_juridico' THEN 2 ELSE 3 END")->orderBy('juego');
    }

    /**
     * Crea (sin duplicar) los papeles que el trámite ya debería tener según su estado:
     * oficio de turno desde la validación académica, dictamen jurídico desde Jurídica y
     * los 3 juegos desde firmas. En un convenio ya formalizado los juegos nacen "firmados".
     */
    public function prepararDocumentosFisicos(?Personal $por = null): void
    {
        $orden = [
            ConvenioEstado::EnValidacionAcademica->value => 1,
            ConvenioEstado::EnRevisionJuridica->value => 2,
            ConvenioEstado::EnFirmas->value => 3,
            ConvenioEstado::Formalizado->value => 4,
        ];
        $nivel = $orden[$this->estado->value] ?? 0;
        $formalizado = $this->estado === ConvenioEstado::Formalizado;

        $crear = function (TipoDocumentoFisico $tipo, EstadoDocumentoFisico $estado, ?int $juego = null) use ($por) {
            $existe = $this->documentosFisicos()->where('tipo', $tipo->value)
                ->when($juego, fn ($q) => $q->where('juego', $juego), fn ($q) => $q->whereNull('juego'))->exists();
            if ($existe) {
                return;
            }
            $doc = $this->documentosFisicos()->create([
                'tipo' => $tipo,
                'juego' => $juego,
                'destino' => $juego ? TipoDocumentoFisico::destinoJuego($juego) : null,
                'estado' => $estado,
                'fecha_estado' => today(),
                'fecha_compromiso' => $estado->plazoHabiles() ? today()->addWeekdays($estado->plazoHabiles()) : null,
                'actualizado_por' => $por?->id,
            ]);
            $doc->movimientos()->create(['estado' => $estado, 'fecha' => today(), 'registrado_por' => $por?->id, 'nota' => 'Alta en el control de documentos físicos.']);
        };

        // El turno a las carreras es 100 % digital (cada coordinación dictamina en su panel): no hay oficio en papel que rastrear.
        // Solo viajan en papel el expediente que va a la Unidad Jurídica y los 3 juegos que firman la empresa y Rectoría.
        if ($nivel >= 2) {
            $crear(TipoDocumentoFisico::DictamenJuridico, $nivel > 2 ? EstadoDocumentoFisico::EnVinculacion : EstadoDocumentoFisico::EnJuridica);
        }
        if ($nivel >= 3) {
            foreach ([1, 2, 3] as $j) {
                $crear(TipoDocumentoFisico::JuegoConvenio, $formalizado ? EstadoDocumentoFisico::Firmado : EstadoDocumentoFisico::PorElaborar, $j);
            }
        }
    }

    /**
     * Mantiene los juegos en sintonía con las firmas que registra el flujo:
     * firma de la empresa → "Firmado por la empresa"; firma de Rectoría → "Firmado por ambas partes".
     */
    public function sincronizarJuegos(EstadoDocumentoFisico $estado, ?Personal $por, string $nota): void
    {
        $this->documentosFisicos()
            ->where('tipo', TipoDocumentoFisico::JuegoConvenio->value)
            ->whereNotIn('estado', [EstadoDocumentoFisico::Extraviado->value, ...EstadoDocumentoFisico::concluidos(), $estado->value])
            ->get()
            ->each(fn (DocumentoFisico $d) => $d->mover($estado, $por, $nota));
    }

    // ---- Estado detallado --------------------------------------------------------------

    /**
     * El estado general dice la fase; esto dice exactamente en qué va y quién tiene la pelota.
     *
     * @return array{texto:string, detalle:?string, familia:string, responsable:string}
     */
    public function estadoDetallado(): array
    {
        $abierta = $this->relationLoaded('seguimientos')
            ? $this->seguimientos->where('estado', SeguimientoEstado::Pendiente)->sortByDesc('id')->first()
            : $this->etapasAbiertas()->latest('id')->first();
        $dias = $abierta ? $abierta->diasHabiles() : null;
        $enDias = $dias !== null ? ($dias === 1 ? 'desde hace 1 día hábil' : "desde hace {$dias} días hábiles") : null;
        $fisicos = $this->relationLoaded('documentosFisicos') ? $this->documentosFisicos : $this->documentosFisicos()->get();
        $juegos = $fisicos->where('tipo', TipoDocumentoFisico::JuegoConvenio);
        $r = fn (string $texto, ?string $detalle, string $familia, string $responsable) => compact('texto', 'detalle', 'familia', 'responsable');

        return match ($this->estado) {
            ConvenioEstado::Solicitado => $this->detalleSolicitud($r),

            ConvenioEstado::EnValidacionAcademica => match (true) {
                $abierta?->etapa === EtapaConvenio::RedaccionBorrador => $r(
                    'Redacción del borrador',
                    $this->getMedia('borrador_convenio')->isEmpty() ? 'Sin borrador adjunto' : 'Borrador v'.$this->getMedia('borrador_convenio')->count().' listo para turnar',
                    'lavanda', 'Vinculación'),
                default => $this->detalleDictamenes($r),
            },

            ConvenioEstado::EnRevisionJuridica => $abierta?->etapa === EtapaConvenio::RedaccionBorrador
                ? $r('Corrigiendo observaciones', 'Jurídica regresó el borrador', 'rosa', 'Vinculación')
                : $r('En Unidad Jurídica', $enDias, ($abierta?->excedeEspera() ? 'rosa' : 'indigo'), 'Unidad Jurídica'),

            ConvenioEstado::EnFirmas => match ($abierta?->etapa) {
                EtapaConvenio::FirmaEmpresa => $juegos->isEmpty() || $juegos->contains(fn ($j) => $j->estado === EstadoDocumentoFisico::PorElaborar)
                    ? $r('Imprimir los 3 juegos', 'Para entregarlos a la empresa', 'ambar', 'Vinculación')
                    : $r(
                        'Pendiente de firma de la empresa',
                        $juegos->where('estado', EstadoDocumentoFisico::ConEmpresa)->count().' de 3 juegos con la empresa · '.$juegos->where('estado', EstadoDocumentoFisico::FirmadoEmpresa)->count().' de vuelta',
                        $juegos->contains(fn ($j) => $j->vencido()) ? 'rosa' : 'ambar', 'Empresa'),
                EtapaConvenio::FirmaRectoria => $r('Pendiente de firma de Rectoría', $enDias, $abierta->excedeEspera() ? 'rosa' : 'ambar', 'Rectoría'),
                EtapaConvenio::ArchivoDistribucion => $r('Por formalizar', 'Registrar folio, vigencia y escaneo', 'turquesa', 'Vinculación'),
                default => $r('En firmas', null, 'ambar', 'Vinculación'),
            },

            ConvenioEstado::Formalizado => $this->detalleFormalizado($r, $juegos),

            ConvenioEstado::Rechazado => $r('Rechazado', $this->relationLoaded('seguimientos')
                ? $this->seguimientos->where('estado', SeguimientoEstado::Rechazado)->last()?->comentario
                : null, 'rosa', 'Nadie (trámite cerrado)'),
        };
    }

    private function detalleSolicitud(\Closure $r): array
    {
        $empresa = $this->empresa;
        $colecciones = $empresa?->obligatorias() ?? Empresa::coleccionesObligatorias();
        $media = $empresa === null ? collect() : ($empresa->relationLoaded('media') ? $empresa->media : $empresa->media()->get())->whereIn('collection_name', $colecciones);
        $vigentes = collect($colecciones)->map(fn ($c) => $media->where('collection_name', $c)->sortByDesc('id')->first())->filter();
        $validados = $vigentes->filter(fn ($m) => $m->getCustomProperty('estado') === 'validada')->count();
        $rechazados = $vigentes->filter(fn ($m) => $m->getCustomProperty('estado') === 'rechazada')->count();
        $total = count($colecciones);

        return match (true) {
            $rechazados > 0 => $r('Esperando correcciones', "{$rechazados} documento(s) rechazado(s) por corregir", 'rosa', 'Empresa'),
            $vigentes->count() < $total => $r('Expediente incompleto', 'Faltan '.($total - $vigentes->count())." de {$total} documentos", 'cielo', 'Empresa'),
            $validados === 0 => $r('Solicitud recibida', "{$total} documentos por revisar", 'cielo', 'Vinculación'),
            $validados < $total => $r('En revisión de documentos', "{$validados} de {$total} validados", 'cielo', 'Vinculación'),
            default => $r('Listo para turnar', 'Expediente completo y validado', 'turquesa', 'Vinculación'),
        };
    }

    private function detalleDictamenes(\Closure $r): array
    {
        $dictamenes = $this->relationLoaded('dictamenes') ? $this->dictamenes : $this->dictamenes()->get();
        $total = $dictamenes->count();
        $listos = $dictamenes->where('dictamen', '!=', Dictamen::Pendiente)->count();

        return $r('Esperando dictámenes', "{$listos} de {$total} carreras han respondido", 'lavanda', 'Coordinaciones de carrera');
    }

    private function detalleFormalizado(\Closure $r, $juegos): array
    {
        $porEntregar = $juegos->filter(fn ($j) => ! $j->estado->concluido())->count();

        if (! $this->estaVigente()) {
            return $this->fecha_fin_vigencia?->isPast()
                ? $r('Vencido', 'Venció el '.$this->fecha_fin_vigencia->format('d/m/Y'), 'rosa', 'Empresa (renovación)')
                : $r('Formalizado', 'Inicia el '.$this->fecha_inicio_vigencia?->format('d/m/Y'), 'cielo', 'Vinculación');
        }

        $dias = (int) today()->diffInDays($this->fecha_fin_vigencia, false);

        return match (true) {
            $dias <= 60 => $r('Por vencer', "Vence en {$dias} días ({$this->fecha_fin_vigencia->format('d/m/Y')})", 'ambar', 'Empresa (renovación)'),
            $porEntregar > 0 => $r('Convenio activo (vigente)', "{$porEntregar} juego(s) por entregar o archivar", 'menta', 'Vinculación'),
            default => $r('Convenio activo (vigente)', 'Hasta el '.$this->fecha_fin_vigencia->format('d/m/Y'), 'menta', '—'),
        };
    }
}
