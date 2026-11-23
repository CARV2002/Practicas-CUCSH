<?php

namespace App\Http\Controllers\Alumno;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\CartaTermino;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Reporte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Enums\ReporteTipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Enums\ValidacionEstado;
use App\Services\Avisos;
use App\Notifications\AvisoOperativo;

class DocumentoController extends Controller
{
    public function index(Request $request): View
    {
        $alumno = $request->user()->alumno;

        return view('alumno.mis-documentos', [
            'alumno' => $alumno->load('carrera'),
            'imss' => $alumno->documentoVigente('constancia_imss'),
            'postulaciones' => $alumno->postulaciones()
                ->with(['programa.convenio.empresa', 'media'])
                ->whereNotIn('estado', [PostulacionEstado::Cancelada->value])
                ->latest('id')
                ->get(),
            'practicas' => $alumno->practicas()
                ->with(['postulacion.programa', 'reportes.media', 'cartaTermino.media'])
                ->latest('practicas.id')
                ->get(),
        ]);
    }

    /**
     * Expediente (kardex + IMSS + créditos): se entrega junto a la coordinación y debe quedar
     * validado ANTES de postularse.
     */
    public function enviarExpediente(Request $request): RedirectResponse
    {
        $alumno = $request->user()->alumno->load('carrera');

        if (in_array($alumno->estadoExpediente(), ['en_revision', 'validado'], true)) {
            throw ValidationException::withMessages(['expediente' => $alumno->estadoExpediente() === 'validado'
                ? 'Tu expediente ya está validado: ya puedes postularte.'
                : 'Tu expediente ya está en revisión con tu coordinación.']);
        }

        $minimo = (float) $alumno->carrera->porcentaje_creditos_minimo;

        $datos = $request->validate([
            'porcentaje_creditos' => ['required', 'numeric', 'min:' . $minimo, 'max:100'],
            'kardex' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            'imss' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            'vigencia_imss' => ['required', 'date', 'after:today'],
        ], [
            'porcentaje_creditos.required' => 'Escribe tu porcentaje de créditos cursados (lo ves en tu kardex).',
            'porcentaje_creditos.min' => "Tu carrera pide al menos {$minimo}% de créditos para hacer prácticas.",
            'kardex.required' => 'Adjunta tu kardex simple del ciclo actual.',
            'imss.required' => 'Adjunta tu constancia de vigencia de derechos del IMSS.',
            'vigencia_imss.required' => 'Escribe la fecha hasta la que está vigente tu seguro (aparece en la constancia).',
            'vigencia_imss.after' => 'La vigencia debe ser posterior a hoy: si ya venció, descarga una constancia actualizada del IMSS.',
        ]);

        $alumno->addMediaFromRequest('kardex')->toMediaCollection('kardex');
        $alumno->addMediaFromRequest('imss')->toMediaCollection('constancia_imss');
        $alumno->update(['vigencia_imss' => $datos['vigencia_imss'], 'porcentaje_creditos' => $datos['porcentaje_creditos']]);

        $this->avisarCoordinacion($request, 'envió su kardex y su constancia IMSS. Revísalos juntos: si están correctos, autorízalo para que pueda postularse.', 'coordinacion.lista-practicantes');

        return back()->with('status', 'Expediente enviado. Tu coordinación revisará juntos tu kardex y tu constancia IMSS; te avisaremos por correo cuando puedas postularte.');
    }

    public function subirImss(Request $request): RedirectResponse
    {
        $alumno = $request->user()->alumno;

        // Un IMSS validado y vigente ya no se reemplaza (no tendría caso volver a revisarlo)
        if ($alumno->tieneImssValidadoVigente()) {
            throw ValidationException::withMessages([
                'archivo' => 'Tu constancia IMSS ya fue validada y sigue vigente: no necesitas subirla de nuevo.',
            ]);
        }

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            'vigencia_imss' => ['required', 'date', 'after:today'],
        ], [
            'archivo.required' => 'Elige el PDF o la imagen de tu constancia de vigencia de derechos.',
            'vigencia_imss.required' => 'Escribe la fecha hasta la que está vigente tu seguro (aparece en la constancia).',
            'vigencia_imss.after' => 'La vigencia debe ser posterior a hoy: si ya venció, descarga una constancia actualizada del IMSS.',
        ]);

        $anterior = $alumno->documentoVigente('constancia_imss')?->getCustomProperty('estado');
        $alumno->addMediaFromRequest('archivo')->toMediaCollection('constancia_imss');
        $alumno->update(['vigencia_imss' => $datos['vigencia_imss']]);

        $this->avisarCoordinacion($request, match (true) {
            $anterior === ValidacionEstado::Rechazada->value => 'reemplazó la constancia IMSS que le devolviste. Revísala de nuevo.',
            $anterior === ValidacionEstado::Validada->value => 'subió su constancia IMSS renovada (la anterior venció). Revísala.',
            default => 'actualizó su constancia IMSS. Revísala junto con su kardex.',
        }, 'coordinacion.lista-practicantes');

        return back()->with('status', 'Constancia IMSS enviada: tu coordinación la revisará.');
    }

    /** Reemplaza el kardex devuelto (o el que sigue en revisión). Uno validado ya no se cambia. */
    public function subirKardex(Request $request): RedirectResponse
    {
        $alumno = $request->user()->alumno;

        $anterior = $alumno->documentoVigente('kardex')?->getCustomProperty('estado');
        if ($anterior === ValidacionEstado::Validada->value) {
            throw ValidationException::withMessages(['archivo' => 'Tu kardex ya fue validado: no necesitas subirlo de nuevo.']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240']], [
            'archivo.required' => 'Elige el PDF o la imagen de tu kardex simple del ciclo actual.',
        ]);

        $alumno->addMediaFromRequest('archivo')->toMediaCollection('kardex');

        $this->avisarCoordinacion($request, $anterior === ValidacionEstado::Rechazada->value
            ? 'reemplazó el kardex que le devolviste. Revísalo de nuevo.'
            : 'actualizó su kardex. Revísalo junto con su constancia IMSS.', 'coordinacion.lista-practicantes');

        return back()->with('status', 'Kardex enviado: tu coordinación lo revisará junto con tu constancia IMSS.');
    }

    public function subirCartaAceptacion(Request $request, Postulacion $postulacion): RedirectResponse
    {
        $this->propia($request, $postulacion);

        if ($postulacion->estado !== PostulacionEstado::CartaPresentacionEmitida) {
            throw ValidationException::withMessages(['archivo' => 'Aún no se te ha emitido la carta de presentación.']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $postulacion->addMediaFromRequest('archivo')->toMediaCollection('carta_aceptacion');

        $this->avisarCoordinacion($request, 'subió su carta de aceptación de «' . $postulacion->programa->titulo . '»; regístrala para activar su práctica.', 'coordinacion.lista-practicantes');

        return back()->with('status', 'Carta de aceptación enviada; tu coordinación la registrará.');
    }

    /**
     * Formato estándar del sistema (PDF) con lo que el alumno redactó: encabezado del CUCSH, datos del alumno y la
     * organización, tabla de horas, actividades y tres firmas. Se imprime, se firma y se sube escaneado.
     */
    public function formatoReporte(Request $request, Reporte $reporte)
    {
        $this->authorize('subirArchivo', $reporte);

        abort_if(blank($reporte->actividades), 404, 'Este reporte se registró sin redacción: no tiene formato generado.');

        $practica = $reporte->practica->load(['postulacion.alumno.user', 'postulacion.alumno.carrera', 'postulacion.programa.convenio.empresa', 'supervisor']);
        $alumno = $practica->postulacion->alumno;
        $programa = $practica->postulacion->programa;
        $validadasAntes = (int) $practica->reportes()->where('estado', ReporteEstado::Validado->value)->where('id', '!=', $reporte->id)->sum('horas_reportadas')
            + (int) ($practica->horas_reconocidas ?? 0);
        $logo = public_path('images/home/logo-udg.png');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('alumno.pdf.reporte', [
            'reporte' => $reporte,
            'practica' => $practica,
            'alumno' => $alumno,
            'programa' => $programa,
            'empresa' => $programa->convenio?->empresa,
            'validadasAntes' => $validadasAntes,
            'logo' => is_file($logo) ? $logo : null,
        ])->setPaper('letter');

        $nombre = "reporte-{$reporte->numero}-{$alumno->num_control}.pdf";

        // ?ver=1: se muestra en la ventana «Ver documento»; sin él, se descarga
        return request()->boolean('ver') ? $pdf->stream($nombre) : $pdf->download($nombre);
    }

    public function subirReporte(Request $request, Reporte $reporte): RedirectResponse
    {
        $practica = $reporte->practica;
        $this->authorize('subirArchivo', $reporte);

        if (
            !in_array($reporte->estado, [ReporteEstado::Pendiente, ReporteEstado::Rechazado], true)
            || $practica->estado !== PracticaEstado::EnCurso
        ) {
            throw ValidationException::withMessages(['archivo' => 'Este reporte ya no admite cambios.']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $reporte->addMediaFromRequest('archivo')->toMediaCollection('reporte_firmado');
        $reporte->update(['estado' => ReporteEstado::Pendiente]);   // un rechazado vuelve a revisión

        $this->avisarCoordinacion($request, 'reenvió su reporte firmado del ' . $reporte->periodo_inicio->format('d/m/Y') . ' para revisión.', 'coordinacion.lista-reportes');


        return back()->with('status', 'Reporte firmado enviado.');
    }

    public function subirCartaTermino(Request $request, Practica $practica): RedirectResponse
    {
        $this->authorize('entregarDocumentos', $practica);

        if ($motivo = $practica->motivoSinCartaTermino()) {
            throw ValidationException::withMessages(['archivo' => $motivo]);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $carta = CartaTermino::firstOrCreate(['practica_id' => $practica->id]);
        $carta->addMediaFromRequest('archivo')->toMediaCollection('carta_termino');
        $carta->update(['estado_validacion' => ValidacionEstado::Pendiente]);   // vuelve a revisión

        $this->avisarCoordinacion($request, 'subió su carta de término para revisión.', 'coordinacion.lista-cierres');


        return back()->with('status', 'Carta de término enviada.');
    }

    public function descargar(Request $request, Media $media)
    {
        $this->authorize('download', $media);

        return $this->entregar($media);
    }

    private function propia(Request $request, Postulacion $postulacion): void
    {
        $this->authorize('subirDocumento', $postulacion);
    }

    private function esDelAlumno(Alumno $alumno, Media $media): bool
    {
        $id = (int) $media->model_id;

        return match ($media->model_type) {
            Alumno::class => $id === (int) $alumno->id,
            Postulacion::class => Postulacion::whereKey($id)->where('alumno_id', $alumno->id)->exists(),
            Reporte::class => Reporte::whereKey($id)
                ->whereHas('practica.postulacion', fn($p) => $p->where('alumno_id', $alumno->id))->exists(),
            CartaTermino::class => CartaTermino::whereKey($id)
                ->whereHas('practica.postulacion', fn($p) => $p->where('alumno_id', $alumno->id))->exists(),
            default => false,
        };
    }

    public function crearReporte(Request $request, Practica $practica): RedirectResponse
    {
        $this->authorize('entregarDocumentos', $practica);

        if ($practica->estado !== PracticaEstado::EnCurso) {
            throw ValidationException::withMessages(['archivo' => 'Solo puedes subir reportes cuando la práctica está en curso.']);
        }

        $tope = today()->min($practica->fecha_fin)->toDateString();

        // Cada carrera define la periodicidad de sus reportes (mensual, bimestral o parcial + final)
        $esquema = $request->user()->alumno->carrera->esquema_reportes ?? \App\Enums\EsquemaReportes::Mensual;
        $permitidos = array_map(fn(ReporteTipo $t) => $t->value, $esquema->tipos());

        $datos = $request->validate([
            'tipo' => ['required', Rule::in($permitidos)],
            'periodo_inicio' => ['required', 'date', 'after_or_equal:' . $practica->fecha_inicio->toDateString(), 'before_or_equal:' . $tope],
            'periodo_fin' => ['required', 'date', 'after_or_equal:periodo_inicio', 'before_or_equal:' . $tope],
            'horas_reportadas' => ['required', 'integer', 'min:1', 'max:1000'],
            // Lo que redacta en pantalla: con esto el sistema genera el formato estándar en PDF
            'actividades' => ['required_without:archivo', 'nullable', 'string', 'min:30', 'max:5000'],
            'logros' => ['nullable', 'string', 'max:3000'],
            // Opcional: si su carrera usa un formato propio, sube ese ya firmado (los datos siguen contando las horas)
            'archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'actividades.required_without' => 'Describe las actividades que realizaste en el periodo (o sube el formato propio de tu carrera ya firmado).',
            'actividades.min' => 'Describe con más detalle tus actividades (al menos 30 caracteres).',
            'periodo_inicio.after_or_equal' => 'El periodo no puede empezar antes de tu práctica.',
            'periodo_fin.before_or_equal' => 'El periodo no puede terminar en el futuro ni después de tu práctica.',
            'tipo.in' => "Tu carrera pide reportes con el esquema «{$esquema->label()}».",
        ]);

        DB::transaction(function () use ($practica, $datos, $request, $esquema) {

            $practica = Practica::lockForUpdate()->findOrFail($practica->id);

            $comprometidas = (int) $practica->reportes()
                ->where('estado', '!=', ReporteEstado::Rechazado->value)
                ->sum('horas_reportadas');

            if ($comprometidas + $datos['horas_reportadas'] > $practica->horas_requeridas) {
                throw ValidationException::withMessages([
                    'horas_reportadas' => 'Con estas horas rebasarías las ' . $practica->horas_requeridas . ' requeridas (llevas ' . $comprometidas . ' entre pendientes y validadas).',
                ]);
            }

            $tipo = ReporteTipo::from($datos['tipo']);

            // El final (y el parcial, en el esquema parcial + final) se entregan una sola vez
            if (
                $esquema->esUnico($tipo)
                && $practica->reportes()->where('tipo', $tipo->value)->where('estado', '!=', ReporteEstado::Rechazado->value)->exists()
            ) {
                throw ValidationException::withMessages(['tipo' => "Ya tienes un reporte {$tipo->label()} en revisión o validado."]);
            }

            $reporte = $practica->reportes()->create([
                'numero' => (int) $practica->reportes()->max('numero') + 1,
                'actividades' => $datos['actividades'] ?? null,
                'logros' => $datos['logros'] ?? null,
                'tipo' => $datos['tipo'],
                'periodo_inicio' => $datos['periodo_inicio'],
                'periodo_fin' => $datos['periodo_fin'],
                'horas_reportadas' => $datos['horas_reportadas'],
                'estado' => ReporteEstado::Pendiente,
            ]);

            if ($request->hasFile('archivo')) {
                $reporte->addMediaFromRequest('archivo')->toMediaCollection('reporte_firmado');
            }
        });

        if ($request->hasFile('archivo')) {
            $this->avisarCoordinacion($request, "envió un reporte {$datos['tipo']} de {$datos['horas_reportadas']} h por validar.", 'coordinacion.lista-reportes');

            return back()->with('status', 'Reporte enviado con tu formato firmado. Tu coordinación lo validará para que cuenten tus horas.');
        }

        return back()->with('status', 'Reporte registrado. Descarga el formato en PDF, recaba la firma y sello de tu jefe directo, escanéalo y súbelo en ese mismo reporte.');
    }

    private function avisarCoordinacion(Request $request, string $mensaje, string $ruta): void
    {
        $user = $request->user();

        Avisos::aCoordinacion((int) $user->alumno->carrera_id, new AvisoOperativo(
            mensaje: "{$user->name} {$mensaje}",
            url: route($ruta, absolute: false),
            detalle: $user->alumno->num_control,
        ));
    }
}