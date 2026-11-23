<?php

namespace App\Http\Controllers\Coordinacion;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ValidacionEstado;
use App\Actions\ActivarPractica;
use App\Actions\EmitirCartaPresentacion;
use App\Actions\RegistrarCartaAceptacion;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Services\Avisos;
use App\Notifications\AvisoOperativo;

class PracticanteController extends Controller
{
    /** Prácticas (con horas acumuladas) y postulaciones en trámite. */
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;

        $practicas = Practica::with(['postulacion.alumno.user', 'postulacion.alumno.carrera', 'postulacion.programa.convenio.empresa', 'supervisor'])
            ->conHorasAcumuladas()
            ->visiblesPara($personal)
            ->when($request->filled('q'), fn($q) => $q->whereHas('postulacion.alumno', fn($a) => $a
                ->where('num_control', 'like', '%' . $request->string('q') . '%')
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', '%' . $request->string('q') . '%'))))
            ->when($request->filled('carrera_id'), fn($q) => $q->whereHas('postulacion.alumno', fn($a) => $a->where('carrera_id', $request->integer('carrera_id'))))
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->string('estado')))
            ->latest('id')
            ->paginate(15, ['*'], 'practicas')
            ->withQueryString();

        $postulaciones = Postulacion::with(['alumno.user', 'alumno.carrera', 'alumno.media', 'programa.convenio.empresa', 'media'])
            ->visiblesPara($personal)
            ->whereIn('estado', [
                PostulacionEstado::Pendiente->value,
                PostulacionEstado::CartaPresentacionEmitida->value,
                PostulacionEstado::CartaAceptacionRecibida->value,
            ])
            ->oldest('fecha_postulacion')
            ->paginate(10, ['*'], 'postulaciones')
            ->withQueryString();

        return view('coordinacion.lista-practicantes', [
            // Kardex e IMSS se revisan juntos ANTES de que el alumno pueda postularse
            'expedientesPorRevisar' => \App\Models\Alumno::conExpedientePorRevisar($personal),
            // Alumnos con bloqueo por sanción o por límite de bajas: solo un desbloqueo excepcional los libera
            'bloqueados' => \App\Models\Alumno::bloqueadosPara($personal),
            'practicas' => $practicas,
            'postulaciones' => $postulaciones,
            'carreras' => Carrera::enAlcance($personal)->orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'estados' => PracticaEstado::cases(),
            'profesores' => Profesor::with('division')->activos()
                ->whereIn('division_id', Carrera::enAlcance($personal)->select('division_id'))
                ->orderBy('nombre')->get(),
        ]);
    }

    public function emitirCarta(Request $request, Postulacion $postulacion, EmitirCartaPresentacion $emitir): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);

        $emitir($postulacion, $request->user()->personal);

        return back()->with('status', 'Carta de presentación emitida; cupo reservado.');
    }

    /**
     * Revisión del expediente del alumno: kardex e IMSS se entregan juntos y se revisan en el mismo momento.
     *  - autorizar: valida ambos; el alumno ya puede postularse a una vacante.
     *  - devolver: rechaza el o los documentos con su motivo; el alumno reemplaza solo el que falló.
     */
    public function revisarExpediente(Request $request, \App\Models\Alumno $alumno): RedirectResponse
    {
        abort_unless($request->user()->personal->puedeVerCarrera((int) $alumno->carrera_id), 403);

        $datos = $request->validate([
            'decision' => ['required', Rule::in(['autorizar', 'devolver'])],
            'documentos' => ['exclude_unless:decision,devolver', 'required', 'array', 'min:1'],
            'documentos.*' => [Rule::in(['kardex', 'imss'])],
            'motivo' => ['exclude_unless:decision,devolver', 'required', 'string', 'max:500'],
        ], [
            'documentos.required' => 'Marca qué documento debe corregir el alumno.',
            'motivo.required' => 'Explica al alumno qué está mal para que sepa qué subir (p. ej. «el kardex es del ciclo anterior»).',
        ]);

        $docs = [
            'kardex' => [$alumno->documentoVigente('kardex'), 'el kardex'],
            'imss' => [$alumno->documentoVigente('constancia_imss'), 'la constancia IMSS'],
        ];

        if ($datos['decision'] === 'devolver') {
            $nombres = [];
            foreach ($datos['documentos'] as $clave) {
                [$media, $nombre] = $docs[$clave];
                if ($media && $media->getCustomProperty('estado') !== ValidacionEstado::Validada->value) {
                    $alumno->resolverDocumento($media, ValidacionEstado::Rechazada, $datos['motivo'], $request->user()->id);
                    $nombres[] = $nombre;
                }
            }

            if ($nombres === []) {
                return back()->withErrors(['documentos' => 'Esos documentos ya están validados: no se pueden devolver.']);
            }

            Avisos::enviar($alumno->user, new AvisoOperativo(
                mensaje: 'Tu coordinación te devolvió ' . implode(' y ', $nombres)
                    . ". Motivo: «{$datos['motivo']}». Qué hacer: entra a «Mis documentos › Tu expediente», busca la tarjeta marcada en rojo y sube el PDF corregido; en cuanto lo valide podrás postularte.",
                url: route('alumno.mis-documentos', absolute: false),
                correo: true,
                asunto: 'Corrige un documento de tu expediente de prácticas',
            ));

            return back()->with('status', 'Expediente devuelto al alumno para que reemplace ' . implode(' y ', $nombres) . '.');
        }

        // Autorizar: los dos documentos deben existir y ninguno estar devuelto
        foreach ($docs as [$media, $nombre]) {
            if ($media === null) {
                return back()->withErrors(['revision' => 'Falta ' . $nombre . ' del alumno: no se puede autorizar.']);
            }
            if ($media->getCustomProperty('estado') === ValidacionEstado::Rechazada->value) {
                return back()->withErrors(['revision' => "Devolviste {$nombre} al alumno: espera a que suba el archivo corregido."]);
            }
        }
        if (!$alumno->tieneImssVigenteHasta(now())) {
            return back()->withErrors(['revision' => 'La vigencia del IMSS que capturó el alumno ya pasó: devuélvelo para que suba una constancia actual.']);
        }
        if ((float) $alumno->porcentaje_creditos < (float) $alumno->carrera->porcentaje_creditos_minimo) {
            return back()->withErrors(['revision' => 'Los créditos del alumno están por debajo del mínimo de su carrera: no se puede autorizar.']);
        }

        foreach ($docs as [$media]) {
            if ($media->getCustomProperty('estado') !== ValidacionEstado::Validada->value) {
                $alumno->resolverDocumento($media, ValidacionEstado::Validada, null, $request->user()->id);
            }
        }

        Avisos::enviar($alumno->user, new AvisoOperativo(
            mensaje: 'Tu coordinación validó tu kardex y tu constancia IMSS. ¡Ya puedes postularte a una vacante de tu carrera!',
            url: route('alumno.oferta-programas', absolute: false),
            correo: true,
            asunto: 'Ya puedes postularte a prácticas profesionales',
        ));

        return back()->with('status', "Expediente de {$alumno->user->name} validado: ya puede postularse.");
    }

    public function rechazarPostulacion(Request $request, Postulacion $postulacion): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);

        if (!$postulacion->estado->puedePasarA(PostulacionEstado::Rechazada)) {
            return back()->withErrors(['postulacion' => "Una postulación «{$postulacion->estado->label()}» ya no se puede rechazar."]);
        }

        $postulacion->update(['estado' => PostulacionEstado::Rechazada]);

        Avisos::enviar($postulacion->alumno->user, new AvisoOperativo(
            mensaje: "Tu postulación a «{$postulacion->programa->titulo}» fue rechazada. Puedes postularte a otro programa de tu carrera.",
            url: route('alumno.oferta-programas', absolute: false),
            correo: true,
            asunto: 'Actualización de tu postulación',
        ));

        return back()->with('status', 'Postulación rechazada.');
    }

    public function subirCartaAceptacion(Request $request, Postulacion $postulacion, RegistrarCartaAceptacion $registrar): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);

        $ya = $postulacion->media()->where('collection_name', 'carta_aceptacion')->exists();

        $request->validate(['archivo' => [$ya ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:10240']]);

        if ($request->hasFile('archivo')) {
            $postulacion->addMediaFromRequest('archivo')->toMediaCollection('carta_aceptacion');
        }

        $registrar($postulacion);

        return back()->with('status', 'Carta de aceptación registrada; la práctica puede activarse.');
    }

    /**
     * Modalidad interna: adjunta el Oficio de Aceptación Interno firmado por el Jefe de Departamento
     * o Director de División del área receptora. Es requisito para activar la práctica.
     */
    public function subirOficioInterno(Request $request, Postulacion $postulacion): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);

        if (!$postulacion->programa->esInterno()) {
            return back()->withErrors(['postulacion' => 'El oficio de aceptación interno solo aplica a proyectos internos.']);
        }

        if (!in_array($postulacion->estado, [PostulacionEstado::CartaPresentacionEmitida, PostulacionEstado::CartaAceptacionRecibida], true)) {
            return back()->withErrors(['postulacion' => 'Primero emite la carta de presentación.']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']], [
            'archivo.required' => 'Adjunta el oficio firmado en PDF.',
        ]);

        $media = $postulacion->addMediaFromRequest('archivo')->toMediaCollection('oficio_aceptacion_interno');
        // Lo sube la propia coordinación con la firma del área receptora: queda validado
        $postulacion->resolverDocumento($media, ValidacionEstado::Validada, null, $request->user()->id);

        return back()->with('status', 'Oficio de aceptación interno registrado.');
    }

    public function activar(Request $request, Postulacion $postulacion, ActivarPractica $activar): RedirectResponse
    {
        $this->authorize('gestionar', $postulacion);
        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'profesor_id' => ['nullable', 'integer', Rule::exists('profesores', 'id')],
        ]);

        $supervisor = isset($datos['profesor_id']) ? Profesor::find($datos['profesor_id']) : null;
        if ($supervisor && !$supervisor->activo) {
            return back()->withErrors(['profesor_id' => 'Ese profesor está desactivado en el catálogo: elige otro o actívalo en «Profesores supervisores».']);
        }

        $activar(
            $postulacion,
            $request->user()->personal,
            Carbon::parse($datos['fecha_inicio']),
            Carbon::parse($datos['fecha_fin']),
            $supervisor
        );

        return back()->with('status', 'Práctica activada.');
    }

    /** Asigna o cambia el supervisor (debe ser de la división de la carrera del alumno). */
    public function asignarSupervisor(Request $request, Practica $practica): RedirectResponse
    {
        $this->authorize('gestionar', $practica);

        if (!in_array($practica->estado, PracticaEstado::activas(), true)) {
            return back()->withErrors(['practica' => 'Solo se asigna supervisor a prácticas activas.']);
        }
        $datos = $request->validate(['profesor_id' => ['required', 'integer', Rule::exists('profesores', 'id')]]);

        $profesor = Profesor::findOrFail($datos['profesor_id']);
        $carrera = $practica->postulacion->alumno->carrera;

        if (!$profesor->activo) {
            return back()->withErrors(['practica' => 'Ese profesor está desactivado en el catálogo: elige otro o actívalo en «Profesores supervisores».']);
        }

        abort_unless($request->user()->personal->puedeVerCarrera((int) $carrera->id), 403);

        if (!$profesor->puedeSupervisar($carrera)) {
            return back()->withErrors(['practica' => 'El profesor no pertenece a la división de la carrera del alumno.']);
        }

        $practica->update(['profesor_supervisor_id' => $profesor->id]);

        Avisos::enviar(Avisos::alumnoDe($practica), new AvisoOperativo(
            mensaje: "Tu profesor supervisor es {$profesor->nombre}.",
            url: route('alumno.mis-practicas', absolute: false),
        ));

        return back()->with('status', 'Supervisor asignado.');
    }

    public function cancelar(Request $request, Practica $practica): RedirectResponse
    {
        $this->authorize('gestionar', $practica);
        abort_unless(
            $request->user()->personal->puedeVerCarrera((int) $practica->postulacion->alumno->carrera_id),
            403
        );

        if (!in_array($practica->estado, PracticaEstado::activas(), true)) {
            return back()->withErrors(['practica' => 'Solo se cancelan prácticas activas.']);
        }

        $datos = $request->validate([
            'motivo_baja' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Practica::MOTIVOS_BAJA))],
            'detalle_baja' => ['required', 'string', 'max:1000'],
        ], [
            'motivo_baja.required' => 'Indica el motivo de la baja.',
            'detalle_baja.required' => 'Describe brevemente lo que pasó.',
        ]);

        // La resolución sale del motivo y de los reportes validados (criterios de la Coordinación de Carrera)
        $horas = $practica->horas_acumuladas;
        $tipo = $practica->resolverBaja($datos['motivo_baja']);
        $practica->update($datos + [
            'estado' => PracticaEstado::Cancelada,
            'tipo_baja' => $tipo,
            'conserva_horas' => $tipo === 'parcial_acreditada',
            'fecha_baja' => now(),
        ]);

        $alumno = $practica->postulacion->alumno->fresh();
        $titulo = $practica->postulacion->programa->titulo;
        $decision = match ($tipo) {
            'parcial_acreditada' => "Conservas tus {$horas} horas validadas: en tu siguiente plaza solo cubrirás las horas restantes.",
            'sancion' => 'Por ser una baja por sanción, tus horas no se conservan.',
            default => 'Como no tenías reportes validados, tus horas no se conservan y empezarás un trámite nuevo.',
        };
        $siguiente = $alumno->estaBloqueado()
            ? ' ' . $alumno->textoBloqueo()
            : ' Ya puedes postularte a otra vacante (te queda ' . (Alumno::LIMITE_BAJAS - $alumno->bajasVigentes()->count()) . ' oportunidad antes del límite de bajas).';

        Avisos::enviar(Avisos::alumnoDe($practica), new AvisoOperativo(
            mensaje: "Tu práctica en «{$titulo}» se dio de baja ({$practica->motivoBajaTexto()}). {$decision}{$siguiente}",
            url: route('alumno.mis-documentos', absolute: false),
            correo: true,
            asunto: 'Tu práctica se dio de baja',
        ));

        Avisos::enviar(Avisos::empresaDe($practica), new AvisoOperativo(
            mensaje: "La práctica de {$alumno->user->name} en «{$titulo}» se dio de baja por la Coordinación ({$practica->motivoBajaTexto()}).",
            url: route('empresa.practicantes.show', $practica, false),
        ));

        $resumen = match ($tipo) {
            'parcial_acreditada' => "baja parcial acreditada: el alumno conserva {$horas} horas.",
            'sancion' => 'baja por sanción: las horas se invalidan.',
            default => 'baja sin reportes validados: las horas no se conservan.',
        };

        return back()->with('status', 'Baja registrada como ' . $resumen
            . ($alumno->estaBloqueado() ? ' El alumno quedó bloqueado para postularse.' : ''));
    }

    /** Desbloqueo excepcional: la coordinación levanta la restricción con una justificación. */
    public function desbloquear(Request $request, Alumno $alumno): RedirectResponse
    {
        abort_unless($request->user()->personal?->puedeVerCarrera((int) $alumno->carrera_id), 403);

        $datos = $request->validate([
            'justificacion' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'justificacion.required' => 'Escribe la justificación del desbloqueo.',
            'justificacion.min' => 'Explica con más detalle la causa de fuerza mayor.',
        ]);

        if (!$alumno->estaBloqueado()) {
            return back()->withErrors(['desbloqueo' => 'Este alumno no está bloqueado.']);
        }

        $alumno->update([
            'desbloqueado_at' => now(),
            'desbloqueo_justificacion' => $datos['justificacion'],
            'desbloqueado_por' => $request->user()->id,
        ]);

        Avisos::enviar($alumno->user, new AvisoOperativo(
            mensaje: 'Tu coordinación levantó la restricción de tu perfil (desbloqueo excepcional). Ya puedes postularte a una vacante de tu carrera.',
            url: route('alumno.oferta-programas', absolute: false),
            correo: true,
            asunto: 'Ya puedes postularte de nuevo',
        ));

        return back()->with('status', "Desbloqueaste a {$alumno->user->name}: ya puede postularse de nuevo.");
    }

    /** Descarga de archivos de una postulación (kardex, cartas). */
    public function descargar(Request $request, Postulacion $postulacion, Media $media)
    {
        abort_unless($media->model_type === Postulacion::class && (int) $media->model_id === (int) $postulacion->id, 404);
        $this->authorize('download', $media);

        return $this->entregar($media);
    }
}