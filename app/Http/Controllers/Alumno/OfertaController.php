<?php

namespace App\Http\Controllers\Alumno;

use App\Enums\Modalidad;
use App\Enums\PostulacionEstado;
use App\Http\Controllers\Controller;
use App\Models\Postulacion;
use App\Models\Programa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Services\Avisos;
use App\Notifications\AvisoOperativo;

class OfertaController extends Controller
{
    public function index(Request $request): View
    {
        $alumno = $request->user()->alumno->load('carrera.division');
        $estados = PostulacionEstado::valores(PostulacionEstado::ocupanCupo());
        $marcadores = implode(',', array_fill(0, count($estados), '?'));
        $chip = $request->string('chip')->toString();

        // Solo programas de su carrera (es la regla de EmitirCartaPresentacion)
        $programas = Programa::abiertos()
            ->conCuposOcupados()
            ->with(['convenio.empresa', 'carrera'])
            ->where('carrera_id', $alumno->carrera_id)
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%' . $request->string('q') . '%';
                $q->where(fn($w) => $w
                    ->where('titulo', 'like', $t)
                    ->orWhere('descripcion', 'like', $t)
                    ->orWhere('sede', 'like', $t)
                    ->orWhereHas('convenio.empresa', fn($e) => $e->where('razon_social', 'like', $t)));
            })
            ->when($request->filled('modalidad'), fn($q) => $q->where('modalidad', $request->string('modalidad')))
            ->when($request->filled('ciclo'), fn($q) => $q->where('ciclo', $request->string('ciclo')))
            ->when($chip === 'nuevas', fn($q) => $q->where('programas.created_at', '>=', now()->subDays(7)))
            ->when($chip === 'cierre', fn($q) => $q->whereRaw(
                "programas.cupos_totales - (select count(*) from postulaciones where postulaciones.programa_id = programas.id and postulaciones.estado in ($marcadores)) <= 2",
                $estados
            ))
            ->latest('programas.id')
            ->paginate(10)
            ->withQueryString();

        $seleccionado = $programas->firstWhere('id', $request->integer('programa')) ?? $programas->first();

        $misPostulaciones = $alumno->postulaciones()->pluck('estado', 'programa_id');

        // Por qué no puede postularse todavía (null = sí puede): práctica, trámite, grado/turno o IMSS
        $bloqueo = \App\Support\Requisitos::alumnoPostularse($alumno);

        return view('alumno.oferta-programas', [
            'alumno' => $alumno,
            'programas' => $programas,
            'seleccionado' => $seleccionado,
            'misPostulaciones' => $misPostulaciones,
            'bloqueo' => $bloqueo,
            'modalidades' => Modalidad::cases(),
            'ciclos' => Programa::abiertos()->where('carrera_id', $alumno->carrera_id)->distinct()->orderByDesc('ciclo')->pluck('ciclo'),
            'chip' => $chip,
        ]);
    }

    public function postular(Request $request, Programa $programa): RedirectResponse
    {
        $alumno = $request->user()->alumno->load('carrera');

        if (!Programa::abiertos()->whereKey($programa->id)->exists()) {
            throw ValidationException::withMessages(['postulacion' => 'El programa ya no está disponible.']);
        }

        if ((int) $programa->carrera_id !== (int) $alumno->carrera_id) {
            throw ValidationException::withMessages(['postulacion' => 'Este programa no está dirigido a tu carrera.']);
        }

        if ($alumno->postulaciones()->where('programa_id', $programa->id)->exists()) {
            throw ValidationException::withMessages(['postulacion' => 'Ya te postulaste a este programa.']);
        }

        if ($motivo = \App\Support\Requisitos::alumnoPostularse($alumno)) {
            throw ValidationException::withMessages(['postulacion' => $motivo]);
        }

        if ($alumno->yaAcreditado()) {
            throw ValidationException::withMessages(['postulacion' => 'Ya acreditaste tus prácticas profesionales.']);
        }

        if ($alumno->tienePracticaCancelada()) {
            throw ValidationException::withMessages([
                'postulacion' => 'Tu práctica fue cancelada, por lo que no puedes postularte a otro programa. Consulta a tu coordinación de carrera.',
            ]);
        }

        if (
            $alumno->tienePracticaActiva()
            || $alumno->postulaciones()->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::enTramite()))->exists()
        ) {
            throw ValidationException::withMessages(['postulacion' => 'Ya tienes una práctica o una empresa en trámite.']);
        }

        $minimo = (float) $alumno->carrera->porcentaje_creditos_minimo;

        $datos = $request->validate([
            'porcentaje_creditos_declarado' => ['required', 'numeric', 'min:' . $minimo, 'max:100'],
            'kardex' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'porcentaje_creditos_declarado.min' => "Tu carrera exige al menos {$minimo}% de créditos para postularte.",
        ]);

        $postulacion = Postulacion::create([
            'alumno_id' => $alumno->id,
            'programa_id' => $programa->id,
            'porcentaje_creditos_declarado' => $datos['porcentaje_creditos_declarado'],
            'fecha_postulacion' => today(),
        ]);

        $postulacion->addMediaFromRequest('kardex')->toMediaCollection('kardex');

        Avisos::enviar(Avisos::coordinadores((int) $alumno->carrera_id), new AvisoOperativo(
            mensaje: "{$request->user()->name} ({$alumno->carrera->carrera_clave}) se postuló a «{$programa->titulo}». Valida créditos e IMSS.",
            url: route('coordinacion.lista-practicantes', absolute: false),
            detalle: $programa->titulo,
        ));

        return redirect()
            ->route('alumno.oferta-programas', ['programa' => $programa->id])
            ->with('status', 'Postulación enviada. Tu coordinación la validará y te emitirá tu carta de presentación.');
    }
}

