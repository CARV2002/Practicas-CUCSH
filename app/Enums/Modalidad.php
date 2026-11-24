<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de programa: externo (vacante de una organización con convenio) o interno (proyecto del CUCSH).
 */
enum Modalidad: string
{
    case Externa = 'externa';   // empresa con convenio formalizado
    case Interna = 'interna';   // proyecto universitario co-diseñado (sin convenio)
}
