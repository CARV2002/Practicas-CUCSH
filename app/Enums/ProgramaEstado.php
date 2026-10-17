<?php

namespace App\Enums;

enum ProgramaEstado: string
{
    case Borrador = 'borrador';
    case Publicado = 'publicado';
    case Cerrado = 'cerrado';
}
