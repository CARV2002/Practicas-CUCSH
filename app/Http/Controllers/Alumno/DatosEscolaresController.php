<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alumno;

use App\Enums\Turno;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Grado y turno del alumno: se imprimen en su Carta de Presentación. */
class DatosEscolaresController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'grado' => ['required', 'integer', 'between:1,12'],
            'turno' => ['required', Rule::enum(Turno::class)],
        ], [
            'grado.required' => 'Indica el semestre que cursas.',
            'turno.required' => 'Indica tu turno.',
        ]);

        $request->user()->alumno->update($datos);

        return back()->with('status', 'datos-escolares-updated');
    }
}
