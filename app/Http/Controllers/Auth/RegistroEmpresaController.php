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

            // Solo la cuenta: los datos fiscales, legales y el expediente se llenan en «Mi convenio»
            $user->empresa()->create([
                'rfc' => $datos['rfc'],
                'razon_social' => $datos['razon_social'],
                'tipo_organismo' => $datos['tipo_organismo'],
                'tipo_organismo_otro' => $datos['tipo_organismo'] === 'otro' ? ($datos['tipo_organismo_otro'] ?? null) : null,
                'contacto_nombre' => $datos['name'],
                'contacto_cargo' => $datos['contacto_cargo'],
                'contacto_telefono' => $datos['contacto_telefono'],
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
