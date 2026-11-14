<?php

namespace App\Console\Commands;

use App\Enums\ConvenioEstado;
use App\Models\Convenio;
use App\Models\ConvenioSeguimiento;
use App\Models\Practica;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use Illuminate\Console\Command;

class AvisosProgramados extends Command
{
    protected $signature = 'practicas:avisos-programados';

    protected $description = 'Avisos diarios: convenios por vencer, esperas excedidas y prácticas por terminar';

    public function handle(): int
    {
        $total = $this->conveniosPorVencer() + $this->esperasExcedidas() + $this->practicasPorTerminar();

        $this->info("Avisos enviados: {$total}");

        return self::SUCCESS;
    }

    private function conveniosPorVencer(): int
    {
        $enviados = 0;

        Convenio::porVencer(60)->with('empresa.user')->get()->each(function (Convenio $c) use (&$enviados) {
            $dias = (int) now()->startOfDay()->diffInDays($c->fecha_fin_vigencia, false);
            $clave = 'vence-'.($dias <= 30 ? 30 : 60)."-{$c->id}";
            $texto = "El convenio {$c->folio} de {$c->empresa->razon_social} vence el {$c->fecha_fin_vigencia->format('d/m/Y')} (en {$dias} días).";

            $renueva = $c->empresa->convenios()
                ->where('id', '!=', $c->id)
                ->whereNotIn('estado', [ConvenioEstado::Formalizado->value, ConvenioEstado::Rechazado->value])
                ->exists();

            $empresa = $c->empresa->user;

            if ($empresa && $empresa->activo && ! $renueva && ! $this->yaAvisado($empresa, $clave)) {
                Avisos::enviar($empresa, new AvisoOperativo(
                    mensaje: "Tu convenio vence el {$c->fecha_fin_vigencia->format('d/m/Y')} (en {$dias} días). Solicita la renovación para no suspender tus programas.",
                    url: route('empresa.convenio', absolute: false),
                    detalle: $c->folio,
                    correo: true,
                    asunto: 'Tu convenio con el CUCSH está por vencer',
                    clave: $clave,
                ));
                $enviados++;
            }

            foreach (Avisos::administradoresDe($c) as $admin) {
                if (! $this->yaAvisado($admin, $clave)) {
                    Avisos::enviar($admin, new AvisoOperativo(
                        mensaje: $texto,
                        url: route('admin.convenios.show', $c, false),
                        detalle: $c->empresa->razon_social,
                        clave: $clave,
                    ));
                    $enviados++;
                }
            }
        });

        return $enviados;
    }

    private function esperasExcedidas(): int
    {
        $enviados = 0;

        ConvenioSeguimiento::with('convenio.empresa')->abiertos()->get()
            ->filter(fn (ConvenioSeguimiento $s) => $s->convenio !== null && $s->excedeEspera())
            ->each(function (ConvenioSeguimiento $s) use (&$enviados) {
                $clave = "espera-{$s->id}";
                $max = $s->etapa->diasHabilesDocumentados()[1];

                foreach (Avisos::administradoresDe($s->convenio) as $admin) {
                    if (! $this->yaAvisado($admin, $clave)) {
                        Avisos::enviar($admin, new AvisoOperativo(
                            mensaje: "{$s->convenio->empresa->razon_social}: «{$s->etapa->label()}» lleva {$s->diasHabiles()} días hábiles (máximo documentado: {$max}). Pendiente de: {$s->etapa->responsable()}.",
                            url: route('admin.convenios.show', $s->convenio, false),
                            detalle: $s->convenio->empresa->razon_social,
                            clave: $clave,
                        ));
                        $enviados++;
                    }
                }
            });

        return $enviados;
    }

    private function practicasPorTerminar(): int
    {
        $enviados = 0;

        Practica::activas()->conHorasAcumuladas()
            ->with('postulacion.alumno.user', 'postulacion.programa')
            ->whereBetween('fecha_fin', [today()->toDateString(), today()->addDays(30)->toDateString()])
            ->get()
            ->reject(fn (Practica $p) => $p->horasCompletas())
            ->each(function (Practica $p) use (&$enviados) {
                $user = Avisos::alumnoDe($p);
                $clave = "termina-30-{$p->id}";

                if ($user && $user->activo && ! $this->yaAvisado($user, $clave)) {
                    $faltan = $p->horas_requeridas - (int) $p->horas_acumuladas;

                    Avisos::enviar($user, new AvisoOperativo(
                        mensaje: "Tu práctica termina el {$p->fecha_fin->format('d/m/Y')} y te faltan {$faltan} horas validadas. Entrega tus reportes a tiempo.",
                        url: route('alumno.mis-practicas', absolute: false),
                        detalle: $p->postulacion->programa->titulo,
                        correo: true,
                        asunto: 'Tu práctica está por terminar',
                        clave: $clave,
                    ));
                    $enviados++;
                }
            });

        return $enviados;
    }

    private function yaAvisado(User $user, string $clave): bool
    {
        return $user->notifications()->where('data->clave', $clave)->exists();
    }
}