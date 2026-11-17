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

    /**
     * Coordinación de carrera activa con alcance sobre esa carrera (o su división).
     * Postulaciones, reportes y cierres van SOLO aquí: ni Vinculación ni el administrador técnico.
     */
    public static function coordinadores(int $carreraId): Collection
    {
        return User::role(Rol::Coordinador->value)->where('activo', true)->with('personal')->get()
            ->filter(fn(User $u) => $u->personal !== null
                && $u->personal->tieneAlcanceDeCoordinacion()
                && $u->personal->puedeVerCarrera($carreraId))
            ->values();
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

    public static function alumnoDe(Practica $practica): ?User
    {
        return $practica->postulacion->alumno->user;
    }

    public static function empresaDe(Practica $practica): ?User
    {
        return $practica->postulacion->programa->convenio?->empresa?->user;
    }
}
