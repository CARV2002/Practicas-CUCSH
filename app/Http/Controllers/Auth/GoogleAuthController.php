<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/** Acceso con Google, solo con correo institucional de la UdG. */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        // "hd" solo es una pista visual para Google; la validación real está en callback().
        return Socialite::driver('google')
            ->with(['hd' => '*', 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->fallo('No se pudo iniciar sesión con Google. Inténtalo de nuevo.');
        }

        $email = Str::lower((string) $google->getEmail());
        $verificado = filter_var($google->user['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
        $dominio = Str::after($email, '@');

        $dominiosAlumno = config('practicas.google.dominios_alumno');
        $dominiosPersonal = config('practicas.google.dominios_personal');
        $esAlumno = in_array($dominio, $dominiosAlumno, true);
        $esPersonal = in_array($dominio, $dominiosPersonal, true);

        if ($email === '' || ! $verificado || ! ($esAlumno || $esPersonal)) {
            return $this->fallo(
                'Solo puedes entrar con tu correo institucional de la UdG ('
                .collect(array_merge($dominiosAlumno, $dominiosPersonal))->map(fn ($d) => '@'.$d)->implode(', ').').'
            );
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            if (! $user->activo) {
                return $this->fallo('Tu cuenta está desactivada. Contacta a la Coordinación de Prácticas.');
            }

            if ($user->email_verified_at === null) {
                // Google ya comprobó que el correo es suyo: se confirma y se invalida
                // la contraseña anterior por si otra persona registró esa cuenta.
                $user->forceFill(['email_verified_at' => now(), 'password' => Str::random(40)])->save();
            }

            return $this->entrar($request, $user);
        }

        if ($esAlumno) {
            $request->session()->put('google_registro', [
                'name' => $google->getName() ?: Str::before($email, '@'),
                'email' => $email,
            ]);

            return redirect()->route('registro.google');
        }

        return $this->fallo(
            'Tu correo institucional aún no tiene acceso al sistema. Solicítalo a la Coordinación de Prácticas: '
            .config('practicas.contacto.correo')
        );
    }

    public function completar(Request $request): View|RedirectResponse
    {
        $datos = $request->session()->get('google_registro');

        if (! $datos) {
            return redirect()->route('login');
        }

        return view('pages.auth.registro-google', [
            'datos' => $datos,
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $datos = $request->session()->get('google_registro');

        if (! $datos) {
            return redirect()->route('login');
        }

        $request->merge(['num_control' => trim((string) $request->input('num_control'))]);

        $validado = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'carrera_id' => ['required', 'integer', Rule::exists('carreras', 'id')],
            'num_control' => ['required', 'string', 'max:20', Rule::unique('alumnos', 'num_control')],
            'aviso_privacidad' => ['accepted'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'carrera_id.exists' => 'Selecciona una carrera válida.',
            'num_control.unique' => 'Ya existe una cuenta con ese código de alumno.',
            'aviso_privacidad.accepted' => 'Debes aceptar el aviso de privacidad para registrarte.',
        ], [
            'name' => 'nombre',
            'carrera_id' => 'carrera',
            'num_control' => 'código de alumno',
            'aviso_privacidad' => 'aviso de privacidad',
        ]);

        if (User::where('email', $datos['email'])->exists()) {
            $request->session()->forget('google_registro');

            return $this->fallo('Ya existe una cuenta con ese correo. Inicia sesión.');
        }

        $user = DB::transaction(function () use ($datos, $validado) {
            $user = User::create([
                'name' => $validado['name'],
                'email' => $datos['email'],
                'password' => Str::random(40), // podrá definir una con «Recuperar contraseña»
                'aviso_privacidad_at' => now(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(Rol::Alumno->value);
            $user->alumno()->create([
                'carrera_id' => $validado['carrera_id'],
                'num_control' => $validado['num_control'],
            ]);

            return $user;
        });

        $request->session()->forget('google_registro');

        return $this->entrar($request, $user);
    }

    private function entrar(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function fallo(string $mensaje): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $mensaje]);
    }
}