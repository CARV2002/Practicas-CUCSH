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
            'divisiones' => Division::with(['carreras' => fn ($q) => $q->orderBy('nombre')])
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function storeDivision(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('divisiones', 'nombre')],
        ]);

        Division::create($datos);

        return back()->with('status', 'División creada.');
    }

    public function updateDivision(Request $request, Division $division): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('divisiones', 'nombre')->ignore($division->id)],
        ]);

        $division->update($datos);

        return back()->with('status', 'División actualizada.');
    }

    public function storeCarrera(Request $request): RedirectResponse
    {
        $request->merge(['carrera_clave' => Str::upper(trim((string) $request->input('carrera_clave')))]);

        Carrera::create($request->validate($this->reglasCarrera()));

        return back()->with('status', 'Carrera creada.');
    }

    public function updateCarrera(Request $request, Carrera $carrera): RedirectResponse
    {
        $request->merge(['carrera_clave' => Str::upper(trim((string) $request->input('carrera_clave')))]);

        $carrera->update($request->validate($this->reglasCarrera($carrera)));

        return back()->with('status', 'Carrera actualizada.');
    }

    /** @return array<string, mixed> */
    private function reglasCarrera(?Carrera $carrera = null): array
    {
        return [
            'division_id' => ['required', 'integer', Rule::exists('divisiones', 'id')],
            'carrera_clave' => ['required', 'string', 'max:10', Rule::unique('carreras', 'carrera_clave')->ignore($carrera?->id)],
            'nombre' => ['required', 'string', 'max:255'],
            'horas_requeridas' => ['required', 'integer', 'min:1', 'max:2000'],
            'porcentaje_creditos_minimo' => ['required', 'numeric', 'min:0', 'max:100'],
            'creditos_otorgados' => ['required', 'integer', 'min:0', 'max:50'],
        ];
    }
}