<?php

namespace App\Policies;

use App\Enums\ConvenioEstado;
use App\Models\Convenio;
use App\Models\User;
use App\Policies\Concerns\ResuelveActores;

class ConvenioPolicy
{
    use ResuelveActores;

    /** Coordinación (dentro de su alcance) o la empresa dueña. */
    public function view(User $user, Convenio $convenio): bool
    {
        if ($this->esAdmin($user)) {
            return $user->personal->tieneAlcanceGlobal()
                || Convenio::visiblesPara($user->personal)->whereKey($convenio->id)->exists();
        }

        $empresaId = $this->empresaId($user);

        return $empresaId !== null && (int) $convenio->empresa_id === $empresaId;
    }

    /** Avanzar el trámite (documentos, dictámenes, borrador, firmas...): solo la Coordinación. */
    public function gestionar(User $user, Convenio $convenio): bool
    {
        return $this->esAdmin($user) && $this->view($user, $convenio);
    }

    /** La empresa solo descarga el firmado de un convenio ya formalizado. */
    public function descargarFirmado(User $user, Convenio $convenio): bool
    {
        if (! $this->view($user, $convenio)) {
            return false;
        }

        return $this->esAdmin($user) || $convenio->estado === ConvenioEstado::Formalizado;
    }
}