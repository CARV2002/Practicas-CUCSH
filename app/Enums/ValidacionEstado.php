<?php

namespace App\Enums;

/** Para cartas, oficios y para el estado de cada archivo en custom_properties de medialibrary. */
enum ValidacionEstado: string
{
    case Pendiente = 'pendiente';
    case Validada = 'validada';
    case Rechazada = 'rechazada';
}
