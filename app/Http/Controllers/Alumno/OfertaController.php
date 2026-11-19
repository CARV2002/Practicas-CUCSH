<?php

namespace App\Http\Controllers\Alumno;

use App\Enums\Modalidad;
use App\Enums\PostulacionEstado;
use App\Http\Controllers\Controller;
use App\Models\Convenio;
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

        // Con solicitud en revisión o empresa en trámite no se buscan otras vacantes (un alumno, una empresa)
        if ($motivo = \App\Support\Requisitos::alumnoVerOferta($alumno)) {
            return view('pages.seccion-bloqueada', [
                'titulo' => 'La oferta de programas no está disponible por ahora',
                'motivo' => $motivo,
                'enlace' => route('alumno.mis-documentos'),
                'enlaceTexto' => 'Ver el estado de mis documentos',
            ]);
        }
        $estados = PostulacionEstado::valores(PostulacionEstado::ocupanCupo());
        $marcadores = implode(',', array_fill(0, count($estados), '?'));
        $chip = $request->string('chip')->toString();

        // Solo programas de su carrera (es la regla de EmitirCartaPresentacion)
        $programas = Programa::abiertos()
            ->conCuposOcupados()
            ->with(['convenio.empresa', 'carrera'])
            ->where('carrera_id', $alumno->carrera_id)
            // Convenios a punto de vencer ya no reciben practicantes nuevos
            ->where(fn ($q) => $q->whereNull('convenio_id')->orWhereHas('convenio', fn ($c) => $c
                ->whereDate('fecha_fin_vigencia', '>=', now()->addDays(Convenio::DIAS_MINIMOS_POSTULACION)->toDateString())))
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

        if ($alumno->tieneBajaSinResolver()) {
            throw ValidationException::withMessages([
                'postulacion' => 'Tu práctica fue cancelada, por lo que no puedes postularte a otro programa. Consulta a tu coordinación de carrera.',
            ]);
        }

        // Un convenio que vence pronto pondría en riesgo las horas: no admite postulaciones nuevas
        if (!$programa->esInterno() && !$programa->convenio?->admitePostulaciones()) {
            throw ValidationException::withMessages([
                'postulacion' => 'El convenio de esta organización vence el ' . $programa->convenio?->fecha_fin_vigencia?->format('d/m/Y')
                    . ' y ya no admite postulaciones nuevas: tus horas quedarían en riesgo.',
            ]);
        }

        if (
            $alumno->tienePracticaActiva()
            || $alumno->postulaciones()->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::enTramite()))->exists()
        ) {
            throw ValidationException::withMessages(['postulacion' => 'Ya tienes una práctica o una empresa en trámite.']);
        }

        $minimo = (float) $alumno->carrera->porcentaje_creditos_minimo;

        // Kardex e IMSS se entregan juntos y la coordinación los revisa en el mismo momento.
        // Si ya tiene un IMSS validado y vigente, no se le vuelve a pedir.
        $pideImss = !$alumno->tieneImssValidadoVigente();

        $datos = $request->validate([
            'porcentaje_creditos_declarado' => ['required', 'numeric', 'min:' . $minimo, 'max:100'],
            'kardex' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'imss' => [$pideImss ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'vigencia_imss' => [$pideImss ? 'required' : 'nullable', 'date', 'after:today'],
            'siiau' => ['accepted'],
        ], [
            'porcentaje_creditos_declarado.required' => 'Escribe tu porcentaje de créditos cursados (lo ves en tu kardex).',
            'porcentaje_creditos_declarado.min' => "Tu carrera exige al menos {$minimo}% de créditos para postularte.",
            'kardex.required' => 'Adjunta tu kardex simple del ciclo actual.',
            'imss.required' => 'Adjunta tu constancia de vigencia de derechos del IMSS.',
            'vigencia_imss.required' => 'Escribe la fecha hasta la que está vigente tu seguro (aparece en la constancia).',
            'vigencia_imss.after' => 'La vigencia del IMSS debe ser posterior a hoy: si ya venció, descarga una constancia actualizada.',
            'siiau.accepted' => 'Confirma que ya inscribiste la materia de prácticas profesionales en SIIAU.',
        ]);

        $postulacion = Postulacion::create([
            'alumno_id' => $alumno->id,
            'programa_id' => $programa->id,
            'porcentaje_creditos_declarado' => $datos['porcentaje_creditos_declarado'],
            'fecha_postulacion' => today(),
            'siiau_confirmado_at' => now(),
        ]);

        $postulacion->addMediaFromRequest('kardex')->toMediaCollection('kardex');

        if ($request->hasFile('imss')) {
            $alumno->addMediaFromRequest('imss')->toMediaCollection('constancia_imss');
            $alumno->update(['vigencia_imss' => $datos['vigencia_imss']]);
        }

        Avisos::aCoordinacion((int) $alumno->carrera_id, new AvisoOperativo(
            mensaje: "{$request->user()->name} ({$alumno->carrera->carrera_clave}) se postuló a «{$programa->titulo}». Revisa juntos su kardex y su constancia IMSS para autorizarlo.",
            url: route('coordinacion.lista-practicantes', absolute: false),
            detalle: $programa->titulo,
        ));

        return redirect()
            ->route('alumno.oferta-programas', ['programa' => $programa->id])
            ->with('status', 'Postulación enviada con tu kardex y tu IMSS. Tu coordinación los revisará juntos: si están correctos te emitirá tu carta de presentación; si no, te dirá qué corregir.');
    }
}

