<?php

namespace App\Services;

use App\Enums\Rol;
use App\Models\Convenio;
use App\Models\Practica;
use App\Models\User;
use App\Notifications\AvisoPracticas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class Avisos
{
    public static function enviar(User|iterable|null $destinatarios, AvisoPracticas $aviso): void
    {
        DB::afterCommit(function () use ($destinatarios, $aviso) {
            $usuarios = collect($destinatarios instanceof User ? [$destinatarios] : $destinatarios)
                ->filter(fn($u) => $u instanceof User && $u->activo !== false)
                ->unique(fn(User $u) => $u->id)
                ->values();

            if ($usuarios->isEmpty()) {
                return;
            }

            try {
                Notification::send($usuarios, $aviso);
            } catch (Throwable $e) {
                report($e);   // un aviso fallido no debe romper la operación ya guardada
            }
        });
    }

    /** Correo a una dirección sin cuenta (p. ej. el buzón institucional de Prácticas). Solo canal mail. */
    public static function enviarCorreo(string $correo, AvisoPracticas $aviso): void
    {
        DB::afterCommit(function () use ($correo, $aviso) {
            try {
                Notification::route('mail', $correo)->notify($aviso);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Coordinación de carrera activa con alcance sobre esa carrera (o su división).
     * Postulaciones, reportes y cierres van SOLO aquí: ni Vinculación ni el administrador.
     */
    public static function coordinadores(int $carreraId): Collection
    {
        return User::role(Rol::Coordinador->value)->where('activo', true)->with('personal')->get()
            ->filter(fn(User $u) => $u->personal !== null
                && $u->personal->tieneAlcanceDeCoordinacion()
                && $u->personal->puedeVerCarrera($carreraId))
            ->values();
    }

    /**
     * Aviso a la coordinación de una carrera. Si la carrera aún no tiene coordinación activa,
     * el aviso no se pierde: va al administrador para que la dé de alta (y la coordinación
     * nueva recibe un resumen de pendientes al crearse; ver UsuarioController::store).
     */
    public static function aCoordinacion(int $carreraId, AvisoPracticas $aviso): void
    {
        $coordinadores = self::coordinadores($carreraId);

        if ($coordinadores->isNotEmpty()) {
            self::enviar($coordinadores, $aviso);

            return;
        }

        $carrera = \App\Models\Carrera::find($carreraId);
        $administradores = User::role(Rol::Administrador->value)->where('activo', true)->get();

        self::enviar($administradores, new \App\Notifications\AvisoOperativo(
            mensaje: 'La carrera ' . ($carrera?->carrera_clave ?? "#{$carreraId}") . ' no tiene coordinación activa en el sistema y quedó un pendiente sin atender: «'
                . $aviso->toArray(new User())['mensaje'] . '». Da de alta su coordinación.',
            url: route('admin.lista-usuarios', ['rol' => Rol::Coordinador->value], false),
            detalle: $carrera?->nombre,
            clave: null,
        ));
    }

    /** Coordinación de la carrera del alumno de esa práctica. */
    public static function coordinadoresDe(Practica $practica): Collection
    {
        return self::coordinadores((int) $practica->postulacion->alumno->carrera_id);
    }

    /** Vinculación activa (Lorena): quien lleva el trámite de convenios. */
    public static function vinculacion(): Collection
    {
        return User::role(Rol::Vinculacion->value)->where('activo', true)->with('personal')->get()
            ->filter(fn(User $u) => $u->personal !== null)
            ->values();
    }

    /** Vinculación que puede ver ese convenio (misma regla que ConvenioPolicy). */
    public static function vinculacionDe(Convenio $convenio): Collection
    {
        return self::vinculacion()
            ->filter(fn(User $u) => $u->can('view', $convenio))
            ->values();
    }

    /** Alumnos activos de la carrera que aún no acreditan (avisos de vacantes nuevas). */
    public static function alumnosDeCarrera(int $carreraId): Collection
    {
        return User::where('activo', true)
            ->whereHas('alumno', fn($a) => $a->where('carrera_id', $carreraId)
                ->whereDoesntHave('practicas', fn($p) => $p->where('practicas.estado', \App\Enums\PracticaEstado::Acreditada->value)))
            ->get();
    }

    public static function alumnoDe(Practica $practica): ?User
    {
        return $practica->postulacion->alumno->user;
    }

    public static function empresaDe(Practica $practica): ?User
    {
        return $practica->postulacion->programa->convenio?->empresa?->user;
    }
}
