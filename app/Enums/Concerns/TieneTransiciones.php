<?php

namespace App\Enums\Concerns;

trait TieneTransiciones
{
    /** @return self[] */
    abstract public function transiciones(): array;

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->transiciones(), true);
    }
}