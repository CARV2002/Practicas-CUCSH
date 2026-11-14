<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\CartaTermino;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\ConvenioSeguimiento;
use App\Models\Empresa;
use App\Models\OficioAcreditacion;
use App\Models\Postulacion;
use App\Models\Reporte;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;
use Illuminate\Auth\Access\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaPolicy
{
    use ResuelveActores;

    public function download(User $user, Media $media): Response
    {
        return $this->puedeDescargar($user, $media)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function puedeDescargar(User $user, Media $media): bool
    {
        $dueno = $media->model;   // null si el dueño fue eliminado (soft delete): se niega

        return match (true) {
            $dueno instanceof Alumno => $this->alumnoId($user) === (int) $dueno->id
                || $this->adminVeCarrera($user, (int) $dueno->carrera_id),

            $dueno instanceof Postulacion => $user->can('subirDocumento', $dueno) || $user->can('gestionar', $dueno),

            $dueno instanceof Reporte => $user->can('subirArchivo', $dueno)
                || $user->can('resolver', $dueno)
                || $user->can('retroalimentar', $dueno),

            $dueno instanceof CartaTermino => $user->can('entregarDocumentos', $dueno->practica)
                || $user->can('gestionar', $dueno->practica),

            $dueno instanceof OficioAcreditacion => $user->can('gestionar', $dueno->practica),

            $dueno instanceof Empresa => $this->esAdmin($user) || $this->empresaId($user) === (int) $dueno->id,

            // La empresa solo ve el firmado; los borradores (versiones de Jurídica) son internos
            $dueno instanceof Convenio => $this->esAdmin($user)
                ? $user->can('view', $dueno)
                : ($media->collection_name === 'convenio_firmado' && $user->can('descargarFirmado', $dueno)),

            $dueno instanceof ConvenioCarrera => $this->adminVeCarrera($user, (int) $dueno->carrera_id),

            $dueno instanceof ConvenioSeguimiento => $this->esAdmin($user) && $user->can('view', $dueno->convenio),

            default => false,
        };
    }
}