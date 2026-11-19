<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Support\Ayuda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Centro de ayuda por rol. Vinculación y el Administrador no lo tienen: se les envía a su panel. */
class AyudaController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $usuario = $request->user();
        $rol = Rol::tryFrom((string) $usuario->roles->first()?->name);

        if (! Ayuda::disponiblePara($rol)) {
            return redirect()->route('dashboard');
        }

        return view('ayuda', [
            'usuario' => $usuario,
            'rol' => $rol,
            'contenido' => Ayuda::para($rol),
        ]);
    }
}
