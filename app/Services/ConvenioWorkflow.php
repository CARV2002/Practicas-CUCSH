<?php

namespace App\Services;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\Rol;
use App\Enums\SeguimientoEstado;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\ConvenioSeguimiento;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use App\Notifications\ConvenioActualizado;
use App\Notifications\NuevaSolicitudConvenio;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * El proceso real de Vinculación como máquina de estados.
 * Cada método = un paso del documento; abre/cierra etapas en la bitácora (convenio_seguimientos)
 * y bloquea la fila del convenio para que dos personas no avancen el mismo trámite a la vez.
 *
 * Quién usa el sistema: Lorena (rol administrador) ejecuta los pasos y CAPTURA lo que resuelven las divisiones,
 * Jurídica y Rectoría (que no entran al sistema). La empresa recibe un aviso en cada cambio de etapa.
 *
 *   solicitar → enviarAValidacionAcademica → registrarDictamenCarrera (por carrera)
 *   → turnarAJuridica → registrarDictamenJuridico → registrarFirma (empresa, Rectoría) → formalizar
 */
class ConvenioWorkflow
{
    /** Fase 1.1 — La empresa envía su propuesta (sustituye al Google Form). */
    public function solicitar(Empresa $empresa, array $datos, array $carreraIds): Convenio
    {
        $carreraIds = array_values(array_unique(array_map('intval', $carreraIds)));

        if ($carreraIds === []) {
            $this->fallar('Selecciona al menos una carrera.');
        }

        if (Carrera::whereIn('id', $carreraIds)->count() !== count($carreraIds)) {
            $this->fallar('Alguna de las carreras seleccionadas no existe.');
        }

        return DB::transaction(function () use ($empresa, $datos, $carreraIds) {
            $convenio = Convenio::create([
                ...Arr::only($datos, [
                    'objetivos', 'actividades_propuestas', 'plazas_estimadas',
                    'medio_conocimiento', 'experiencia_previa',
                ]),
                'empresa_id' => $empresa->id,
                'estado' => ConvenioEstado::Solicitado,
                'fecha_recepcion' => today(),
            ]);

            // Un dictamen "pendiente" por cada carrera solicitada
            $convenio->dictamenes()->createMany(array_map(
                fn (int $id) => ['carrera_id' => $id, 'dictamen' => Dictamen::Pendiente],
                $carreraIds
            ));

            $this->abrirEtapa($convenio, EtapaConvenio::RevisionDocumentos, null);
            $this->avisarAdministradores($convenio);

            return $convenio;
        });
    }

    /** Fases 1.1 → 1.2/2.1 — Documentos en orden: se turna a las carreras (sustituye al Oficio de Turno). */
    public function enviarAValidacionAcademica(Convenio $convenio, Personal $por): Convenio
    {
        return DB::transaction(function () use ($convenio, $por) {
            $convenio = $this->bloquear($convenio);
            $this->exigirEstado($convenio, ConvenioEstado::Solicitado);

            if (! $convenio->empresa->expedienteCompleto()) {
                $this->fallar('El expediente de la empresa está incompleto o tiene documentos sin validar.');
            }

            $convenio->update([
                'estado' => ConvenioEstado::EnValidacionAcademica,
                'gestionado_por' => $convenio->gestionado_por ?? $por->id,
            ]);

            $this->cerrarEtapa($convenio, EtapaConvenio::RevisionDocumentos, SeguimientoEstado::Aprobado, $por);
            $this->abrirEtapa($convenio, EtapaConvenio::TurnoAcademico, $por, 'Turnado a las carreras solicitadas.');
            $this->avisarEmpresa($convenio, 'Tu documentación fue aceptada. Tu solicitud pasó a la validación académica de las carreras.');

            return $convenio;
        });
    }

    /** Fase 2.2 — El coordinador emite el Dictamen de Pertinencia de SU carrera. */
    public function registrarDictamenCarrera(
        ConvenioCarrera $dictamen,
        Dictamen $resultado,
        ?string $observaciones,
        Personal $por,
    ): Convenio {
        return DB::transaction(function () use ($dictamen, $resultado, $observaciones, $por) {
            // Orden de bloqueo fijo: convenio -> dictamen
            $convenio = $this->bloquear(Convenio::findOrFail($dictamen->convenio_id));
            $dictamen = ConvenioCarrera::lockForUpdate()->findOrFail($dictamen->id);

            $this->exigirEstado($convenio, ConvenioEstado::EnValidacionAcademica);

            if (! $por->puedeVerCarrera((int) $dictamen->carrera_id)) {
                $this->fallar('No tienes alcance para dictaminar esta carrera.');
            }

            if ($resultado === Dictamen::Pendiente) {
                $this->fallar('El dictamen debe ser aprobatorio o negativo.');
            }

            if ($resultado === Dictamen::Negativo && blank($observaciones)) {
                $this->fallar('Un dictamen negativo debe explicar el motivo (observaciones).');
            }

            $dictamen->update([
                'dictamen' => $resultado,
                'observaciones' => $observaciones,
                'fecha_dictamen' => today(),
                'dictaminado_por' => $por->id,
            ]);

            if ($convenio->dictamenesPendientes() > 0) {
                return $convenio;   // faltan carreras por responder
            }

            // Fase 3.1 — "Si todas rechazaron, el proceso muere"
            if (! $convenio->tieneAprobatorio()) {
                $this->rechazar($convenio, $por, 'Todas las carreras emitieron dictamen negativo.');

                return $convenio->refresh();
            }

            // Hay al menos una carrera aprobada: Lorena arma el borrador solo con ellas
            $this->cerrarEtapa($convenio, EtapaConvenio::TurnoAcademico, SeguimientoEstado::Aprobado, $por);
            $this->abrirEtapa(
                $convenio,
                EtapaConvenio::RedaccionBorrador,
                null,
                'Dictámenes recibidos. Redactar el borrador solo con las carreras aprobatorias.'
            );
            $this->avisarEmpresa($convenio, 'Las carreras emitieron su dictamen. Estamos redactando tu convenio.');

            return $convenio;
        });
    }

    /** Fase 3.2 — El borrador (y cada versión corregida) se turna a la Unidad Jurídica. */
    public function turnarAJuridica(Convenio $convenio, Personal $por): Convenio
    {
        return DB::transaction(function () use ($convenio, $por) {
            $convenio = $this->bloquear($convenio);
            $this->exigirEstado($convenio, ConvenioEstado::EnValidacionAcademica, ConvenioEstado::EnRevisionJuridica);

            if ($this->etapaAbierta($convenio, EtapaConvenio::RedaccionBorrador) === null) {
                $this->fallar('No hay un borrador pendiente de turnar.');
            }

            if (! $convenio->puedePasarAJuridica()) {
                $this->fallar('Faltan dictámenes por carrera o ninguna resultó aprobatoria.');
            }

            if (! $convenio->media()->where('collection_name', 'borrador_convenio')->exists()) {
                $this->fallar('Adjunta el borrador del convenio antes de turnarlo.');
            }

            $this->cerrarEtapa($convenio, EtapaConvenio::RedaccionBorrador, SeguimientoEstado::Aprobado, $por);
            $convenio->update(['estado' => ConvenioEstado::EnRevisionJuridica]);
            $this->abrirEtapa($convenio, EtapaConvenio::DictamenJuridico, $por);
            $this->avisarEmpresa($convenio, 'Tu convenio está en revisión de la Unidad Jurídica.');

            return $convenio;
        });
    }

    /**
     * Fase 3.2 — Dictamen de Viabilidad Jurídica.
     * Aprobado → firmas · Observado → regresa a redacción · Rechazado → se cierra el trámite.
     */
    public function registrarDictamenJuridico(
        Convenio $convenio,
        SeguimientoEstado $resultado,
        ?string $comentario,
        Personal $por,
    ): Convenio {
        return DB::transaction(function () use ($convenio, $resultado, $comentario, $por) {
            $convenio = $this->bloquear($convenio);
            $this->exigirEstado($convenio, ConvenioEstado::EnRevisionJuridica);

            if ($this->etapaAbierta($convenio, EtapaConvenio::DictamenJuridico) === null) {
                $this->fallar('No hay un dictamen jurídico pendiente.');
            }

            if ($resultado === SeguimientoEstado::Pendiente) {
                $this->fallar('El dictamen debe ser aprobado, observado o rechazado.');
            }

            if ($resultado !== SeguimientoEstado::Aprobado && blank($comentario)) {
                $this->fallar('Una observación o rechazo debe explicar el motivo.');
            }

            match ($resultado) {
                SeguimientoEstado::Aprobado => $this->aprobarJuridica($convenio, $por, $comentario),
                SeguimientoEstado::Observado => $this->observarJuridica($convenio, $por, $comentario),
                SeguimientoEstado::Rechazado => $this->rechazar($convenio, $por, $comentario),
            };

            return $convenio->refresh();
        });
    }

    /** Fase 4.1 / 4.2 — Firma de la empresa (3 juegos) y firma de Rectoría. */
    public function registrarFirma(Convenio $convenio, EtapaConvenio $etapa, Personal $por, ?string $comentario = null): Convenio
    {
        if (! in_array($etapa, [EtapaConvenio::FirmaEmpresa, EtapaConvenio::FirmaRectoria], true)) {
            $this->fallar('Solo se registran firmas de la empresa o de Rectoría.');
        }

        return DB::transaction(function () use ($convenio, $etapa, $por, $comentario) {
            $convenio = $this->bloquear($convenio);
            $this->exigirEstado($convenio, ConvenioEstado::EnFirmas);

            if ($this->etapaAbierta($convenio, $etapa) === null) {
                $this->fallar("La etapa «{$etapa->label()}» no está pendiente (las firmas van en orden).");
            }

            $this->cerrarEtapa($convenio, $etapa, SeguimientoEstado::Aprobado, $por, $comentario);

            $siguiente = $etapa === EtapaConvenio::FirmaEmpresa
                ? EtapaConvenio::FirmaRectoria
                : EtapaConvenio::ArchivoDistribucion;

            $this->abrirEtapa($convenio, $siguiente, $por);
            $this->avisarEmpresa($convenio, $etapa === EtapaConvenio::FirmaEmpresa
                ? 'Recibimos tu firma. El convenio pasa a la firma de Rectoría.'
                : 'Rectoría firmó el convenio. Estamos registrándolo y archivándolo.');

            return $convenio;
        });
    }

    /** Fase 4.3 — Registro y archivo: el convenio nace legalmente. */
    public function formalizar(
        Convenio $convenio,
        Personal $por,
        string $folio,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?string $comentario = null,
    ): Convenio {
        return DB::transaction(function () use ($convenio, $por, $folio, $inicio, $fin, $comentario) {
            $convenio = $this->bloquear($convenio);
            $this->exigirEstado($convenio, ConvenioEstado::EnFirmas);

            if ($this->etapaAbierta($convenio, EtapaConvenio::ArchivoDistribucion) === null) {
                $this->fallar('Aún faltan firmas por registrar.');
            }

            if (! $convenio->media()->where('collection_name', 'convenio_firmado')->exists()) {
                $this->fallar('Adjunta el escaneo del convenio firmado.');
            }

            if ($fin->lessThanOrEqualTo($inicio)) {
                $this->fallar('La vigencia debe terminar después de iniciar.');
            }

            if (Convenio::where('folio', $folio)->where('id', '!=', $convenio->id)->exists()) {
                $this->fallar("El folio {$folio} ya está en uso.");
            }

            $convenio->update([
                'folio' => $folio,
                'fecha_inicio_vigencia' => $inicio,
                'fecha_fin_vigencia' => $fin,
                'estado' => ConvenioEstado::Formalizado,
            ]);

            $this->cerrarEtapa($convenio, EtapaConvenio::ArchivoDistribucion, SeguimientoEstado::Aprobado, $por, $comentario);
            $this->avisarEmpresa($convenio, "Tu convenio quedó formalizado (folio {$folio}), vigente del {$inicio->format('d/m/Y')} al {$fin->format('d/m/Y')}. Ya puedes publicar tus ofertas.");

            return $convenio;
        });
    }

    /** Cierra el trámite (Vinculación, Jurídica, o "todas las carreras dijeron no"). */
    public function rechazar(Convenio $convenio, ?Personal $por, string $motivo): Convenio
    {
        return DB::transaction(function () use ($convenio, $por, $motivo) {
            $convenio = $this->bloquear($convenio);

            if ($convenio->estado->esFinal()) {
                $this->fallar('El convenio ya está cerrado.');
            }

            $actual = $this->etapaDe($convenio->estado);

            $cerradas = ConvenioSeguimiento::where('convenio_id', $convenio->id)
                ->where('estado', SeguimientoEstado::Pendiente->value)
                ->update([
                    'estado' => SeguimientoEstado::Rechazado->value,
                    'fecha_resolucion' => today()->toDateString(),
                    'registrado_por' => $por?->id,
                    'comentario' => $motivo,
                ]);

            if ($cerradas === 0) {
                ConvenioSeguimiento::create([
                    'convenio_id' => $convenio->id,
                    'registrado_por' => $por?->id,
                    'etapa' => $actual,
                    'estado' => SeguimientoEstado::Rechazado,
                    'comentario' => $motivo,
                    'fecha_registro' => today(),
                    'fecha_resolucion' => today(),
                ]);
            }

            $convenio->update(['estado' => ConvenioEstado::Rechazado]);
            $this->avisarEmpresa($convenio, "Tu solicitud de convenio fue rechazada: {$motivo}");

            return $convenio;
        });
    }

    /** Los convenios suelen durar de 2 a 5 años: solo se AVISA, no se bloquea. */
    public function advertenciaVigencia(CarbonInterface $inicio, CarbonInterface $fin): ?string
    {
        $anios = abs($inicio->diffInMonths($fin)) / 12;

        return ($anios < 2 || $anios > 5)
            ? sprintf('La vigencia es de %.1f años; lo habitual es de 2 a 5.', $anios)
            : null;
    }

    // ---- internos --------------------------------------------------------------------

    private function aprobarJuridica(Convenio $convenio, Personal $por, ?string $comentario): void
    {
        $this->cerrarEtapa($convenio, EtapaConvenio::DictamenJuridico, SeguimientoEstado::Aprobado, $por, $comentario);
        $convenio->update(['estado' => ConvenioEstado::EnFirmas]);
        $this->abrirEtapa($convenio, EtapaConvenio::FirmaEmpresa, $por, 'Imprimir 3 juegos y llamar a la empresa.');
        $this->avisarEmpresa($convenio, 'La Unidad Jurídica aprobó tu convenio. La Coordinación de Prácticas te contactará para entregar los 3 juegos originales y recabar tu firma.');
    }

    private function observarJuridica(Convenio $convenio, Personal $por, ?string $comentario): void
    {
        // Jurídica regresa el Word: Lorena corrige y vuelve a turnar. Queda el historial completo.
        $this->cerrarEtapa($convenio, EtapaConvenio::DictamenJuridico, SeguimientoEstado::Observado, $por, $comentario);
        $this->abrirEtapa($convenio, EtapaConvenio::RedaccionBorrador, null, 'Corregir el borrador según las observaciones de Jurídica.');
    }

    /**
     * Avisa a la empresa (que sí usa el sistema) cuando su convenio cambia de etapa.
     * Va en afterCommit: si el correo falla, el cambio de estado ya quedó guardado.
     */
    private function avisarEmpresa(Convenio $convenio, string $mensaje): void
    {
        DB::afterCommit(function () use ($convenio, $mensaje) {
            $convenio->loadMissing('empresa.user');

            $convenio->empresa?->user?->notify(new ConvenioActualizado($convenio, $mensaje));
        });
    }

    /** Avisa a Lorena (rol administrador) que llegó una solicitud nueva. */
    private function avisarAdministradores(Convenio $convenio): void
    {
        DB::afterCommit(function () use ($convenio) {
            Notification::send(
                User::role(Rol::Administrador->value)->get(),
                new NuevaSolicitudConvenio($convenio)
            );
        });
    }

    private function abrirEtapa(Convenio $convenio, EtapaConvenio $etapa, ?Personal $por, ?string $comentario = null): ConvenioSeguimiento
    {
        return ConvenioSeguimiento::create([
            'convenio_id' => $convenio->id,
            'registrado_por' => $por?->id,
            'etapa' => $etapa,
            'estado' => SeguimientoEstado::Pendiente,
            'comentario' => $comentario,
            'fecha_registro' => today(),
        ]);
    }

    private function cerrarEtapa(
        Convenio $convenio,
        EtapaConvenio $etapa,
        SeguimientoEstado $resultado,
        ?Personal $por,
        ?string $comentario = null,
    ): ConvenioSeguimiento {
        $abierta = $this->etapaAbierta($convenio, $etapa);

        if ($abierta === null) {
            return ConvenioSeguimiento::create([
                'convenio_id' => $convenio->id,
                'registrado_por' => $por?->id,
                'etapa' => $etapa,
                'estado' => $resultado,
                'comentario' => $comentario,
                'fecha_registro' => today(),
                'fecha_resolucion' => today(),
            ]);
        }

        $abierta->update([
            'estado' => $resultado,
            'fecha_resolucion' => today(),
            'registrado_por' => $por?->id ?? $abierta->registrado_por,
            'comentario' => $comentario ?? $abierta->comentario,
        ]);

        return $abierta;
    }

    private function etapaAbierta(Convenio $convenio, EtapaConvenio $etapa): ?ConvenioSeguimiento
    {
        return ConvenioSeguimiento::where('convenio_id', $convenio->id)
            ->where('etapa', $etapa->value)
            ->where('estado', SeguimientoEstado::Pendiente->value)
            ->latest('id')
            ->first();
    }

    private function etapaDe(ConvenioEstado $estado): EtapaConvenio
    {
        return match ($estado) {
            ConvenioEstado::Solicitado => EtapaConvenio::RevisionDocumentos,
            ConvenioEstado::EnValidacionAcademica => EtapaConvenio::TurnoAcademico,
            ConvenioEstado::EnRevisionJuridica => EtapaConvenio::DictamenJuridico,
            default => EtapaConvenio::FirmaEmpresa,
        };
    }

    private function bloquear(Convenio $convenio): Convenio
    {
        return Convenio::with('empresa')->lockForUpdate()->findOrFail($convenio->id);
    }

    private function exigirEstado(Convenio $convenio, ConvenioEstado ...$permitidos): void
    {
        if (! in_array($convenio->estado, $permitidos, true)) {
            $esperado = implode(' o ', array_map(fn (ConvenioEstado $e) => $e->label(), $permitidos));
            $this->fallar("El convenio está «{$convenio->estado->label()}»; este paso requiere: {$esperado}.");
        }
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['convenio' => $mensaje]);
    }
}
