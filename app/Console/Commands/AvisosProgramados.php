<?php

namespace App\Console\Commands;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EstadoDocumentoFisico;
use App\Enums\EtapaConvenio;
use App\Http\Controllers\Admin\DocumentoFisicoController;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\ConvenioSeguimiento;
use App\Models\DocumentoFisico;
use App\Models\Practica;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Services\Avisos;
use Illuminate\Console\Command;

class AvisosProgramados extends Command
{
    protected $signature = 'practicas:avisos-programados';

    protected $description = 'Avisos diarios: convenios por vencer, esperas excedidas, documentos físicos, dictámenes pendientes y prácticas por terminar';

    public function handle(): int
    {
        $total = $this->conveniosPorVencer() + $this->esperasExcedidas() + $this->documentosFisicos()
            + $this->juegosConEmpresa() + $this->dictamenesPendientes() + $this->practicasPorTerminar();

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

            foreach (Avisos::vinculacionDe($c) as $admin) {
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

                foreach (Avisos::vinculacionDe($s->convenio) as $admin) {
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

    /**
     * Papeles fuera de la oficina que regresan mañana o ya debían haber regresado.
     * Un aviso por convenio y por fecha de regreso, para no saturar la campana.
     */
    private function documentosFisicos(): int
    {
        $enviados = 0;

        DocumentoFisico::fuera()->with('convenio.empresa')
            ->whereNotNull('fecha_compromiso')
            ->whereDate('fecha_compromiso', '<=', today()->addWeekday()->toDateString())
            ->get()
            ->filter(fn (DocumentoFisico $d) => $d->convenio !== null)
            ->groupBy(fn (DocumentoFisico $d) => $d->convenio_id . '-' . $d->fecha_compromiso->toDateString())
            ->each(function ($docs) use (&$enviados) {
                $primero = $docs->first();
                $convenio = $primero->convenio;
                $vencido = $primero->vencido();
                $clave = 'fisico-' . $convenio->id . '-' . $primero->fecha_compromiso->toDateString() . ($vencido ? '-v' : '-p');
                $lista = $docs->map(fn (DocumentoFisico $d) => $d->nombre() . ' (' . mb_strtolower($d->estado->label()) . ')')->join(', ', ' y ');
                $cuando = $vencido
                    ? 'debían regresar el ' . $primero->fecha_compromiso->format('d/m/Y')
                    : 'deben regresar el ' . $primero->fecha_compromiso->format('d/m/Y');

                foreach (Avisos::vinculacionDe($convenio) as $admin) {
                    if (! $this->yaAvisado($admin, $clave)) {
                        Avisos::enviar($admin, new AvisoOperativo(
                            mensaje: "{$convenio->empresa->razon_social}: {$lista} {$cuando}. Confirma dónde están.",
                            url: route('admin.documentos-fisicos', ['vista' => $vencido ? 'vencidos' : 'fuera'], false),
                            detalle: 'Documentos físicos',
                            clave: $clave,
                        ));
                        $enviados++;
                    }
                }
            });

        return $enviados;
    }

    /** La empresa tiene juegos originales por firmar y su fecha de entrega está a 2 días o ya pasó. */
    private function juegosConEmpresa(): int
    {
        $enviados = 0;

        DocumentoFisico::where('estado', EstadoDocumentoFisico::ConEmpresa->value)
            ->with('convenio.empresa.user')
            ->whereDate('fecha_compromiso', '<=', today()->addWeekdays(2)->toDateString())
            ->get()
            ->filter(fn (DocumentoFisico $d) => $d->convenio?->empresa?->user !== null)
            ->groupBy('convenio_id')
            ->each(function ($juegos) use (&$enviados) {
                $convenio = $juegos->first()->convenio;
                $user = $convenio->empresa->user;
                $clave = 'juegos-' . $convenio->id . '-' . $juegos->min('fecha_compromiso')->toDateString();

                if ($user->activo && ! $this->yaAvisado($user, $clave)) {
                    Avisos::enviar($user, new AvisoOperativo(
                        mensaje: DocumentoFisicoController::mensajeEmpresa($juegos),
                        url: route('empresa.convenio', absolute: false),
                        detalle: 'Convenio con el CUCSH',
                        correo: true,
                        asunto: 'Recordatorio: devuelve los juegos firmados de tu convenio',
                        clave: $clave,
                    ));
                    $enviados++;
                }
            });

        return $enviados;
    }

    /** Coordinaciones con un dictamen de pertinencia pendiente desde hace 5+ días hábiles (una vez por semana). */
    private function dictamenesPendientes(): int
    {
        $enviados = 0;

        ConvenioCarrera::where('dictamen', Dictamen::Pendiente->value)
            ->whereHas('convenio', fn ($c) => $c->where('estado', ConvenioEstado::EnValidacionAcademica->value))
            ->with(['carrera', 'convenio.empresa', 'convenio.seguimientos'])
            ->get()
            ->each(function (ConvenioCarrera $d) use (&$enviados) {
                $turno = $d->convenio->seguimientos->where('etapa', EtapaConvenio::TurnoAcademico)->sortByDesc('id')->first();
                $dias = $turno ? $turno->diasHabiles() : 0;

                if ($dias < 5) {
                    return;
                }

                $clave = "dictamen-{$d->id}-" . today()->format('o-W');

                foreach (Avisos::coordinadores((int) $d->carrera_id) as $coord) {
                    if (! $this->yaAvisado($coord, $clave)) {
                        Avisos::enviar($coord, new AvisoOperativo(
                            mensaje: "Recordatorio: el dictamen de pertinencia de {$d->carrera->carrera_clave} para {$d->convenio->empresa->razon_social} lleva {$dias} días hábiles pendiente.",
                            url: route('coordinacion.lista-dictamenes', absolute: false),
                            detalle: $d->convenio->empresa->razon_social,
                            correo: true,
                            asunto: 'Dictamen de pertinencia pendiente',
                            clave: $clave,
                        ));
                        $enviados++;
                    }
                }
            });

        return $enviados;
    }

    private function yaAvisado(User $user, string $clave): bool
    {
        return $user->notifications()->where('data->clave', $clave)->exists();
    }
}