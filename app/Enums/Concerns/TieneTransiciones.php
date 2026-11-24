<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Máquina de estados de los enums de estado: cada enum declara a qué estados puede pasar
 * y puedePasarA() lo verifica antes de cualquier cambio.
 */
trait TieneTransiciones
{
    /** @return self[] */
    abstract public function transiciones(): array;

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->transiciones(), true);
    }
}
