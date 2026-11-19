<?php

namespace App\Support;

use App\Enums\ConvenioEstado;
use App\Enums\PostulacionEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Models\Convenio;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Programa;

/**
 * «¿Ya se puede hacer esto? Si no, ¿por qué?» en un solo lugar.
 *
 * Cada método regresa null cuando la acción está permitida, o el motivo (en español, dirigido
 * al usuario) cuando no. Lo usan el menú (pestañas bloqueadas), las vistas (botones deshabilitados
 * con su explicación) y los controladores (para no confiar solo en la vista).
 */
class Requisitos
{
    /* --------------------------------- Empresa --------------------------------- */

    /** Proponer vacantes: requiere convenio formalizado y vigente. */
    public static function empresaProponerVacantes(Empresa $empresa): ?string
    {
        if ($empresa->convenios()->vigentes()->exists()) {
            return null;
        }

        return $empresa->convenios()->whereNotIn('estado', [ConvenioEstado::Formalizado->value, ConvenioEstado::Rechazado->value])->exists()
            ? 'Tu convenio sigue en trámite: podrás proponer vacantes cuando quede formalizado.'
            : 'Necesitas un convenio formalizado y vigente para proponer vacantes.';
    }

    /** Practicantes: solo cuando la empresa ya tuvo un convenio formalizado. */
    public static function empresaVerPracticantes(Empresa $empresa): ?string
    {
        return $empresa->convenios()->where('estado', ConvenioEstado::Formalizado->value)->exists()
            ? null
            : 'Se habilita cuando tengas un convenio formalizado y alumnos asignados.';
    }

    /** Enviar la solicitud (o renovación) de convenio. */
    public static function empresaSolicitarConvenio(Empresa $empresa): ?string
    {
        $convenios = $empresa->convenios()->get();

        if ($convenios->contains(fn(Convenio $c) => !$c->estado->esFinal())) {
            return 'Ya tienes una solicitud de convenio en trámite.';
        }

        $vigente = $convenios->first(fn(Convenio $c) => $c->estaVigente());
        if ($vigente && now()->diffInDays($vigente->fecha_fin_vigencia, false) > 60) {
            return 'Tu convenio sigue vigente; podrás renovarlo 60 días antes de su vencimiento.';
        }

        if (blank($empresa->representante_legal)) {
            return 'Captura el nombre del representante legal en la sección Documentos.';
        }

        // Las declaraciones van tal cual al Formato de PP: sin ellas Vinculación tendría que pedirlas
        $faltanLegales = collect(['representante_cargo', 'domicilio_fiscal', 'constitucion', 'objeto_social', 'facultad_firmante'])
            ->filter(fn ($campo) => blank($empresa->{$campo}));
        if ($faltanLegales->isNotEmpty()) {
            return 'Completa tus datos legales para el convenio en la sección Documentos (faltan ' . $faltanLegales->count() . ').';
        }

        $faltantes = collect(Empresa::coleccionesObligatorias())
            ->filter(fn($col) => $empresa->documentoVigente($col) === null);

        if ($faltantes->isNotEmpty()) {
            return 'Sube todos los documentos obligatorios en la sección Documentos (faltan ' . $faltantes->count() . ').';
        }

        return null;
    }

    /* --------------------------------- Alumno --------------------------------- */

    /** Postularse (a cualquier programa de su carrera). */
    public static function alumnoPostularse(Alumno $alumno): ?string
    {
        return match (true) {
            $alumno->yaAcreditado() => 'Ya acreditaste tus prácticas profesionales.',
            $alumno->tienePracticaActiva() => 'Ya tienes una práctica activa.',
            $alumno->tieneBajaSinResolver() => 'Tu práctica fue cancelada; consulta a tu coordinación de carrera antes de postularte de nuevo.',
            $alumno->postulaciones()->whereIn('estado', PostulacionEstado::valores(PostulacionEstado::enTramite()))->exists()
                => 'Ya tienes una empresa en trámite; termina ese proceso antes de postularte a otra.',
            $alumno->grado === null || $alumno->turno === null
                => 'Captura tu grado y turno en «Mi perfil» antes de postularte (van en tu carta de presentación).',
            // El IMSS ya no es requisito previo: se entrega junto con el kardex al postularse
            default => null,
        };
    }

    /**
     * Oferta de programas: mientras su solicitud está en revisión o ya tiene empresa, el alumno no
     * busca otras vacantes (un alumno, una empresa). Se reabre si la postulación se rechaza o cancela.
     */
    public static function alumnoVerOferta(Alumno $alumno): ?string
    {
        $estados = $alumno->postulaciones()->pluck('estado');

        return match (true) {
            $alumno->yaAcreditado() => 'Ya acreditaste tus prácticas profesionales.',
            $alumno->tienePracticaActiva() => 'Ya tienes una práctica en curso: consulta tu avance en «Mis prácticas».',
            $estados->contains(PostulacionEstado::Pendiente)
                => 'Tu coordinación está revisando tu kardex e IMSS. Se habilita si tu solicitud se rechaza o cancela.',
            $estados->contains(fn ($e) => in_array($e, [PostulacionEstado::CartaPresentacionEmitida, PostulacionEstado::CartaAceptacionRecibida], true))
                => 'Ya tienes una empresa en trámite: sigue con tu carta de aceptación en «Mis documentos».',
            default => null,
        };
    }

    /** Mis prácticas: tiene sentido cuando ya hay al menos una postulación. */
    public static function alumnoVerPracticas(Alumno $alumno): ?string
    {
        return $alumno->postulaciones()->exists() ? null : 'Se habilita cuando te postules a un programa.';
    }

    /* ------------------------------- Coordinación ------------------------------- */

    /** @return string[] Lo que falta para emitir la carta de presentación (vacío = se puede). */
    public static function emitirCarta(Postulacion $po): array
    {
        $alumno = $po->alumno;
        $programa = $po->programa;
        $faltan = [];

        if ($po->estado !== PostulacionEstado::Pendiente) {
            return ['La postulación ya no está pendiente.'];
        }
        if (!$alumno->documentoValidado('constancia_imss')) {
            $faltan[] = 'Valida la constancia IMSS del alumno.';
        }
        if (!$alumno->tieneImssVigenteHasta(now())) {
            $faltan[] = 'El IMSS del alumno no está vigente.';
        }
        if (!$po->documentoValidado('kardex')) {
            $faltan[] = $po->documentoVigente('kardex')?->getCustomProperty('estado') === ValidacionEstado::Rechazada->value
                ? 'El kardex fue devuelto: espera a que el alumno suba uno nuevo.'
                : 'Valida el kardex del alumno.';
        }
        if ($alumno->grado === null || $alumno->turno === null) {
            $faltan[] = 'El alumno no ha capturado su grado y turno.';
        }
        if ((float) $po->porcentaje_creditos_declarado < (float) $alumno->carrera->porcentaje_creditos_minimo) {
            $faltan[] = "No cumple el {$alumno->carrera->porcentaje_creditos_minimo}% mínimo de créditos.";
        }
        if ($programa->estado !== ProgramaEstado::Publicado) {
            $faltan[] = 'El programa ya no está publicado.';
        } elseif ($programa->cuposOcupados() >= $programa->cupos_totales) {
            $faltan[] = 'El programa ya no tiene cupos.';
        }
        if (!$programa->esInterno() && !$programa->convenio?->estaVigente()) {
            $faltan[] = 'El convenio de la empresa no está vigente.';
        }

        return $faltan;
    }

    /** Activar la práctica (con la carta de aceptación ya registrada). */
    public static function activarPractica(Postulacion $po): ?string
    {
        if ($po->programa->esInterno() && $po->documentoVigente('oficio_aceptacion_interno') === null) {
            return 'Sube primero el Oficio de Aceptación Interno firmado por el área receptora.';
        }

        return null;
    }

    /** Aprobar y publicar una vacante de empresa. */
    public static function aprobarVacante(Programa $programa): ?string
    {
        if ($programa->estado !== ProgramaEstado::PendienteAprobacion) {
            return 'Solo se aprueban vacantes pendientes.';
        }
        if (!$programa->convenio?->estaVigente()) {
            return 'El convenio de la empresa no está vigente: no se puede publicar.';
        }
        if (!$programa->convenio->autorizaCarrera((int) $programa->carrera_id)) {
            return 'La carrera no tiene dictamen aprobatorio en el convenio de la empresa.';
        }

        return null;
    }

    /** Validar la carta de término (pasa la práctica a cierre). */
    public static function validarCartaTermino(Practica $practica): ?string
    {
        if (!$practica->horasCompletas()) {
            return "Faltan horas validadas ({$practica->progresoTexto()}).";
        }
        if ($practica->reportes()->where('estado', ReporteEstado::Pendiente->value)->exists()) {
            return 'Hay reportes pendientes de revisar: resuélvelos antes de validar la carta.';
        }

        return null;
    }

    /* -------------------------------- Vinculación -------------------------------- */

    /** Turnar la solicitud a las carreras (validación académica). */
    public static function turnarAValidacionAcademica(Convenio $convenio): ?string
    {
        $empresa = $convenio->empresa;
        $pendientes = collect(Empresa::coleccionesObligatorias())
            ->reject(fn($col) => $empresa->documentoValidado($col));

        return $pendientes->isEmpty()
            ? null
            : 'Valida primero todo el expediente de la empresa (faltan ' . $pendientes->count() . ' documento(s) por validar).';
    }
}
