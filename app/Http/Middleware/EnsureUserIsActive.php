<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de una cuenta desactivada en cuanto hace su siguiente petición.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->activo === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta está desactivada. Contacta a la Coordinación de Prácticas Profesionales.',
            ]);
        }

        // Sesión abierta de una cuenta cuyo perfil se eliminó: se cierra en vez de pedirle verificar el correo
        if ($user && ! $user->tienePerfil()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta ya no está registrada en el sistema. Si crees que es un error, contacta a la Coordinación de Prácticas Profesionales.',
            ]);
        }

        return $next($request);
    }
}
