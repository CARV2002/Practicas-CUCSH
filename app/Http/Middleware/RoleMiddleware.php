<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use Closure;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /** Acepta uno o varios roles: `role:alumno` o `role:administrador,empresa`. */
    public function handle($request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (! $user->hasAnyRole($roles)) {
            abort(403);
        }

        // Sin perfil de Personal no hay alcance definido: se niega con mensaje claro
        if (in_array(Rol::Administrador->value, $roles, true) && $user->personal === null) {
            abort(403, 'Tu cuenta de administrador no tiene perfil de Coordinación. Contacta al responsable del sistema.');
        }

        return $next($request);
    }
}