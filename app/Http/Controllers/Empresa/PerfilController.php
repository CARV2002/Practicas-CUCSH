<?php

declare(strict_types=1);

namespace App\Http\Controllers\Empresa;

use App\Http\Controllers\Controller;
use App\Support\Formatos;
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
        $request->merge(['contacto_telefono' => Formatos::digitos($request->input('contacto_telefono')) ?: null]);

        $request->user()->empresa->update($request->validateWithBag('organizacion', [
            'contacto_nombre' => ['required', 'string', 'max:255', 'regex:'.Formatos::NOMBRE_PERSONA],
            'contacto_cargo' => ['nullable', 'string', 'max:255', 'regex:'.Formatos::TEXTO_LETRAS],
            'contacto_telefono' => ['nullable', 'regex:'.Formatos::TELEFONO],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'proyectos_principales' => ['nullable', 'string', 'max:3000'],
        ], [
            'contacto_nombre.required' => 'Escribe el nombre de la persona de contacto.',
            'contacto_nombre.regex' => 'Escribe el nombre completo del contacto (nombre y apellido), solo con letras.',
        ] + Formatos::mensajes(), [
            'contacto_nombre' => 'persona de contacto',
            'contacto_cargo' => 'cargo',
            'contacto_telefono' => 'teléfono',
            'descripcion' => 'descripción',
            'proyectos_principales' => 'proyectos principales',
        ]));

        return redirect()->route('profile.edit', ['seccion' => 'organizacion'])->with('status', 'organizacion-updated');
    }
}
