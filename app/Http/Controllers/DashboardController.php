<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use Illuminate\Http\RedirectResponse;

/**
 * Ruta /dashboard: manda a cada usuario al panel de su rol.
 */
class DashboardController extends Controller
{
    /** Cada usuario entra al panel de su rol; sin rol no hay acceso. */
    public function index(): RedirectResponse
    {
        $user = auth()->user();

        foreach (Rol::cases() as $rol) {
            if ($user->hasRole($rol->value)) {
                return redirect()->route($rol->panel());
            }
        }

        abort(403, 'Tu cuenta no tiene un rol asignado.');
    }
}
