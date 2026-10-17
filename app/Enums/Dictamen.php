<?php

namespace App\Enums;

/** Dictamen Técnico de Pertinencia Académica de UNA carrera sobre UN convenio. */
enum Dictamen: string
{
    case Pendiente = 'pendiente';
    case Aprobatorio = 'aprobatorio';
    case Negativo = 'negativo';
}
