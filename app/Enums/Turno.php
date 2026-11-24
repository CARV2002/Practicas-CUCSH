<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Turno en que estudia el alumno (matutino, vespertino o mixto): se imprime en la carta de presentación.
 */
enum Turno: string
{
    case Matutino = 'matutino';
    case Vespertino = 'vespertino';
    case Mixto = 'mixto';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
