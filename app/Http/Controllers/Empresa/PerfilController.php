<?php

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La organización mantiene al día su contacto y su presentación desde «Mi perfil».
 * Razón social, RFC y tipo de organismo son datos legales: solo los corrige Vinculación.
 */
class PerfilController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->user()->empresa->update($request->validateWithBag('organizacion', [
            'contacto_nombre' => ['required', 'string', 'max:255'],
            'contacto_cargo' => ['nullable', 'string', 'max:255'],
            'contacto_telefono' => ['nullable', 'string', 'max:20'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'proyectos_principales' => ['nullable', 'string', 'max:3000'],
        ], [
            'contacto_nombre.required' => 'Escribe el nombre de la persona de contacto.',
            'contacto_telefono.max' => 'El teléfono no debe pasar de 20 caracteres.',
        ], [
            'contacto_nombre' => 'persona de contacto',
            'contacto_cargo' => 'cargo',
            'contacto_telefono' => 'teléfono',
            'descripcion' => 'descripción',
            'proyectos_principales' => 'proyectos principales',
        ]));

        return redirect()->route('profile.edit', ['seccion' => 'organizacion'])->with('status', 'organizacion-updated');
    }
}
