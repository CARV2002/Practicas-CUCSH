<?php

namespace App\Enums;

/** Nombres de rol de spatie/laravel-permission. */
enum Rol: string
{
    case Alumno = 'alumno';
    case Empresa = 'empresa';
    case Vinculacion = 'vinculacion';
    case SecretariaAcademica = 'secretaria_academica';
    case DirectorDivision = 'director_division';
    case CoordinadorCarrera = 'coordinador_carrera';
    case Juridico = 'juridico';
    case Rectoria = 'rectoria';
    case ControlEscolar = 'control_escolar';

    /** @return self[] roles que pertenecen a la tabla `personal`. */
    public static function personal(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $r) => ! in_array($r, [self::Alumno, self::Empresa], true)
        ));
    }
}
