<?php

namespace App\Enums;

enum TipoOrganismo: string
{
    case Publico = 'publico';
    case Privado = 'privado';
    case SociedadCivil = 'sociedad_civil';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::Privado => 'Privado',
            self::SociedadCivil => 'Sociedad civil',
            self::Otro => 'Otro',
        };
    }
}
