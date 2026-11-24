<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Auditoria;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;

/**
 * Anota en la bitácora los eventos de acceso: inicio y cierre de sesión, intentos fallidos, bloqueos,
 * contraseña restablecida y correo verificado.
 */
class RegistrarAccesos
{
    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'inicioSesion',
            Logout::class => 'cierreSesion',
            Failed::class => 'fallido',
            Lockout::class => 'bloqueado',
            PasswordReset::class => 'contrasenaRestablecida',
            Verified::class => 'correoVerificado',
        ];
    }

    public function inicioSesion(Login $e): void
    {
        if ($e->user instanceof User) {
            Auditoria::registrar('acceso.inicio_sesion', "Inicio de sesión de {$e->user->email}", $e->user, actor: $e->user);
        }
    }

    public function cierreSesion(Logout $e): void
    {
        if ($e->user instanceof User) {
            Auditoria::registrar('acceso.cierre_sesion', "Cierre de sesión de {$e->user->email}", $e->user, actor: $e->user);
        }
    }

    public function fallido(Failed $e): void
    {
        Auditoria::registrar(
            'acceso.fallido',
            'Intento fallido de inicio de sesión con el correo '.($e->credentials['email'] ?? 'desconocido'),
            $e->user instanceof User ? $e->user : null,
        );
    }

    public function bloqueado(Lockout $e): void
    {
        Auditoria::registrar('acceso.bloqueado', 'Bloqueo por exceso de intentos con el correo '.$e->request->input('email', 'desconocido'));
    }

    public function contrasenaRestablecida(PasswordReset $e): void
    {
        if ($e->user instanceof User) {
            Auditoria::registrar('acceso.contrasena_restablecida', "Contraseña restablecida por correo: {$e->user->email}", $e->user, actor: $e->user);
        }
    }

    public function correoVerificado(Verified $e): void
    {
        if ($e->user instanceof User) {
            Auditoria::registrar('acceso.correo_verificado', "Correo verificado: {$e->user->email}", $e->user, actor: $e->user);
        }
    }
}
