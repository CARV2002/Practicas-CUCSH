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

        // Un IMSS validado y vigente ya no se reemplaza (no tendría caso volver a revisarlo)
        if ($alumno->tieneImssValidadoVigente()) {
            throw ValidationException::withMessages([
                'archivo' => 'Tu constancia IMSS ya fue validada y sigue vigente: no necesitas subirla de nuevo.',
            ]);
        }

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'vigencia_imss' => ['required', 'date', 'after:today'],
        ], [
            'archivo.required' => 'Elige el PDF o la imagen de tu constancia de vigencia de derechos.',
            'vigencia_imss.required' => 'Escribe la fecha hasta la que está vigente tu seguro (aparece en la constancia).',
            'vigencia_imss.after' => 'La vigencia debe ser posterior a hoy: si ya venció, descarga una constancia actualizada del IMSS.',
        ]);

        $anterior = $alumno->documentoVigente('constancia_imss')?->getCustomProperty('estado');
        $alumno->addMediaFromRequest('archivo')->toMediaCollection('constancia_imss');
        $alumno->update(['vigencia_imss' => $datos['vigencia_imss']]);

        // Se revisa junto con el kardex: solo se avisa si ya hay algo que revisar (postulación o práctica)
        $enRevision = $alumno->postulaciones()->where('estado', PostulacionEstado::Pendiente->value)->exists()
            || $alumno->tienePracticaActiva();

        if ($enRevision) {
            $this->avisarCoordinacion($request, $anterior === ValidacionEstado::Rechazada->value
                ? 'reemplazó su constancia IMSS que le devolviste. Revísala de nuevo.'
                : 'actualizó su constancia de vigencia IMSS para validar.', 'coordinacion.lista-practicantes');
        }

        return back()->with('status', $enRevision
            ? 'Constancia IMSS enviada: tu coordinación la revisará junto con tu kardex.'
            : 'Constancia IMSS guardada. Se revisará junto con tu kardex cuando te postules a una vacante.');
    }

    public function subirKardex(Request $request, Postulacion $postulacion): RedirectResponse
    {
        $this->propia($request, $postulacion);

        if ($postulacion->estado !== PostulacionEstado::Pendiente) {
            throw ValidationException::withMessages(['archivo' => 'Tu coordinación ya autorizó esta postulación: el kardex ya no se puede cambiar.']);
        }

        $anterior = $postulacion->documentoVigente('kardex')?->getCustomProperty('estado');
        if ($anterior === ValidacionEstado::Validada->value) {
            throw ValidationException::withMessages(['archivo' => 'Tu kardex ya fue validado: no necesitas subirlo de nuevo.']);
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']], [
            'archivo.required' => 'Elige el PDF o la imagen de tu kardex simple del ciclo actual.',
        ]);

        $postulacion->addMediaFromRequest('archivo')->toMediaCollection('kardex');

        $this->avisarCoordinacion($request, ($anterior === ValidacionEstado::Rechazada->value
            ? 'reemplazó el kardex que le devolviste'
            : 'actualizó su kardex') . " para «{$postulacion->programa->titulo}». Revísalo de nuevo.", 'coordinacion.lista-practicantes');

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
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
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
                'tipo' => $datos['tipo'],
                'periodo_inicio' => $datos['periodo_inicio'],
                'periodo_fin' => $datos['periodo_fin'],
                'horas_reportadas' => $datos['horas_reportadas'],
                'estado' => ReporteEstado::Pendiente,
            ]);

            $reporte->addMediaFromRequest('archivo')->toMediaCollection('reporte_firmado');
        });

        $this->avisarCoordinacion($request, "envió un reporte {$datos['tipo']} de {$datos['horas_reportadas']} h por validar.", 'coordinacion.lista-reportes');

        return back()->with('status', 'Reporte enviado. Tu coordinación lo validará para que cuenten tus horas.');
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