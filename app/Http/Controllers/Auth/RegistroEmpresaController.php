<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroEmpresaRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Registro público de empresas: crea usuario + rol "empresa" + perfil de empresa. */
class RegistroEmpresaController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.registro-empresa', [
            'tiposOrganismo' => TipoOrganismo::cases(),
        ]);
    }

    public function store(RegistroEmpresaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $user = DB::transaction(function () use ($datos) {
            $user = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'aviso_privacidad_at' => now(),
            ]);

            $user->assignRole(Rol::Empresa->value);

            $user->empresa()->create([
                'rfc' => $datos['rfc'],
                'razon_social' => $datos['razon_social'],
                'tipo_organismo' => $datos['tipo_organismo'],
                'contacto_nombre' => $datos['name'],
                'contacto_cargo' => $datos['contacto_cargo'] ?? null,
                'contacto_telefono' => $datos['contacto_telefono'] ?? null,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
