<?php

namespace App\Enums;

/**
 * Roles del sistema (spatie/laravel-permission).
 *
 *  - Administrador: Coordinación de Servicios Académicos. Consulta convenios en SOLO LECTURA (no los opera) y es el
 *                   único que administra cuentas de personal (máx. 2 activas por coordinación), la estructura del
 *                   catálogo (divisiones y carreras, sin reglas académicas) y la auditoría.
 *  - Vinculacion:   Vinculación / Prácticas Profesionales (Lorena). Solo el trámite de convenios: empresas y
 *                   expedientes, turno a Secretaría/División, seguimiento de dictámenes, Jurídica, firmas y formalización.
 *  - Coordinador:   Coordinación de carrera (o de división). Dictamina la pertinencia de los convenios de su carrera
 *                   y lleva todo lo académico del alumno: aprueba vacantes, proyectos internos, expediente (IMSS/kardex),
 *                   carta de presentación, reportes, cierre y reglas de su carrera.
 *                   Su perfil de Personal DEBE tener carrera_id o division_id (alcance).
 *  - Empresa:       tramita su convenio y ofrece vacantes.
 *  - Alumno:        consulta ofertas y gestiona su práctica.
 *
 * Secretaría Académica, División, Jurídica, Rectoría y Control Escolar no usan el sistema: Vinculación registra
 * los turnos, el dictamen jurídico y las firmas; la coordinación registra el envío a Control Escolar y la captura en SIIAU.
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
            self::Administrador => 'Administrador',
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

    /**
     * @return string[] Roles que entran a coordinacion/ (proceso académico del alumno).
     * Solo las coordinaciones de carrera: el administrador (Coordinación de Servicios Académicos) no tiene autoridad
     * académica sobre las licenciaturas, así que no valida kardex, vacantes, reportes ni cierres.
     */
    public static function deCoordinacion(): array
    {
        return [self::Coordinador->value];
    }
}
