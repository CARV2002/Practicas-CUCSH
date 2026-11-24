<?php

declare(strict_types=1);

namespace App\Informes;

use App\Enums\TipoDocumentoFisico;
use App\Models\DocumentoFisico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Dónde está cada papel del trámite (los mismos filtros de la pantalla «Documentos físicos»: vista y búsqueda). */
class InformeDocumentosFisicos extends Informe
{
    public function encabezados(): array
    {
        return ['Organización', 'Folio del convenio', 'Papel', 'Estado', 'Lo tiene', 'Desde', 'Días hábiles en ese estado', 'Debe regresar', 'Situación', 'Destino del juego', 'Notas'];
    }

    /** @return Builder<DocumentoFisico> */
    public function consulta(): Builder
    {
        return DocumentoFisico::with('convenio.empresa')
            ->where('tipo', '!=', TipoDocumentoFisico::OficioTurno->value)   // el turno es digital: ya no se rastrea
            ->whereHas('convenio', fn ($c) => $c->visiblesPara($this->personal))
            ->enVista($this->filtro('vista') ?: 'activos', $this->filtro('q'));
    }

    /** @param  DocumentoFisico  $modelo */
    public function fila(Model $modelo): array
    {
        return [
            $modelo->convenio->empresa->razon_social,
            $modelo->convenio->folio ?? 'Sin folio',
            $modelo->nombre(),
            $modelo->estado->label(),
            $modelo->estado->custodio(),
            $modelo->fecha_estado->format('d/m/Y'),
            $modelo->diasEnEstado(),
            $modelo->estado->fueraDeOficina() ? $modelo->fecha_compromiso?->format('d/m/Y') : '',
            match (true) {
                $modelo->estado->concluido() => 'Concluido',
                ! $modelo->estado->fueraDeOficina() => 'En la oficina de Vínculos',
                $modelo->vencido() => 'Regreso vencido',
                $modelo->porVencer() => 'Regresa muy pronto',
                default => 'A tiempo',
            },
            $modelo->destino,
            $modelo->notas,
        ];
    }
}
