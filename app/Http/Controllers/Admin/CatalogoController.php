<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogoController extends Controller
{
    public function index(): View
    {
        return view('admin.catalogos', [
            'divisiones' => Division::with(['carreras' => fn ($q) => $q->withCount(['alumnos', 'personal'])->orderBy('nombre')])->withCount('personal')
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function storeDivision(Request $request): RedirectResponse
    {
        $request->merge(['nombre' => preg_replace('/\s+/u', ' ', trim((string) $request->input('nombre')))]);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', new \App\Rules\UnicoNormalizado('divisiones', 'nombre', 'Ya existe una división con ese nombre.')],
        ]);

        Division::create($datos);

        return back()->with('status', 'División creada.');
    }

    public function updateDivision(Request $request, Division $division): RedirectResponse
    {
        $request->merge(['nombre' => preg_replace('/\s+/u', ' ', trim((string) $request->input('nombre')))]);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', new \App\Rules\UnicoNormalizado('divisiones', 'nombre', 'Ya existe una división con ese nombre.', $division->id)],
        ]);

        $division->update($datos);

        return back()->with('status', 'División actualizada.');
    }

    public function storeCarrera(Request $request): RedirectResponse
    {
        $request->merge(['carrera_clave' => Str::upper(trim((string) $request->input('carrera_clave')))]);

        // Nace con reglas iniciales; desde ahí solo su coordinación las ajusta
        Carrera::create($request->validate($this->reglasCarrera()) + Carrera::REGLAS_INICIALES);

        return back()->with('status', 'Carrera creada con reglas iniciales (' . Carrera::REGLAS_INICIALES['horas_requeridas'] . ' h, '
            . Carrera::REGLAS_INICIALES['porcentaje_creditos_minimo'] . '% de créditos). Su coordinación las ajusta en «Reglas de mi carrera».');
    }

    public function updateCarrera(Request $request, Carrera $carrera): RedirectResponse
    {
        $request->merge(['carrera_clave' => Str::upper(trim((string) $request->input('carrera_clave')))]);

        $carrera->update($request->validate($this->reglasCarrera($carrera)));

        return back()->with('status', 'Carrera actualizada.');
    }

    /**
     * El administrador solo maneja la estructura (división, clave y nombre). Horas, % de créditos y créditos
     * otorgados son de la coordinación: si llegan en la petición se ignoran (validate solo regresa estas llaves).
     *
     * @return array<string, mixed>
     */
    private function reglasCarrera(?Carrera $carrera = null): array
    {
        return [
            'division_id' => ['required', 'integer', Rule::exists('divisiones', 'id')],
            'carrera_clave' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]{2,10}$/', Rule::unique('carreras', 'carrera_clave')->ignore($carrera?->id)],
            'nombre' => ['required', 'string', 'max:255', new \App\Rules\UnicoNormalizado('carreras', 'nombre', 'Ya existe una carrera con ese nombre.', $carrera?->id)],
        ];
    }
}