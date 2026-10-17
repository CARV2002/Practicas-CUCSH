<?php

namespace App\Enums;

enum TipoOrganismo: string
{
    case Publico = 'publico';
    case Privado = 'privado';
    case SociedadCivil = 'sociedad_civil';
    case Otro = 'otro';
}
