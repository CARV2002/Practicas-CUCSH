<?php

namespace App\Enums;

enum ReporteEstado: string
{
    case Pendiente = 'pendiente';
    case Validado = 'validado';
    case Rechazado = 'rechazado';
}
