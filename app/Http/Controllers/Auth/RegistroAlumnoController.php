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
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
