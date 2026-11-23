<?php

namespace App\Models;

use App\Enums\ReporteEstado;
use App\Enums\ReporteTipo;
use App\Models\Concerns\ValidaDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Observers\ReporteObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy; 
use App\Models\Concerns\Auditable;

#[ObservedBy([ReporteObserver::class])]
class Reporte extends Model implements HasMedia
{
    use InteractsWithMedia, ValidaDocumentos, Auditable;

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

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }
}
