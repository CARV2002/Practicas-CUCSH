<?php

namespace App\Enums;

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
