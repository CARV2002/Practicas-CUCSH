<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Rol;
use Closure;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a uno o varios roles (role:administrador,vinculacion); si no corresponde, 403.
 */
class RoleMiddleware
{
    /** Acepta uno o varios roles: `role:alumno` o `role:coordinador,administrador`. */
    public function handle($request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (! $user->hasAnyRole($roles)) {
            abort(403);
        }

        $deRuta = array_values(array_filter($roles, fn (string $r) => $user->hasRole($r)));
        $esPersonal = collect($deRuta)->contains(fn (string $r) => Rol::tryFrom($r)?->esPersonal() === true);

        // Sin perfil de Personal no hay alcance definido: se niega con mensaje claro
        if ($esPersonal && $user->personal === null) {
            abort(403, 'Tu cuenta no tiene perfil de personal institucional. Contacta al responsable del sistema.');
        }

        // Un coordinador (que no es además administrador) debe tener carrera o división asignada
        if ($deRuta === [Rol::Coordinador->value] && ! $user->personal->tieneAlcanceDeCoordinacion()) {
            abort(403, 'Tu cuenta de coordinación no tiene carrera ni división asignada. Contacta a Vinculación.');
        }

        return $next($request);
    }
}
