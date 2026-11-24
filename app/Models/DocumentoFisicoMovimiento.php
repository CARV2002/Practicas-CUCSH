<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDocumentoFisico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de un papel del convenio: cada cambio de estado con fecha y nota.
 */
class DocumentoFisicoMovimiento extends Model
{
    protected $table = 'documento_fisico_movimientos';

    protected $fillable = ['documento_fisico_id', 'estado', 'nota', 'fecha', 'registrado_por'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoDocumentoFisico::class,
            'fecha' => 'date',
        ];
    }

    /** @return BelongsTo<DocumentoFisico, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoFisico::class, 'documento_fisico_id');
    }

    /** @return BelongsTo<Personal, $this> */
    public function registrador(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'registrado_por');
    }
}
