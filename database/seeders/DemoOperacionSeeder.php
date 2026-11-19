<?php

namespace Database\Seeders;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\Modalidad;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\ReporteTipo;
use App\Enums\Rol;
use App\Enums\SeguimientoEstado;
use App\Enums\TipoOrganismo;
use App\Enums\ValidacionEstado;
use App\Models\Alumno;
use App\Models\CartaTermino;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\OficioAcreditacion;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de OPERACIÓN para probar los tres paneles de punta a punta (solo entorno local).
 * Idempotente: cada registro se busca antes de crearse. Los PDF son ficticios (storage/app/private).
 *
 * Contraseña de todas las cuentas: password123
 */
class DemoOperacionSeeder extends Seeder
{
    private Personal $lorena;

    /** @var array<string, Personal> Coordinación de cada carrera demo (ABOG, CRIM, DEMO) */
    private array $coordinacion = [];

    /** @var \Illuminate\Support\Collection<string, Carrera> */
    private $carreras;

    /** @var array<string, Profesor> */
    private array $profes = [];

    public function run(): void
    {
        if (!app()->environment('local')) {
            return;
        }

        \App\Services\Auditoria::sinRegistro(fn() => $this->sembrar());
    }

    private function sembrar(): void
    {
        $this->carreras = Carrera::whereIn('carrera_clave', ['ABOG', 'CRIM', 'DEMO'])->get()->keyBy('carrera_clave');

        if ($this->carreras->count() < 3) {
            $this->command?->warn('Faltan las carreras ABOG, CRIM o DEMO. Corre CarreraSeeder y DatabaseSeeder primero.');

            return;
        }

        $this->lorena = $this->perfilLorena();
        $this->coordinacion = $this->perfilesCoordinacion();

        $this->profesores();
        $this->expedientesParciales();
        $this->convenioEnFirmas();

        $convenio = $this->convenioVigente();
        $p = $this->programas($convenio);

        $this->alumnosYPostulaciones($p);
        $this->usuarioInactivo();
        $this->avisosDemo();
        $this->documentosFisicosDemo();
    }


    /* -------------------------- Vinculación y coordinación -------------------------- */

    /** Lorena (Vinculación): gestiona y registra el trámite de convenios. */
    private function perfilLorena(): Personal
    {
        $this->call(PersonalSeeder::class);   // administrador, Vinculación y coordinadores demo

        return PersonalSeeder::vinculacion();
    }

    /** @return array<string, Personal> La coordinación de carrera valida, autoriza y acredita a sus alumnos. */
    private function perfilesCoordinacion(): array
    {
        return collect(array_keys(PersonalSeeder::COORDINADORES))
            ->mapWithKeys(fn(string $clave) => [$clave => PersonalSeeder::coordinador($clave)])
            ->all();
    }

    private function coordinacionDe(Alumno $alumno): Personal
    {
        $clave = $this->carreras->firstWhere('id', $alumno->carrera_id)->carrera_clave;

        return $this->coordinacion[$clave];
    }

    private function profesores(): void
    {
        $div = fn(string $clave) => $this->carreras[$clave]->division_id;

        $lista = [
            'juridico_1' => ['Dr. Ernesto Salcedo Rivas', $div('ABOG')],
            'juridico_2' => ['Mtra. Lucía Ornelas Prado', $div('ABOG')],
            'demo' => ['Mtro. Profesor Demo', $div('DEMO')],
        ];

        // Un profesor de OTRA división: sirve para probar el rechazo de supervisores inválidos
        $historia = Carrera::where('carrera_clave', 'HIST')->first();
        if ($historia) {
            $lista['historia'] = ['Dra. Marcela Ibarra Chávez', $historia->division_id];
        }

        foreach ($lista as $clave => [$nombre, $divisionId]) {
            $this->profes[$clave] = Profesor::firstOrCreate(
                ['nombre' => $nombre, 'division_id' => $divisionId],
                ['email' => str($nombre)->slug('.')->append('@academicos.demo')->toString()]
            );
        }
    }

    /** Expedientes a medio validar: para probar validar/rechazar documentos desde la bandeja. */
    private function expedientesParciales(): void
    {
        $empresa = Empresa::where('rfc', 'ESO010101AB1')->first(); // la crea ConvenioSeeder (convenio "solicitado")

        if (!$empresa) {
            return;
        }

        $this->adjuntar($empresa, 'cv_instancia', 'cv-instancia.pdf');
        $this->adjuntar($empresa, 'identificacion_representante', 'identificacion.pdf');
        $this->adjuntar(
            $empresa,
            'acta_constitutiva',
            'acta-constitutiva.pdf',
            ValidacionEstado::Rechazada,
            'El acta está ilegible; sube un escaneo a color.'
        );
    }

    /** Convenio en estado "en_firmas" (ConvenioSeeder no lo cubre). */
    private function convenioEnFirmas(): void
    {
        $empresa = $this->empresa('contacto7@test.com', 'EFI070707MN7', 'Instituto de Estudios Fiscales de Occidente', TipoOrganismo::Publico);
        $this->expedienteCompleto($empresa);

        $convenio = Convenio::firstOrCreate(['empresa_id' => $empresa->id], [
            'estado' => ConvenioEstado::EnFirmas,
            'gestionado_por' => $this->lorena->id,
            'fecha_recepcion' => now()->subDays(45)->toDateString(),
            'objetivos' => 'Recibir practicantes de derecho fiscal.',
            'actividades_propuestas' => 'Apoyo en consulta de expedientes y redacción de oficios.',
            'plazas_estimadas' => 3,
        ]);

        $this->dictamen($convenio, 'ABOG', Dictamen::Aprobatorio);
        $this->adjuntar($convenio, 'borrador_convenio', 'borrador-convenio.pdf');

        $etapas = [EtapaConvenio::RevisionDocumentos, EtapaConvenio::TurnoAcademico, EtapaConvenio::RedaccionBorrador, EtapaConvenio::DictamenJuridico];
        foreach ($etapas as $i => $etapa) {
            $this->seguimiento($convenio, $etapa, SeguimientoEstado::Aprobado, now()->subDays(45 - $i * 8), now()->subDays(38 - $i * 8));
        }
        $this->seguimiento($convenio, EtapaConvenio::FirmaEmpresa, SeguimientoEstado::Pendiente, now()->subDays(4), null, 'Imprimir 3 juegos y llamar a la empresa.');
    }

    /** Empresa con convenio formalizado y vigente (empresa@test.com): la que publica ofertas. */
    private function convenioVigente(): Convenio
    {
        $empresa = Empresa::where('rfc', 'DEM010101AAA')->firstOrFail(); // la crea DatabaseSeeder::datosDemo
        $this->expedienteCompleto($empresa);

        $convenio = Convenio::firstOrCreate(['empresa_id' => $empresa->id], [
            'estado' => ConvenioEstado::Formalizado,
            'gestionado_por' => $this->lorena->id,
            'folio' => 'CV-DEMO-001',
            'fecha_recepcion' => now()->subMonths(6)->toDateString(),
            'fecha_inicio_vigencia' => now()->subMonths(2)->toDateString(),
            'fecha_fin_vigencia' => now()->addYears(3)->toDateString(),
            'objetivos' => 'Recibir practicantes de Derecho, Criminología y la carrera demo.',
            'actividades_propuestas' => 'Apoyo en archivo, atención a usuarios y proyectos de investigación.',
            'plazas_estimadas' => 20,
        ]);

        // Las programas solo pueden ofertar carreras con dictamen aprobatorio
        foreach (['ABOG', 'CRIM', 'DEMO'] as $clave) {
            $this->dictamen($convenio, $clave, Dictamen::Aprobatorio);
        }

        $this->adjuntar($convenio, 'borrador_convenio', 'borrador-convenio.pdf');
        $this->adjuntar($convenio, 'convenio_firmado', 'convenio-firmado.pdf');

        foreach (EtapaConvenio::cases() as $i => $etapa) {
            $this->seguimiento($convenio, $etapa, SeguimientoEstado::Aprobado, now()->subMonths(6)->addDays($i * 8), now()->subMonths(6)->addDays($i * 8 + 6));
        }

        return $convenio;
    }

    /* ------------------------------- Programas ------------------------------ */

    /** @return array<string, Programa> */
    private function programas(Convenio $c): array
    {
        return [
            'abog_abierto' => $this->programa('Auxiliar jurídico en archivo judicial', 'ABOG', ProgramaEstado::Publicado, 6, $c),
            'abog_pendiente' => $this->programa('Apoyo en litigio civil (por aprobar)', 'ABOG', ProgramaEstado::PendienteAprobacion, 4, $c),
            'abog_cerrado' => $this->programa('Asistente de notaría (cerrado)', 'ABOG', ProgramaEstado::Cerrado, 3, $c, '2026B'),
            'abog_interno' => $this->programa('Proyecto interno: clínica jurídica', 'ABOG', ProgramaEstado::Publicado, 2, null),
            'crim_agotado' => $this->programa('Apoyo en defensoría pública', 'CRIM', ProgramaEstado::Publicado, 1, $c),
            'crim_abierto' => $this->programa('Analista de política criminal', 'CRIM', ProgramaEstado::Publicado, 3, $c),
            'demo_abierto' => $this->programa('Apoyo administrativo demo', 'DEMO', ProgramaEstado::Publicado, 3, $c),
            'demo_interno' => $this->programa('Proyecto interno demo', 'DEMO', ProgramaEstado::Publicado, 2, null),
        ];
    }

    private function programa(string $titulo, string $clave, ProgramaEstado $estado, int $cupos, ?Convenio $convenio, string $ciclo = '2027A'): Programa
    {
        $carrera = $this->carreras[$clave];

        return Programa::firstOrCreate(
            ['titulo' => $titulo, 'carrera_id' => $carrera->id, 'ciclo' => $ciclo],
            [
                'convenio_id' => $convenio?->id,
                'modalidad' => $convenio ? Modalidad::Externa : Modalidad::Interna,
                'descripcion' => 'Programa de demostración para pruebas del sistema.',
                'actividades' => ['Catalogar y archivar documentos', 'Atender consultas de usuarios', 'Elaborar reportes semanales'],
                'perfil_requerido' => 'Responsable, ordenado y con disposición de aprendizaje.',
                'horario' => 'Lunes a viernes, 9:00 a 13:00',
                'sede' => $convenio ? 'Guadalajara, Jalisco' : 'CUCSH, Zapopan',
                'cupos_totales' => $cupos,
                'estado' => $estado,
            ]
        );
    }

    /* ---------------------- Alumnos, postulaciones, prácticas ---------------------- */

    /** @param array<string, Programa> $p */
    private function alumnosYPostulaciones(array $p): void
    {
        $vigente = now()->addYear();

        // Regla: el expediente (kardex + IMSS) se valida ANTES de postularse. Nadie avanza sin él.

        // 1. Elegible: expediente validado y postulación pendiente de carta (+ una cancelada)
        $elegible = $this->alumno('alumno.elegible@test.com', 'Elena Elegible', 'ABOG', 'DEMO0002', $vigente, creditos: 72.5);
        $this->postulacion($elegible, $p['abog_abierto'], PostulacionEstado::Pendiente, 72.5);
        $this->postulacion($elegible, $p['abog_interno'], PostulacionEstado::Cancelada, 72.5);

        // 2. Carta de presentación emitida (falta carta de aceptación)
        $tramite = $this->alumno('alumno.tramite@test.com', 'Tomás Trámite', 'ABOG', 'DEMO0003', $vigente, creditos: 68);
        $this->postulacion($tramite, $p['abog_abierto'], PostulacionEstado::CartaPresentacionEmitida, 68, validada: true, presentacion: true);

        // 3. Carta de aceptación recibida: listo para "Activar práctica"
        $acepta = $this->alumno('alumno.aceptacion@test.com', 'Ana Aceptación', 'ABOG', 'DEMO0004', $vigente, creditos: 80);
        $this->postulacion($acepta, $p['abog_abierto'], PostulacionEstado::CartaAceptacionRecibida, 80, validada: true, presentacion: true, aceptacion: true);

        // 4. Restringido: envió su expediente con créditos insuficientes (30% < 50%); está en revisión
        //    y su coordinación debe devolverlo. No puede postularse mientras tanto.
        $this->alumno('alumno.restringido@test.com', 'Rafael Restringido', 'ABOG', 'DEMO0005', $vigente, creditos: 30, expediente: 'en_revision');

        // 5. Práctica EN CURSO con reportes
        $enCurso = $this->alumno('alumno.practica@test.com', 'Paula Práctica', 'ABOG', 'DEMO0006', $vigente, creditos: 85);
        $po = $this->postulacion($enCurso, $p['abog_abierto'], PostulacionEstado::Aceptada, 85, validada: true, presentacion: true, aceptacion: true);
        $practica = $this->practica($po, 'ABOG', PracticaEstado::EnCurso, now()->subMonths(3), now()->addMonths(3), $this->profes['juridico_1']);
        $this->reportesEnCurso($practica);

        // 6. Práctica CANCELADA
        $cancelada = $this->alumno('alumno.cancelada@test.com', 'Carlos Cancelado', 'ABOG', 'DEMO0007', $vigente, creditos: 60);
        $po = $this->postulacion($cancelada, $p['abog_abierto'], PostulacionEstado::Cancelada, 60, validada: true, presentacion: true, aceptacion: true);
        $practica = $this->practica($po, 'ABOG', PracticaEstado::Cancelada, now()->subMonths(4), now()->addMonths(2), $this->profes['juridico_2']);
        // Baja formal: la coordinación decidió conservar sus horas; su expediente sigue validado y puede postularse de nuevo
        $practica->update(['motivo_baja' => 'cambio_plaza', 'detalle_baja' => 'La organización cerró el área.', 'conserva_horas' => true]);
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Validado, 40, now()->subMonths(4), now()->subMonths(3), true, 'Buen inicio.');

        // 7. Práctica ACREDITADA (ciclo completo: carta de término + oficio + SIIAU). Ocupa el único cupo de crim_agotado.
        $acreditada = $this->alumno('alumno.acreditada@test.com', 'Alicia Acreditada', 'CRIM', 'DEMO0008', $vigente, creditos: 90);
        $po = $this->postulacion($acreditada, $p['crim_agotado'], PostulacionEstado::Aceptada, 90, validada: true, presentacion: true, aceptacion: true);
        $practica = $this->practica($po, 'CRIM', PracticaEstado::Acreditada, now()->subMonths(9), now()->subMonths(3), $this->profes['juridico_1']);
        $this->reportesAcreditada($practica);
    }

    private function reportesEnCurso(Practica $practica): void
    {
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Validado, 80, now()->subMonths(3), now()->subMonths(2), true, 'Cumplió las actividades.', 'Excelente actitud y puntualidad.');
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Validado, 75, now()->subMonths(2), now()->subMonths(1), true, 'Reporte completo.');
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Rechazado, 70, now()->subMonths(1), now(), true, 'Las horas no coinciden con la bitácora; corregir y reenviar.');
        $this->reporte($practica, ReporteTipo::Intermedio, ReporteEstado::Pendiente, 90, now()->subMonths(3), now(), true);          // entregado, por revisar
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Pendiente, 40, now()->startOfMonth(), now()->endOfMonth(), false); // sin archivo firmado
    }

    private function reportesAcreditada(Practica $practica): void
    {
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Validado, 110, now()->subMonths(9), now()->subMonths(7), true, 'Aprobado.', 'Muy buen desempeño.');
        $this->reporte($practica, ReporteTipo::Intermedio, ReporteEstado::Validado, 100, now()->subMonths(7), now()->subMonths(5), true, 'Aprobado.');
        $this->reporte($practica, ReporteTipo::Final , ReporteEstado::Validado, 90, now()->subMonths(5), now()->subMonths(3), true, 'Aprobado.');

        $carta = CartaTermino::firstOrCreate(['practica_id' => $practica->id], ['estado_validacion' => ValidacionEstado::Validada]);
        $this->adjuntar($carta, 'carta_termino', 'carta-termino.pdf', ValidacionEstado::Validada);

        $oficio = OficioAcreditacion::firstOrCreate(['practica_id' => $practica->id], [
            'emitido_por' => $this->coordinacion['CRIM']->id,   // el oficio lo emite la coordinación de la carrera
            'estado_validacion' => ValidacionEstado::Validada,
            'enviado_control_escolar_at' => now()->subMonths(2),
            'capturado_siiau_at' => now()->subMonths(2)->addDays(5),
        ]);
        $this->adjuntar($oficio, 'oficio_acreditacion', 'oficio-acreditacion.pdf', ValidacionEstado::Validada);
    }

    /** Papeles del trámite híbrido: control abierto y juegos con la empresa (uno ya vencido). */
    private function documentosFisicosDemo(): void
    {
        $enProceso = [
            ConvenioEstado::EnValidacionAcademica->value, ConvenioEstado::EnRevisionJuridica->value,
            ConvenioEstado::EnFirmas->value, ConvenioEstado::Formalizado->value,
        ];

        Convenio::whereIn('estado', $enProceso)->get()
            ->each(fn (Convenio $c) => $c->prepararDocumentosFisicos($this->lorena));

        Convenio::where('estado', ConvenioEstado::EnFirmas->value)->get()->each(function (Convenio $c) {
            $c->documentosFisicos()
                ->where('tipo', \App\Enums\TipoDocumentoFisico::JuegoConvenio->value)
                ->where('estado', \App\Enums\EstadoDocumentoFisico::PorElaborar->value)
                ->get()
                ->each(fn ($j, $i) => $j->mover(
                    \App\Enums\EstadoDocumentoFisico::ConEmpresa,
                    $this->lorena,
                    'Entregados al representante legal.',
                    $i === 0 ? today()->subDay() : null,
                    today()->subWeekdays(6),
                ));
        });
    }

    /**
     * Avisos de ejemplo para que la campana de cada panel no aparezca vacía en local.
     * En operación real los generan las acciones (solicitudes, dictámenes, validaciones...).
     */
    private function avisosDemo(): void
    {
        $avisos = [
            'vinculacion@test.com' => [
                ['Nueva solicitud de convenio: Bufete Jurídico Integral S.C.', '/admin/lista-convenios?estado=solicitado', 'Bufete Jurídico Integral S.C.'],
                ['La Coordinación de ABOG emitió dictamen aprobatorio para Comisión Estatal de Justicia. Faltan 1 carrera(s).', '/admin/lista-dictamenes', 'Comisión Estatal de Justicia'],
            ],
            'coord.abog@test.com' => [
                ['Empresa Demo S.A. de C.V. registró la vacante «Apoyo en litigio civil (por aprobar)». Revisa horario y actividades para aprobarla.', '/coordinacion/lista-programas?estado=pendiente_aprobacion', 'Apoyo en litigio civil'],
                ['Elena Elegible (ABOG) se postuló a «Auxiliar jurídico en archivo judicial». Valida créditos e IMSS.', '/coordinacion/lista-practicantes', 'Auxiliar jurídico en archivo judicial'],
            ],
            'empresa@test.com' => [
                ['Tu convenio quedó formalizado (folio CV-DEMO-001). Ya puedes proponer vacantes para las carreras aprobadas.', '/empresa/convenio', 'CV-DEMO-001'],
            ],
            'alumno.elegible@test.com' => [
                ['Tu coordinación validó tu constancia IMSS.', '/alumno/mis-documentos', null],
            ],
        ];

        foreach ($avisos as $email => $lista) {
            $user = User::where('email', $email)->first();
            if (!$user || $user->notifications()->exists()) {
                continue;   // idempotente
            }
            foreach ($lista as [$mensaje, $url, $detalle]) {
                // Solo campana (sin correo): AvisoOperativo con correo=false
                $user->notify(new \App\Notifications\AvisoOperativo($mensaje, $url, $detalle));
            }
        }
    }

    private function usuarioInactivo(): void
    {
        // Para probar el bloqueo de login de cuentas desactivadas
        $this->alumno('inactivo@test.com', 'Irene Inactiva', 'ABOG', 'DEMO0009', now()->addYear(), activo: false);
    }

    /* -------------------------------- Helpers -------------------------------- */

    private function usuario(string $email, string $nombre, Rol $rol, bool $activo = true): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => 'password123', 'activo' => $activo, 'aviso_privacidad_at' => now()]
        );

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->assignRole($rol->value);

        return $user;
    }

    private function empresa(string $email, string $rfc, string $razon, TipoOrganismo $tipo): Empresa
    {
        $user = $this->usuario($email, 'Contacto ' . $razon, Rol::Empresa);

        return Empresa::firstOrCreate(['rfc' => $rfc], [
            'user_id' => $user->id,
            'razon_social' => $razon,
            'tipo_organismo' => $tipo,
            'contacto_nombre' => 'Contacto ' . $razon,
            'representante_legal' => 'Representante de ' . $razon,
        ]);
    }

    private function expedienteCompleto(Empresa $empresa): void
    {
        foreach (Empresa::coleccionesObligatorias() as $coleccion) {
            $this->adjuntar($empresa, $coleccion, str_replace('_', '-', $coleccion) . '.pdf', ValidacionEstado::Validada);
        }
    }

    private function dictamen(Convenio $convenio, string $clave, Dictamen $resultado): void
    {
        $convenio->dictamenes()->firstOrCreate(['carrera_id' => $this->carreras[$clave]->id], [
            'dictamen' => $resultado,
            'dictaminado_por' => $this->lorena->id,
            'fecha_dictamen' => now()->subMonths(5)->toDateString(),
        ]);
    }

    private function seguimiento(Convenio $convenio, EtapaConvenio $etapa, SeguimientoEstado $estado, Carbon $registro, ?Carbon $resolucion, ?string $comentario = null): void
    {
        $convenio->seguimientos()->firstOrCreate(
            ['etapa' => $etapa->value, 'estado' => $estado->value],
            [
                'registrado_por' => $this->lorena->id,
                'comentario' => $comentario,
                'fecha_registro' => $registro->toDateString(),
                'fecha_resolucion' => $resolucion?->toDateString(),
            ]
        );
    }

    /**
     * Alumno con su expediente (kardex + IMSS), que se valida ANTES de postularse.
     * $expediente: validado | en_revision | devuelto | sin_enviar
     */
    private function alumno(string $email, string $nombre, string $clave, string $control, ?Carbon $imss,
        bool $activo = true, ?float $creditos = null, string $expediente = 'validado'): Alumno
    {
        $user = $this->usuario($email, $nombre, Rol::Alumno, $activo);

        $alumno = Alumno::firstOrCreate(['user_id' => $user->id], [
            'carrera_id' => $this->carreras[$clave]->id,
            'num_control' => $control,
            'grado' => 7,
            'turno' => \App\Enums\Turno::Matutino,
            'vigencia_imss' => $imss?->toDateString(),
            'porcentaje_creditos' => $creditos,
        ]);

        if ($imss && $expediente !== 'sin_enviar') {
            $estado = match ($expediente) {
                'validado' => ValidacionEstado::Validada,
                'devuelto' => ValidacionEstado::Rechazada,
                default => null,   // en revisión
            };
            $this->adjuntar($alumno, 'kardex', 'kardex.pdf', $estado, $expediente === 'devuelto' ? 'El kardex es de un ciclo anterior.' : null);
            $this->adjuntar($alumno, 'constancia_imss', 'constancia-imss.pdf', $expediente === 'devuelto' ? ValidacionEstado::Validada : $estado);
        }

        return $alumno;
    }

    private function postulacion(
        Alumno $alumno,
        Programa $programa,
        PostulacionEstado $estado,
        float $creditos,
        bool $validada = false,
        bool $presentacion = false,
        bool $aceptacion = false,
    ): Postulacion {
        $po = Postulacion::firstOrCreate(
            ['alumno_id' => $alumno->id, 'programa_id' => $programa->id],
            [
                'estado' => $estado,
                'validado_por' => $validada ? $this->coordinacionDe($alumno)->id : null,
                'porcentaje_creditos_declarado' => $creditos,
                'fecha_postulacion' => now()->subDays(20)->toDateString(),
                'siiau_confirmado_at' => now()->subDays(20),
            ]
        );

        // El kardex vive en el expediente del alumno (validado antes de postularse)
        $presentacion && $this->adjuntar($po, 'carta_presentacion', 'carta-presentacion.pdf');
        $aceptacion && $this->adjuntar($po, 'carta_aceptacion', 'carta-aceptacion.pdf');

        return $po;
    }

    private function practica(Postulacion $po, string $clave, PracticaEstado $estado, Carbon $inicio, Carbon $fin, ?Profesor $supervisor): Practica
    {
        return Practica::firstOrCreate(['postulacion_id' => $po->id], [
            'autorizado_por' => $this->coordinacion[$clave]->id,
            'profesor_supervisor_id' => $supervisor?->id,
            'modalidad' => Modalidad::Externa,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
            'horas_requeridas' => $this->carreras[$clave]->horas_requeridas,
            'estado' => $estado,
        ]);
    }

    private function reporte(
        Practica $practica,
        ReporteTipo $tipo,
        ReporteEstado $estado,
        int $horas,
        Carbon $inicio,
        Carbon $fin,
        bool $conArchivo,
        ?string $evaluacion = null,
        ?string $retroalimentacion = null,
    ): void {
        $reporte = Reporte::firstOrCreate(
            ['practica_id' => $practica->id, 'tipo' => $tipo->value, 'estado' => $estado->value, 'horas_reportadas' => $horas],
            [
                'periodo_inicio' => $inicio->toDateString(),
                'periodo_fin' => $fin->toDateString(),
                'evaluacion_profesor' => $evaluacion,
                'retroalimentacion_empresa' => $retroalimentacion,
            ]
        );

        $conArchivo && $this->adjuntar($reporte, 'reporte_firmado', "reporte-{$tipo->value}-{$horas}h.pdf");
    }

    /** Adjunta un PDF ficticio solo si la colección está vacía (idempotente). */
    private function adjuntar($modelo, string $coleccion, string $nombre, ?ValidacionEstado $estado = null, ?string $motivo = null): void
    {
        if ($modelo->getMedia($coleccion)->isNotEmpty()) {
            return;
        }

        $ruta = tempnam(sys_get_temp_dir(), 'demo');
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n% DOCUMENTO FICTICIO DE DEMOSTRACION: {$nombre}\n");

        $media = $modelo->addMedia($ruta)->usingFileName($nombre)->toMediaCollection($coleccion);

        if ($estado) {
            $modelo->resolverDocumento($media, $estado, $motivo, $this->lorena->user_id);
        }
    }
}