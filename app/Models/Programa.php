<?php

namespace App\Models;

use App\Enums\Modalidad;
use App\Enums\PostulacionEstado;
use App\Enums\ProgramaEstado;
use App\Observers\ProgramaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ProgramaObserver::class])]
class Programa extends Model
{
    protected $table = 'programas';

    protected $fillable = [
        'convenio_id', 'carrera_id', 'modalidad', 'ciclo', 'titulo', 'descripcion',
        'actividades', 'perfil_requerido', 'horario', 'sede', 'cupos_totales', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'modalidad' => Modalidad::class,
            'estado' => ProgramaEstado::class,
            'actividades' => 'array',   // lista de puntos a realizar
            'cupos_totales' => 'integer',
        ];
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    public function esInterno(): bool
    {
        return $this->modalidad === Modalidad::Interna;
    }

    public function cuposOcupados(): int
    {
        return $this->postulaciones()
            ->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::ocupanCupo()))
            ->count();
    }

    /** cupos_totales - ocupados. Si cargaste scopeConCuposOcupados() no hace consulta extra. */
    protected function cuposDisponibles(): Attribute
    {
        return Attribute::get(function () {
            $ocupados = $this->attributes['cupos_ocupados'] ?? $this->cuposOcupados();

            return max(0, $this->cupos_totales - (int) $ocupados);
        });
    }

    /** Para listados: agrega cupos_ocupados en una sola consulta. */
    public function scopeConCuposOcupados(Builder $query): Builder
    {
        return $query->withCount([
            'postulaciones as cupos_ocupados' => fn ($q) => $q->whereIn(
                'estado', PostulacionEstado::valores(PostulacionEstado::ocupanCupo())
            ),
        ]);
    }

    /** Lo que un alumno puede ver/postular: publicado, convenio vigente (o interno) y con cupo. */
    public function scopeAbiertos(Builder $query): Builder
    {
        $estados = PostulacionEstado::valores(PostulacionEstado::ocupanCupo());
        $marcadores = implode(',', array_fill(0, count($estados), '?'));

        return $query
            ->where('estado', ProgramaEstado::Publicado->value)
            ->where(fn (Builder $q) => $q
                ->whereNull('convenio_id')
                ->orWhereHas('convenio', fn (Builder $c) => $c->vigentes()))
            ->whereRaw(
                "programas.cupos_totales > (select count(*) from postulaciones where postulaciones.programa_id = programas.id and postulaciones.estado in ($marcadores))",
                $estados
            );
    }
}
