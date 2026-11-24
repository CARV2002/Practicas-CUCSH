<?php

declare(strict_types=1);

namespace App\Http\Controllers\Empresa;

use App\Enums\Dictamen;
use App\Enums\Modalidad;
use App\Enums\PostulacionEstado;
use App\Enums\ProgramaEstado;
use App\Http\Controllers\Controller;
use App\Models\Programa;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use App\Support\Requisitos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Vacantes de la empresa. La empresa las registra y edita, pero quien las publica es la
 * Coordinación de la carrera: nacen (y vuelven tras cada cambio) en «pendiente_aprobacion».
 */
class ProgramaController extends Controller
{
    /** Programas de la empresa, con cupos y su convenio vigente. */
    public function index(Request $request): View
    {
        $empresa = $request->user()->empresa;

        $programas = Programa::with(['carrera', 'convenio'])
            ->conCuposOcupados()
            ->whereHas('convenio', fn ($c) => $c->where('empresa_id', $empresa->id))
            ->when($request->filled('q'), fn ($q) => $q->where('titulo', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Convenios vigentes con las carreras aprobadas en cada uno (las únicas para las que se puede ofertar)
        $convenios = $empresa->convenios()
            ->vigentes()
            ->with(['dictamenes' => fn ($d) => $d->where('dictamen', Dictamen::Aprobatorio->value)->with('carrera')])
            ->get();

        return view('empresa.lista-programas', [
            'programas' => $programas,
            'convenios' => $convenios,
            'estados' => ProgramaEstado::cases(),
            'puedeCrear' => $convenios->isNotEmpty(),
            'motivoBloqueo' => Requisitos::empresaProponerVacantes($empresa),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($motivo = Requisitos::empresaProponerVacantes($request->user()->empresa)) {
            throw ValidationException::withMessages(['programa' => $motivo]);
        }

        $datos = $this->validar($request);

        $this->convenioPropio($request, (int) $datos['convenio_id'], (int) $datos['carrera_id']);

        // Nace pendiente: no es visible para los alumnos hasta que la Coordinación de la carrera la apruebe
        $programa = Programa::create($datos + [
            'modalidad' => Modalidad::Externa->value,
            'estado' => ProgramaEstado::PendienteAprobacion->value,
        ]);

        $this->avisarCoordinacion($request, $programa, 'registró la vacante');

        return back()->with('status', 'Vacante enviada a la Coordinación de la carrera para su aprobación.');
    }

    public function update(Request $request, Programa $programa): RedirectResponse
    {
        $this->autorizar($request, $programa);

        if ($programa->postulaciones()->exists()) {
            return back()->withErrors(['programa' => 'La vacante ya tiene postulaciones: ciérrala y registra una nueva.']);
        }

        $datos = $this->validar($request);

        $this->convenioPropio($request, (int) $datos['convenio_id'], (int) $datos['carrera_id']);

        // Cualquier cambio de la empresa vuelve a revisión (horarios y actividades)
        $programa->update($datos + [
            'estado' => ProgramaEstado::PendienteAprobacion->value,
            'motivo_rechazo' => null,
            'revisado_por' => null,
            'revisado_at' => null,
        ]);

        $this->avisarCoordinacion($request, $programa, 'modificó la vacante');

        return back()->with('status', 'Vacante actualizada y enviada de nuevo a aprobación.');
    }

    /** La empresa cierra su vacante publicada o la reabre; publicarla por primera vez es decisión de la Coordinación. */
    public function cambiarEstado(Request $request, Programa $programa): RedirectResponse
    {
        $this->autorizar($request, $programa);

        $request->validate(['estado' => ['required', Rule::in([ProgramaEstado::Cerrado->value, ProgramaEstado::Publicado->value])]], [
            'estado.in' => 'Solo puedes cerrar o reabrir tu vacante.',
        ]);

        // Reabrir: decisión de la empresa. Si la cerró la Coordinación por una irregularidad, vuelve a revisión.
        if ($request->input('estado') === ProgramaEstado::Publicado->value) {
            if ($programa->estado !== ProgramaEstado::Cerrado) {
                return back()->withErrors(['estado' => 'Solo se puede reabrir una vacante cerrada; publicarla por primera vez le corresponde a la Coordinación.']);
            }

            if ($programa->observaciones_cierre) {
                $programa->update(['estado' => ProgramaEstado::PendienteAprobacion, 'revisado_por' => null, 'revisado_at' => null]);
                $this->avisarCoordinacion($request, $programa, 'pidió reabrir la vacante que cerraste');

                return back()->with('status', 'La Coordinación había cerrado esta vacante: se envió de nuevo a su revisión antes de reabrirla.');
            }

            if (! $programa->convenio?->estaVigente()) {
                return back()->withErrors(['programa' => 'Tu convenio ya no está vigente: no puedes reabrir la vacante.']);
            }

            $programa->update(['estado' => ProgramaEstado::Publicado]);

            return back()->with('status', 'Vacante reabierta: los alumnos ya pueden postularse de nuevo.');
        }

        if ($programa->estado !== ProgramaEstado::Publicado) {
            return back()->withErrors(['programa' => 'Solo se puede cerrar una vacante publicada.']);
        }

        $programa->update(['estado' => ProgramaEstado::Cerrado]);

        return back()->with('status', 'Vacante cerrada.');
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

    /**
     * El convenio elegido debe ser de la empresa, estar vigente y tener dictamen aprobatorio de la carrera:
     * el formulario ya solo ofrece esas combinaciones, pero no se confía solo en la vista.
     */
    private function convenioPropio(Request $request, int $convenioId, int $carreraId): void
    {
        $convenio = $request->user()->empresa->convenios()->whereKey($convenioId)->first();

        if ($convenio === null) {
            throw ValidationException::withMessages(['convenio_id' => 'El convenio no pertenece a tu empresa.']);
        }

        if (! $convenio->estaVigente()) {
            throw ValidationException::withMessages(['convenio_id' => 'Ese convenio no está vigente: elige un convenio formalizado y vigente.']);
        }

        if (! $convenio->autorizaCarrera($carreraId)) {
            throw ValidationException::withMessages(['carrera_id' => 'Esa carrera no aprobó tu convenio: solo puedes ofertar vacantes a las carreras con dictamen aprobatorio.']);
        }
    }

    private function avisarCoordinacion(Request $request, Programa $programa, string $accion): void
    {
        Avisos::aCoordinacion((int) $programa->carrera_id, new AvisoOperativo(
            mensaje: "{$request->user()->empresa->razon_social} {$accion} «{$programa->titulo}». Revisa horario y actividades para aprobarla.",
            url: route('coordinacion.lista-programas', ['estado' => ProgramaEstado::PendienteAprobacion->value], false),
            detalle: $programa->titulo,
        ));
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
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        return $datos;
    }
}
