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

        // Teléfono (10 números) y extensión (solo números) se capturan aparte y se guardan juntos
        $request->merge([
            'coordinacion_telefono' => \App\Support\Formatos::digitos($request->input('coordinacion_telefono')) ?: null,
            'coordinacion_extension' => \App\Support\Formatos::digitos($request->input('coordinacion_extension')) ?: null,
        ]);

        $datos = $request->validate([
            'horas_requeridas' => ['required', 'integer', 'min:1', 'max:2000'],
            'porcentaje_creditos_minimo' => ['required', 'numeric', 'min:0', 'max:100'],
            'creditos_otorgados' => ['required', 'integer', 'min:0', 'max:50'],
            'esquema_reportes' => ['sometimes', 'required', Rule::enum(EsquemaReportes::class)],
            'coordinacion_correo' => ['nullable', 'email', 'max:255'],
            'coordinacion_telefono' => ['nullable', 'regex:' . \App\Support\Formatos::TELEFONO],
            'coordinacion_extension' => ['nullable', 'regex:' . \App\Support\Formatos::EXTENSION],
        ], \App\Support\Formatos::mensajes());

        $datos['coordinacion_telefono'] = \App\Support\Formatos::telefonoConExtension($datos['coordinacion_telefono'] ?? null, $datos['coordinacion_extension'] ?? null);
        unset($datos['coordinacion_extension']);

        $carrera->update($datos);

        return back()->with('status', "Reglas de {$carrera->carrera_clave} actualizadas.");
    }
}
