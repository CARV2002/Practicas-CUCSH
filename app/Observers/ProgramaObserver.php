<?php

namespace App\Observers;

use App\Enums\Modalidad;
use App\Enums\ProgramaEstado;
use App\Models\Convenio;
use App\Models\Programa;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de la oferta, aplicadas SIEMPRE (formulario, seeders, tinker...):
 *  - externo => requiere convenio; la carrera debe tener dictamen aprobatorio en él
 *  - interno => sin convenio
 *  - publicar exige convenio vigente
 *  - no bajar cupos_totales por debajo de los ya ocupados
 */
class ProgramaObserver
{
    public function saving(Programa $programa): void
    {
        $modalidad = $programa->modalidad ?? Modalidad::Externa;

        if ($programa->exists && $programa->postulaciones()->exists()) {
            if ($programa->isDirty(['carrera_id', 'convenio_id', 'modalidad', 'ciclo'])) {
                $this->fallar('Ya hay postulaciones: no se pueden cambiar carrera, convenio, modalidad ni ciclo. Crea otro programa.');
            }

            if ($programa->isDirty('estado') && $programa->estado === ProgramaEstado::Borrador) {
                $this->fallar('Ya hay postulaciones: no se puede regresar a borrador. Ciérralo en su lugar.');
            }
        }

        if ($modalidad === Modalidad::Interna) {
            if ($programa->convenio_id !== null) {
                $this->fallar('Un programa de modalidad interna no lleva convenio.');
            }
        } else {
            if ($programa->convenio_id === null) {
                $this->fallar('Un programa externo requiere un convenio.');
            }

            $convenio = Convenio::find($programa->convenio_id);

            if ($convenio === null || !$convenio->autorizaCarrera((int) $programa->carrera_id)) {
                $this->fallar('La carrera no tiene dictamen aprobatorio en el convenio de la empresa.');
            }

            if (
                $programa->isDirty('estado')
                && $programa->estado === ProgramaEstado::Publicado
                && !$convenio->estaVigente()
            ) {
                $this->fallar('No se puede publicar: el convenio no está vigente.');
            }
        }

        if (
            $programa->exists
            && $programa->isDirty('cupos_totales')
            && $programa->cupos_totales < $programa->cuposOcupados()
        ) {
            $this->fallar("No puedes bajar los cupos a {$programa->cupos_totales}: ya hay {$programa->cuposOcupados()} ocupados.");
        }
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['programa' => $mensaje]);
    }
}
