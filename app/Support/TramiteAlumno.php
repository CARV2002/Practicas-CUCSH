<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Models\Alumno;
use App\Models\Postulacion;
use App\Models\Practica;

/**
 * Dónde va el alumno en su trámite y qué debe hacer, en UN solo lugar.
 *
 * Lo usan el panel (pasos, «Tu siguiente paso», Avisos), «Mis documentos» (guía de pasos)
 * y la campana (pendientes), para que todas las pantallas digan lo mismo.
 */
final class TramiteAlumno
{
    /** Pasos del trámite: [título, ayuda]. */
    public const PASOS = [
        ['Entrega tu kardex e IMSS', 'A tu coordinación de carrera'],
        ['Revisión de tu coordinación', 'Valida ambos documentos'],
        ['Postúlate a una vacante', 'Una organización a la vez'],
        ['Cartas de presentación y aceptación', 'Preséntate en la organización'],
        ['Práctica y reportes', 'Reporta tus horas'],
        ['Cierre y acreditación', 'Carta de término y SIIAU'],
    ];

    public readonly ?Practica $practica;        // activa, en cierre o acreditada

    public readonly ?Practica $ultimaBaja;      // baja más reciente sin un trámite nuevo después

    public readonly ?Postulacion $postulacion;  // pendiente o en trámite (una organización a la vez)

    public readonly string $expediente;

    public readonly ?string $bloqueo;

    private function __construct(public readonly Alumno $alumno)
    {
        $practicas = Practica::whereHas('postulacion', fn ($q) => $q->where('alumno_id', $alumno->id))
            ->with('postulacion.programa.convenio.empresa')
            ->latest('id')
            ->get();

        $this->practica = $practicas->first(fn (Practica $p) => $p->estado !== PracticaEstado::Cancelada);

        $this->postulacion = $alumno->postulaciones()
            ->with('programa.convenio.empresa')
            ->whereIn('estado', PostulacionEstado::valores([PostulacionEstado::Pendiente, ...PostulacionEstado::enTramite()]))
            ->latest('id')
            ->first();

        $baja = $practicas->first(fn (Practica $p) => $p->estado === PracticaEstado::Cancelada);
        $this->ultimaBaja = $baja && ! $this->practica && ! $this->postulacion ? $baja : null;

        $this->expediente = $alumno->estadoExpediente();
        $this->bloqueo = $alumno->motivoBloqueo();
    }

    public static function de(Alumno $alumno): self
    {
        return new self($alumno);
    }

    /** Paso actual (1 a 6; 7 = todo completo). */
    public function paso(): int
    {
        return match (true) {
            $this->practica?->estado === PracticaEstado::Acreditada => 7,
            $this->practica?->estado === PracticaEstado::EnCierre => 6,
            $this->practica !== null => 5,
            $this->postulacion !== null => 4,
            $this->bloqueo !== null, $this->expediente === 'validado' => 3,
            in_array($this->expediente, ['en_revision', 'devuelto'], true) => 2,
            default => 1,
        };
    }

    /** ¿El paso actual requiere que el alumno corrija algo? (se pinta en rojo) */
    public function pasoConAlerta(): bool
    {
        return match ($this->paso()) {
            1 => $this->expediente === 'imss_vencido',
            2 => $this->expediente === 'devuelto',
            3 => $this->bloqueo !== null,
            default => false,
        };
    }

    /** @return list<array{titulo:string, ayuda:string, estado:string}> estado: hecho | actual | alerta | pendiente */
    public function pasos(): array
    {
        $actual = $this->paso();

        return collect(self::PASOS)->map(function (array $p, int $i) use ($actual) {
            $n = $i + 1;
            [$titulo, $ayuda] = $p;

            if ($n === 2 && $this->expediente === 'devuelto' && $actual === 2) {
                [$titulo, $ayuda] = ['Corrige lo que te pidan', 'Tu coordinación te devolvió un documento'];
            }
            if ($n === 3 && $this->bloqueo !== null && $actual === 3) {
                $ayuda = 'Bloqueado: acude a tu coordinación';
            }

            return [
                'titulo' => $titulo,
                'ayuda' => $ayuda,
                'estado' => match (true) {
                    $n < $actual => 'hecho',
                    $n === $actual => $this->pasoConAlerta() ? 'alerta' : 'actual',
                    default => 'pendiente',
                },
            ];
        })->all();
    }

    /**
     * La situación del alumno en un aviso claro (panel, Avisos y campana).
     *
     * @return array{tipo:string, titulo:string, texto:string, accion:?array{0:string,1:string}}
     */
    public function situacion(): array
    {
        $organizacion = fn (?Postulacion $po) => $po?->programa?->convenio?->empresa->razon_social ?? 'el área receptora';
        $docs = route('alumno.mis-documentos', absolute: false);

        if ($this->practica) {
            $pr = $this->practica;
            $titulo = $pr->postulacion->programa->titulo;

            return match ($pr->estado) {
                PracticaEstado::Acreditada => $this->aviso('exito', 'Práctica acreditada',
                    "Tu práctica en «{$titulo}» quedó acreditada. ¡Felicidades, cerraste tu trámite!", null),
                PracticaEstado::EnCierre => $this->aviso('info', 'Tu carta de término fue validada',
                    'Tu coordinación enviará tu oficio a Control Escolar para capturar tu acreditación en SIIAU. No tienes que hacer nada más.', null),
                default => $this->aviso('info', 'Tu práctica está en curso',
                    "Llevas {$pr->horas_acumuladas} de {$pr->horas_requeridas} horas validadas en «{$titulo}». Entrega tus reportes firmados a tiempo para que cuenten.",
                    ['Ver mis prácticas', route('alumno.mis-practicas', absolute: false)]),
            };
        }

        if ($po = $this->postulacion) {
            $titulo = $po->programa->titulo;

            return match ($po->estado) {
                PostulacionEstado::CartaAceptacionRecibida => $this->aviso('info', 'Tu carta de aceptación quedó registrada',
                    "Tu coordinación activará tu práctica en «{$titulo}». Te avisaremos cuando empiece y puedas reportar tus horas.",
                    ['Ver mis documentos', $docs]),
                PostulacionEstado::CartaPresentacionEmitida => $this->aviso('advertencia', 'Preséntate en la organización',
                    "Tu carta de presentación para «{$titulo}» ya está generada: descárgala, preséntala en {$organizacion($po)} y entrega a tu coordinación la carta de aceptación que te den.",
                    ['Descargar mi carta', $docs]),
                default => $this->aviso('info', "Ya te postulaste a «{$titulo}»",
                    'Tu coordinación emitirá tu carta de presentación. Te avisaremos cuando esté lista.',
                    ['Ver mis documentos', $docs]),
            };
        }

        if ($this->bloqueo !== null) {
            return $this->aviso('error', 'No puedes postularte por ahora', $this->alumno->textoBloqueo(), ['Ver mis documentos', $docs]);
        }

        if ($baja = $this->ultimaBaja) {
            $horas = $baja->horasQueConserva();
            $restantes = Alumno::LIMITE_BAJAS - $this->alumno->bajasVigentes()->count();
            $resolucion = match (true) {
                $horas > 0 => "Conservas {$horas} horas validadas: en tu siguiente plaza solo cubrirás las restantes.",
                $baja->tipo_baja === 'sancion' => 'Por ser una baja por sanción, tus horas no se conservan.',
                default => 'Como no tenías reportes validados, tus horas no se conservan.',
            };

            if ($this->expediente === 'validado') {
                return $this->aviso('advertencia', 'Tu práctica se dio de baja',
                    "{$resolucion} Tu expediente sigue validado: ya puedes postularte a otra vacante. Te queda {$restantes} oportunidad antes del límite de bajas.",
                    ['Buscar otra vacante', route('alumno.oferta-programas', absolute: false)]);
            }
        }

        return match ($this->expediente) {
            'validado' => $this->aviso('exito', 'Ya puedes postularte',
                'Tu coordinación validó tu kardex y tu constancia IMSS. Elige una vacante de tu carrera y postúlate.',
                ['Ver vacantes', route('alumno.oferta-programas', absolute: false)]),
            'en_revision' => $this->aviso('info', 'Tu expediente está en revisión',
                'Tu coordinación está revisando juntos tu kardex y tu constancia IMSS. Si están correctos, te avisaremos y podrás postularte.',
                ['Ver mis documentos', $docs]),
            'devuelto' => $this->aviso('error', 'Corrige tu expediente',
                $this->textoDevuelto(), ['Corregir en mis documentos', $docs]),
            'imss_vencido' => $this->aviso('error', 'Tu constancia IMSS venció',
                'Sube una constancia vigente'.($this->alumno->documentoVigente('kardex') ? '' : ' junto con tu kardex').' en Mis documentos; cuando tu coordinación la valide, podrás postularte.',
                ['Subir mi constancia', $docs]),
            default => $this->aviso('advertencia', 'Entrega tu kardex y tu constancia IMSS',
                'Entrégalos a tu coordinación en Mis documentos. Cuando los valide, podrás postularte a una vacante.',
                ['Entregar mis documentos', $docs]),
        };
    }

    /** Qué documento devolvió la coordinación y por qué. */
    private function textoDevuelto(): string
    {
        $devueltos = collect(['kardex' => 'tu kardex', 'constancia_imss' => 'tu constancia IMSS'])
            ->map(fn (string $nombre, string $col) => [$nombre, $this->alumno->documentoVigente($col)])
            ->filter(fn ($d) => $d[1]?->getCustomProperty('estado') === 'rechazada');

        $motivos = $devueltos->map(fn ($d) => $d[1]->getCustomProperty('motivo'))->filter()->map(fn ($m) => rtrim($m, '.'));

        return 'Tu coordinación te devolvió '.$devueltos->map(fn ($d) => $d[0])->implode(' y ').'.'
            .($motivos->isNotEmpty() ? ' Motivo: '.$motivos->implode('; ').'.' : '')
            .' Sube el documento corregido en Mis documentos para que lo revise de nuevo y puedas postularte.';
    }

    private function aviso(string $tipo, string $titulo, string $texto, ?array $accion): array
    {
        return compact('tipo', 'titulo', 'texto', 'accion');
    }
}
