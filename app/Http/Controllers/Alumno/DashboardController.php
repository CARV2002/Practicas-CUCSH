<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alumno;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ValidacionEstado;
use App\Http\Controllers\Controller;
use App\Models\Practica;
use App\Support\Pendientes;
use App\Support\Requisitos;
use App\Support\TramiteAlumno;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel del alumno: en qué paso de su trámite va, avance de horas y lo que tiene por atender.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $alumno = $user->alumno->load('carrera');

        $practica = Practica::whereHas('postulacion', fn ($p) => $p->where('alumno_id', $alumno->id))
            ->with(['postulacion.programa.convenio.empresa', 'supervisor', 'reportes.media', 'cartaTermino'])
            ->conHorasAcumuladas()
            // Una práctica dada de baja ya no es «su práctica»: su situación la explica TramiteAlumno
            ->where('estado', '!=', PracticaEstado::Cancelada->value)
            ->latest('id')
            ->first();

        $tramite = TramiteAlumno::de($alumno);

        $enTramite = $alumno->postulaciones()
            ->with('programa.convenio.empresa')
            ->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::enTramite()))
            ->latest('id')
            ->first();

        $imss = $alumno->documentoVigente('constancia_imss');
        $imssEstado = $imss?->getCustomProperty('estado');

        $base = [
            'tramite' => $tramite,
            'situacion' => $tramite->situacion(),
            'alumno' => $alumno,
            'practica' => $practica,
            'enTramite' => $enTramite,
            'saludo' => explode(' ', trim($user->name))[0],
            'pendientes' => Pendientes::de($user),
            // Por qué la oferta todavía no se abre (null = ya puede postularse)
            'bloqueoOferta' => Requisitos::alumnoVerOferta($alumno),
            'imssOk' => $imssEstado === ValidacionEstado::Validada->value
                && $alumno->tieneImssVigenteHasta(now()),
        ];

        if (! $practica) {
            return view('alumno.dashboard', $base + [
                'paso' => $tramite->situacion()['texto'],
                'kpis' => null,
                'alertas' => collect(),
                'motivacion' => $this->motivacionSinPractica($tramite),
            ]);
        }

        $requeridas = max(1, (int) $practica->horas_requeridas);
        $horas = (int) $practica->horas_acumuladas;
        $pct = min(100, (int) round($horas / $requeridas * 100));
        $activa = in_array($practica->estado, PracticaEstado::activas(), true);

        $reportes = $practica->reportes;
        $validados = $reportes->where('estado', ReporteEstado::Validado)->count();
        $rechazados = $reportes->where('estado', ReporteEstado::Rechazado);
        $sinArchivo = $reportes->filter(fn ($r) => $r->estado === ReporteEstado::Pendiente
            && $r->media->where('collection_name', 'reporte_firmado')->isEmpty());

        $diasRestantes = max(0, (int) now()->startOfDay()->diffInDays($practica->fecha_fin->copy()->startOfDay(), false));

        $alertas = collect();
        foreach ($rechazados as $r) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Reporte rechazado',
                'texto' => 'Periodo '.$r->periodo_inicio->format('d/m').' – '.$r->periodo_fin->format('d/m/Y').'. Corrígelo y vuelve a subirlo.']);
        }
        foreach ($sinArchivo as $r) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Reporte '.$r->tipo->value.' sin archivo firmado',
                'texto' => 'Periodo '.$r->periodo_inicio->format('d/m').' – '.$r->periodo_fin->format('d/m/Y').'.']);
        }
        if ($imssEstado === ValidacionEstado::Rechazada->value) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Corrige tu constancia IMSS', 'texto' => 'Tu coordinación te pidió corregirla: sube la constancia correcta en Mis documentos.']);
        } elseif ($activa && $alumno->vigencia_imss && $alumno->vigencia_imss->lt($practica->fecha_fin)) {
            $alertas->push(['tipo' => 'danger', 'titulo' => 'Tu IMSS vence antes de terminar',
                'texto' => 'Vence el '.$alumno->vigencia_imss->format('d/m/Y').'.']);
        }
        if ($activa && $practica->supervisor === null) {
            $alertas->push(['tipo' => 'info', 'titulo' => 'Supervisor por asignar', 'texto' => 'Tu coordinación te asignará un profesor supervisor.']);
        }

        return view('alumno.dashboard', $base + [
            'paso' => $this->paso($practica, $horas, $requeridas, $diasRestantes, $sinArchivo->count(), $rechazados->count()),
            'kpis' => [
                'horas' => $horas,
                'requeridas' => $requeridas,
                'pct' => $pct,
                'validados' => $validados,
                'rechazados' => $rechazados->count(),
                'reportes_total' => $reportes->count(),
                'docs_ok' => $validados,
                'dias_restantes' => $diasRestantes,
                'pendientes' => $sinArchivo->count() + $rechazados->count(),
            ],
            'programa' => $practica->postulacion->programa,
            'empresa' => $practica->postulacion->programa->convenio?->empresa,
            'alertas' => $alertas->take(4),
            'motivacion' => $this->motivacion($pct, $activa, $practica->estado === PracticaEstado::Acreditada),
        ]);
    }

    private function paso(Practica $p, int $horas, int $req, int $dias, int $sinArchivo, int $rechazados): string
    {
        $supervisor = $p->supervisor?->nombre;

        return match (true) {
            $p->estado === PracticaEstado::Acreditada => '¡Tu práctica profesional quedó acreditada! Misión cumplida. 🎓',
            $p->estado === PracticaEstado::Cancelada => 'Tu práctica fue cancelada. Consulta a tu coordinación si necesitas aclararlo.',
            $p->estado === PracticaEstado::EnCierre => 'Tu carta de término fue validada. Solo falta que tu coordinación envíe tu oficio y Control Escolar capture tu acreditación en SIIAU.',
            $rechazados > 0 => "Tienes {$rechazados} reporte(s) rechazado(s). Corrígelos y vuelve a subirlos para que tus horas cuenten.",
            $sinArchivo > 0 => "Tienes {$sinArchivo} reporte(s) pendiente(s) de entregar firmado".($supervisor ? ". Tu supervisor es {$supervisor}." : '.'),
            $horas >= $req => '¡Completaste tus horas! Pide a tu jefe directo en la organización tu carta de término (en hoja membretada, firmada y sellada), escanéala y súbela en Mis documentos.',
            $dias > 0 && $dias <= 30 => 'Quedan '.$dias.' días y te faltan '.($req - $horas).' horas validadas. Es momento de acelerar. 💪',
            default => 'Te faltan '.($req - $horas).' horas validadas. Cada reporte a tiempo te acerca a la meta.',
        };
    }

    /** @return array{icono:string,titulo:string,texto:string} */
    /** Antes de la práctica: según el paso en que va (mismo cálculo que la guía de pasos). */
    private function motivacionSinPractica(TramiteAlumno $tramite): array
    {
        return match (true) {
            $tramite->bloqueo !== null => ['icono' => '🧭', 'titulo' => 'Habla con tu coordinación', 'texto' => 'Tu coordinación puede revisar tu caso en persona y, si hubo una causa de fuerza mayor, levantar la restricción.'],
            $tramite->paso() === 4 => ['icono' => '🤝', 'titulo' => 'Ya casi empiezas', 'texto' => 'En cuanto tu coordinación valide tu carta de aceptación, tu práctica quedará activa y empezarás a sumar horas.'],
            $tramite->paso() === 3 => ['icono' => '🎯', 'titulo' => 'Elige bien tu plaza', 'texto' => 'Te postulas a una organización a la vez: revisa horario, actividades y sede antes de decidir.'],
            default => ['icono' => '🛡️', 'titulo' => 'Tu primer paso cuenta', 'texto' => 'Entrega tu kardex e IMSS; con tu expediente validado podrás postularte al programa que más te guste.'],
        };
    }

    /** @return array{icono:string,titulo:string,texto:string} */
    private function motivacion(int $pct, bool $activa, bool $acreditada): array
    {
        return match (true) {
            $acreditada => ['icono' => '🎓', 'titulo' => '¡Acreditada!', 'texto' => 'Cerraste tu práctica con éxito. Lo aprendido ya es parte de tu perfil profesional.'],
            ! $activa && $pct === 0 => ['icono' => '🛡️', 'titulo' => 'Tu primer paso cuenta', 'texto' => 'Entrega tu kardex e IMSS; con tu expediente validado podrás postularte al programa que más te guste.'],
            $pct >= 100 => ['icono' => '🏆', 'titulo' => '¡Meta alcanzada!', 'texto' => 'Completaste tus horas. Solo queda cerrar el trámite documental.'],
            $pct >= 75 => ['icono' => '🚀', 'titulo' => 'Recta final', 'texto' => 'Ya pasaste el 75%. El esfuerzo constante está dando resultados.'],
            $pct >= 50 => ['icono' => '⚡', 'titulo' => 'Mitad del camino', 'texto' => 'Superaste la mitad. Mantén el ritmo, vas muy bien.'],
            $pct >= 25 => ['icono' => '🌿', 'titulo' => 'Buen arranque', 'texto' => 'Ya tienes la base. La constancia de ahora define tu cierre.'],
            default => ['icono' => '🌱', 'titulo' => 'Todo gran camino empieza así', 'texto' => 'Estás iniciando. Entrega tus reportes a tiempo y construye tu experiencia.'],
        };
    }
}
