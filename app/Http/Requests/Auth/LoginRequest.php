<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Auditoria;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inicio de sesión: valida correo y contraseña y limita los intentos fallidos.
 */
class LoginRequest extends FormRequest
{
    /**
     * Cualquiera puede intentar iniciar sesión.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Revisa correo y contraseña; si fallan, suma un intento al límite.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Solo se revela después de acertar la contraseña, así no se filtra qué correos existen.
        $user = User::where('email', $this->string('email')->lower()->toString())->first();

        if ($user && ! $user->activo && Hash::check($this->string('password')->toString(), $user->password)) {
            Auditoria::registrar('acceso.cuenta_inactiva', "Intento de acceso a la cuenta desactivada {$user->email}", $user, actor: $user);
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está desactivada. Contacta a la Coordinación de Prácticas Profesionales.',
            ]);
        }

        // Cuenta sin perfil (su alumno, empresa o personal se eliminó): se trata como si no existiera
        if ($user && ! $user->tienePerfil()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (
            ! Auth::attempt(
                [...$this->only('email', 'password'), 'activo' => true],
                $this->boolean('remember')
            )
        ) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Tras 5 intentos fallidos se bloquea el acceso un rato (protege contra adivinar contraseñas).
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Los intentos se cuentan por correo + IP.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
