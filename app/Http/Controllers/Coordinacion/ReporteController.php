<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinacion;

use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ReporteTipo;
use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Practica;
use App\Models\Reporte;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reportes de horas por validar: la coordinación valida o rechaza el reporte firmado; solo los validados suman horas.
 */
class ReporteController extends Controller
{
    /**
     * Reportes por práctica (horas y estado de validación),
     * avance de cada practicante y prácticas próximas a cerrar.
     */
    public function index(Request $request): View
    {
        $personal = $request->user()->personal;

        $reportes = Reporte::with(['practica.postulacion.alumno.user', 'practica.postulacion.alumno.carrera', 'practica.postulacion.programa.convenio.empresa', 'media'])
            ->whereHas('practica', fn ($p) => $p->visiblesPara($personal))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')))
            // Origen de la vacante: proyecto interno de la carrera (sin convenio) u organización externa
            ->when($request->input('origen') === 'interno', fn ($q) => $q->whereHas('practica.postulacion.programa', fn ($p) => $p->whereNull('convenio_id')))
            ->when($request->input('origen') === 'externo', fn ($q) => $q->whereHas('practica.postulacion.programa', fn ($p) => $p->whereNotNull('convenio_id')))
            ->when($request->filled('carrera_id'), fn ($q) => $q->whereHas('practica.postulacion.alumno', fn ($a) => $a->where('carrera_id', $request->integer('carrera_id'))))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('practica.postulacion.alumno', fn ($a) => $a
                ->where('num_control', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->string('q').'%'))))
            ->orderByRaw("case estado when 'pendiente' then 0 else 1 end")
            ->latest('periodo_fin')
            ->paginate(15, ['*'], 'reportes')
            ->withQueryString();

        $avance = Practica::with(['postulacion.alumno.user', 'postulacion.alumno.carrera'])
            ->conHorasAcumuladas()
            ->visiblesPara($personal)
            ->activas()
            ->orderBy('fecha_fin')
            ->paginate(10, ['*'], 'avance')
            ->withQueryString();

        // Próximas a cerrar: terminan en 30 días o ya cubren las horas requeridas
        $porCerrar = Practica::with(['postulacion.alumno.user', 'postulacion.alumno.carrera'])
            ->conHorasAcumuladas()
            ->visiblesPara($personal)
            ->activas()
            ->get()
            ->filter(fn ($p) => $p->fecha_fin->lessThanOrEqualTo(now()->addDays(30)) || $p->horasCompletas())
            ->sortBy('fecha_fin')
            ->values();

        return view('coordinacion.lista-reportes', [
            'reportes' => $reportes,
            'avance' => $avance,
            'porCerrar' => $porCerrar,
            'carreras' => Carrera::enAlcance($personal)->orderBy('nombre')->get(['id', 'carrera_clave', 'nombre']),
            'estados' => ReporteEstado::cases(),
            'tipos' => ReporteTipo::cases(),
            'porOrigen' => [
                'interno' => Reporte::whereHas('practica', fn ($p) => $p->visiblesPara($personal))->whereHas('practica.postulacion.programa', fn ($p) => $p->whereNull('convenio_id'))->count(),
                'externo' => Reporte::whereHas('practica', fn ($p) => $p->visiblesPara($personal))->whereHas('practica.postulacion.programa', fn ($p) => $p->whereNotNull('convenio_id'))->count(),
            ],
            'pendientes' => Reporte::where('estado', ReporteEstado::Pendiente->value)
                ->whereHas('practica', fn ($p) => $p->visiblesPara($personal))
                ->count(),
        ]);
    }

    /** Valida o rechaza un reporte de horas; las horas validadas son las que cuentan. */
    public function resolver(Request $request, Reporte $reporte): RedirectResponse
    {
        $this->authorize('resolver', $reporte);

        $datos = $request->validate([
            'estado' => ['required', Rule::in([ReporteEstado::Validado->value, ReporteEstado::Rechazado->value])],
            // Al rechazar, el alumno necesita saber qué corregir
            'evaluacion_profesor' => ['nullable', 'required_if:estado,'.ReporteEstado::Rechazado->value, 'string', 'max:2000'],
            'retroalimentacion_empresa' => ['nullable', 'string', 'max:2000'],
        ], [
            'evaluacion_profesor.required_if' => 'Escribe el motivo del rechazo para que el alumno sepa qué corregir.',
        ]);

        if ($reporte->practica->estado !== PracticaEstado::EnCurso) {
            return back()->withErrors(['reporte' => 'Los reportes solo se revisan mientras la práctica está en curso.']);
        }

        // Sin el escaneo firmado y sellado por la organización no hay nada que auditar: ni se valida ni se rechaza
        if ($reporte->estado === ReporteEstado::Pendiente && ! $reporte->media()->where('collection_name', 'reporte_firmado')->exists()) {
            return back()->withErrors(['reporte' => 'El alumno aún no sube el reporte firmado y sellado: se podrá validar o rechazar cuando lo suba.']);
        }

        // Lo validado ya no se rechaza: sus horas ya cuentan para el alumno
        if ($reporte->estado === ReporteEstado::Validado && $datos['estado'] === ReporteEstado::Rechazado->value) {
            return back()->withErrors(['reporte' => 'Este reporte ya está validado y sus horas ya cuentan: no se puede rechazar.']);
        }

        $reporte->update($datos);

        $ok = $datos['estado'] === ReporteEstado::Validado->value;
        $periodo = $reporte->periodo_inicio->format('d/m').' al '.$reporte->periodo_fin->format('d/m/Y');

        Avisos::enviar(Avisos::alumnoDe($reporte->practica), new AvisoOperativo(
            mensaje: $ok
            ? "Tu reporte del {$periodo} fue validado: se sumaron {$reporte->horas_reportadas} horas."
            : "Tu coordinación te devolvió el reporte del {$periodo}. Motivo: «{$datos['evaluacion_profesor']}». Qué hacer: entra a «Mis documentos › Reportes de horas», busca el reporte marcado en rojo, corrígelo y súbelo firmado de nuevo.",
            url: route($ok ? 'alumno.mis-practicas' : 'alumno.mis-documentos', absolute: false),
            correo: ! $ok,
            asunto: 'Tu reporte fue rechazado',
        ));

        // Con este reporte se completaron las horas: la carta de término la emite la organización, no la coordinación
        $practica = $reporte->practica->fresh();
        if ($ok && $practica->horasCompletas()) {
            $this->avisarHorasCompletas($practica);
        }

        return back()->with('status', 'Reporte '.($datos['estado'] === ReporteEstado::Validado->value ? 'validado' : 'rechazado').'.');
    }

    /**
     * El alumno cubrió sus horas: él pide la carta de término a su jefe directo y la organización la emite
     * en su hoja membretada, firmada y sellada. El alumno la sube escaneada y la coordinación la valida.
     */
    private function avisarHorasCompletas(Practica $practica): void
    {
        $alumno = Avisos::alumnoDe($practica);
        $titulo = $practica->postulacion->programa->titulo;

        Avisos::enviar($alumno, new AvisoOperativo(
            mensaje: "¡Completaste tus {$practica->horas_requeridas} horas en «{$titulo}»! Qué hacer: pide a tu jefe directo en la organización tu carta de término en hoja membretada, firmada y sellada; escanéala y súbela en «Mis documentos › Carta de término».",
            url: route('alumno.mis-documentos', absolute: false),
            correo: true,
            asunto: 'Completaste tus horas: solicita tu carta de término',
        ));

        // Proyecto interno: no hay organización externa a quien avisar
        Avisos::enviar(Avisos::empresaDe($practica), new AvisoOperativo(
            mensaje: "{$alumno?->name} completó sus {$practica->horas_requeridas} horas de práctica en «{$titulo}». Qué hacer: emite su carta de término en la hoja membretada de tu organización, fírmala, séllala y entrégasela para que la suba al sistema.",
            url: route('empresa.practicantes.show', $practica, false),
            correo: true,
            asunto: 'Tu practicante completó sus horas: emite su carta de término',
        ));
    }

    /** Descarga del reporte firmado (disco privado). */
    public function descargar(Request $request, Reporte $reporte, Media $media): Response
    {
        return $this->entregarDe($reporte, $media);
    }
}
