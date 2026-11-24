<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ConvenioEstado;
use App\Enums\EstadoDocumentoFisico;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\Rol;
use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\DocumentoFisico;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Support\Collection;

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
            $items[] = self::item($situacion['tipo'], $situacion['titulo'].': '.$situacion['texto'],
                $situacion['accion'][1] ?? route('alumno.mis-documentos', absolute: false));
        }

        $rechazados = Reporte::where('estado', ReporteEstado::Rechazado->value)
            ->whereHas('practica', fn ($q) => $q->whereIn('estado', PracticaEstado::valores(PracticaEstado::activas()))
                ->whereHas('postulacion', fn ($p) => $p->where('alumno_id', $alumno->id)))
            ->count();
        if ($rechazados > 0) {
            $items[] = self::item('error', "Tienes {$rechazados} reporte(s) rechazado(s): corrígelos y vuelve a subirlos para que tus horas cuenten.",
                route('alumno.mis-documentos', absolute: false), $rechazados);
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

    /**
     * Conteos de lo que la coordinación tiene por atender. Es la única fuente: la usan la campana,
     * el saludo del panel («Tienes N pendientes») y sus tarjetas, para que siempre coincidan.
     *
     * @return array{expedientes:int, imss:int, kardex:int, postulaciones:int, cartas_aceptacion:int, reportes:int,
     *               cartas_termino:int, en_cierre:int, vacantes:int, bloqueados:int}
     */
    public static function conteosCoordinacion(Personal $personal): array
    {
        $ids = $personal->carreraIdsEnAlcance();
        $enAlcance = fn ($q) => $ids === null ? $q : $q->whereIn('carrera_id', $ids);
        $porValidar = fn ($doc) => $doc !== null && in_array($doc->getCustomProperty('estado'), [null, ValidacionEstado::Pendiente->value], true);
        $expedientes = Alumno::conExpedientePorRevisar($personal);

        return [
            'expedientes' => $expedientes->count(),
            'imss' => $expedientes->filter(fn ($a) => $porValidar($a->documentoVigente('constancia_imss')))->count(),
            'kardex' => $expedientes->filter(fn ($a) => $porValidar($a->documentoVigente('kardex')))->count(),
            'postulaciones' => Postulacion::visiblesPara($personal)->where('estado', PostulacionEstado::Pendiente->value)->count(),
            'cartas_aceptacion' => Postulacion::visiblesPara($personal)->where('estado', PostulacionEstado::CartaAceptacionRecibida->value)->count(),
            // Solo los que el alumno ya entregó firmados: los que esperan archivo son pendiente del alumno
            'reportes' => Reporte::where('estado', ReporteEstado::Pendiente->value)
                ->whereHas('practica', fn ($p) => $p->visiblesPara($personal)->activas())->whereHas('media')->count(),
            'cartas_termino' => Practica::visiblesPara($personal)->where('estado', PracticaEstado::EnCurso->value)
                ->whereHas('cartaTermino', fn ($c) => $c->where('estado_validacion', ValidacionEstado::Pendiente->value))->count(),
            'en_cierre' => Practica::visiblesPara($personal)->where('estado', PracticaEstado::EnCierre->value)->count(),
            'vacantes' => $enAlcance(Programa::query())->where('estado', ProgramaEstado::PendienteAprobacion->value)->count(),
            'bloqueados' => Alumno::bloqueadosPara($personal)->count(),
        ];
    }

    /** Total de pendientes de la coordinación (lo que dice el saludo del panel). */
    public static function totalCoordinacion(array $c): int
    {
        return $c['expedientes'] + $c['postulaciones'] + $c['cartas_aceptacion'] + $c['reportes']
            + $c['cartas_termino'] + $c['en_cierre'] + $c['vacantes'] + $c['bloqueados'];
    }

    private static function coordinacion(User $user): array
    {
        $c = self::conteosCoordinacion($user->personal);
        $practicantes = route('coordinacion.lista-practicantes', absolute: false);
        $cierres = route('coordinacion.lista-cierres', absolute: false);

        $conteos = [
            [$c['expedientes'], 'advertencia', 'expediente(s) de alumnos (kardex e IMSS) por revisar: al validarlos, el alumno ya puede postularse.', $practicantes],
            [$c['postulaciones'], 'advertencia', 'alumno(s) ya se postularon con su expediente validado: emite su carta de presentación.', $practicantes],
            [$c['cartas_aceptacion'], 'info', 'carta(s) de aceptación registradas: activa la práctica.', $practicantes],
            [$c['reportes'], 'info', 'reporte(s) firmados por revisar.', route('coordinacion.lista-reportes', absolute: false)],
            [$c['cartas_termino'], 'info', 'carta(s) de término por validar.', $cierres],
            [$c['en_cierre'], 'info', 'práctica(s) en cierre: envía el oficio a Control Escolar y registra la captura en SIIAU.', $cierres],
            [$c['vacantes'], 'info', 'vacante(s) de empresas por aprobar.', route('coordinacion.lista-programas', ['estado' => 'pendiente_aprobacion'], false)],
            [$c['bloqueados'], 'info', 'alumno(s) bloqueados por bajas: solo tú puedes hacer un desbloqueo excepcional.', $practicantes],
        ];

        return collect($conteos)
            ->filter(fn ($x) => $x[0] > 0)
            ->map(fn ($x) => self::item($x[1], "{$x[0]} {$x[2]}", $x[3], $x[0]))
            ->values()
            ->all();
    }

    private static function empresa(Empresa $empresa): array
    {
        $items = [];

        $rechazados = collect($empresa->obligatorias())
            ->filter(fn ($col) => $empresa->documentoVigente($col)?->getCustomProperty('estado') === ValidacionEstado::Rechazada->value)
            ->count();
        if ($rechazados > 0) {
            $items[] = self::item('error', "Vinculación te devolvió {$rechazados} documento(s): revisa el motivo y súbelos corregidos.", route('empresa.documentos', absolute: false), $rechazados);
        }

        if (! $empresa->convenios()->exists() && ($motivo = Requisitos::empresaSolicitarConvenio($empresa))) {
            $items[] = self::item('advertencia', "Para solicitar tu convenio: {$motivo}", route('empresa.documentos', absolute: false));
        }

        $juegos = DocumentoFisico::where('estado', EstadoDocumentoFisico::ConEmpresa->value)
            ->whereHas('convenio', fn ($c) => $c->where('empresa_id', $empresa->id))->count();
        if ($juegos > 0) {
            $items[] = self::item('advertencia', "Tienes {$juegos} juego(s) original(es) del convenio por firmar y entregar en Vinculación.", route('empresa.convenio', absolute: false), $juegos);
        }

        $sinComentar = Reporte::whereHas('practica', fn ($p) => $p->activas()
            ->whereHas('postulacion.programa.convenio', fn ($c) => $c->where('empresa_id', $empresa->id)))
            ->where('estado', ReporteEstado::Pendiente->value)->whereNull('retroalimentacion_empresa')->count();
        if ($sinComentar > 0) {
            $items[] = self::item('info', "{$sinComentar} reporte(s) de tus practicantes esperan tu comentario.", route('empresa.lista-practicantes', absolute: false), $sinComentar);
        }
        $rechazadas = Programa::whereHas('convenio', fn ($c) => $c->where('empresa_id', $empresa->id))
            ->where('estado', ProgramaEstado::Rechazado->value)->count();
        if ($rechazadas > 0) {
            $items[] = self::item('info', "{$rechazadas} vacante(s) no fueron aprobadas: revisa las observaciones de la coordinación.", route('empresa.lista-programas', absolute: false), $rechazadas);
        }

        return $items;
    }

    /**
     * Lo que Vinculación tiene por atender: los convenios donde le toca actuar (lo mismo que «Te toca a ti»
     * en la bandeja), los papeles fuera de plazo y los convenios por vencer. Misma fuente para la campana y el saludo.
     *
     * @return array{te_tocan:Collection, fisicos_vencidos:int, por_vencer:int}
     */
    public static function conteosVinculacion(): array
    {
        $teTocan = Convenio::with(['empresa.media', 'seguimientos', 'dictamenes', 'documentosFisicos'])
            ->whereNot('estado', ConvenioEstado::Rechazado->value)->get()
            ->map(fn (Convenio $c) => $c->estadoDetallado())
            ->filter(fn ($d) => $d['responsable'] === 'Vinculación')
            ->countBy('texto');

        return [
            'te_tocan' => $teTocan,
            'fisicos_vencidos' => DocumentoFisico::deTramiteVivo()->vencidos()->count(),
            'por_vencer' => Convenio::porVencer(60)->count(),
        ];
    }

    public static function totalVinculacion(array $c): int
    {
        return $c['te_tocan']->sum() + $c['fisicos_vencidos'] + $c['por_vencer'];
    }

    private static function vinculacion(User $user): array
    {
        $c = self::conteosVinculacion();
        $items = $c['te_tocan']
            ->map(fn (int $n, string $texto) => $texto === 'Convenio activo (vigente)'
                // Formalizado y vigente, pero con juegos por entregar o archivar: es trabajo de papeles
                ? self::item('advertencia', "{$n} convenio(s) formalizados con juegos por entregar o archivar.", route('admin.documentos-fisicos', ['vista' => 'casa'], false), $n)
                : self::item('advertencia', "{$n} convenio(s) en «{$texto}»: te toca atenderlos.", route('admin.lista-convenios', absolute: false), $n))
            ->values()->all();

        if ($c['fisicos_vencidos'] > 0) {
            $items[] = self::item('error', "{$c['fisicos_vencidos']} documento(s) físico(s) fuera del plazo acordado: da seguimiento.", route('admin.documentos-fisicos', ['vista' => 'vencidos'], false), $c['fisicos_vencidos']);
        }
        if ($c['por_vencer'] > 0) {
            $items[] = self::item('info', "{$c['por_vencer']} convenio(s) vencen en los próximos 60 días: avisa a la organización para que renueve.", route('admin.lista-convenios', absolute: false), $c['por_vencer']);
        }

        if ($user->hasRole(Rol::Administrador->value)) {
            $sinCoordinacion = Carrera::all()->filter(fn (Carrera $x) => $x->coordinadores()->isEmpty())->count();
            if ($sinCoordinacion > 0) {
                $items[] = self::item('info', "{$sinCoordinacion} carrera(s) sin coordinación en la plataforma: crea su cuenta en Usuarios.", route('admin.lista-usuarios', absolute: false), $sinCoordinacion);
            }
        }

        return $items;
    }

    /** $n: cuántas cosas representa el aviso (para que el saludo del panel sume lo mismo que la campana). */
    private static function item(string $tipo, string $mensaje, string $url, int $n = 1): array
    {
        return compact('tipo', 'mensaje', 'url', 'n');
    }

    /** Total de pendientes del usuario: lo que dice el saludo de su panel. */
    public static function total(User $user): int
    {
        return self::sumar(self::de($user));
    }

    /**
     * Suma de una lista ya calculada con de(): así el panel no la calcula dos veces (saludo y tarjeta).
     *
     * @param  array<int, array{n?: int}>  $pendientes
     */
    public static function sumar(array $pendientes): int
    {
        return (int) array_sum(array_column($pendientes, 'n'));
    }
}
