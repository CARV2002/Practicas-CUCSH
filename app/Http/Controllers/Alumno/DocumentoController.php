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

    public function subirImss(Request $request): RedirectResponse
    {
        $alumno = $request->user()->alumno;

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'vigencia_imss' => ['required', 'date', 'after:today'],
        ], [
            'vigencia_imss.after' => 'La vigencia del seguro debe ser posterior a hoy.',
        ]);

        $alumno->addMediaFromRequest('archivo')->toMediaCollection('constancia_imss');
        $alumno->update(['vigencia_imss' => $datos['vigencia_imss']]);

        return back()->with('status', 'Constancia IMSS enviada a revisión.');
    }

    public function subirKardex(Request $request, Postulacion $postulacion): RedirectResponse
    {
        $this->propia($request, $postulacion);

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);

        $postulacion->addMediaFromRequest('archivo')->toMediaCollection('kardex');

        return back()->with('status', 'Kardex enviado.');
    }

    public function subirCartaAceptacion(Request $request, Postulacion $postulacion): RedirectResponse
    {
        $this->propia($request, $postulacion);

        if ($postulacion->estado !== PostulacionEstado::CartaPresentacionEmitida) {
            throw ValidationException::withMessages(['archivo' => 'Aún no se te ha emitido la carta de presentación.']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $postulacion->addMediaFromRequest('archivo')->toMediaCollection('carta_aceptacion');

        $this->avisarCoordinacion($request, 'subió su carta de aceptación de «'.$postulacion->programa->titulo.'»; regístrala para activar su práctica.', 'admin.lista-practicantes');

        return back()->with('status', 'Carta de aceptación enviada; la Coordinación la registrará.');
    }

    public function subirReporte(Request $request, Reporte $reporte): RedirectResponse
    {
        $practica = $reporte->practica;
        $this->authorize('subirArchivo', $reporte);

        if (
            !in_array($reporte->estado, [ReporteEstado::Pendiente, ReporteEstado::Rechazado], true)
            || !in_array($practica->estado, PracticaEstado::activas(), true)
        ) {
            throw ValidationException::withMessages(['archivo' => 'Este reporte ya no admite cambios.']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $reporte->addMediaFromRequest('archivo')->toMediaCollection('reporte_firmado');
        $reporte->update(['estado' => ReporteEstado::Pendiente]);   // un rechazado vuelve a revisión

        $this->avisarCoordinacion($request, 'reenvió su reporte firmado del '.$reporte->periodo_inicio->format('d/m/Y').' para revisión.', 'admin.lista-reportes');


        return back()->with('status', 'Reporte firmado enviado.');
    }

    public function subirCartaTermino(Request $request, Practica $practica): RedirectResponse
    {
        $this->authorize('entregarDocumentos', $practica);

        if ($practica->estado !== PracticaEstado::EnCurso) {
            throw ValidationException::withMessages(['archivo' => 'Tu carta de término ya no admite cambios.']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $carta = CartaTermino::firstOrCreate(['practica_id' => $practica->id]);
        $carta->addMediaFromRequest('archivo')->toMediaCollection('carta_termino');
        $carta->update(['estado_validacion' => ValidacionEstado::Pendiente]);   // vuelve a revisión

        $this->avisarCoordinacion($request, 'subió su carta de término para revisión.', 'admin.lista-cierres');


        return back()->with('status', 'Carta de término enviada.');
    }

    public function descargar(Request $request, Media $media)
    {
        $this->authorize('download', $media);

        return $media->toResponse($request);
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

        if (!in_array($practica->estado, PracticaEstado::activas(), true)) {
            throw ValidationException::withMessages(['archivo' => 'La práctica ya no está activa.']);
        }

        $tope = today()->min($practica->fecha_fin)->toDateString();

        $datos = $request->validate([
            'tipo' => ['required', Rule::enum(ReporteTipo::class)],
            'periodo_inicio' => ['required', 'date', 'after_or_equal:' . $practica->fecha_inicio->toDateString(), 'before_or_equal:' . $tope],
            'periodo_fin' => ['required', 'date', 'after_or_equal:periodo_inicio', 'before_or_equal:' . $tope],
            'horas_reportadas' => ['required', 'integer', 'min:1', 'max:1000'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'periodo_inicio.after_or_equal' => 'El periodo no puede empezar antes de tu práctica.',
            'periodo_fin.before_or_equal' => 'El periodo no puede terminar en el futuro ni después de tu práctica.',
        ]);

        DB::transaction(function () use ($practica, $datos, $request) {
            $this->avisarCoordinacion($request, "envió un reporte {$datos['tipo']} de {$datos['horas_reportadas']} h por validar.", 'admin.lista-reportes');

            $practica = Practica::lockForUpdate()->findOrFail($practica->id);

            $comprometidas = (int) $practica->reportes()
                ->where('estado', '!=', ReporteEstado::Rechazado->value)
                ->sum('horas_reportadas');

            if ($comprometidas + $datos['horas_reportadas'] > $practica->horas_requeridas) {
                throw ValidationException::withMessages([
                    'horas_reportadas' => 'Con estas horas rebasarías las ' . $practica->horas_requeridas . ' requeridas (llevas ' . $comprometidas . ' entre pendientes y validadas).',
                ]);
            }

            if (
                $datos['tipo'] === ReporteTipo::Final ->value
                && $practica->reportes()->where('tipo', 'final')->where('estado', '!=', ReporteEstado::Rechazado->value)->exists()
            ) {
                throw ValidationException::withMessages(['tipo' => 'Ya tienes un reporte final en revisión o validado.']);
            }

            $reporte = $practica->reportes()->create([
                'tipo' => $datos['tipo'],
                'periodo_inicio' => $datos['periodo_inicio'],
                'periodo_fin' => $datos['periodo_fin'],
                'horas_reportadas' => $datos['horas_reportadas'],
                'estado' => ReporteEstado::Pendiente,
            ]);

            $reporte->addMediaFromRequest('archivo')->toMediaCollection('reporte_firmado');
        });

        return back()->with('status', 'Reporte enviado. La Coordinación lo validará para que cuenten tus horas.');
    }

    private function avisarCoordinacion(Request $request, string $mensaje, string $ruta): void
    {
        $user = $request->user();

        Avisos::enviar(Avisos::administradores((int) $user->alumno->carrera_id), new AvisoOperativo(
            mensaje: "{$user->name} {$mensaje}",
            url: route($ruta, absolute: false),
            detalle: $user->alumno->num_control,
        ));
    }
}