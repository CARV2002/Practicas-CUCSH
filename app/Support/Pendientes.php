<?php

namespace App\Support;

use App\Enums\ConvenioEstado;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\Rol;
use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;

/**
 * «Lo que tienes por atender», calculado en vivo para cada usuario.
 *
 * Las notificaciones de la campana solo existen cuando alguien hace algo (validar, rechazar…);
 * estos pendientes salen del estado actual, así que cada usuario ve su situación aunque no
 * haya recibido avisos (por ejemplo, datos cargados antes de las reglas nuevas).
 */
final class Pendientes
{
    /** @return list<array{tipo:string, mensaje:string, url:string}> */
    public static function de(User $user): array
    {
        return match (true) {
            $user->hasRole(Rol::Alumno->value) && $user->alumno => self::alumno($user->alumno),
            $user->hasRole(Rol::Coordinador->value) && $user->personal => self::coordinacion($user),
            $user->hasRole(Rol::Empresa->value) && $user->empresa => self::empresa($user->empresa),
            $user->hasAnyRole([Rol::Vinculacion->value, Rol::Administrador->value]) => self::vinculacion($user),
            default => [],
        };
    }

    private static function alumno(Alumno $alumno): array
    {
        $items = [];
        $situacion = TramiteAlumno::de($alumno)->situacion();

        // Su situación siempre encabeza la lista (salvo cuando ya acreditó y no queda nada)
        if ($situacion['titulo'] !== 'Práctica acreditada') {
            $items[] = self::item($situacion['tipo'], $situacion['titulo'] . ': ' . $situacion['texto'],
                $situacion['accion'][1] ?? route('alumno.mis-documentos', absolute: false));
        }

        $rechazados = Reporte::where('estado', ReporteEstado::Rechazado->value)
            ->whereHas('practica', fn ($q) => $q->whereIn('estado', PracticaEstado::valores(PracticaEstado::activas()))
                ->whereHas('postulacion', fn ($p) => $p->where('alumno_id', $alumno->id)))
            ->count();
        if ($rechazados > 0) {
            $items[] = self::item('error', "Tienes {$rechazados} reporte(s) rechazado(s): corrígelos y vuelve a subirlos para que tus horas cuenten.",
                route('alumno.mis-documentos', absolute: false));
        }

        $carta = Practica::whereHas('postulacion', fn ($p) => $p->where('alumno_id', $alumno->id))
            ->where('estado', PracticaEstado::EnCurso->value)
            ->whereHas('cartaTermino', fn ($c) => $c->where('estado_validacion', ValidacionEstado::Rechazada->value))
            ->exists();
        if ($carta) {
            $items[] = self::item('error', 'Tu coordinación te devolvió la carta de término: revisa el motivo y súbela corregida.',
                route('alumno.mis-documentos', absolute: false));
        }

        return $items;
    }

    private static function coordinacion(User $user): array
    {
        $personal = $user->personal;
        $ids = $personal->carreraIdsEnAlcance();
        $enAlcance = fn ($q) => $ids === null ? $q : $q->whereIn('carrera_id', $ids);
        $practicantes = route('coordinacion.lista-practicantes', absolute: false);

        $conteos = [
            [Alumno::conExpedientePorRevisar($personal)->count(), 'advertencia', 'expediente(s) de alumnos (kardex e IMSS) por revisar: al validarlos, el alumno ya puede postularse.', $practicantes],
            [Postulacion::visiblesPara($personal)->where('estado', PostulacionEstado::Pendiente->value)->count(), 'advertencia', 'alumno(s) ya se postularon con su expediente validado: emite su carta de presentación.', $practicantes],
            [Postulacion::visiblesPara($personal)->where('estado', PostulacionEstado::CartaAceptacionRecibida->value)->count(), 'info', 'carta(s) de aceptación registradas: activa la práctica.', $practicantes],
            [Reporte::where('estado', ReporteEstado::Pendiente->value)->whereHas('practica', fn ($p) => $p->visiblesPara($personal)->activas())
                ->whereHas('media')->count(), 'info', 'reporte(s) firmados por revisar.', route('coordinacion.lista-reportes', absolute: false)],
            [Practica::visiblesPara($personal)->where('estado', PracticaEstado::EnCurso->value)
                ->whereHas('cartaTermino', fn ($c) => $c->where('estado_validacion', ValidacionEstado::Pendiente->value))->count(),
                'info', 'carta(s) de término por validar.', route('coordinacion.lista-cierres', absolute: false)],
            [$enAlcance(Programa::query())->where('estado', ProgramaEstado::PendienteAprobacion->value)->count(), 'info', 'vacante(s) de empresas por aprobar.', route('coordinacion.lista-programas', absolute: false)],
            [Alumno::bloqueadosPara($personal)->count(), 'info', 'alumno(s) bloqueados por bajas: solo tú puedes hacer un desbloqueo excepcional.', $practicantes],
        ];

        return collect($conteos)
            ->filter(fn ($c) => $c[0] > 0)
            ->map(fn ($c) => self::item($c[1], "{$c[0]} {$c[2]}", $c[3]))
            ->values()
            ->all();
    }

    private static function empresa(Empresa $empresa): array
    {
        $items = [];

        $rechazados = collect(Empresa::coleccionesObligatorias())
            ->filter(fn ($col) => $empresa->documentoVigente($col)?->getCustomProperty('estado') === ValidacionEstado::Rechazada->value)
            ->count();
        if ($rechazados > 0) {
            $items[] = self::item('error', "Vinculación te devolvió {$rechazados} documento(s): revisa el motivo y súbelos corregidos.", route('empresa.documentos', absolute: false));
        }

        if (!$empresa->convenios()->exists() && ($motivo = Requisitos::empresaSolicitarConvenio($empresa))) {
            $items[] = self::item('advertencia', "Para solicitar tu convenio: {$motivo}", route('empresa.documentos', absolute: false));
        }

        $rechazadas = Programa::whereHas('convenio', fn ($c) => $c->where('empresa_id', $empresa->id))
            ->where('estado', ProgramaEstado::Rechazado->value)->count();
        if ($rechazadas > 0) {
            $items[] = self::item('info', "{$rechazadas} vacante(s) no fueron aprobadas: revisa las observaciones de la coordinación.", route('empresa.lista-programas', absolute: false));
        }

        return $items;
    }

    private static function vinculacion(User $user): array
    {
        $solicitados = Convenio::where('estado', ConvenioEstado::Solicitado->value)->count();
        $sinCoordinacion = !$user->hasRole(Rol::Administrador->value) ? 0 : Carrera::all()->filter(fn (Carrera $c) => $c->coordinadores()->isEmpty())->count();

        return collect([
            [$solicitados, 'advertencia', 'solicitud(es) de convenio por revisar.', route('admin.lista-convenios', ['estado' => 'solicitado'], false)],
            [$sinCoordinacion, 'info', 'carrera(s) sin coordinación en la plataforma: sus avisos te llegan a ti.', route('admin.lista-usuarios', absolute: false)],
        ])
            ->filter(fn ($c) => $c[0] > 0)
            ->map(fn ($c) => self::item($c[1], "{$c[0]} {$c[2]}", $c[3]))
            ->values()
            ->all();
    }

    private static function item(string $tipo, string $mensaje, string $url): array
    {
        return compact('tipo', 'mensaje', 'url');
    }
}
