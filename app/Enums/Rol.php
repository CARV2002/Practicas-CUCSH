<?php

namespace App\Enums;

/**
 * Roles del sistema (spatie/laravel-permission).
 *
 * Hoy el sistema lo usan tres actores:
 *  - Administrador: la Coordinación de Prácticas Profesionales (Lorena), que lleva el proceso de convenio.
 *  - Empresa: tramita su convenio y publica sus ofertas.
 *  - Alumno: consulta ofertas y gestiona su práctica.
 *
 * Divisiones, Secretaría Académica, Jurídica, Rectoría y Control Escolar NO usan el sistema por ahora:
 * Lorena captura sus dictámenes y firmas. Si en el futuro lo usan, agrega aquí el caso
 * (p. ej. DirectorDivision, CoordinadorCarrera, Tutor, Juridico, ControlEscolar) y RolesSeeder lo crea
 * sin migración.
 */
enum Rol: string
{
    case Administrador = 'administrador';
    case Empresa = 'empresa';
    case Alumno = 'alumno';

    /** Nombre de la ruta del panel de cada rol (lo usa DashboardController). */
    public function panel(): string
    {
        return match ($this) {
            self::Administrador => 'admin.dashboard',
            self::Empresa => 'empresa.dashboard',
            self::Alumno => 'alumno.dashboard',
        };
    }
}
