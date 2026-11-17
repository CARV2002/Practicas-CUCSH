<?php

namespace App\Policies;

use App\Enums\ConvenioEstado;
use App\Models\Convenio;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;

class ConvenioPolicy
{
    use ResuelveActores;

    /** Vinculación, la coordinación de una carrera que participa en él, o la empresa dueña. */
    public function view(User $user, Convenio $convenio): bool
    {
        if ($this->esVinculacion($user)) {
            return $user->personal->tieneAlcanceGlobal()
                || Convenio::visiblesPara($user->personal)->whereKey($convenio->id)->exists();
        }

        if ($this->esCoordinacion($user)) {
            return Convenio::visiblesPara($user->personal)->whereKey($convenio->id)->exists();
        }

        $empresaId = $this->empresaId($user);

        return $empresaId !== null && (int) $convenio->empresa_id === $empresaId;
    }

    /** Avanzar el trámite (documentos, dictámenes, borrador, firmas...): solo Vinculación. */
    public function gestionar(User $user, Convenio $convenio): bool
    {
        return $this->esVinculacion($user) && $this->view($user, $convenio);
    }

    /** La empresa y la coordinación solo descargan el firmado de un convenio ya formalizado. */
    public function descargarFirmado(User $user, Convenio $convenio): bool
    {
        if (! $this->view($user, $convenio)) {
            return false;
        }

        return $this->esVinculacion($user) || $convenio->estado === ConvenioEstado::Formalizado;
    }
}
