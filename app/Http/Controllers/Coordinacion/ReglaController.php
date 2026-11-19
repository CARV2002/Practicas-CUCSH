<?php

namespace App\Http\Controllers\Coordinacion;

use App\Enums\EsquemaReportes;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reglas de las carreras de la coordinación: horas requeridas, % mínimo de créditos, créditos que otorga,
 * periodicidad de reportes y el contacto que ven los alumnos («Tu coordinación»).
 * El alta de divisiones/carreras sigue siendo del administrador.
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
            'esquemas' => EsquemaReportes::cases(),
        ]);
    }

    public function update(Request $request, Carrera $carrera): RedirectResponse
    {
        abort_unless($request->user()->personal->puedeVerCarrera((int) $carrera->id), 403);

        $carrera->update($request->validate([
            'horas_requeridas' => ['required', 'integer', 'min:1', 'max:2000'],
            'porcentaje_creditos_minimo' => ['required', 'numeric', 'min:0', 'max:100'],
            'creditos_otorgados' => ['required', 'integer', 'min:0', 'max:50'],
            'esquema_reportes' => ['sometimes', 'required', Rule::enum(EsquemaReportes::class)],
            'coordinacion_correo' => ['nullable', 'email', 'max:255'],
            'coordinacion_telefono' => ['nullable', 'string', 'max:40'],
        ]));

        return back()->with('status', "Reglas de {$carrera->carrera_clave} actualizadas.");
    }
}
