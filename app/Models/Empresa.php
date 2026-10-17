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

class Empresa extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes, ValidaDocumentos;

    protected $table = 'empresas';

    protected $fillable = [
        'user_id', 'rfc', 'razon_social', 'tipo_organismo', 'domicilio_fiscal', 'representante_legal',
        'contacto_nombre', 'contacto_cargo', 'contacto_telefono', 'descripcion', 'proyectos_principales',
    ];

    protected function casts(): array
    {
        return ['tipo_organismo' => TipoOrganismo::class];
    }

    /** Expediente: datos personales sensibles -> disco "local" (privado, storage/app/private). */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cv_instancia')
            ->useDisk('local')
            ->acceptsMimeTypes([
                'application/pdf', 'application/msword',
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
            'identificacion_representante', 'acta_constitutiva', 'constancia_situacion_fiscal',
            'comprobante_domicilio',
            'poder_notarial',   // opcional: pendiente de confirmar si se pide aparte del acta
        ];
    }

    /** Documentos que deben estar validados antes de turnar el convenio a las carreras. */
    public static function coleccionesObligatorias(): array
    {
        return [
            'cv_instancia', 'identificacion_representante', 'acta_constitutiva',
            'constancia_situacion_fiscal', 'comprobante_domicilio',
        ];
    }

    /** Lo que Lorena hoy revisa "a ojo" en el paso 1.1. */
    public function expedienteCompleto(): bool
    {
        foreach (self::coleccionesObligatorias() as $coleccion) {
            if (! $this->documentoValidado($coleccion)) {
                return false;
            }
        }

        return true;
    }
}
