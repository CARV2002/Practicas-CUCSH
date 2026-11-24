<?php

declare(strict_types=1);

namespace App\Informes;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Empresas con su situación frente al CUCSH (la misma que usa el PDF de la bandeja).
 * El filtro «estado» acepta una clave de Empresa::SITUACIONES; como la situación se calcula, se filtra fila por fila.
 */
class InformeEmpresas extends Informe
{
    public function encabezados(): array
    {
        return ['Razón social', 'RFC', 'Tipo de organismo', 'Situación', 'Detalle', 'Representante legal', 'Cargo',
            'Contacto', 'Correo', 'Teléfono', 'Domicilio legal', 'Solicitudes', 'Registrada'];
    }

    /** @return Builder<Empresa> */
    public function consulta(): Builder
    {
        return Empresa::with(['user', 'convenios'])->withCount('convenios')->orderBy('razon_social');
    }

    /** @param  Empresa  $modelo */
    public function fila(Model $modelo): ?array
    {
        $situacion = $modelo->situacion();
        $buscada = $this->filtro('estado');

        if (isset(Empresa::SITUACIONES[$buscada]) && $situacion['clave'] !== $buscada) {
            return null;
        }

        return [
            $modelo->razon_social, $modelo->rfc, $modelo->tipo_organismo->label(), $situacion['texto'], $situacion['detalle'],
            $modelo->representante_legal, $modelo->representante_cargo, $modelo->contacto_nombre, $modelo->user?->email,
            $modelo->contacto_telefono, $modelo->domicilio_fiscal, $modelo->convenios_count, $modelo->created_at?->format('Y-m-d'),
        ];
    }
}
