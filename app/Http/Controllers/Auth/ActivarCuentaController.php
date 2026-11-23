<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * «Configurar mi contraseña» del personal que da de alta el administrador.
 * Quien abre el enlace demuestra tener acceso a su correo institucional: la cuenta se activa
 * y el correo queda verificado en el mismo paso.
 */
class ActivarCuentaController extends Controller
{
    public function create(Request $request, string $token): View
    {
        $email = (string) $request->query('email');
        $usuario = $email !== '' ? User::where('email', $email)->first() : null;

        // Enlace vencido (72 h), ya usado o alterado: se avisa ANTES de que escriba su contraseña
        $vigente = $usuario !== null && Password::broker('invitaciones')->tokenExists($usuario, $token);

        return view('pages.auth.activar-cuenta', ['token' => $token, 'email' => $email, 'vencido' => !$vigente]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden. Escribe la misma en ambos campos.',
        ]);

        $usuario = null;
        $estado = Password::broker('invitaciones')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$usuario) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->markEmailAsVerified();
                $usuario = $user;
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'El enlace ya venció o ya se usó. Solicita uno nuevo con «¿Olvidaste tu contraseña?» o pide a la administración que te lo reenvíe.',
            ]);
        }

        if (!$usuario->activo) {
            return redirect()->route('login')->with('status', 'Tu contraseña quedó lista, pero tu cuenta está desactivada. Contacta a la administración.');
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Tu contraseña quedó lista y tu cuenta está activa. Te damos la bienvenida.');
    }
}
