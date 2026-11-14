<?php

namespace App\Http\Controllers\Empresa;

use App\Enums\Modalidad;
use App\Enums\PostulacionEstado;
use App\Enums\ProgramaEstado;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Programa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProgramaController extends Controller
{
    /** Programas de la empresa, con cupos y su convenio vigente. */
    public function index(Request $request): View
    {
        $empresa = $request->user()->empresa;

        $programas = Programa::with(['carrera', 'convenio'])
            ->conCuposOcupados()
            ->whereHas('convenio', fn($c) => $c->where('empresa_id', $empresa->id))
            ->when($request->filled('q'), fn($q) => $q->where('titulo', 'like', '%' . $request->string('q') . '%'))
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->string('estado')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Convenios vigentes con las carreras aprobadas en cada uno (las únicas para las que se puede ofertar)
        $convenios = $empresa->convenios()
            ->vigentes()
            ->with(['dictamenes' => fn($d) => $d->where('dictamen', \App\Enums\Dictamen::Aprobatorio->value)->with('carrera')])
            ->get();

        return view('empresa.lista-programas', [
            'programas' => $programas,
            'convenios' => $convenios,
            'estados' => ProgramaEstado::cases(),
            'puedeCrear' => $convenios->isNotEmpty(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $this->convenioPropio($request, (int) $datos['convenio_id']);

        Programa::create($datos + ['modalidad' => Modalidad::Externa->value, 'estado' => ProgramaEstado::Borrador->value]);

        return back()->with('status', 'Programa creado como borrador.');
    }

    public function update(Request $request, Programa $programa): RedirectResponse
    {
        $this->autorizar($request, $programa);

        $datos = $this->validar($request);

        $this->convenioPropio($request, (int) $datos['convenio_id']);

        $programa->update($datos);

        return back()->with('status', 'Programa actualizado.');
    }

    /** La empresa solo publica, cierra o regresa a borrador (las reglas las aplica ProgramaObserver). */
    public function cambiarEstado(Request $request, Programa $programa): RedirectResponse
    {
        $this->autorizar($request, $programa);

        $datos = $request->validate(['estado' => ['required', Rule::enum(ProgramaEstado::class)]]);

        $programa->update(['estado' => $datos['estado']]);

        return back()->with('status', 'Estado actualizado.');
    }

    public function destroy(Request $request, Programa $programa): RedirectResponse
    {
        $this->autorizar($request, $programa);

        if ($programa->postulaciones()->exists()) {
            return back()->withErrors(['programa' => 'No se puede eliminar: ya tiene postulaciones. Ciérralo en su lugar.']);
        }

        $programa->delete();

        return back()->with('status', 'Programa eliminado.');
    }

    /** Postulantes de un programa (solo lectura para la empresa). */
    public function postulantes(Request $request, Programa $programa): View
    {
        $this->autorizar($request, $programa);

        return view('empresa.programa-postulantes', [
            'programa' => $programa->load('carrera'),
            'postulaciones' => $programa->postulaciones()
                ->with('alumno.user', 'alumno.carrera')
                ->whereIn('estado', PostulacionEstado::valores([
                    PostulacionEstado::CartaPresentacionEmitida,
                    PostulacionEstado::CartaAceptacionRecibida,
                    PostulacionEstado::Aceptada,
                ]))
                ->latest('id')
                ->get(),
        ]);
    }

    private function autorizar(Request $request, Programa $programa): void
    {
        $this->authorize('gestionar', $programa);
    }

    private function convenioPropio(Request $request, int $convenioId): void
    {
        $propio = $request->user()->empresa->convenios()->whereKey($convenioId)->exists();

        if (!$propio) {
            throw ValidationException::withMessages(['convenio_id' => 'El convenio no pertenece a tu empresa.']);
        }
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'convenio_id' => ['required', 'integer', Rule::exists('convenios', 'id')],
            'carrera_id' => ['required', 'integer', Rule::exists('carreras', 'id')],
            'ciclo' => ['required', 'string', 'regex:/^\d{4}[AB]$/'],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'actividades' => ['required', 'string', 'max:3000'],
            'perfil_requerido' => ['required', 'string', 'max:2000'],
            'horario' => ['nullable', 'string', 'max:255'],
            'sede' => ['nullable', 'string', 'max:255'],
            'cupos_totales' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'ciclo.regex' => 'El ciclo debe tener el formato 2027A o 2027B.',
        ]);

        $datos['actividades'] = collect(preg_split('/\R/', $datos['actividades']))
            ->map(fn($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        return $datos;
    }
}