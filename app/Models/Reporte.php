<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReporteEstado;
use App\Enums\ReporteTipo;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ValidaDocumentos;
use App\Observers\ReporteObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Reporte de horas del alumno: periodo, actividades y el archivo firmado que valida la coordinación.
 */
#[ObservedBy([ReporteObserver::class])]
class Reporte extends Model implements HasMedia
{
    use Auditable, InteractsWithMedia, ValidaDocumentos;

    protected $table = 'reportes';

    protected $fillable = [
        'practica_id', 'tipo', 'numero', 'periodo_inicio', 'periodo_fin', 'horas_reportadas', 'actividades', 'logros',
        'retroalimentacion_empresa', 'evaluacion_profesor', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => ReporteTipo::class,
            'estado' => ReporteEstado::class,
            'periodo_inicio' => 'date',
            'periodo_fin' => 'date',
            'horas_reportadas' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('reporte_firmado')->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    /** @return BelongsTo<Practica, $this> */
    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }
}
