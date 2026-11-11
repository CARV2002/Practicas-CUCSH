<?php

namespace App\Http\Controllers\Alumno;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Models\Practica;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MisPracticasController extends Controller
{
    public function index(Request $request): View
    {
        $alumno = $request->user()->alumno->load('carrera');

        $base = fn () => Practica::whereHas('postulacion', fn ($p) => $p->where('alumno_id', $alumno->id))
            ->with(['postulacion.programa.convenio.empresa', 'supervisor', 'reportes.media', 'cartaTermino'])
            ->conHorasAcumuladas();

        $practica = $base()->activas()->latest('id')->first() ?? $base()->latest('id')->first();

        $enTramite = $alumno->postulaciones()
            ->with('programa.convenio.empresa')
            ->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::enTramite()))
            ->latest('id')
            ->first();

        if (! $practica) {
            return view('alumno.mis-practicas', [
                'alumno' => $alumno,
                'practica' => null,
                'enTramite' => $enTramite,
                'mensaje' => $this->mensajeSinPractica($enTramite),
            ]);
        }

        $requeridas = max(1, (int) $practica->horas_requeridas);
        $horas = (int) $practica->horas_acumuladas;
        $pct = min(100, (int) round($horas / $requeridas * 100));

        $inicio = $practica->fecha_inicio->copy()->startOfDay();
        $fin = $practica->fecha_fin->copy()->startOfDay();
        $hoy = now()->startOfDay();

        $semanasTotales = max(1, (int) ceil($inicio->diffInDays($fin) / 7));
        $diasTranscurridos = max(1, (int) $inicio->diffInDays(min($hoy, $fin)) + 1);
        $diasRestantes = max(0, (int) $hoy->diffInDays($fin, false));

        $reportes = $practica->reportes->sortByDesc('periodo_fin')->values();
        $activa = in_array($practica->estado, PracticaEstado::activas(), true);

        $pendientes = $reportes->filter(fn ($r) => $r->estado === ReporteEstado::Pendiente
            && $r->getMedia('reporte_firmado')->isEmpty());
        $rechazados = $reportes->filter(fn ($r) => $r->estado === ReporteEstado::Rechazado);

        $imss = $alumno->documentoVigente('constancia_imss');
        $imssRechazado = $imss?->getCustomProperty('estado') === ValidacionEstado::Rechazada->value;

        // Alertas de atención (máx. 4, las más urgentes primero)
        $alertas = collect();

        foreach ($rechazados as $r) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Reporte rechazado',
                'texto' => 'Periodo '.$r->periodo_inicio->format('d/m').' – '.$r->periodo_fin->format('d/m/Y').'. Corrígelo y vuelve a subirlo.']);
        }
        foreach ($pendientes as $r) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Reporte '.$r->tipo->value.' sin archivo firmado',
                'texto' => 'Periodo '.$r->periodo_inicio->format('d/m').' – '.$r->periodo_fin->format('d/m/Y').'.']);
        }
        if ($imssRechazado) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Constancia IMSS rechazada', 'texto' => 'Súbela de nuevo en Mis documentos.']);
        } elseif ($alumno->vigencia_imss && $activa && $alumno->vigencia_imss->lt($fin)) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Tu IMSS vence antes de terminar la práctica',
                'texto' => 'Vence el '.$alumno->vigencia_imss->format('d/m/Y').'. Renueva tu constancia.']);
        }
        if ($activa && $diasRestantes > 0 && $diasRestantes <= 30 && $horas < $requeridas) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Quedan '.$diasRestantes.' días de práctica',
                'texto' => 'Te faltan '.($requeridas - $horas).' horas validadas.']);
        }
        if ($alertas->isEmpty()) {
            $alertas->push(['tipo' => 'info', 'titulo' => 'Todo en orden', 'texto' => 'No tienes pendientes por atender.']);
        }

        // Hitos del programa
        $hitos = collect([25, 50, 75, 100])->map(fn ($p) => [
            'pct' => $p,
            'logrado' => $pct >= $p,
            'icono' => ['25' => '🌱', '50' => '⚡', '75' => '🚀', '100' => '🏆'][$p],
            'titulo' => $p === 100 ? 'Horas completas' : $p.'% del programa',
            'detalle' => round($requeridas * $p / 100).' horas validadas',
        ]);

        return view('alumno.mis-practicas', [
            'alumno' => $alumno,
            'practica' => $practica,
            'enTramite' => $enTramite,
            'programa' => $practica->postulacion->programa,
            'empresa' => $practica->postulacion->programa->convenio?->empresa,
            'activa' => $activa,
            'horas' => $horas,
            'requeridas' => $requeridas,
            'pct' => $pct,
            'metricas' => [
                'meta_semanal' => round($requeridas / $semanasTotales, 1),
                'ritmo_semanal' => round($horas / $diasTranscurridos * 7, 1),
                'dias_restantes' => $diasRestantes,
                'avance_tiempo' => min(100, (int) round($diasTranscurridos / max(1, $inicio->diffInDays($fin) + 1) * 100)),
            ],
            'reportes' => $reportes->take(5),
            'alertas' => $alertas->take(4),
            'hitos' => $hitos,
            'pendientes' => $pendientes->count(),
            'mensaje' => $this->mensaje($practica, $pct, $diasRestantes, $horas, $requeridas, $pendientes->count(), $rechazados->count(), $activa),
            'motivacion' => $this->motivacion($pct, $activa),
        ]);
    }

    /** Mensaje contextual: el "siguiente paso" del alumno. */
    private function mensaje(Practica $p, int $pct, int $dias, int $horas, int $requeridas, int $pendientes, int $rechazados, bool $activa): string
    {
        if ($p->estado === PracticaEstado::Acreditada) {
            return '¡Felicidades! Tu práctica profesional quedó acreditada. Misión cumplida. 🎓';
        }
        if ($p->estado === PracticaEstado::Cancelada) {
            return 'Esta práctica fue cancelada. Si crees que es un error, consulta a la Coordinación de Prácticas.';
        }
        if ($p->estado === PracticaEstado::EnCierre) {
            return 'Tu carta de término ya fue validada. Solo falta que la Coordinación capture tu acreditación; no tienes que hacer nada más.';
        }
        if ($rechazados > 0) {
            return 'Un reporte fue rechazado. Revísalo, corrígelo y súbelo de nuevo para que tus horas cuenten.';
        }
        if ($pendientes > 0) {
            return "Tienes {$pendientes} reporte(s) sin archivo firmado. Súbelos en Mis documentos para que se validen tus horas.";
        }
        if ($horas >= $requeridas) {
            return '¡Completaste tus horas! Sube tu carta de término en Mis documentos para cerrar tu práctica.';
        }

        $faltan = $requeridas - $horas;

        if ($dias > 0 && $dias <= 30) {
            return "Quedan {$dias} días y te faltan {$faltan} horas validadas. Es momento de acelerar el paso. 💪";
        }

        return "Te faltan {$faltan} horas validadas para completar tu práctica. Cada reporte a tiempo te acerca a la meta.";
    }

    /** @return array{icono:string,titulo:string,texto:string} */
    private function motivacion(int $pct, bool $activa): array
    {
        return match (true) {
            ! $activa => ['icono' => '🏁', 'titulo' => 'Etapa concluida', 'texto' => 'Cerraste esta etapa. Lo aprendido aquí ya es parte de tu perfil profesional.'],
            $pct >= 100 => ['icono' => '🏆', 'titulo' => '¡Meta alcanzada!', 'texto' => 'Completaste tus horas. Solo queda cerrar el trámite documental.'],
            $pct >= 75 => ['icono' => '🚀', 'titulo' => 'Recta final', 'texto' => 'Ya pasaste el 75%. El esfuerzo constante está dando resultados.'],
            $pct >= 50 => ['icono' => '⚡', 'titulo' => 'Mitad del camino', 'texto' => 'Superaste la mitad de tu programa. Mantén el ritmo, vas muy bien.'],
            $pct >= 25 => ['icono' => '🌿', 'titulo' => 'Buen arranque', 'texto' => 'Ya tienes la base. La constancia de ahora define tu cierre.'],
            default => ['icono' => '🌱', 'titulo' => 'Todo gran camino empieza así', 'texto' => 'Estás iniciando. Registra tus reportes a tiempo y construye tu experiencia profesional.'],
        };
    }

    /** @return array{icono:string,titulo:string,texto:string} */
    private function mensajeSinPractica($enTramite): array
    {
        if ($enTramite) {
            return match ($enTramite->estado) {
                PostulacionEstado::CartaPresentacionEmitida => ['icono' => '📨', 'titulo' => 'Tu carta de presentación está lista',
                    'texto' => 'Preséntate con '.($enTramite->programa->convenio?->empresa->razon_social ?? 'el programa').' y sube la carta de aceptación en Mis documentos.'],
                default => ['icono' => '⏳', 'titulo' => 'Tu práctica está por activarse',
                    'texto' => 'La Coordinación registrará tu carta de aceptación y activará tu práctica en breve.'],
            };
        }

        return ['icono' => '🎓', 'titulo' => 'Tu práctica comienza con una postulación',
            'texto' => 'Explora los programas disponibles para tu carrera y da el primer paso.'];
    }
}