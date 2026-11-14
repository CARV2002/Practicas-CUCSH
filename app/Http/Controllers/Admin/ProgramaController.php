<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Modalidad;
use App\Enums\ProgramaEstado;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Programa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramaController extends Controller
{
    public function index(Request $request): View
    {
        $programas = Programa::with(['convenio.empresa', 'carrera'])
            ->conCuposOcupados()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('titulo', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('convenio.empresa', fn ($e) => $e->where('razon_social', 'like', '%'.$request->string('q').'%'))))
            ->when($request->filled('carrera_id'), fn ($q) => $q->where('carrera_id', $request->integer('carrera_id')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('modalidad'), fn ($q) => $q->where('modalidad', $request->string('modalidad')))
            ->when($request->filled('ciclo'), fn ($q) => $q->where('ciclo', $request->string('ciclo')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.lista-programas', [
            'programas' => $programas,
            'carreras' => Carrera::orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'estados' => ProgramaEstado::cases(),
            'modalidades' => Modalidad::cases(),
            'ciclos' => Programa::select('ciclo')->distinct()->orderByDesc('ciclo')->pluck('ciclo'),
            'convenios' => Convenio::vigentes()->with(['empresa', 'dictamenes.carrera'])->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Programa::create($this->validar($request));

        return back()->with('status', 'Programa creado como borrador.');
    }

    public function update(Request $request, Programa $programa): RedirectResponse
    {
        $this->authorize('gestionar', $programa);

        $programa->update($this->validar($request, $programa));

        return back()->with('status', 'Programa actualizado.');
    }

    /** Cambia el estado: publicar, cerrar o regresar a borrador. Las reglas las aplica ProgramaObserver. */
    public function cambiarEstado(Request $request, Programa $programa): RedirectResponse
    {
        $this->authorize('gestionar', $programa);
        
        $datos = $request->validate(['estado' => ['required', Rule::enum(ProgramaEstado::class)]]);

        $programa->update(['estado' => $datos['estado']]);

        return back()->with('status', 'Estado actualizado.');
    }

    public function destroy(Programa $programa): RedirectResponse
    {
        $this->authorize('gestionar', $programa);

        if ($programa->postulaciones()->exists()) {
            return back()->withErrors(['programa' => 'No se puede eliminar: ya tiene postulaciones. Ciérralo en su lugar.']);
        }

        $programa->delete();

        return back()->with('status', 'Programa eliminado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Programa $programa = null): array
    {
        $datos = $request->validate([
            'modalidad' => ['required', Rule::enum(Modalidad::class)],
            'convenio_id' => ['nullable', 'integer', Rule::exists('convenios', 'id')],
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

        if ($datos['modalidad'] === Modalidad::Interna->value) {
            $datos['convenio_id'] = null;
        }

        // El textarea trae un punto por línea; se guarda como lista JSON
        $datos['actividades'] = collect(preg_split('/\R/', $datos['actividades']))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        return $datos;
    }
}

