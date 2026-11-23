<?php

namespace Database\Seeders;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EstadoDocumentoFisico as EF;
use App\Enums\EtapaConvenio;
use App\Enums\Modalidad;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ReporteTipo;
use App\Enums\Rol;
use App\Enums\SeguimientoEstado;
use App\Enums\TipoDocumentoFisico as TF;
use App\Enums\TipoOrganismo;
use App\Enums\Turno;
use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Models\CartaTermino;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Empresa;
use App\Models\OficioAcreditacion;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Notifications\ConvenioActualizado;
use App\Support\DatosNotariales;
use App\Support\Formatos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de operación para probar el sistema de punta a punta en local, con organizaciones y alumnos
 * en cada situación real del proceso. Contraseña de todas las cuentas: password123.
 *
 * ORGANIZACIONES (convenio)                                 ALUMNOS (situación)
 *  bufete.integral@test.com    Solicitado (acta devuelta)    valeria.campos      Sin expediente
 *  cedh.jalisco@test.com       Dictamen académico            diego.hernandez     Expediente en revisión
 *  museo.occidente@test.com    En Jurídica (v1 turnada)      mariana.lopez       Kardex devuelto
 *  tecnologias.educativas@...  Jurídica pidió correcciones   oscar.jimenez       IMSS vencido devuelto
 *  estudios.fiscales@test.com  En firmas (juegos fuera)      fernanda.ruiz       Créditos insuficientes
 *  despacho.ruiz@test.com      Formalizado (vacantes)        andrea.morales      Lista para postularse
 *  alianza.linguistica@...     Formalizado (vacantes)        luis.ortega         Postulación por validar
 *  migrantes.occidente@...     Formalizado por vencer        karla.mendoza       Carta de presentación emitida
 *  consejo.participacion@...   Formalizado (vacantes)        jorge.salazar       Carta de aceptación recibida
 *  notaria45@test.com          Rechazado (dictamen negativo) daniela.guzman      Práctica en curso (reportes)
 *  editorial.piramide@...      Registrada sin solicitud      miguel.torres       Práctica interna en curso
 *                                                            emiliano.castro     Carta de término por revisar
 *                                                            sofia.ramirez       En cierre (falta oficio)
 *                                                            regina.vargas       Baja parcial (conserva horas)
 *                                                            bruno.estrada       Bloqueado: 2 bajas
 *                                                            sergio.lozano       Bloqueado: sanción
 *                                                            alicia.romero       Acreditada
 *                                                            paola.sandoval      Postulación rechazada
 *                                                            irene.navarro       Cuenta desactivada
 * Los correos de alumnos son <nombre.apellido>@test.com.
 */
class OperacionSeeder extends Seeder
{
    private Personal $lorena;

    /** @var \Illuminate\Support\Collection<string, Carrera> */
    private $carreras;

    /** @var array<string, Personal> */
    private array $coordinacion = [];

    /** @var array<string, Profesor> */
    private array $profes = [];

    /** @var array<string, Programa> */
    private array $p = [];

    private int $control = 221000100;

    public function run(): void
    {
        if (!app()->environment('local')) {
            return;
        }

        \App\Services\Auditoria::sinRegistro(fn () => $this->sembrar());
    }

    private function sembrar(): void
    {
        $this->carreras = Carrera::all()->keyBy('carrera_clave');
        if ($this->carreras->count() < 15) {
            $this->command?->warn('Falta el catálogo de carreras: corre CarreraSeeder primero.');

            return;
        }

        $this->call(PersonalSeeder::class);
        $this->lorena = PersonalSeeder::vinculacion();
        foreach (array_keys(PersonalSeeder::COORDINADORES) as $clave) {
            $this->coordinacion[$clave] = PersonalSeeder::coordinador($clave);
        }

        $this->profesores();
        $this->organizaciones();
        $this->programasInternos();
        $this->alumnos();
        $this->documentosFisicos();
    }

    /* ================================ Profesores ================================ */

    private function profesores(): void
    {
        $div = fn (string $clave) => $this->carreras[$clave]->division_id;

        $lista = [
            'jur1' => ['Dr. Ernesto Salcedo Rivas', 'ABOG', '3312450981'],
            'jur2' => ['Mtra. Lucía Ornelas Prado', 'ABOG', null],
            'pol1' => ['Dra. Teresa Aguayo León', 'SOCI', '3318820045'],
            'pol2' => ['Mtro. Raúl Ponce Barragán', 'LREI', null],
            'his1' => ['Dra. Marcela Ibarra Chávez', 'HIST', '3331107764'],
            'his2' => ['Dr. Joaquín Robles Haro', 'LETR', null],
            'est1' => ['Mtra. Verónica Lomelí Gil', 'COMP', '3336692210'],
            'est2' => ['Dr. Héctor Zúñiga Ávila', 'GEOG', null],
            'cul1' => ['Mtra. Sandra Bouchard Ruiz', 'DIFL', '3320098812'],
            'cul2' => ['Mtro. Daniel Fitzgerald Mora', 'DILE', null],
        ];

        foreach ($lista as $clave => [$nombre, $carrera, $telefono]) {
            $this->profes[$clave] = Profesor::firstOrCreate(['nombre' => $nombre], [
                'division_id' => $div($carrera),
                'email' => str($nombre)->after('. ')->slug('.')->append('@academicos.test')->toString(),
                'telefono' => Formatos::telefonoConExtension($telefono, null),
                'activo' => true,
            ]);
        }

        // Jubilado: ya no aparece para nuevas asignaciones
        Profesor::firstOrCreate(['nombre' => 'Mtro. Alberto Gómez Ríos'], [
            'division_id' => $div('ABOG'), 'email' => 'alberto.gomez.rios@academicos.test', 'activo' => false,
        ]);
    }

    /* ============================== Organizaciones ============================== */

    private function organizaciones(): void
    {
        // 1. Solicitud recién enviada: Vinculación revisa el expediente (le devolvió el acta)
        $e = $this->empresa('bufete.integral@test.com', 'BJI150312AB1', 'Bufete Jurídico Integral S.C.', TipoOrganismo::SociedadCivil,
            'Lic. Mariana Esquivel Rangel', 'Coordinadora administrativa', 'Lic. Arturo Esquivel Rangel', 'Despacho jurídico',
            'Despacho fundado en 2015 que asesora a pequeñas empresas y familias en materia civil, mercantil y familiar.');
        foreach (['constancia_situacion_fiscal', 'comprobante_domicilio', 'identificacion_representante'] as $col) {
            $this->adjuntar($e, $col, str_replace('_', '-', $col) . '.pdf', fechaEmision: now()->subWeeks(3));
        }
        $this->adjuntar($e, 'acta_constitutiva', 'acta-constitutiva.pdf', ValidacionEstado::Rechazada, 'El acta está ilegible en las páginas 3 y 4; súbela escaneada a color y completa.');
        $c = $this->convenio($e, ConvenioEstado::Solicitado, now()->subDays(3), ['ABOG', 'CRIM'], [
            'objetivos' => 'Que los practicantes apliquen la teoría procesal en asuntos reales de litigio civil y familiar.',
            'actividades_propuestas' => '1) Revisión de expedientes. 2) Redacción de escritos iniciales. 3) Acompañamiento a audiencias.',
            'plazas_estimadas' => 4,
        ]);
        $this->etapa($c, EtapaConvenio::RevisionDocumentos, SeguimientoEstado::Pendiente, now()->subDays(3));
        $this->aviso('vinculacion@test.com', 'Nueva solicitud de convenio: Bufete Jurídico Integral S.C.', '/admin/lista-convenios?estado=solicitado', 'Bufete Jurídico Integral S.C.');
        $this->aviso('bufete.integral@test.com', 'Vinculación te devolvió «Acta constitutiva» de tu expediente. Motivo: «El acta está ilegible en las páginas 3 y 4; súbela escaneada a color y completa.». Qué hacer: entra a «Documentos», busca la tarjeta marcada en rojo y sube el archivo corregido para continuar con tu convenio.', '/empresa/documentos', 'Acta constitutiva');

        // 2. En dictamen académico: una carrera ya aprobó, dos esperan
        $e = $this->empresa('cedh.jalisco@test.com', 'CDH930101JL4', 'Comisión de Derechos Humanos del Estado de Occidente', TipoOrganismo::Publico,
            'Mtra. Ana Lilia Corona Paz', 'Jefa de Capacitación', 'Dr. Ernesto Vallejo Quintero', 'Organismo público autónomo',
            'Organismo que atiende quejas por violaciones a derechos humanos y promueve su cultura en el estado.');
        $this->expedienteCompleto($e);
        $c = $this->convenio($e, ConvenioEstado::EnValidacionAcademica, now()->subDays(14), ['ABOG', 'SOCI', 'LREI'], [
            'objetivos' => 'Fortalecer la atención a víctimas con practicantes que apliquen enfoques jurídico y social.',
            'actividades_propuestas' => 'Atención a quejosos, integración de expedientes, investigación social y apoyo en campañas de difusión.',
            'plazas_estimadas' => 6,
        ], ['ABOG' => Dictamen::Aprobatorio]);
        $c->update(['fecha_turno_secretaria' => now()->subDays(11), 'fecha_turno_division' => now()->subDays(10)]);
        $this->etapa($c, EtapaConvenio::RevisionDocumentos, SeguimientoEstado::Aprobado, now()->subDays(14), now()->subDays(12));
        $this->etapa($c, EtapaConvenio::TurnoAcademico, SeguimientoEstado::Pendiente, now()->subDays(12));
        $this->aviso('coord.soci@test.com', 'Comisión de Derechos Humanos del Estado de Occidente solicitó convenio para Sociología: emite tu dictamen de pertinencia académica.', '/coordinacion/lista-dictamenes', 'Dictamen pendiente');
        $this->aviso('coord.lrei@test.com', 'Comisión de Derechos Humanos del Estado de Occidente solicitó convenio para Relaciones Internacionales: emite tu dictamen de pertinencia académica.', '/coordinacion/lista-dictamenes', 'Dictamen pendiente');

        // 3. En la Unidad Jurídica: v1 turnada, esperando dictamen
        $e = $this->empresa('museo.occidente@test.com', 'MHO080520RT2', 'Museo de Historia Regional de Occidente A.C.', TipoOrganismo::SociedadCivil,
            'Lic. Fabiola Arteaga Robles', 'Coordinadora de Servicios Educativos', 'Mtro. Gilberto Saldaña Nava', 'Museo y centro de documentación',
            'Asociación que resguarda colecciones históricas del occidente de México y ofrece visitas y talleres educativos.');
        $this->expedienteCompleto($e);
        $c = $this->convenio($e, ConvenioEstado::EnRevisionJuridica, now()->subDays(40), ['HIST', 'ANTR'], [
            'objetivos' => 'Involucrar a practicantes en la conservación y difusión del patrimonio histórico regional.',
            'actividades_propuestas' => 'Catalogación de acervos, investigación para fichas museográficas y guía de visitas escolares.',
            'plazas_estimadas' => 4, 'vigencia_anios' => 3,
        ], ['HIST' => Dictamen::Aprobatorio, 'ANTR' => Dictamen::Aprobatorio]);
        $this->bitacora($c, [EtapaConvenio::RevisionDocumentos, EtapaConvenio::TurnoAcademico, EtapaConvenio::RedaccionBorrador], now()->subDays(40));
        $this->etapa($c, EtapaConvenio::DictamenJuridico, SeguimientoEstado::Pendiente, now()->subDays(9), null, 'Se turnó la versión v1 del borrador a la Unidad Jurídica.');
        $this->borrador($c, 1, now()->subDays(9));

        // 4. Jurídica pidió correcciones: el trámite regresó a redacción
        $e = $this->empresa('tecnologias.educativas@test.com', 'TEO110215QW8', 'Tecnologías Educativas del Occidente S.A. de C.V.', TipoOrganismo::Privado,
            'Ing. Daniela Ruvalcaba Ortiz', 'Gerente de Talento', 'Lic. Héctor Villalobos Mora', 'Desarrollo de contenidos educativos',
            'Empresa que produce plataformas y contenidos digitales para escuelas de educación básica y media superior.');
        $this->expedienteCompleto($e);
        $c = $this->convenio($e, ConvenioEstado::EnRevisionJuridica, now()->subDays(55), ['COMP', 'LETR'], [
            'objetivos' => 'Que los practicantes desarrollen contenidos y estrategias de comunicación educativa.',
            'actividades_propuestas' => 'Redacción y corrección de contenidos, guiones para video y gestión de redes institucionales.',
            'plazas_estimadas' => 3, 'vigencia_anios' => 2,
        ], ['COMP' => Dictamen::Aprobatorio, 'LETR' => Dictamen::Negativo]);
        $this->bitacora($c, [EtapaConvenio::RevisionDocumentos, EtapaConvenio::TurnoAcademico, EtapaConvenio::RedaccionBorrador], now()->subDays(55));
        $observacion = 'Ajustar la cláusula QUINTA: falta precisar quién cubre el seguro de los practicantes durante las actividades fuera de la empresa.';
        $this->etapa($c, EtapaConvenio::DictamenJuridico, SeguimientoEstado::Observado, now()->subDays(20), now()->subDays(4), $observacion);
        $this->etapa($c, EtapaConvenio::RedaccionBorrador, SeguimientoEstado::Pendiente, now()->subDays(4), null, 'Corregir el borrador según las observaciones de Jurídica.');
        $this->borrador($c, 1, now()->subDays(20), observada: $observacion);

        // 5. En firmas: los 3 juegos con la empresa (uno ya debió regresar)
        $e = $this->empresa('estudios.fiscales@test.com', 'IEF050909MN7', 'Instituto de Estudios Fiscales de Occidente', TipoOrganismo::Publico,
            'C.P. Laura Benítez Aranda', 'Subdirectora de Vinculación', 'Mtro. Arturo Medina Castañeda', 'Investigación y capacitación fiscal',
            'Dependencia estatal que capacita a servidores públicos y elabora estudios sobre finanzas públicas.');
        $this->expedienteCompleto($e);
        $c = $this->convenio($e, ConvenioEstado::EnFirmas, now()->subDays(70), ['ABOG', 'EPYG'], [
            'objetivos' => 'Apoyar la investigación aplicada en derecho fiscal y finanzas públicas.',
            'actividades_propuestas' => 'Consulta de expedientes, análisis de legislación fiscal y apoyo en cursos de capacitación.',
            'plazas_estimadas' => 3, 'vigencia_anios' => 3,
        ], ['ABOG' => Dictamen::Aprobatorio, 'EPYG' => Dictamen::Aprobatorio]);
        $this->bitacora($c, [EtapaConvenio::RevisionDocumentos, EtapaConvenio::TurnoAcademico, EtapaConvenio::RedaccionBorrador, EtapaConvenio::DictamenJuridico], now()->subDays(70));
        $this->etapa($c, EtapaConvenio::FirmaEmpresa, SeguimientoEstado::Pendiente, now()->subDays(8), null, 'Imprimir 3 juegos y llamar a la empresa.');
        $this->borrador($c, 1, now()->subDays(25));

        // 6-9. Formalizados (vigentes): publican vacantes y reciben practicantes
        $despacho = $this->formalizado('despacho.ruiz@test.com', 'DRA120601KP3', 'Despacho Ruiz & Asociados S.C.', TipoOrganismo::SociedadCivil,
            'Lic. Jorge Ramírez Lozano', 'Socio director', 'Lic. Patricia Ruiz Velasco', 'Servicios jurídicos',
            'Despacho con 12 años de experiencia en litigio civil, familiar y penal; recibe practicantes cada semestre.',
            ['ABOG', 'ABOS', 'CRIM'], 'CV-2025-031', now()->subMonths(8), 3, 'Litigio y asesoría jurídica');
        $alianza = $this->formalizado('alianza.linguistica@test.com', 'ALO100822HS5', 'Alianza Lingüística de Occidente A.C.', TipoOrganismo::SociedadCivil,
            'Mtra. Claudia Herrera Vázquez', 'Directora académica', 'Lic. Michel Laurent Ochoa', 'Enseñanza de idiomas',
            'Centro cultural que imparte cursos de francés e inglés y promueve la literatura en lenguas extranjeras.',
            ['DIFL', 'DILE', 'ESCR'], 'CV-2025-044', now()->subMonths(5), 3, 'Enseñanza de idiomas');
        $migrantes = $this->formalizado('migrantes.occidente@test.com', 'ACM090405LX1', 'Asociación Civil Migrantes de Occidente', TipoOrganismo::SociedadCivil,
            'Lic. Sofía Delgado Ibarra', 'Coordinadora de proyectos', 'Dr. Raúl Fonseca Aguirre', 'Atención a población migrante',
            'Asociación que acompaña a personas migrantes y sus familias con orientación jurídica, social y documental.',
            ['SOCI', 'LREI', 'EPYG'], 'CV-2023-014', now()->subYears(3)->addDays(24), 3, 'Investigación social');
        $consejo = $this->formalizado('consejo.participacion@test.com', 'CPC160118GE9', 'Consejo de Participación Ciudadana de Jalisco', TipoOrganismo::Publico,
            'Mtro. Iván Cisneros Robles', 'Jefe de Vinculación', 'Dra. Alejandra Fuentes Guerra', 'Participación ciudadana',
            'Organismo público que organiza consultas ciudadanas y promueve la participación en la vida pública.',
            ['EPYG', 'COMP', 'GEOG'], 'CV-2026-002', now()->subMonths(10), 2, 'Participación ciudadana');
        $this->aviso('migrantes.occidente@test.com', 'Tu convenio CV-2023-014 vence en 24 días. Envía la solicitud de renovación desde «Mi convenio» para no suspender tus vacantes.', '/empresa/convenio', 'CV-2023-014');

        // Vacantes de las organizaciones (las aprueba la coordinación de cada carrera)
        $this->p['litigio'] = $this->programa('Auxiliar jurídico en litigio civil', 'ABOG', ProgramaEstado::Publicado, 6, $despacho,
            ['Revisar y foliar expedientes', 'Redactar escritos iniciales y promociones', 'Acompañar a audiencias en juzgados civiles'], 'Lunes a viernes, 9:00 a 14:00', 'Av. Chapultepec 480, Col. Americana, Guadalajara');
        $this->p['orales'] = $this->programa('Apoyo en juicios orales (sabatino)', 'ABOS', ProgramaEstado::Publicado, 3, $despacho,
            ['Preparar carpetas de audiencia', 'Elaborar resúmenes de casos', 'Apoyar en la logística de audiencias'], 'Sábados, 8:00 a 15:00', 'Av. Chapultepec 480, Col. Americana, Guadalajara');
        $this->p['penal'] = $this->programa('Analista de casos penales', 'CRIM', ProgramaEstado::Publicado, 2, $despacho,
            ['Analizar carpetas de investigación', 'Elaborar perfiles criminológicos', 'Sistematizar estadística de casos'], 'Lunes a viernes, 15:00 a 19:00', 'Av. Chapultepec 480, Col. Americana, Guadalajara');
        $this->p['familiar'] = $this->programa('Asistente en derecho familiar', 'ABOG', ProgramaEstado::PendienteAprobacion, 3, $despacho,
            ['Atender a clientes en materia familiar', 'Elaborar convenios de divorcio y pensión', 'Dar seguimiento a juicios familiares'], 'Lunes a jueves, 10:00 a 14:00', 'Av. Chapultepec 480, Col. Americana, Guadalajara');
        $this->p['notificador'] = $this->programa('Notificador auxiliar', 'ABOG', ProgramaEstado::Rechazado, 2, $despacho,
            ['Entregar notificaciones', 'Llevar paquetería a juzgados'], 'Lunes a viernes, 8:00 a 12:00', 'Zona metropolitana de Guadalajara',
            motivo: 'Las actividades son de mensajería y no corresponden al perfil de egreso de Abogado; agrega tareas de análisis o redacción jurídica.');
        $this->p['peritajes'] = $this->programa('Apoyo en peritajes', 'CRIM', ProgramaEstado::Cerrado, 2, $despacho,
            ['Ordenar dictámenes periciales', 'Capturar bases de datos de casos'], 'Lunes a viernes, 9:00 a 13:00', 'Av. Chapultepec 480, Col. Americana, Guadalajara', ciclo: '2026B');

        $this->p['ingles'] = $this->programa('Docente auxiliar de inglés', 'DILE', ProgramaEstado::Publicado, 4, $alianza,
            ['Apoyar al docente titular en clase', 'Diseñar materiales didácticos', 'Aplicar y calificar exámenes de práctica'], 'Lunes a viernes, 16:00 a 20:00', 'Av. Vallarta 1250, Col. Americana, Guadalajara');
        $this->p['frances'] = $this->programa('Asistente en cursos de francés', 'DIFL', ProgramaEstado::Publicado, 3, $alianza,
            ['Preparar actividades de conversación', 'Atender el centro de autoacceso', 'Organizar eventos culturales francófonos'], 'Lunes a viernes, 10:00 a 14:00', 'Av. Vallarta 1250, Col. Americana, Guadalajara');
        $this->p['escritura'] = $this->programa('Taller de escritura para adolescentes', 'ESCR', ProgramaEstado::PendienteAprobacion, 2, $alianza,
            ['Diseñar sesiones de escritura creativa', 'Acompañar a los participantes en sus textos', 'Editar la antología final'], 'Sábados, 10:00 a 13:00', 'Av. Vallarta 1250, Col. Americana, Guadalajara');

        $this->p['migracion'] = $this->programa('Investigación social con población migrante', 'SOCI', ProgramaEstado::Publicado, 3, $migrantes,
            ['Aplicar entrevistas y encuestas', 'Sistematizar información de casos', 'Elaborar informes de diagnóstico'], 'Lunes a viernes, 9:00 a 13:00', 'Calle Morelos 1520, Col. Centro, Guadalajara');
        $this->p['consular'] = $this->programa('Apoyo en gestión consular y documental', 'LREI', ProgramaEstado::Publicado, 2, $migrantes,
            ['Orientar sobre trámites consulares', 'Dar seguimiento a solicitudes de documentos', 'Mantener el directorio de consulados'], 'Martes a sábado, 9:00 a 13:00', 'Calle Morelos 1520, Col. Centro, Guadalajara');

        $this->p['participacion'] = $this->programa('Observación de procesos de participación ciudadana', 'EPYG', ProgramaEstado::Publicado, 5, $consejo,
            ['Observar sesiones y consultas públicas', 'Elaborar minutas y reportes', 'Analizar resultados de participación'], 'Lunes a viernes, 9:00 a 14:00', 'Av. Alcalde 1350, Col. Miraflores, Guadalajara');
        $this->p['comunicacion'] = $this->programa('Comunicación institucional y redes sociales', 'COMP', ProgramaEstado::Publicado, 2, $consejo,
            ['Redactar boletines de prensa', 'Planear contenido para redes sociales', 'Monitorear medios'], 'Lunes a viernes, 10:00 a 15:00', 'Av. Alcalde 1350, Col. Miraflores, Guadalajara');
        $this->p['cartografia'] = $this->programa('Cartografía de zonas de participación', 'GEOG', ProgramaEstado::Publicado, 2, $consejo,
            ['Elaborar mapas temáticos con SIG', 'Actualizar bases de datos geográficas', 'Apoyar en estudios territoriales'], 'Lunes a viernes, 8:00 a 13:00', 'Av. Alcalde 1350, Col. Miraflores, Guadalajara');

        $this->aviso('coord.abog@test.com', 'Despacho Ruiz & Asociados S.C. registró la vacante «Asistente en derecho familiar». Revisa horario y actividades para aprobarla.', '/coordinacion/lista-programas?estado=pendiente_aprobacion', 'Asistente en derecho familiar');
        $this->aviso('coord.escr@test.com', 'Alianza Lingüística de Occidente A.C. registró la vacante «Taller de escritura para adolescentes». Revisa horario y actividades para aprobarla.', '/coordinacion/lista-programas?estado=pendiente_aprobacion', 'Taller de escritura para adolescentes');
        $this->aviso('despacho.ruiz@test.com', 'La Coordinación te devolvió la vacante «Notificador auxiliar». Motivo: «Las actividades son de mensajería y no corresponden al perfil de egreso de Abogado; agrega tareas de análisis o redacción jurídica.». Qué hacer: en «Mis vacantes», en la fila marcada en rojo, pulsa Editar, corrígela y guarda: se envía sola a revisión.', '/empresa/lista-programas', 'Notificador auxiliar');

        // 10. Rechazado: ninguna carrera aprobó (la empresa recibe el aviso con qué corregir)
        $e = $this->empresa('notaria45@test.com', 'NOT060606KL6', 'Notaría Pública No. 45 de Zapopan', TipoOrganismo::Privado,
            'Lic. Patricia Navarro Gil', 'Asistente de la notaría', 'Lic. Federico Anaya Robledo', 'Servicios notariales',
            'Notaría que formaliza actos jurídicos, testamentos y constitución de sociedades.');
        $this->expedienteCompleto($e);
        $c = $this->convenio($e, ConvenioEstado::Rechazado, now()->subDays(30), ['HIST'], [
            'objetivos' => 'Recibir apoyo en la organización del archivo notarial.',
            'actividades_propuestas' => 'Archivo, fotocopiado y atención en recepción.',
            'plazas_estimadas' => 2,
        ], ['HIST' => Dictamen::Negativo], ['HIST' => 'Las actividades son administrativas (archivo y recepción): no hay investigación histórica ni trabajo con fuentes.']);
        $this->etapa($c, EtapaConvenio::RevisionDocumentos, SeguimientoEstado::Aprobado, now()->subDays(30), now()->subDays(27));
        $this->etapa($c, EtapaConvenio::TurnoAcademico, SeguimientoEstado::Rechazado, now()->subDays(27), now()->subDays(6), 'Todas las carreras emitieron dictamen negativo.');
        if ($e->user->notifications()->doesntExist()) {
            $e->user->notify(new ConvenioActualizado($c, 'Ninguna de las carreras que elegiste aprobó tu propuesta de convenio. Observaciones de las coordinaciones: Licenciatura en Historia: Las actividades son administrativas (archivo y recepción): no hay investigación histórica ni trabajo con fuentes. Qué hacer: Ajusta tus objetivos y actividades propuestas en «Mi convenio», sección «Perfiles requeridos» (marcada en rojo), y envía una nueva solicitud; tu expediente se conserva.'));
        }

        // 11. Recién registrada: aún no envía su solicitud (datos a medias)
        $this->usuario('editorial.piramide@test.com', 'Lic. Rodrigo Medina Salas', Rol::Empresa);
        Empresa::firstOrCreate(['rfc' => 'EPL180301TT4'], [
            'user_id' => User::where('email', 'editorial.piramide@test.com')->value('id'),
            'razon_social' => 'Editorial Pirámide de Letras S.A. de C.V.',
            'tipo_organismo' => TipoOrganismo::Privado,
            'contacto_nombre' => 'Lic. Rodrigo Medina Salas',
            'contacto_cargo' => 'Editor en jefe',
            'contacto_telefono' => '3336781122',
            'giro' => 'Editorial',
        ]);
    }

    /** Proyectos internos del CUCSH (los crea la propia coordinación, sin convenio). */
    private function programasInternos(): void
    {
        $this->p['archivo'] = $this->programa('Archivo Histórico del CUCSH', 'HIST', ProgramaEstado::Publicado, 3, null,
            ['Clasificar y describir fondos documentales', 'Digitalizar documentos', 'Atender a investigadores'], 'Lunes a viernes, 9:00 a 13:00', 'Edificio de Rectoría, CUCSH Belenes');
        $this->p['correccion'] = $this->programa('Corrección de estilo en publicaciones del CUCSH', 'LETR', ProgramaEstado::Publicado, 2, null,
            ['Corregir textos para revistas y libros', 'Cotejar pruebas de imprenta', 'Aplicar el manual de estilo'], 'Lunes a viernes, 10:00 a 14:00', 'Coordinación Editorial, CUCSH Belenes');
        $this->p['revista'] = $this->programa('Revista de Filosofía: asistencia editorial', 'FILO', ProgramaEstado::Borrador, 2, null,
            ['Gestionar el envío de artículos a dictamen', 'Preparar originales', 'Difundir los números publicados'], 'Martes y jueves, 10:00 a 14:00', 'Departamento de Filosofía, CUCSH Belenes');
        $this->p['antropologia'] = $this->programa('Laboratorio de antropología visual', 'ANTR', ProgramaEstado::Publicado, 2, null,
            ['Registrar trabajo de campo en foto y video', 'Catalogar el acervo audiovisual', 'Editar materiales de divulgación'], 'Lunes a viernes, 12:00 a 16:00', 'Departamento de Antropología, CUCSH Belenes');
        $this->p['clinica'] = $this->programa('Clínica jurídica del CUCSH', 'ABOG', ProgramaEstado::Publicado, 4, null,
            ['Orientar a personas de escasos recursos', 'Elaborar escritos bajo supervisión', 'Dar seguimiento a casos'], 'Lunes a viernes, 15:00 a 19:00', 'Clínica jurídica, CUCSH La Normal');
    }

    /* ================================= Alumnos ================================= */

    private function alumnos(): void
    {
        $vigente = now()->addMonths(10);

        // Expediente: antes de postularse, la coordinación valida kardex e IMSS
        $this->alumno('Valeria Campos Ruiz', 'LETR', $vigente, 58, expediente: 'sin_enviar');
        $diego = $this->alumno('Diego Hernández Soto', 'FILO', $vigente, 66, expediente: 'en_revision');
        $this->aviso('coord.filo@test.com', 'Diego Hernández Soto (FILO) envió su kardex y su constancia IMSS para revisión.', '/coordinacion/lista-practicantes', null);
        $this->alumno('Mariana López Vega', 'SOCI', $vigente, 71, expediente: 'devuelto', motivo: 'El kardex es del ciclo 2026A; descarga el kardex simple del ciclo actual en SIIAU.');
        $this->alumno('Óscar Jiménez Lara', 'GEOG', now()->subMonths(2), 74, expediente: 'imss_vencido');
        $this->alumno('Fernanda Ruiz Castillo', 'ANTR', $vigente, 42);   // validado, pero no cumple el 50 % de créditos
        $this->alumno('Andrea Morales Pinto', 'DILE', $vigente, 63);     // lista para postularse

        // Postulaciones en trámite
        $luis = $this->alumno('Luis Fernando Ortega Ramos', 'ABOG', $vigente, 72.5);
        $this->postulacion($luis, $this->p['litigio'], PostulacionEstado::Pendiente);
        $this->aviso('coord.abog@test.com', 'Luis Fernando Ortega Ramos (ABOG) se postuló a «Auxiliar jurídico en litigio civil». Su kardex e IMSS ya están validados: emite su carta de presentación.', '/coordinacion/lista-practicantes?seccion=postulaciones', 'Auxiliar jurídico en litigio civil');

        $karla = $this->alumno('Karla Mendoza Flores', 'EPYG', $vigente, 68);
        $this->postulacion($karla, $this->p['participacion'], PostulacionEstado::CartaPresentacionEmitida, presentacion: true);

        $jorge = $this->alumno('Jorge Iván Salazar Núñez', 'CRIM', $vigente, 80);
        $this->postulacion($jorge, $this->p['penal'], PostulacionEstado::CartaAceptacionRecibida, presentacion: true, aceptacion: true);

        $paola = $this->alumno('Paola Sandoval Reyes', 'LETR', $vigente, 76);
        $this->postulacion($paola, $this->p['correccion'], PostulacionEstado::Rechazada);
        $this->aviso($paola->user->email, 'Tu postulación a «Corrección de estilo en publicaciones del CUCSH» fue rechazada. Puedes postularte a otro programa de tu carrera.', '/alumno/oferta-programas', null);

        // Prácticas en curso
        $daniela = $this->alumno('Daniela Guzmán Ríos', 'ABOG', $vigente, 85);
        $pr = $this->practica($this->postulacion($daniela, $this->p['litigio'], PostulacionEstado::Aceptada, presentacion: true, aceptacion: true),
            PracticaEstado::EnCurso, now()->subMonths(3), now()->addMonths(3), $this->profes['jur1']);
        $this->reporte($pr, 1, ReporteEstado::Validado, 80, now()->subMonths(3), now()->subMonths(2), true, 'Cumplió todas las actividades del periodo.', 'Excelente disposición y puntualidad.');
        $this->reporte($pr, 2, ReporteEstado::Validado, 75, now()->subMonths(2), now()->subMonth(), true, 'Reporte completo.');
        $this->reporte($pr, 3, ReporteEstado::Rechazado, 70, now()->subMonth(), now()->subDays(3), true, 'Las horas no coinciden con la bitácora firmada por tu jefe directo; corrige y vuelve a subirlo.');
        $this->reporte($pr, 4, ReporteEstado::Pendiente, 40, now()->subDays(3), now()->addWeeks(3), false, actividades: 'Revisión de expedientes del juzgado tercero civil, redacción de dos demandas de cobro y asistencia a una audiencia de conciliación.');
        $this->aviso($daniela->user->email, 'Tu coordinación te devolvió el reporte del ' . now()->subMonth()->format('d/m') . ' al ' . now()->subDays(3)->format('d/m/Y') . '. Motivo: «Las horas no coinciden con la bitácora firmada por tu jefe directo; corrige y vuelve a subirlo.». Qué hacer: entra a «Mis documentos › Reportes de horas», busca el reporte marcado en rojo, corrígelo y súbelo firmado de nuevo.', '/alumno/mis-documentos', null);

        $miguel = $this->alumno('Miguel Ángel Torres Vela', 'HIST', $vigente, 78);
        $pr = $this->practica($this->postulacion($miguel, $this->p['archivo'], PostulacionEstado::Aceptada, presentacion: true, oficioInterno: true),
            PracticaEstado::EnCurso, now()->subMonths(2), now()->addMonths(4), $this->profes['his1'], Modalidad::Interna);
        $this->reporte($pr, 1, ReporteEstado::Validado, 120, now()->subMonths(2), now()->subMonth(), true, 'Muy buen avance en la descripción del fondo.');
        $this->reporte($pr, 2, ReporteEstado::Pendiente, 110, now()->subMonth(), now(), true);   // subido: por validar

        // Cierre: carta de término por revisar y otra ya en cierre
        $emiliano = $this->alumno('Emiliano Castro Díaz', 'DILE', $vigente, 82);
        $pr = $this->practica($this->postulacion($emiliano, $this->p['ingles'], PostulacionEstado::Aceptada, presentacion: true, aceptacion: true),
            PracticaEstado::EnCurso, now()->subMonths(5), now()->subWeek(), $this->profes['cul2']);
        $this->reporte($pr, 1, ReporteEstado::Validado, 100, now()->subMonths(5), now()->subMonths(3), true, 'Aprobado.');
        $this->reporte($pr, 2, ReporteEstado::Validado, 100, now()->subMonths(3), now()->subWeek(), true, 'Aprobado.', 'Gran apoyo a los grupos de nivel básico.');
        $carta = CartaTermino::firstOrCreate(['practica_id' => $pr->id], ['estado_validacion' => ValidacionEstado::Pendiente]);
        $this->adjuntar($carta, 'carta_termino', 'carta-termino.pdf');

        $sofia = $this->alumno('Sofía Ramírez Aguilar', 'COMP', $vigente, 88);
        $pr = $this->practica($this->postulacion($sofia, $this->p['comunicacion'], PostulacionEstado::Aceptada, presentacion: true, aceptacion: true),
            PracticaEstado::EnCierre, now()->subMonths(6), now()->subMonth(), $this->profes['est1']);
        $this->reporte($pr, 1, ReporteEstado::Validado, 150, now()->subMonths(6), now()->subMonths(3), true, 'Aprobado.');
        $this->reporte($pr, 2, ReporteEstado::Validado, 150, now()->subMonths(3), now()->subMonth(), true, 'Aprobado.');
        $carta = CartaTermino::firstOrCreate(['practica_id' => $pr->id], ['estado_validacion' => ValidacionEstado::Validada]);
        $this->adjuntar($carta, 'carta_termino', 'carta-termino.pdf', ValidacionEstado::Validada);

        // Bajas
        $regina = $this->alumno('Regina Vargas Medina', 'DIFL', $vigente, 70);
        $pr = $this->practica($this->postulacion($regina, $this->p['frances'], PostulacionEstado::Cancelada, presentacion: true, aceptacion: true),
            PracticaEstado::Cancelada, now()->subMonths(4), now()->addMonths(2), $this->profes['cul1']);
        $this->reporte($pr, 1, ReporteEstado::Validado, 60, now()->subMonths(4), now()->subMonths(3), true, 'Buen inicio.');
        $this->baja($pr, 'cierre_empresa', 'La organización suspendió temporalmente los cursos del turno matutino.', now()->subMonths(2));

        $bruno = $this->alumno('Bruno Estrada Cárdenas', 'SOCI', $vigente, 75);
        $pr = $this->practica($this->postulacion($bruno, $this->p['migracion'], PostulacionEstado::Cancelada, presentacion: true, aceptacion: true),
            PracticaEstado::Cancelada, now()->subMonths(9), now()->subMonths(5), $this->profes['pol1']);
        $this->baja($pr, 'cambio_proyecto', 'El proyecto terminó antes de su primer reporte.', now()->subMonths(8));
        $pr = $this->practica($this->postulacion($bruno, $this->p['participacion'], PostulacionEstado::Cancelada, presentacion: true, aceptacion: true),
            PracticaEstado::Cancelada, now()->subMonths(5), now()->addMonth(), $this->profes['pol1']);
        $this->reporte($pr, 1, ReporteEstado::Validado, 60, now()->subMonths(5), now()->subMonths(4), true, 'Cumplido.');
        $this->baja($pr, 'mutuo_acuerdo', 'El área receptora y el alumno acordaron terminar la práctica.', now()->subWeeks(3));
        $this->aviso('coord.soci@test.com', 'Bruno Estrada Cárdenas (SOCI) llegó al límite de 2 bajas: ya no puede postularse sin un desbloqueo excepcional.', '/coordinacion/lista-practicantes?seccion=bloqueados', null);

        $sergio = $this->alumno('Sergio Lozano Pineda', 'CRIM', $vigente, 70);
        $pr = $this->practica($this->postulacion($sergio, $this->p['penal'], PostulacionEstado::Cancelada, presentacion: true, aceptacion: true),
            PracticaEstado::Cancelada, now()->subMonths(4), now()->addMonths(2), $this->profes['jur2']);
        $this->reporte($pr, 1, ReporteEstado::Validado, 35, now()->subMonths(4), now()->subMonths(3), true, 'Regular.');
        $this->baja($pr, 'abandono', 'La organización reportó que el alumno dejó de asistir sin avisar.', now()->subWeeks(2));

        // Ciclo completo: acreditada
        $alicia = $this->alumno('Alicia Romero Fuentes', 'LREI', $vigente, 90);
        $pr = $this->practica($this->postulacion($alicia, $this->p['consular'], PostulacionEstado::Aceptada, presentacion: true, aceptacion: true),
            PracticaEstado::Acreditada, now()->subMonths(10), now()->subMonths(4), $this->profes['pol2']);
        $this->reporte($pr, 1, ReporteEstado::Validado, 120, now()->subMonths(10), now()->subMonths(8), true, 'Aprobado.', 'Muy buen desempeño con los usuarios.');
        $this->reporte($pr, 2, ReporteEstado::Validado, 120, now()->subMonths(8), now()->subMonths(4), true, 'Aprobado.');
        $carta = CartaTermino::firstOrCreate(['practica_id' => $pr->id], ['estado_validacion' => ValidacionEstado::Validada]);
        $this->adjuntar($carta, 'carta_termino', 'carta-termino.pdf', ValidacionEstado::Validada);
        $oficio = OficioAcreditacion::firstOrCreate(['practica_id' => $pr->id], [
            'emitido_por' => $this->coordinacion['LREI']->id,
            'estado_validacion' => ValidacionEstado::Validada,
            'enviado_control_escolar_at' => now()->subMonths(3),
            'capturado_siiau_at' => now()->subMonths(3)->addDays(5),
        ]);
        $this->adjuntar($oficio, 'oficio_acreditacion', 'oficio-acreditacion.pdf', ValidacionEstado::Validada);
        $this->aviso($alicia->user->email, '¡Tu práctica profesional quedó acreditada! Control Escolar capturó tu acreditación en SIIAU.', '/alumno/mis-practicas', null);

        // Cuenta desactivada (para probar el bloqueo de inicio de sesión)
        $this->alumno('Irene Navarro Téllez', 'ESCR', $vigente, 60, activo: false);
    }

    /* ============================ Documentos físicos ============================ */

    private function documentosFisicos(): void
    {
        Convenio::whereIn('estado', [ConvenioEstado::EnRevisionJuridica->value, ConvenioEstado::EnFirmas->value, ConvenioEstado::Formalizado->value])
            ->with('empresa')->get()->each(function (Convenio $c) {
                $c->prepararDocumentosFisicos($this->lorena);
                $expediente = $c->documentosFisicos()->where('tipo', TF::DictamenJuridico->value)->first();
                $juegos = $c->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->orderBy('juego')->get();

                // Jurídica pidió correcciones: el expediente regresó con observaciones
                if ($c->empresa->rfc === 'TEO110215QW8' && $expediente?->estado === EF::EnJuridica) {
                    $expediente->mover(EF::ConObservaciones, $this->lorena, 'Ajustar la cláusula QUINTA (seguro de los practicantes).', null, today()->subWeekdays(3));
                }

                // En firmas: los 3 juegos con la empresa; el primero ya debió regresar
                if ($c->estado === ConvenioEstado::EnFirmas) {
                    $juegos->where('estado', EF::PorElaborar)->values()->each(fn ($j, $i) => $j->mover(
                        EF::ConEmpresa, $this->lorena, 'Entregados al representante legal.', $i === 0 ? today()->subDay() : null, today()->subWeekdays(6)));
                }

                // Formalizados: el expediente y el juego de Vinculación concluyen; los otros dos se entregan
                if ($c->estado === ConvenioEstado::Formalizado) {
                    $expediente && !in_array($expediente->estado->value, EF::concluidos(), true)
                        && $expediente->mover(EF::Archivado, $this->lorena, 'Convenio formalizado: el expediente jurídico concluye.');
                    foreach ($juegos as $j) {
                        if (in_array($j->estado->value, EF::concluidos(), true)) {
                            continue;
                        }
                        // El convenio más reciente aún tiene un juego por repartir
                        $porRepartir = $c->folio === 'CV-2026-002' && $j->juego === 2;
                        $j->juego === 1
                            ? $j->mover(EF::Archivado, $this->lorena, 'Queda en el archivo de Vinculación.')
                            : ($porRepartir ? null : $j->mover(EF::Entregado, $this->lorena, 'Entregado a su destino.'));
                    }
                }
            });
    }

    /* ================================= Helpers ================================= */

    private function usuario(string $email, string $nombre, Rol $rol, bool $activo = true): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $nombre, 'password' => 'password123', 'activo' => $activo, 'aviso_privacidad_at' => now(),
        ]);
        $user->hasVerifiedEmail() || $user->markEmailAsVerified();
        $user->syncRoles([$rol->value]);

        return $user;
    }

    /** Organización con su perfil, domicilio y datos legales completos. */
    private function empresa(string $email, string $rfc, string $razon, TipoOrganismo $tipo, string $contacto, string $cargo,
        string $representante, string $giro, string $descripcion): Empresa
    {
        $user = $this->usuario($email, $contacto, Rol::Empresa);
        $escritura = DatosNotariales::usaEscritura($tipo);
        $n = abs(crc32($rfc)) % 900;

        $datos = [
            'user_id' => $user->id, 'razon_social' => $razon, 'tipo_organismo' => $tipo,
            'contacto_nombre' => $contacto, 'contacto_cargo' => $cargo, 'contacto_telefono' => '33' . str_pad((string) (31000000 + $n * 7919), 8, '0', STR_PAD_LEFT),
            'representante_legal' => $representante, 'representante_cargo' => $escritura ? 'Representante legal' : 'Titular de la dependencia',
            'giro' => $giro, 'descripcion' => $descripcion,
            'proyectos_principales' => 'Atención al público, gestión documental y proyectos de investigación aplicada con universidades.',
            'calle' => 'Av. Juárez', 'numero_exterior' => (string) (100 + $n), 'colonia' => 'Centro', 'codigo_postal' => '44100',
            'municipio' => 'Guadalajara', 'entidad' => 'Jalisco',
            'objeto_social' => 'Las actividades propias de su giro y la colaboración con instituciones educativas en la formación de practicantes.',
            'facultad_origen' => $escritura ? 'acta' : 'nombramiento',
            'facultad_firmante' => $escritura
                ? 'las facultades que le otorga la escritura constitutiva citada en la declaración I'
                : 'el nombramiento de fecha 1 de enero de 2025 expedido por el titular de la dependencia',
        ];

        $datos += $escritura
            ? DatosNotariales::preparar([
                'escritura_numero' => (string) (10200 + $n * 13), 'escritura_fecha' => now()->subYears(8)->startOfMonth()->toDateString(),
                'notario_nombre' => 'Lic. Ricardo Alatorre Gómez', 'notaria_numero' => '12', 'notaria_ciudad' => 'Guadalajara, Jalisco',
                'folio_mercantil' => $tipo === TipoOrganismo::Privado ? 'N-2018-0' . (4500 + $n) : null,
            ], $tipo)
            : ['constitucion' => 'el decreto publicado en el Periódico Oficial «El Estado de Jalisco» el 15 de marzo de 2012'];

        $empresa = Empresa::firstOrCreate(['rfc' => $rfc], $datos);
        $empresa->domicilio_fiscal || $empresa->update(['domicilio_fiscal' => $empresa->domicilioCompleto()]);

        return $empresa;
    }

    private function expedienteCompleto(Empresa $empresa): void
    {
        foreach ($empresa->obligatorias() as $col) {
            $this->adjuntar($empresa, $col, str_replace('_', '-', $col) . '.pdf', ValidacionEstado::Validada,
                fechaEmision: in_array($col, Empresa::CON_VIGENCIA, true) ? now()->subWeeks(3) : null);
        }
    }

    /**
     * @param  string[]  $carreras  claves de las carreras solicitadas
     * @param  array<string, Dictamen>  $dictamenes  resultado por clave (las demás quedan pendientes)
     * @param  array<string, string>  $observaciones
     */
    private function convenio(Empresa $empresa, ConvenioEstado $estado, Carbon $recepcion, array $carreras, array $datos,
        array $dictamenes = [], array $observaciones = []): Convenio
    {
        $convenio = Convenio::firstOrCreate(['empresa_id' => $empresa->id, 'estado' => $estado->value], [
            'gestionado_por' => $this->lorena->id,
            'fecha_recepcion' => $recepcion->toDateString(),
            'medio_conocimiento' => 'Por la página del CUCSH',
            'experiencia_previa' => 'Ninguno',
        ] + $datos);

        foreach ($carreras as $clave) {
            $resultado = $dictamenes[$clave] ?? Dictamen::Pendiente;
            $d = $convenio->dictamenes()->firstOrCreate(['carrera_id' => $this->carreras[$clave]->id], [
                'dictamen' => $resultado,
                'dictaminado_por' => $resultado === Dictamen::Pendiente ? null : $this->coordinacion[$clave]->id,
                'fecha_dictamen' => $resultado === Dictamen::Pendiente ? null : $recepcion->copy()->addDays(8)->toDateString(),
                'observaciones' => $observaciones[$clave] ?? ($resultado === Dictamen::Negativo ? 'Las actividades propuestas no corresponden al perfil de egreso de la carrera.' : null),
            ]);
            $resultado === Dictamen::Pendiente || $this->adjuntar($d, 'dictamen_pertinencia', "dictamen-{$clave}.pdf");
        }

        return $convenio;
    }

    /** Convenio formalizado y vigente, con su bitácora, borrador y convenio firmado. */
    private function formalizado(string $email, string $rfc, string $razon, TipoOrganismo $tipo, string $contacto, string $cargo,
        string $representante, string $giro, string $descripcion, array $carreras, string $folio, Carbon $inicio, int $anios, string $tema): Convenio
    {
        $empresa = $this->empresa($email, $rfc, $razon, $tipo, $contacto, $cargo, $representante, $giro, $descripcion);
        $this->expedienteCompleto($empresa);

        $convenio = $this->convenio($empresa, ConvenioEstado::Formalizado, $inicio->copy()->subMonths(3), $carreras, [
            'folio' => $folio,
            'objetivos' => "Ofrecer a los practicantes experiencia profesional en {$tema}, en línea con el plan de estudios de cada carrera.",
            'actividades_propuestas' => 'Investigación aplicada, elaboración de documentos e informes, atención al público y apoyo en proyectos de la organización.',
            'plazas_estimadas' => 8,
            'vigencia_anios' => $anios,
            'fecha_inicio_vigencia' => $inicio->toDateString(),
            'fecha_fin_vigencia' => $inicio->copy()->addYears($anios)->subDay()->toDateString(),
        ], array_fill_keys($carreras, Dictamen::Aprobatorio));

        $this->bitacora($convenio, EtapaConvenio::cases(), $inicio->copy()->subMonths(3));
        $this->borrador($convenio, 1, $inicio->copy()->subMonth());
        $this->adjuntar($convenio, 'convenio_firmado', "convenio-{$folio}-firmado.pdf");

        return $convenio;
    }

    /** @param EtapaConvenio[] $etapas  etapas ya resueltas, en orden */
    private function bitacora(Convenio $convenio, array $etapas, Carbon $desde): void
    {
        foreach ($etapas as $i => $etapa) {
            $registro = $desde->copy()->addDays($i * 9);
            $this->etapa($convenio, $etapa, SeguimientoEstado::Aprobado, $registro, $registro->copy()->addDays(6));
            match ($etapa) {
                EtapaConvenio::DictamenJuridico => $this->adjuntarEtapa($convenio, $etapa, 'dictamen-juridico.pdf'),
                EtapaConvenio::FirmaEmpresa => $this->adjuntarEtapa($convenio, $etapa, 'firma-empresa-ultima-hoja.pdf'),
                EtapaConvenio::FirmaRectoria => $this->adjuntarEtapa($convenio, $etapa, 'convenio-firmado-rectoria.pdf'),
                default => null,
            };
        }
    }

    private function etapa(Convenio $convenio, EtapaConvenio $etapa, SeguimientoEstado $estado, Carbon $registro, ?Carbon $resolucion = null, ?string $comentario = null): void
    {
        $convenio->seguimientos()->firstOrCreate(['etapa' => $etapa->value, 'estado' => $estado->value], [
            'registrado_por' => $this->lorena->id,
            'comentario' => $comentario,
            'fecha_registro' => $registro->toDateString(),
            'fecha_resolucion' => $resolucion?->toDateString(),
        ]);
    }

    private function adjuntarEtapa(Convenio $convenio, EtapaConvenio $etapa, string $archivo): void
    {
        $s = $convenio->seguimientos()->where('etapa', $etapa->value)->where('estado', '!=', SeguimientoEstado::Pendiente->value)->first();
        $s && $this->adjuntar($s, 'documento_etapa', $archivo);
    }

    /** Versión del borrador (Word + PDF para imprimir), como la genera Vinculación. */
    private function borrador(Convenio $convenio, int $version, Carbon $fecha, ?string $observada = null): void
    {
        if ($convenio->getMedia('borrador_convenio')->isNotEmpty()) {
            return;
        }
        $props = ['version' => $version] + ($observada ? ['observada_juridica' => $observada] : []);
        $word = $this->archivo("borrador-convenio-v{$version}.docx");
        $convenio->addMedia($word)->usingFileName("borrador-convenio-v{$version}.docx")->withCustomProperties($props)->toMediaCollection('borrador_convenio');
        $pdf = $this->archivo("convenio-v{$version}.pdf");
        $convenio->addMedia($pdf)->usingFileName("convenio-v{$version}.pdf")->withCustomProperties(['version' => $version])->toMediaCollection('borrador_pdf');
        $convenio->media()->whereIn('collection_name', ['borrador_convenio', 'borrador_pdf'])->update(['created_at' => $fecha, 'updated_at' => $fecha]);
    }

    private function programa(string $titulo, string $clave, ProgramaEstado $estado, int $cupos, ?Convenio $convenio, array $actividades,
        string $horario, string $sede, ?string $motivo = null, string $ciclo = '2027A'): Programa
    {
        return Programa::firstOrCreate(['titulo' => $titulo, 'carrera_id' => $this->carreras[$clave]->id, 'ciclo' => $ciclo], [
            'convenio_id' => $convenio?->id,
            'modalidad' => $convenio ? Modalidad::Externa : Modalidad::Interna,
            'descripcion' => $convenio
                ? "Vacante en {$convenio->empresa->razon_social} para alumnos de {$this->carreras[$clave]->nombre}."
                : "Proyecto interno del CUCSH para alumnos de {$this->carreras[$clave]->nombre}.",
            'actividades' => $actividades,
            'perfil_requerido' => 'Alumno regular con el porcentaje mínimo de créditos de su carrera, responsable y con buena redacción.',
            'horario' => $horario,
            'sede' => $sede,
            'cupos_totales' => $cupos,
            'estado' => $estado,
            'motivo_rechazo' => $motivo,
            'revisado_por' => in_array($estado, [ProgramaEstado::Publicado, ProgramaEstado::Rechazado, ProgramaEstado::Cerrado], true) ? $this->coordinacion[$clave]->id : null,
            'revisado_at' => in_array($estado, [ProgramaEstado::Publicado, ProgramaEstado::Rechazado, ProgramaEstado::Cerrado], true) ? now()->subWeeks(2) : null,
        ]);
    }

    /**
     * Alumno con su expediente (kardex + IMSS).
     * $expediente: validado | en_revision | devuelto | imss_vencido | sin_enviar
     */
    private function alumno(string $nombre, string $clave, ?Carbon $imss, ?float $creditos, bool $activo = true,
        string $expediente = 'validado', ?string $motivo = null): Alumno
    {
        // nombre.primer-apellido@test.com (p. ej. «Luis Fernando Ortega Ramos» → luis.ortega@test.com)
        $partes = str($nombre)->ascii()->lower()->explode(' ')->values();
        $email = $partes[0] . '.' . $partes[$partes->count() - 2] . '@test.com';
        $user = $this->usuario($email, $nombre, Rol::Alumno, $activo);

        $alumno = Alumno::firstOrCreate(['user_id' => $user->id], [
            'carrera_id' => $this->carreras[$clave]->id,
            'num_control' => (string) $this->control++,
            'grado' => 7,
            'turno' => Turno::Matutino,
            'vigencia_imss' => $expediente === 'sin_enviar' ? null : $imss?->toDateString(),
            'porcentaje_creditos' => $creditos,
        ]);

        match ($expediente) {
            'validado' => [$this->adjuntar($alumno, 'kardex', 'kardex.pdf', ValidacionEstado::Validada), $this->adjuntar($alumno, 'constancia_imss', 'constancia-imss.pdf', ValidacionEstado::Validada)],
            'en_revision' => [$this->adjuntar($alumno, 'kardex', 'kardex.pdf'), $this->adjuntar($alumno, 'constancia_imss', 'constancia-imss.pdf')],
            'devuelto' => [$this->adjuntar($alumno, 'kardex', 'kardex.pdf', ValidacionEstado::Rechazada, $motivo), $this->adjuntar($alumno, 'constancia_imss', 'constancia-imss.pdf', ValidacionEstado::Validada)],
            'imss_vencido' => [$this->adjuntar($alumno, 'kardex', 'kardex.pdf'), $this->adjuntar($alumno, 'constancia_imss', 'constancia-imss.pdf', ValidacionEstado::Rechazada,
                'La constancia está vencida (vigencia al ' . $imss?->format('d/m/Y') . '). Descarga una vigente en el portal del IMSS.')],
            default => null,
        };

        if ($expediente === 'devuelto' || $expediente === 'imss_vencido') {
            $doc = $expediente === 'devuelto' ? 'kardex' : 'constancia IMSS';
            $this->aviso($email, "Tu coordinación te devolvió tu {$doc}. Motivo: «" . ($expediente === 'devuelto' ? $motivo : 'La constancia está vencida.') . '». Qué hacer: entra a «Mis documentos › Tu expediente», busca la tarjeta marcada en rojo y sube el archivo corregido.', '/alumno/mis-documentos', null);
        }

        return $alumno;
    }

    private function postulacion(Alumno $alumno, Programa $programa, PostulacionEstado $estado, bool $presentacion = false,
        bool $aceptacion = false, bool $oficioInterno = false): Postulacion
    {
        $validada = $estado !== PostulacionEstado::Pendiente;
        $clave = $this->carreras->firstWhere('id', $alumno->carrera_id)->carrera_clave;

        $po = Postulacion::firstOrCreate(['alumno_id' => $alumno->id, 'programa_id' => $programa->id], [
            'estado' => $estado,
            'validado_por' => $validada ? $this->coordinacion[$clave]->id : null,
            'porcentaje_creditos_declarado' => $alumno->porcentaje_creditos,
            'fecha_postulacion' => now()->subDays(20)->toDateString(),
            'siiau_confirmado_at' => now()->subDays(20),
        ]);

        $presentacion && $this->adjuntar($po, 'carta_presentacion', 'carta-presentacion.pdf');
        $aceptacion && $this->adjuntar($po, 'carta_aceptacion', 'carta-aceptacion.pdf');
        $oficioInterno && $this->adjuntar($po, 'oficio_aceptacion_interno', 'oficio-aceptacion-interno.pdf');

        return $po;
    }

    private function practica(Postulacion $po, PracticaEstado $estado, Carbon $inicio, Carbon $fin, ?Profesor $supervisor,
        Modalidad $modalidad = Modalidad::Externa): Practica
    {
        $carrera = $this->carreras->firstWhere('id', $po->alumno->carrera_id);

        return Practica::firstOrCreate(['postulacion_id' => $po->id], [
            'autorizado_por' => $this->coordinacion[$carrera->carrera_clave]->id,
            'profesor_supervisor_id' => $supervisor?->id,
            'modalidad' => $modalidad,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
            'horas_requeridas' => $carrera->horas_requeridas,
            'estado' => $estado,
        ]);
    }

    /** Baja formal: el tipo sale del motivo y de los reportes validados, como en la coordinación. */
    private function baja(Practica $practica, string $motivo, string $detalle, Carbon $fecha): void
    {
        if ($practica->motivo_baja) {
            return;
        }
        $tipo = $practica->fresh()->resolverBaja($motivo);
        $practica->update([
            'motivo_baja' => $motivo, 'detalle_baja' => $detalle, 'tipo_baja' => $tipo,
            'conserva_horas' => $tipo === 'parcial_acreditada', 'fecha_baja' => $fecha,
        ]);
    }

    private function reporte(Practica $practica, int $numero, ReporteEstado $estado, int $horas, Carbon $inicio, Carbon $fin, bool $conArchivo,
        ?string $evaluacion = null, ?string $retroalimentacion = null, ?string $actividades = null): void
    {
        $reporte = Reporte::firstOrCreate(['practica_id' => $practica->id, 'numero' => $numero], [
            'tipo' => ReporteTipo::Mensual,
            'estado' => $estado,
            'horas_reportadas' => $horas,
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fin' => $fin->toDateString(),
            'actividades' => $actividades,
            'evaluacion_profesor' => $evaluacion,
            'retroalimentacion_empresa' => $retroalimentacion,
        ]);

        $conArchivo && $this->adjuntar($reporte, 'reporte_firmado', "reporte-{$numero}-firmado.pdf");
    }

    /** Aviso en la campana (sin correo), sin repetirlo si ya existe. */
    private function aviso(string $email, string $mensaje, string $url, ?string $detalle): void
    {
        $user = User::where('email', $email)->first();
        if ($user && !$user->notifications()->where('data->mensaje', $mensaje)->exists()) {
            $user->notify(new AvisoOperativo($mensaje, $url, $detalle));
        }
    }

    /** Adjunta un PDF ficticio solo si la colección está vacía (idempotente). */
    private function adjuntar($modelo, string $coleccion, string $nombre, ?ValidacionEstado $estado = null, ?string $motivo = null, ?Carbon $fechaEmision = null): void
    {
        if ($modelo->getMedia($coleccion)->isNotEmpty()) {
            return;
        }

        $media = $modelo->addMedia($this->archivo($nombre))->usingFileName($nombre)->toMediaCollection($coleccion);
        $fechaEmision && $media->setCustomProperty('fecha_emision', $fechaEmision->toDateString())->save();

        if ($estado) {
            $modelo->resolverDocumento($media, $estado, $motivo, $this->lorena->user_id);
        }
    }

    /** Archivo temporal con un PDF mínimo válido (o un .docx de relleno). */
    private function archivo(string $nombre): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'seed');

        if (str_ends_with($nombre, '.docx')) {
            // Word real (mínimo) para que se reconozca como .docx
            $doc = new \PhpOffice\PhpWord\PhpWord();
            $doc->addSection()->addText("Borrador de convenio de prueba: {$nombre}");
            \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007')->save($ruta);

            return $ruta;
        }

        file_put_contents($ruta, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n% Documento de prueba: {$nombre}\n");

        return $ruta;
    }
}
