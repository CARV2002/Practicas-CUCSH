<?php

namespace App\Http\Controllers\Coordinacion;

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
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;

class ProgramaController extends Controller
{
    /** Programas y vacantes de las carreras de la coordinación (internos y de empresas con convenio). */
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;
        $ids = $personal->carreraIdsEnAlcance();

        $programas = Programa::with(['convenio.empresa', 'carrera'])
            ->conCuposOcupados()
            ->when($ids !== null, fn ($q) => $q->whereIn('carrera_id', $ids))
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

        return view('coordinacion.lista-programas', [
            'programas' => $programas,
            'carreras' => Carrera::enAlcance($personal)->orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'estados' => ProgramaEstado::cases(),
            'modalidades' => Modalidad::cases(),
            'ciclos' => Programa::select('ciclo')->distinct()
                ->when($ids !== null, fn ($q) => $q->whereIn('carrera_id', $ids))
                ->orderByDesc('ciclo')->pluck('ciclo'),
            'convenios' => Convenio::vigentes()->visiblesPara($personal)->with(['empresa', 'dictamenes.carrera'])->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        abort_unless($request->user()->personal->puedeVerCarrera((int) $datos['carrera_id']), 403);

        Programa::create($datos);

        return back()->with('status', 'Proyecto interno creado como borrador.');
    }

    public function update(Request $request, Programa $programa): RedirectResponse
    {
        $this->authorize('gestionar', $programa);

        if (!$programa->esInterno()) {
            return back()->withErrors(['programa' => 'Las vacantes externas las edita la empresa; tú las apruebas o rechazas.']);
        }

        $datos = $this->validar($request, $programa);

        // No se puede "mover" un programa a una carrera fuera de su alcance
        abort_unless($request->user()->personal->puedeVerCarrera((int) $datos['carrera_id']), 403);

        $programa->update($datos);

        return back()->with('status', 'Programa actualizado.');
    }

    /**
     * Publicar, cerrar o regresar a borrador los programas propios de la coordinación.
     * Las vacantes que envía una empresa se resuelven con aprobar()/rechazar().
     */
    public function cambiarEstado(Request $request, Programa $programa): RedirectResponse
    {
        $this->authorize('gestionar', $programa);

        $datos = $request->validate(['estado' => ['required', Rule::in([
            ProgramaEstado::Borrador->value, ProgramaEstado::Publicado->value, ProgramaEstado::Cerrado->value,
        ])]]);

        if (in_array($programa->estado, [ProgramaEstado::PendienteAprobacion, ProgramaEstado::Rechazado], true)) {
            return back()->withErrors(['programa' => 'Es una vacante enviada por la empresa: apruébala o recházala.']);
        }

        $nuevo = ProgramaEstado::from($datos['estado']);
        $publica = $nuevo === ProgramaEstado::Publicado && $programa->estado !== ProgramaEstado::Publicado;

        $programa->update(['estado' => $nuevo]);

        if ($publica) {
            $this->avisarAlumnos($programa);
        }

        return back()->with('status', 'Estado actualizado.');
    }

    /** La Coordinación verifica horario y actividades y publica la vacante de la empresa. */
    public function aprobar(Request $request, Programa $programa): RedirectResponse
    {
        $this->authorize('gestionar', $programa);

        if ($programa->estado !== ProgramaEstado::PendienteAprobacion) {
            return back()->withErrors(['programa' => 'Solo se aprueban vacantes pendientes de aprobación.']);
        }

        // ProgramaObserver exige convenio vigente y dictamen aprobatorio de la carrera
        $programa->update([
            'estado' => ProgramaEstado::Publicado,
            'motivo_rechazo' => null,
            'revisado_por' => $request->user()->personal->id,
            'revisado_at' => now(),
        ]);

        Avisos::enviar($programa->convenio?->empresa?->user, new AvisoOperativo(
            mensaje: "La Coordinación aprobó tu vacante «{$programa->titulo}»: ya es visible para los alumnos.",
            url: route('empresa.lista-programas', absolute: false),
            detalle: $programa->titulo,
            correo: true,
            asunto: 'Tu vacante fue aprobada',
        ));

        $this->avisarAlumnos($programa);

        return back()->with('status', 'Vacante aprobada y publicada.');
    }

    public function rechazar(Request $request, Programa $programa): RedirectResponse
    {
        $this->authorize('gestionar', $programa);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:1000']], [
            'motivo.required' => 'Explica a la empresa por qué se rechaza la vacante.',
        ]);

        if ($programa->estado !== ProgramaEstado::PendienteAprobacion) {
            return back()->withErrors(['programa' => 'Solo se rechazan vacantes pendientes de aprobación.']);
        }

        $programa->update([
            'estado' => ProgramaEstado::Rechazado,
            'motivo_rechazo' => $datos['motivo'],
            'revisado_por' => $request->user()->personal->id,
            'revisado_at' => now(),
        ]);

        Avisos::enviar($programa->convenio?->empresa?->user, new AvisoOperativo(
            mensaje: "La Coordinación rechazó tu vacante «{$programa->titulo}»: {$datos['motivo']}. Corrígela y envíala de nuevo.",
            url: route('empresa.lista-programas', absolute: false),
            detalle: $programa->titulo,
            correo: true,
            asunto: 'Tu vacante requiere cambios',
        ));

        return back()->with('status', 'Vacante rechazada; se avisó a la empresa.');
    }

    /** Avisa en plataforma a los alumnos de la carrera que hay una vacante nueva. */
    private function avisarAlumnos(Programa $programa): void
    {
        Avisos::enviar(Avisos::alumnosDeCarrera((int) $programa->carrera_id), new AvisoOperativo(
            mensaje: "Nueva vacante disponible para tu carrera: «{$programa->titulo}»" . ($programa->convenio?->empresa ? " en {$programa->convenio->empresa->razon_social}" : '') . '.',
            url: route('alumno.oferta-programas', ['programa' => $programa->id], false),
            detalle: $programa->titulo,
        ));
    }

    public function destroy(Programa $programa): RedirectResponse
    {
        $this->authorize('gestionar', $programa);

        if (!$programa->esInterno()) {
            return back()->withErrors(['programa' => 'Las vacantes externas las elimina la empresa; tú puedes rechazarlas o cerrarlas.']);
        }

        if ($programa->postulaciones()->exists()) {
            return back()->withErrors(['programa' => 'No se puede eliminar: ya tiene postulaciones. Ciérralo en su lugar.']);
        }

        $programa->delete();

        return back()->with('status', 'Programa eliminado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Programa $programa = null): array
    {
        // La coordinación solo registra proyectos internos: las vacantes externas las ofrece la empresa
        $request->merge(['modalidad' => Modalidad::Interna->value]);

        $datos = $request->validate([
            'modalidad' => ['required', Rule::in([Modalidad::Interna->value])],
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

        $datos['convenio_id'] = null;

        // El textarea trae un punto por línea; se guarda como lista JSON
        $datos['actividades'] = collect(preg_split('/\R/', $datos['actividades']))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        return $datos;
    }
}

