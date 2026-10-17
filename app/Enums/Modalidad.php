<?php

namespace App\Enums;

enum Modalidad: string
{
    case Externa = 'externa';   // empresa con convenio formalizado
    case Interna = 'interna';   // proyecto universitario co-diseñado (sin convenio)
}
