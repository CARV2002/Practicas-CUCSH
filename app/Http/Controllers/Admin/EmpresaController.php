<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\TipoOrganismo;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    public function index(Request $request): View
    {
        $empresas = Empresa::with(['user', 'convenios'])
            ->withCount('convenios')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('razon_social', 'like', '%'.$request->string('q').'%')
                ->orWhere('rfc', 'like', '%'.$request->string('q').'%')
                ->orWhere('contacto_nombre', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_organismo', $request->string('tipo')))
            ->when($request->string('convenio')->toString() === 'vigente', fn ($q) => $q->whereHas('convenios', fn ($c) => $c->vigentes()))
            ->when($request->string('convenio')->toString() === 'sin', fn ($q) => $q->whereDoesntHave('convenios', fn ($c) => $c->vigentes()))
            ->orderBy('razon_social')
            ->paginate(15)
            ->withQueryString();

        return view('admin.lista-empresas', [
            'empresas' => $empresas,
            'tipos' => TipoOrganismo::cases(),
            'estados' => ConvenioEstado::cases(),
        ]);
    }

    public function show(Empresa $empresa): View
    {
        $empresa->load(['user', 'convenios.dictamenes.carrera']);

        return view('admin.empresa-show', [
            'empresa' => $empresa,
            'colecciones' => Empresa::coleccionesObligatorias(),
            'expedienteCompleto' => $empresa->expedienteCompleto(),
        ]);
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $request->merge(['rfc' => \Illuminate\Support\Str::upper(trim((string) $request->input('rfc')))]);

        $datos = $request->validate([
            'rfc' => ['required', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/u', Rule::unique('empresas', 'rfc')->ignore($empresa->id)],
            'razon_social' => ['required', 'string', 'max:255'],
            'tipo_organismo' => ['required', Rule::enum(TipoOrganismo::class)],
            'domicilio_fiscal' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'contacto_nombre' => ['required', 'string', 'max:255'],
            'contacto_cargo' => ['nullable', 'string', 'max:255'],
            'contacto_telefono' => ['nullable', 'string', 'max:20'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'proyectos_principales' => ['nullable', 'string', 'max:3000'],
        ]);

        $empresa->update($datos);

        return back()->with('status', 'Empresa actualizada.');
    }

    /** Activa o desactiva la cuenta de acceso de la empresa. */
    public function toggleActivo(Empresa $empresa): RedirectResponse
    {
        $user = $empresa->user;

        abort_if($user === null, 404);

        $user->update(['activo' => ! $user->activo]);

        return back()->with('status', $user->activo ? 'Cuenta activada.' : 'Cuenta desactivada.');
    }
}
