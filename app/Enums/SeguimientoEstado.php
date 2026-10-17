<?php

namespace App\Enums;

enum SeguimientoEstado: string
{
    case Pendiente = 'pendiente';   // etapa abierta: alguien tiene "la pelota"
    case Aprobado = 'aprobado';
    case Observado = 'observado';   // se devuelve para corregir (p. ej. Jurídica regresa el Word)
    case Rechazado = 'rechazado';
}
