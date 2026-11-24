<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinacion;

use App\Enums\PracticaEstado;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Division;
use App\Models\Profesor;
use App\Rules\UnicoNormalizado;
use App\Support\Formatos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de profesores supervisores que administra cada coordinación. Los profesores no tienen cuenta:
 * la coordinación los asigna a cada práctica (deben ser de la división de la carrera del alumno).
 * Nunca se borran: se desactivan, para no perder a quién supervisó cada práctica.
 */
class ProfesorController extends Controller
{
    public function index(Request $request): View
    {
        $divisiones = $this->divisionesEnAlcance($request);

        $profesores = Profesor::with('division')
            ->withCount(['practicas as practicas_activas_count' => fn ($q) => $q->whereIn('estado', PracticaEstado::valores(PracticaEstado::activas()))])
            ->whereIn('division_id', $divisiones->pluck('id'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->when($request->string('estado')->toString() === 'activos', fn ($q) => $q->where('activo', true))
            ->when($request->string('estado')->toString() === 'inactivos', fn ($q) => $q->where('activo', false))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('coordinacion.profesores', [
            'profesores' => $profesores,
            'divisiones' => $divisiones,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        Profesor::create($datos + ['activo' => true]);

        return back()->with('status', "Profesor {$datos['nombre']} agregado al catálogo.");
    }

    public function update(Request $request, Profesor $profesor): RedirectResponse
    {
        $this->autorizar($request, $profesor);

        $datos = $this->validar($request, $profesor);

        // Cambiar de división a alguien con prácticas activas dejaría supervisores fuera de la división del alumno
        if ((int) $datos['division_id'] !== (int) $profesor->division_id
            && $profesor->practicas()->whereIn('estado', PracticaEstado::valores(PracticaEstado::activas()))->exists()) {
            return back()->withErrors(['division_id' => 'Este profesor supervisa prácticas en curso: no se puede cambiar de división hasta que terminen.']);
        }

        $profesor->update($datos);

        return back()->with('status', 'Datos del profesor actualizados.');
    }

    public function toggleActivo(Request $request, Profesor $profesor): RedirectResponse
    {
        $this->autorizar($request, $profesor);

        $profesor->update(['activo' => ! $profesor->activo]);

        $activas = $profesor->practicas()->whereIn('estado', PracticaEstado::valores(PracticaEstado::activas()))->count();

        return back()->with('status', $profesor->activo
            ? 'Profesor activado: ya se puede asignar a prácticas.'
            : 'Profesor desactivado: ya no aparece para nuevas asignaciones.'.($activas ? " Sigue registrado en sus {$activas} práctica(s) en curso; reasígnalas si hace falta." : ''));
    }

    /** Divisiones de las carreras que coordina (una carrera → su división; una división → esa misma). */
    private function divisionesEnAlcance(Request $request)
    {
        return Division::whereIn('id', Carrera::enAlcance($request->user()->personal)->select('division_id'))->orderBy('nombre')->get();
    }

    private function autorizar(Request $request, Profesor $profesor): void
    {
        abort_unless($this->divisionesEnAlcance($request)->contains('id', $profesor->division_id), 403);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Profesor $profesor = null): array
    {
        // Una coordinación pertenece a una sola división: el profesor queda en ella aunque no se envíe
        $divisiones = $this->divisionesEnAlcance($request);
        if ($divisiones->count() === 1) {
            $request->merge(['division_id' => $divisiones->first()->id]);
        }

        $request->merge([
            'nombre' => preg_replace('/\s+/u', ' ', trim((string) $request->input('nombre'))),
            'email' => Str::lower(trim((string) $request->input('email'))) ?: null,
            'telefono' => Formatos::digitos($request->input('telefono')) ?: null,
            'extension' => Formatos::digitos($request->input('extension')) ?: null,
        ]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:5', 'max:'.Formatos::MAX_NOMBRE_PERSONA, 'regex:'.Formatos::NOMBRE_PERSONA,
                new UnicoNormalizado('profesores', 'nombre', 'Ese profesor ya está en el catálogo.', $profesor?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('profesores', 'email')->ignore($profesor?->id)],
            'telefono' => ['nullable', 'regex:'.Formatos::TELEFONO],
            'extension' => ['nullable', 'regex:'.Formatos::EXTENSION],
            'division_id' => ['required', 'integer', Rule::in($divisiones->pluck('id')->all())],
        ], [
            'nombre.regex' => 'Escribe el nombre del profesor solo con letras (puede llevar grado: Dr., Mtra.).',
            'email.unique' => 'Ya hay un profesor con ese correo en el catálogo.',
            'division_id.in' => 'Elige una división de las carreras que coordinas.',
        ] + Formatos::mensajes());

        // Teléfono (10 números) y extensión se capturan aparte y se guardan juntos
        $datos['telefono'] = Formatos::telefonoConExtension($datos['telefono'] ?? null, $datos['extension'] ?? null);
        unset($datos['extension']);

        return $datos;
    }
}
