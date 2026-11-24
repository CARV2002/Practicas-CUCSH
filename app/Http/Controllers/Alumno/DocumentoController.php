<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alumno;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ReporteTipo;
use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Alumno\CrearReporteRequest;
use App\Http\Requests\Alumno\EnviarExpedienteRequest;
use App\Http\Requests\Alumno\SubirImssRequest;
use App\Models\CartaTermino;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Reporte;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use App\Services\FormatoReporte;
use App\Support\ReglasArchivo;
use App\Support\Requisitos;
use App\Support\TramiteAlumno;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Mis documentos» del alumno: expediente (kardex + IMSS), cartas, reportes de horas y carta de término.
 */
class DocumentoController extends Controller
{
    public function index(Request $request): View
    {
        $alumno = $request->user()->alumno;

        return view('alumno.mis-documentos', [
            'alumno' => $alumno->load('carrera'),
            'imss' => $alumno->documentoVigente('constancia_imss'),
            // Guía: los mismos pasos que el panel
            'tramite' => TramiteAlumno::de($alumno),
            // Por qué todavía no puede postularse (null = ya puede)
            'motivoOferta' => Requisitos::alumnoVerOferta($alumno),
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
    public function enviarExpediente(EnviarExpedienteRequest $request): RedirectResponse
    {
        $alumno = $request->alumno();
        $datos = $request->validated();

        $alumno->addMediaFromRequest('kardex')->toMediaCollection('kardex');
        $alumno->addMediaFromRequest('imss')->toMediaCollection('constancia_imss');
        $alumno->update(['vigencia_imss' => $datos['vigencia_imss'], 'porcentaje_creditos' => $datos['porcentaje_creditos']]);

        $this->avisarCoordinacion($request, 'envió su kardex y su constancia IMSS. Revísalos juntos: si están correctos, autorízalo para que pueda postularse.', 'coordinacion.lista-practicantes');

        return back()->with('status', 'Expediente enviado. Tu coordinación revisará juntos tu kardex y tu constancia IMSS; te avisaremos por correo cuando puedas postularte.');
    }

    public function subirImss(SubirImssRequest $request): RedirectResponse
    {
        $alumno = $request->user()->alumno;
        $datos = $request->validated();

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

        $request->validate(['archivo' => ReglasArchivo::pdfRequerido(estricto: true)], [
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

        $request->validate(['archivo' => ReglasArchivo::pdfRequerido()]);

        $postulacion->addMediaFromRequest('archivo')->toMediaCollection('carta_aceptacion');

        $this->avisarCoordinacion($request, 'subió su carta de aceptación de «'.$postulacion->programa->titulo.'»; regístrala para activar su práctica.', 'coordinacion.lista-practicantes');

        return back()->with('status', 'Carta de aceptación enviada; tu coordinación la registrará.');
    }

    /** Formato estándar del reporte en PDF (FormatoReporte): se imprime, se firma y se sube escaneado. */
    public function formatoReporte(Reporte $reporte, FormatoReporte $formato): Response
    {
        $this->authorize('subirArchivo', $reporte);

        abort_if(blank($reporte->actividades), 404, 'Este reporte se registró sin redacción: no tiene formato generado.');

        $pdf = $formato->generar($reporte);
        $nombre = $formato->nombre($reporte);

        // ?ver=1: se muestra en la ventana «Ver documento»; sin él, se descarga
        return request()->boolean('ver') ? $pdf->stream($nombre) : $pdf->download($nombre);
    }

    public function subirReporte(Request $request, Reporte $reporte): RedirectResponse
    {
        $practica = $reporte->practica;
        $this->authorize('subirArchivo', $reporte);

        if (
            ! in_array($reporte->estado, [ReporteEstado::Pendiente, ReporteEstado::Rechazado], true)
            || $practica->estado !== PracticaEstado::EnCurso
        ) {
            throw ValidationException::withMessages(['archivo' => 'Este reporte ya no admite cambios.']);
        }

        $request->validate(['archivo' => ReglasArchivo::pdfRequerido()]);

        $reporte->addMediaFromRequest('archivo')->toMediaCollection('reporte_firmado');
        $reporte->update(['estado' => ReporteEstado::Pendiente]);   // un rechazado vuelve a revisión

        $this->avisarCoordinacion($request, 'reenvió su reporte firmado del '.$reporte->periodo_inicio->format('d/m/Y').' para revisión.', 'coordinacion.lista-reportes');

        return back()->with('status', 'Reporte firmado enviado.');
    }

    public function subirCartaTermino(Request $request, Practica $practica): RedirectResponse
    {
        $this->authorize('entregarDocumentos', $practica);

        if ($motivo = $practica->motivoSinCartaTermino()) {
            throw ValidationException::withMessages(['archivo' => $motivo]);
        }

        $request->validate(['archivo' => ReglasArchivo::pdfRequerido()]);

        $carta = CartaTermino::firstOrCreate(['practica_id' => $practica->id]);
        $carta->addMediaFromRequest('archivo')->toMediaCollection('carta_termino');
        $carta->update(['estado_validacion' => ValidacionEstado::Pendiente]);   // vuelve a revisión

        $this->avisarCoordinacion($request, 'subió su carta de término para revisión.', 'coordinacion.lista-cierres');

        return back()->with('status', 'Carta de término enviada.');
    }

    public function descargar(Request $request, Media $media): Response
    {
        $this->authorize('download', $media);

        return $this->entregar($media);
    }

    private function propia(Request $request, Postulacion $postulacion): void
    {
        $this->authorize('subirDocumento', $postulacion);
    }

    public function crearReporte(CrearReporteRequest $request, Practica $practica): RedirectResponse
    {
        if ($practica->estado !== PracticaEstado::EnCurso) {
            throw ValidationException::withMessages(['archivo' => 'Solo puedes subir reportes cuando la práctica está en curso.']);
        }

        $datos = $request->validated();
        $esquema = $request->esquema();

        DB::transaction(function () use ($practica, $datos, $request, $esquema) {

            $practica = Practica::lockForUpdate()->findOrFail($practica->id);

            $comprometidas = (int) $practica->reportes()
                ->where('estado', '!=', ReporteEstado::Rechazado->value)
                ->sum('horas_reportadas');

            if ($comprometidas + $datos['horas_reportadas'] > $practica->horas_requeridas) {
                throw ValidationException::withMessages([
                    'horas_reportadas' => 'Con estas horas rebasarías las '.$practica->horas_requeridas.' requeridas (llevas '.$comprometidas.' entre pendientes y validadas).',
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
