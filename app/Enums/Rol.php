<?php

namespace App\Enums;

/**
 * Roles del sistema (spatie/laravel-permission).
 *
 *  - Administrador: superusuario técnico. Entra a todo (Vinculación y Coordinación) y da de alta personal.
 *  - Vinculacion:   Vinculación / Prácticas Profesionales (Lorena). Solo el trámite de convenios:
 *                   empresas, expedientes, turnos, dictámenes capturados, firmas, formalización y alta de coordinadores.
 *  - Coordinador:   Coordinación de carrera (o Dirección de división). Todo lo académico del alumno:
 *                   programas/vacantes, postulaciones, reportes, cierres y reglas de su carrera.
 *                   Su perfil de Personal DEBE tener carrera_id o division_id (alcance).
 *  - Empresa:       tramita su convenio y ofrece vacantes.
 *  - Alumno:        consulta ofertas y gestiona su práctica.
 *
 * Secretaría Académica, Jurídica, Rectoría y Control Escolar no usan el sistema: Vinculación captura sus turnos y firmas.
 */
enum Rol: string
{
    case Administrador = 'administrador';
    case Vinculacion = 'vinculacion';
    case Coordinador = 'coordinador';
    case Empresa = 'empresa';
    case Alumno = 'alumno';

    /** Nombre de la ruta del panel de cada rol (lo usa DashboardController). */
    public function panel(): string
    {
        return match ($this) {
            self::Administrador, self::Vinculacion => 'admin.dashboard',
            self::Coordinador => 'coordinacion.dashboard',
            self::Empresa => 'empresa.dashboard',
            self::Alumno => 'alumno.dashboard',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador técnico',
            self::Vinculacion => 'Vinculación',
            self::Coordinador => 'Coordinación de carrera',
            self::Empresa => 'Empresa',
            self::Alumno => 'Alumno',
        };
    }

    /** Roles de personal institucional: requieren perfil en la tabla personal. */
    public function esPersonal(): bool
    {
        return in_array($this, [self::Administrador, self::Vinculacion, self::Coordinador], true);
    }

    /** @return string[] Roles que entran a admin/ (trámite de convenios). */
    public static function deVinculacion(): array
    {
        return [self::Vinculacion->value, self::Administrador->value];
    }

    /** @return string[] Roles que entran a coordinacion/ (proceso académico del alumno). */
    public static function deCoordinacion(): array
    {
        return [self::Coordinador->value, self::Administrador->value];
    }
}
