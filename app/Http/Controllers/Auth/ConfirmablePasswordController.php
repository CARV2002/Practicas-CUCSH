<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Confirmación de contraseña antes de acciones sensibles.
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * Pantalla para confirmar la contraseña antes de una acción sensible.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Confirma la contraseña; vale por unos minutos (password_timeout).
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
