<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PracticaEstado;
use App\Models\Alumno;
use App\Models\Practica;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use Illuminate\Support\Facades\DB;

/**
 * Baja de una práctica en curso, decidida por la coordinación.
 *
 * El tipo de baja sale del motivo y de los reportes ya validados (criterios de la Coordinación de Carrera):
 *  - parcial_acreditada: el alumno conserva sus horas validadas para su siguiente plaza.
 *  - sancion: las horas se invalidan.
 *  - sin reportes validados: no hay horas que conservar; empieza un trámite nuevo.
 *
 * Avisa al alumno (con lo que sigue para él) y a la organización.
 */
class DarBajaPractica
{
    /**
     * @return array{tipo: string, horas: int, bloqueado: bool}|null null si otra petición ya la dio de baja
     */
    public function __invoke(Practica $practica, string $motivo, string $detalle): ?array
    {
        $horas = $practica->horas_acumuladas;

        // Todo en una transacción con la fila bloqueada: la práctica y su postulación (vía PracticaObserver)
        // cambian juntas, y dos clics seguidos no registran la baja dos veces.
        $tipo = DB::transaction(function () use ($practica, $motivo, $detalle) {
            $bloqueada = Practica::lockForUpdate()->findOrFail($practica->id);
            if (! in_array($bloqueada->estado, PracticaEstado::activas(), true)) {
                return null;
            }

            $tipo = $bloqueada->resolverBaja($motivo);
            $bloqueada->update([
                'motivo_baja' => $motivo,
                'detalle_baja' => $detalle,
                'estado' => PracticaEstado::Cancelada,
                'tipo_baja' => $tipo,
                'conserva_horas' => $tipo === 'parcial_acreditada',
                'fecha_baja' => now(),
            ]);

            return $tipo;
        });

        if ($tipo === null) {
            return null;
        }

        $practica->refresh();
        $alumno = $practica->postulacion->alumno->fresh();

        $this->avisar($practica, $alumno, $tipo, $horas);

        return ['tipo' => $tipo, 'horas' => $horas, 'bloqueado' => $alumno->estaBloqueado()];
    }

    private function avisar(Practica $practica, Alumno $alumno, string $tipo, int $horas): void
    {
        $titulo = $practica->postulacion->programa->titulo;

        $decision = match ($tipo) {
            'parcial_acreditada' => "Conservas tus {$horas} horas validadas: en tu siguiente plaza solo cubrirás las horas restantes.",
            'sancion' => 'Por ser una baja por sanción, tus horas no se conservan.',
            default => 'Como no tenías reportes validados, tus horas no se conservan y empezarás un trámite nuevo.',
        };
        $siguiente = $alumno->estaBloqueado()
            ? ' '.$alumno->textoBloqueo()
            : ' Ya puedes postularte a otra vacante (te queda '.(Alumno::LIMITE_BAJAS - $alumno->bajasVigentes()->count()).' oportunidad antes del límite de bajas).';

        Avisos::enviar(Avisos::alumnoDe($practica), new AvisoOperativo(
            mensaje: "Tu práctica en «{$titulo}» se dio de baja ({$practica->motivoBajaTexto()}). {$decision}{$siguiente}",
            url: route('alumno.mis-documentos', absolute: false),
            correo: true,
            asunto: 'Tu práctica se dio de baja',
        ));

        Avisos::enviar(Avisos::empresaDe($practica), new AvisoOperativo(
            mensaje: "La práctica de {$alumno->user->name} en «{$titulo}» se dio de baja por la Coordinación ({$practica->motivoBajaTexto()}).",
            url: route('empresa.practicantes.show', $practica, false),
        ));
    }
}
