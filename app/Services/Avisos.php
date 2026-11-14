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

    /** Coordinación activa; con $carreraId, solo quien tiene alcance sobre esa carrera. */
    public static function administradores(?int $carreraId = null): Collection
    {
        return User::role(Rol::Administrador->value)->where('activo', true)->with('personal')->get()
            ->filter(fn(User $u) => $u->personal !== null
                && ($carreraId === null || $u->personal->puedeVerCarrera($carreraId)))
            ->values();
    }

    /** Coordinación que puede ver ese convenio (misma regla que ConvenioPolicy). */
    public static function administradoresDe(Convenio $convenio): Collection
    {
        return User::role(Rol::Administrador->value)->where('activo', true)->with('personal')->get()
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

    /** Coordinación activa con alcance global (Lorena): quien lleva el trámite de convenios. */
    public static function coordinacionGlobal(): Collection
    {
        return self::administradores()
            ->filter(fn(User $u) => $u->personal->tieneAlcanceGlobal())
            ->values();
    }
}