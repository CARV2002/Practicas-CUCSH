<?php

namespace App\Http\Controllers\Coordinacion;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reglas académicas de las carreras de la coordinación: horas requeridas, % mínimo de créditos
 * y créditos que otorga. El alta de divisiones/carreras sigue siendo del administrador técnico.
 */
class ReglaController extends Controller
{
    public function index(Request $request): View
    {
        return view('coordinacion.reglas', [
            'carreras' => Carrera::with('division')
                ->enAlcance($request->user()->personal)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function update(Request $request, Carrera $carrera): RedirectResponse
    {
        abort_unless($request->user()->personal->puedeVerCarrera((int) $carrera->id), 403);

        $carrera->update($request->validate([
            'horas_requeridas' => ['required', 'integer', 'min:1', 'max:2000'],
            'porcentaje_creditos_minimo' => ['required', 'numeric', 'min:0', 'max:100'],
            'creditos_otorgados' => ['required', 'integer', 'min:0', 'max:50'],
        ]));

        return back()->with('status', "Reglas de {$carrera->carrera_clave} actualizadas.");
    }
}
