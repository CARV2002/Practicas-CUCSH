<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ConvenioEstado;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\Rol;
use App\Enums\ValidacionEstado;

/**
 * Contenido del Centro de ayuda por rol (Coordinación, Empresa y Alumno).
 * Vinculación y el Administrador no tienen centro de ayuda: operan el sistema.
 *
 * Todo lo que se describe aquí corresponde a lo que el sistema hace realmente (rutas, estados y reglas).
 */
final class Ayuda
{
    /** Roles con centro de ayuda. */
    public static function disponiblePara(?Rol $rol): bool
    {
        return ! in_array($rol, [Rol::Vinculacion, Rol::Administrador], true);
    }

    /**
     * @return array{
     *   eyebrow:string, titulo:string, subtitulo:string,
     *   pasos: array<int, array{titulo:string, texto:string, ruta:?string, icono:string}>,
     *   temas: array<int, array{categoria:string, pregunta:string, respuesta:string}>,
     *   estados: array<int, array{grupo:string, items: array<int, array{estado:\BackedEnum, texto:string}>}>
     * }
     */
    public static function para(?Rol $rol): array
    {
        return match ($rol) {
            Rol::Coordinador => self::coordinacion(),
            Rol::Empresa => self::empresa(),
            default => self::alumno(),
        };
    }

    private static function alumno(): array
    {
        return [
            'eyebrow' => 'Centro de ayuda · Alumnos',
            'titulo' => '¿En qué te ayudamos?',
            'subtitulo' => 'Tu práctica profesional, paso a paso. Tu postulación, cartas, reportes y liberación los atiende la coordinación de tu carrera.',
            'pasos' => [
                ['titulo' => 'Completa tu perfil', 'texto' => 'Captura tu grado y turno: aparecen en tu carta de presentación.', 'ruta' => 'profile.edit', 'icono' => 'alumno'],
                ['titulo' => 'Sube tu constancia IMSS', 'texto' => 'Tu coordinación la valida. Debe estar vigente durante toda la práctica.', 'ruta' => 'alumno.mis-documentos', 'icono' => 'imss'],
                ['titulo' => 'Postúlate', 'texto' => 'Elige un programa de tu carrera, sube tu kardex y declara tu porcentaje de créditos.', 'ruta' => 'alumno.oferta-programas', 'icono' => 'buscar'],
                ['titulo' => 'Preséntate', 'texto' => 'Con tu carta de presentación acude a la organización y sube la carta de aceptación sellada.', 'ruta' => 'alumno.mis-documentos', 'icono' => 'documento'],
                ['titulo' => 'Reporta tus horas', 'texto' => 'Sube cada reporte firmado. Tus horas cuentan cuando tu coordinación lo valida.', 'ruta' => 'alumno.mis-documentos', 'icono' => 'reporte'],
                ['titulo' => 'Cierra tu práctica', 'texto' => 'Al completar tus horas sube la carta de término. Tu coordinación envía el oficio a Control Escolar.', 'ruta' => 'alumno.mis-practicas', 'icono' => 'cierre'],
            ],
            'temas' => [
                ['categoria' => 'Postulación', 'pregunta' => '¿Por qué no puedo postularme?', 'respuesta' => 'El botón «Postularme» se habilita cuando tienes grado y turno en tu perfil, una constancia IMSS subida, no rechazada y vigente, y no tienes otra empresa en trámite ni una práctica activa. Si algo falta, el botón te dice exactamente qué.'],
                ['categoria' => 'Postulación', 'pregunta' => '¿Qué programas veo en la oferta?', 'respuesta' => 'Solo los programas publicados para tu carrera: vacantes de organizaciones con convenio vigente (modalidad externa) y proyectos universitarios (modalidad interna), siempre que tengan cupo.'],
                ['categoria' => 'Postulación', 'pregunta' => '¿Qué porcentaje de créditos necesito?', 'respuesta' => 'Cada carrera define un mínimo. Lo declaras al postularte junto con tu kardex, y tu coordinación lo verifica antes de emitir tu carta de presentación.'],
                ['categoria' => 'Documentos', 'pregunta' => '¿Qué pasa si rechazan mi constancia IMSS o mi kardex?', 'respuesta' => 'Recibes un aviso con el motivo en la campana y por correo. Corrige el documento y vuelve a subirlo en «Mis documentos».'],
                ['categoria' => 'Documentos', 'pregunta' => '¿Dónde subo la carta de aceptación?', 'respuesta' => 'En «Mis documentos», dentro de tu postulación, cuando ya tengas tu carta de presentación. Después, tu coordinación activa tu práctica.'],
                ['categoria' => 'Reportes', 'pregunta' => '¿Cuándo cuentan mis horas?', 'respuesta' => 'Cuando tu coordinación valida el reporte. Un reporte rechazado no suma horas hasta que lo corrijas y lo vuelvas a subir.'],
                ['categoria' => 'Reportes', 'pregunta' => '¿Cada cuánto entrego reportes?', 'respuesta' => 'Depende del esquema de tu carrera: mensual, bimestral o parcial (a la mitad de las horas), y siempre un reporte final.'],
                ['categoria' => 'Cierre', 'pregunta' => '¿Cómo se acredita mi práctica?', 'respuesta' => 'Al completar tus horas sube la carta de término. Tu coordinación la valida, envía el oficio a Control Escolar y registra la captura en SIIAU. Entonces tu práctica queda «Acreditada».'],
                ['categoria' => 'Cuenta', 'pregunta' => '¿Cómo cambio mi correo o mi contraseña?', 'respuesta' => 'En «Mi perfil». Si cambias tu correo tendrás que confirmarlo de nuevo con el enlace que te enviamos.'],
            ],
            'estados' => [
                ['grupo' => 'Tu postulación', 'items' => [
                    ['estado' => PostulacionEstado::Pendiente, 'texto' => 'Tu coordinación revisa tus créditos y tu kardex.'],
                    ['estado' => PostulacionEstado::CartaPresentacionEmitida, 'texto' => 'Ya puedes presentarte con la organización.'],
                    ['estado' => PostulacionEstado::CartaAceptacionRecibida, 'texto' => 'Tu coordinación activará tu práctica.'],
                    ['estado' => PostulacionEstado::Aceptada, 'texto' => 'Tu práctica está activa.'],
                    ['estado' => PostulacionEstado::Rechazada, 'texto' => 'Revisa el motivo y postúlate a otro programa.'],
                ]],
                ['grupo' => 'Tus reportes', 'items' => [
                    ['estado' => ReporteEstado::Pendiente, 'texto' => 'Sube el archivo firmado o espera la validación.'],
                    ['estado' => ReporteEstado::Validado, 'texto' => 'Sus horas ya cuentan.'],
                    ['estado' => ReporteEstado::Rechazado, 'texto' => 'Corrígelo y vuelve a subirlo.'],
                ]],
                ['grupo' => 'Tu práctica', 'items' => [
                    ['estado' => PracticaEstado::EnCurso, 'texto' => 'Estás acumulando horas.'],
                    ['estado' => PracticaEstado::EnCierre, 'texto' => 'Tu carta de término fue validada; falta el trámite con Control Escolar.'],
                    ['estado' => PracticaEstado::Acreditada, 'texto' => '¡Listo! Ya está capturada en SIIAU.'],
                ]],
            ],
        ];
    }

    private static function empresa(): array
    {
        return [
            'eyebrow' => 'Centro de ayuda · Organizaciones',
            'titulo' => '¿En qué le ayudamos?',
            'subtitulo' => 'Del registro a recibir practicantes. Vinculación del CUCSH acompaña su convenio en cada fase.',
            'pasos' => [
                ['titulo' => 'Integre su expediente', 'texto' => 'Suba los documentos obligatorios. Vinculación los valida uno por uno.', 'ruta' => 'empresa.documentos', 'icono' => 'documento'],
                ['titulo' => 'Solicite el convenio', 'texto' => 'Indique objetivos, actividades, plazas estimadas y las carreras de interés.', 'ruta' => 'empresa.convenio', 'icono' => 'empresa'],
                ['titulo' => 'Siga el trámite', 'texto' => 'Revisión de documentos, validación académica, revisión jurídica y firmas.', 'ruta' => 'empresa.convenio', 'icono' => 'reloj'],
                ['titulo' => 'Proponga vacantes', 'texto' => 'Con convenio vigente, para las carreras con dictamen aprobatorio. La coordinación de cada carrera las aprueba.', 'ruta' => 'empresa.lista-programas', 'icono' => 'vacante'],
                ['titulo' => 'Reciba practicantes', 'texto' => 'El alumno llega con carta de presentación. Entréguele la carta de aceptación sellada.', 'ruta' => 'empresa.lista-practicantes', 'icono' => 'usuarios'],
                ['titulo' => 'Dé retroalimentación', 'texto' => 'Comente los reportes de horas de sus practicantes.', 'ruta' => 'empresa.lista-practicantes', 'icono' => 'comentario'],
            ],
            'temas' => [
                ['categoria' => 'Convenio', 'pregunta' => '¿Por qué no puedo enviar la solicitud de convenio?', 'respuesta' => 'Primero deben estar subidos todos los documentos obligatorios de su expediente. El botón le indica cuántos faltan.'],
                ['categoria' => 'Convenio', 'pregunta' => '¿Cuáles son las fases del convenio?', 'respuesta' => 'Revisión de documentos (Vinculación), validación académica (dictamen de cada carrera), revisión jurídica (Unidad Jurídica) y firmas (su organización y Rectoría). Al terminar, el convenio queda formalizado y vigente.'],
                ['categoria' => 'Convenio', 'pregunta' => '¿Me avisan cuando cambia de fase?', 'respuesta' => 'Sí. Cada cambio de etapa aparece en la campana de notificaciones de su panel.'],
                ['categoria' => 'Convenio', 'pregunta' => '¿Puedo cambiar al representante legal?', 'respuesta' => 'Sí, en «Mi perfil» o en «Documentos», excepto mientras su convenio está en revisión.'],
                ['categoria' => 'Vacantes', 'pregunta' => '¿Por qué no puedo proponer vacantes?', 'respuesta' => 'Se necesita un convenio formalizado y vigente. Solo puede ofertar para las carreras que dieron dictamen aprobatorio.'],
                ['categoria' => 'Vacantes', 'pregunta' => '¿Por qué mi vacante no aparece a los alumnos?', 'respuesta' => 'Mientras está «Pendiente de aprobación», la coordinación de la carrera la revisa. Los alumnos solo ven vacantes publicadas y con cupo.'],
                ['categoria' => 'Practicantes', 'pregunta' => '¿Qué hago cuando llega un alumno?', 'respuesta' => 'El alumno se presenta con su carta de presentación. Si lo acepta, entréguele la carta de aceptación sellada. Él la sube y su coordinación activa la práctica.'],
                ['categoria' => 'Practicantes', 'pregunta' => '¿Para qué sirve mi retroalimentación?', 'respuesta' => 'Queda en cada reporte de horas y la consulta la coordinación al validarlo.'],
                ['categoria' => 'Convenio', 'pregunta' => '¿Qué pasa cuando el convenio está por vencer?', 'respuesta' => 'Su panel le avisa 60 días antes. Solicite la renovación para seguir recibiendo practicantes.'],
            ],
            'estados' => [
                ['grupo' => 'Su convenio', 'items' => [
                    ['estado' => ConvenioEstado::Solicitado, 'texto' => 'Vinculación revisa su expediente.'],
                    ['estado' => ConvenioEstado::EnValidacionAcademica, 'texto' => 'Las carreras emiten su dictamen.'],
                    ['estado' => ConvenioEstado::EnRevisionJuridica, 'texto' => 'La Unidad Jurídica revisa el convenio.'],
                    ['estado' => ConvenioEstado::EnFirmas, 'texto' => 'Firma de su organización y de Rectoría.'],
                    ['estado' => ConvenioEstado::Formalizado, 'texto' => 'Ya puede proponer vacantes.'],
                ]],
                ['grupo' => 'Sus vacantes', 'items' => [
                    ['estado' => ProgramaEstado::PendienteAprobacion, 'texto' => 'La coordinación de la carrera la revisa.'],
                    ['estado' => ProgramaEstado::Publicado, 'texto' => 'Visible para los alumnos de la carrera.'],
                    ['estado' => ProgramaEstado::Rechazado, 'texto' => 'Revise el motivo, ajústela y vuelva a enviarla.'],
                    ['estado' => ProgramaEstado::Cerrado, 'texto' => 'Ya no recibe postulaciones.'],
                ]],
            ],
        ];
    }

    private static function coordinacion(): array
    {
        return [
            'eyebrow' => 'Centro de ayuda · Coordinación de carrera',
            'titulo' => 'Guía de tu coordinación',
            'subtitulo' => 'Llevas todo lo académico del alumno y dictaminas los convenios de tu carrera. Aquí tienes el flujo completo.',
            'pasos' => [
                ['titulo' => 'Configura tu carrera', 'texto' => 'Horas requeridas, % mínimo de créditos, esquema de reportes y contacto público.', 'ruta' => 'coordinacion.reglas', 'icono' => 'informe'],
                ['titulo' => 'Dictamina convenios', 'texto' => 'Emite el dictamen de pertinencia académica con su PDF.', 'ruta' => 'coordinacion.lista-dictamenes', 'icono' => 'academico'],
                ['titulo' => 'Aprueba vacantes', 'texto' => 'Revisa lo que proponen las empresas y crea proyectos internos.', 'ruta' => 'coordinacion.lista-programas', 'icono' => 'vacante'],
                ['titulo' => 'Valida y emite cartas', 'texto' => 'Valida IMSS y kardex y emite la carta de presentación.', 'ruta' => 'coordinacion.lista-practicantes', 'icono' => 'imss'],
                ['titulo' => 'Activa y da seguimiento', 'texto' => 'Registra la aceptación, activa la práctica, asigna supervisor y valida reportes.', 'ruta' => 'coordinacion.lista-reportes', 'icono' => 'reporte'],
                ['titulo' => 'Cierra la práctica', 'texto' => 'Valida la carta de término, envía el oficio y registra la captura en SIIAU.', 'ruta' => 'coordinacion.lista-cierres', 'icono' => 'cierre'],
            ],
            'temas' => [
                ['categoria' => 'Convenios', 'pregunta' => '¿Cuándo me toca dictaminar un convenio?', 'respuesta' => 'Cuando Vinculación turna el convenio a validación académica e incluye tu carrera. Lo verás en «Dictámenes» con sus objetivos y actividades propuestas. Un dictamen emitido ya no se puede cambiar.'],
                ['categoria' => 'Vacantes', 'pregunta' => '¿Qué vacantes debo aprobar?', 'respuesta' => 'Las que proponen las organizaciones con convenio vigente para tu carrera. Los proyectos internos (sin convenio) los creas tú mismo.'],
                ['categoria' => 'Expediente', 'pregunta' => '¿Por qué no puedo emitir la carta de presentación?', 'respuesta' => 'El botón te lista lo que falta: constancia IMSS validada y vigente, kardex no rechazado, porcentaje de créditos suficiente, grado y turno capturados por el alumno, y un programa publicado, con cupo y (si es externo) con convenio vigente.'],
                ['categoria' => 'Expediente', 'pregunta' => '¿Qué pasa si rechazo un documento?', 'respuesta' => 'El alumno recibe un aviso con tu motivo en su campana y por correo, y puede subir uno nuevo.'],
                ['categoria' => 'Prácticas', 'pregunta' => '¿Cómo activo una práctica?', 'respuesta' => 'Registra la carta de aceptación (externa) o el oficio de aceptación interno (proyecto universitario) y activa la práctica. Después puedes asignarle un profesor supervisor.'],
                ['categoria' => 'Reportes', 'pregunta' => '¿Cuándo cuentan las horas del alumno?', 'respuesta' => 'Solo al validar el reporte. Si lo rechazas, explica el motivo: el alumno lo corrige y lo vuelve a subir.'],
                ['categoria' => 'Cierre', 'pregunta' => '¿Cuándo puedo validar la carta de término?', 'respuesta' => 'Cuando no quedan reportes pendientes. Luego envía el oficio a Control Escolar y registra la captura en SIIAU: la práctica queda acreditada.'],
                ['categoria' => 'Carrera', 'pregunta' => '¿Dónde actualizo el correo que ven los alumnos?', 'respuesta' => 'En «Reglas de mi carrera». Ese correo y teléfono aparecen en «Tu coordinación» del alumno y en el directorio público.'],
            ],
            'estados' => [
                ['grupo' => 'Postulaciones', 'items' => [
                    ['estado' => PostulacionEstado::Pendiente, 'texto' => 'Revisa créditos, IMSS y kardex.'],
                    ['estado' => PostulacionEstado::CartaPresentacionEmitida, 'texto' => 'El alumno se presenta con la organización.'],
                    ['estado' => PostulacionEstado::CartaAceptacionRecibida, 'texto' => 'Lista para activar la práctica.'],
                ]],
                ['grupo' => 'Documentos y reportes', 'items' => [
                    ['estado' => ValidacionEstado::Pendiente, 'texto' => 'Espera tu validación.'],
                    ['estado' => ValidacionEstado::Validada, 'texto' => 'Cumple el requisito.'],
                    ['estado' => ValidacionEstado::Rechazada, 'texto' => 'El alumno debe subirlo de nuevo.'],
                ]],
                ['grupo' => 'Prácticas', 'items' => [
                    ['estado' => PracticaEstado::EnCurso, 'texto' => 'Acumulando horas validadas.'],
                    ['estado' => PracticaEstado::EnCierre, 'texto' => 'Envía el oficio y registra SIIAU.'],
                    ['estado' => PracticaEstado::Acreditada, 'texto' => 'Trámite concluido.'],
                ]],
            ],
        ];
    }
}
