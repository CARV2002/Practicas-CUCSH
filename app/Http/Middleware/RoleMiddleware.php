<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Acepta uno o varios roles: `role:alumno` o `role:administrador,empresa`.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->hasAnyRole($roles)) {
            abort(403);
        }

        return $next($request);
    }
}
