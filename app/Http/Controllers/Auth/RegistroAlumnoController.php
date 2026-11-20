<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroAlumnoRequest;
use App\Models\Carrera;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Registro público de alumnos: crea usuario + rol "alumno" + perfil, todo en una transacción. */
class RegistroAlumnoController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.registro-alumno', [
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(RegistroAlumnoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $user = DB::transaction(function () use ($datos) {
            $user = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],   // el cast "hashed" la encripta
                'aviso_privacidad_at' => now(),     // evidencia del consentimiento
            ]);

            $user->assignRole(Rol::Alumno->value);

            $user->alumno()->create([
                'carrera_id' => $datos['carrera_id'],
                'num_control' => $datos['num_control'],
                'grado' => $datos['grado'],
                'turno' => $datos['turno'],
            ]);

            return $user;
        });

        Auth::login($user);

        // Si Gmail no acepta el envío, la cuenta ya existe: se avisa y puede reenviar el correo de confirmación
        try {
            event(new Registered($user));
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            report($e);

            return redirect()->route('verification.notice')->with('status', 'verification-failed');
        }

        return redirect(route('dashboard', absolute: false));
    }
}
