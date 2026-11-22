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
use Illuminate\Support\Facades\DB;
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
        $this->datosLegalesDemo();
        $this->documentosFisicosDemo();
        $this->completarDemo();
    }

    /**
     * Completa los datos de demostración para que cada pantalla tenga información en cada situación
     * (solo llena lo que falta; se puede correr varias veces):
     *  - contacto, teléfono, descripción y proyectos de cada organización;
     *  - quién dictaminó cada carrera (su coordinación, no Vinculación) y su dictamen firmado en PDF;
     *  - bitácora completa, borrador y convenio firmado de los convenios formalizados;
     *  - evidencia escaneada de cada firma y del dictamen jurídico.
     */
    private function completarDemo(): void
    {
        $contactos = [
            ['Coordinadora de Recursos Humanos', 'Brindamos servicios a la comunidad de Jalisco y abrimos espacios para que estudiantes del CUCSH apliquen lo que aprenden.'],
            ['Jefe de Vinculación', 'Institución con más de diez años de trabajo en su sector y experiencia recibiendo practicantes universitarios.'],
            ['Director Administrativo', 'Organización comprometida con la formación profesional de jóvenes en ciencias sociales y humanidades.'],
        ];

        $personas = [
            ['Mtra. Claudia Herrera Vázquez', 'Lic. Jorge Ramírez Lozano'],
            ['Lic. Patricia Navarro Gil', 'Mtro. Arturo Medina Castañeda'],
            ['Lic. Sofía Delgado Ibarra', 'Dr. Raúl Fonseca Aguirre'],
            ['Ing. Daniela Ruvalcaba Ortiz', 'Lic. Héctor Villalobos Mora'],
        ];

        // Solo organizaciones de prueba (@test.com): nunca toca las que se registren de verdad
        Empresa::with('user')->whereHas('user', fn ($u) => $u->where('email', 'like', '%@test.com'))->get()->each(function (Empresa $e, int $i) use ($contactos, $personas) {
            [$cargo, $descripcion] = $contactos[$i % count($contactos)];
            [$contacto, $representante] = $personas[$i % count($personas)];

            // Nombres de relleno («Contacto …», «Representante de …») → nombres de persona
            if (str_starts_with((string) $e->representante_legal, 'Representante de ') || blank($e->representante_legal)) {
                $e->representante_legal = $representante;
            }
            if (str_starts_with((string) $e->contacto_nombre, 'Contacto ')) {
                $e->contacto_nombre = $contacto;
            }
            if ($e->user && str_starts_with($e->user->name, 'Contacto ')) {
                $e->user->update(['name' => $contacto]);
            }
            $e->save();
            $e->update(array_filter([
                'contacto_cargo' => $e->contacto_cargo ?: $cargo,
                'contacto_telefono' => $e->contacto_telefono ?: '33' . str_pad((string) (31000000 + $e->id * 1371), 8, '0', STR_PAD_LEFT),
                'descripcion' => $e->descripcion ?: $descripcion,
                'proyectos_principales' => $e->proyectos_principales ?: 'Atención al público, gestión documental y proyectos de investigación aplicada.',
            ]));

            // Solicitud por secciones: giro, domicilio desglosado y origen de las facultades de quien firma
            $escritura = \App\Support\DatosNotariales::usaEscritura($e->tipo_organismo);
            if (blank($e->calle)) {
                $e->update([
                    'giro' => $escritura ? 'Servicios profesionales' : 'Administración pública',
                    'calle' => 'Av. Juárez', 'numero_exterior' => (string) (900 + $e->id * 7), 'colonia' => 'Centro',
                    'codigo_postal' => '44100', 'municipio' => 'Guadalajara', 'entidad' => 'Jalisco',
                ]);
                $e->update(['domicilio_fiscal' => $e->domicilioCompleto()]);
            }
            if (blank($e->facultad_origen)) {
                $e->update($escritura
                    ? ['facultad_origen' => 'acta', 'facultad_firmante' => 'las facultades que le otorga la escritura constitutiva citada en la declaración I']
                    : ['facultad_origen' => 'nombramiento', 'facultad_firmante' => 'el nombramiento de fecha 1 de enero de 2025 expedido por el titular de la dependencia']);
            }

            // Las dependencias públicas acreditan su existencia con su documento de creación y el nombramiento del titular
            if (!$escritura) {
                $enRevision = $e->convenios()->where('estado', ConvenioEstado::Solicitado->value)->exists();
                $estado = $enRevision ? null : ValidacionEstado::Validada;
                $this->adjuntar($e, 'documento_creacion', 'decreto-de-creacion.pdf', $estado);
                $this->adjuntar($e, 'nombramiento_representante', 'nombramiento-titular.pdf', $estado);
            }

            // Quien ya pasó la revisión de documentos tiene su expediente completo y validado
            $avanzado = $e->convenios()->whereNotIn('estado', [ConvenioEstado::Solicitado->value, ConvenioEstado::Rechazado->value])->exists();
            if ($avanzado) {
                foreach ($e->obligatorias() as $col) {
                    $this->adjuntar($e->load('media'), $col, str_replace('_', '-', $col) . '.pdf', ValidacionEstado::Validada);
                    $m = $e->load('media')->documentoVigente($col);
                    if ($m->getCustomProperty('estado') !== ValidacionEstado::Validada->value) {
                        $e->resolverDocumento($m, ValidacionEstado::Validada, null, $this->lorena->user_id);
                    }
                    if (in_array($col, Empresa::CON_VIGENCIA, true)) {
                        $m->getCustomProperty('fecha_emision') || $m->setCustomProperty('fecha_emision', $m->created_at->copy()->subWeeks(2)->toDateString())->save();
                    }
                }
            }
        });

        Convenio::with(['dictamenes.carrera', 'seguimientos', 'empresa'])
            ->whereHas('empresa.user', fn ($u) => $u->where('email', 'like', '%@test.com'))   // solo convenios de prueba
            ->get()->each(function (Convenio $c) {
            // Dictámenes: los emite la coordinación de cada carrera, con su PDF firmado y sellado
            foreach ($c->dictamenes->where('dictamen', '!=', Dictamen::Pendiente) as $d) {
                $coordinacion = Personal::where('carrera_id', $d->carrera_id)->first()
                    ?? Personal::where('division_id', $d->carrera->division_id)->whereNull('carrera_id')->first();
                $d->update(array_filter([
                    'dictaminado_por' => $coordinacion?->id ?? $d->dictaminado_por,
                    'fecha_dictamen' => $d->fecha_dictamen ?? $c->fecha_recepcion?->copy()->addDays(10),
                    'observaciones' => $d->dictamen === Dictamen::Negativo && blank($d->observaciones)
                        ? 'Las actividades propuestas no corresponden al perfil de egreso de la carrera.' : null,
                ]));
                $this->adjuntar($d, 'dictamen_pertinencia', "dictamen-{$d->carrera->carrera_clave}-{$c->id}.pdf");
            }

            // Formalizados: bitácora completa antes del inicio de vigencia, borrador y convenio firmado
            if ($c->estado === ConvenioEstado::Formalizado) {
                $inicio = ($c->fecha_inicio_vigencia ?? now())->copy();
                foreach (EtapaConvenio::cases() as $i => $etapa) {
                    if (!$c->seguimientos->contains(fn ($s) => $s->etapa === $etapa)) {
                        $registro = $inicio->copy()->subDays(70 - $i * 10);
                        $this->seguimiento($c, $etapa, SeguimientoEstado::Aprobado, $registro, $registro->copy()->addDays(6));
                    }
                }
                $this->adjuntar($c, 'borrador_convenio', 'borrador-convenio.pdf');
                $this->adjuntar($c, 'convenio_firmado', 'convenio-firmado.pdf');
            }

            // Rechazado: antes del dictamen, Vinculación aprobó los documentos
            if ($c->estado === ConvenioEstado::Rechazado && !$c->seguimientos->contains(fn ($s) => $s->etapa === EtapaConvenio::RevisionDocumentos)) {
                $primera = $c->seguimientos->min('fecha_registro');
                $registro = ($primera ? $primera->copy()->subDays(4) : ($c->fecha_recepcion ?? now()->subMonth())->copy());
                $this->seguimiento($c, EtapaConvenio::RevisionDocumentos, SeguimientoEstado::Aprobado, $registro, $registro->copy()->addDays(3));
            }

            // Evidencias en la bitácora: firmas (empresa y Rectoría) y dictamen jurídico
            foreach ($c->seguimientos()->where('estado', '!=', SeguimientoEstado::Pendiente->value)->get() as $s) {
                $archivo = match ($s->etapa) {
                    EtapaConvenio::FirmaEmpresa => 'firma-empresa-ultima-hoja.pdf',
                    EtapaConvenio::FirmaRectoria => 'convenio-firmado-rectoria.pdf',
                    EtapaConvenio::DictamenJuridico => 'dictamen-juridico.pdf',
                    default => null,
                };
                if ($archivo) {
                    $this->adjuntar($s, 'documento_etapa', $archivo);
                }
            }
        });
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

        // 4. Restringido (paso 1): subió una constancia IMSS vencida y su coordinación se la devolvió.
        //    No tiene postulaciones: nadie se postula sin su expediente validado.
        $this->alumno('alumno.restringido@test.com', 'Rafael Restringido', 'ABOG', 'DEMO0005', now()->subMonths(2), creditos: 62, expediente: 'imss_vencido');

        // 5. Práctica EN CURSO con reportes
        $enCurso = $this->alumno('alumno.practica@test.com', 'Paula Práctica', 'ABOG', 'DEMO0006', $vigente, creditos: 85);
        $po = $this->postulacion($enCurso, $p['abog_abierto'], PostulacionEstado::Aceptada, 85, validada: true, presentacion: true, aceptacion: true);
        $practica = $this->practica($po, 'ABOG', PracticaEstado::EnCurso, now()->subMonths(3), now()->addMonths(3), $this->profes['juridico_1']);
        $this->reportesEnCurso($practica);

        // 6. Práctica CANCELADA
        $cancelada = $this->alumno('alumno.cancelada@test.com', 'Carlos Cancelado', 'ABOG', 'DEMO0007', $vigente, creditos: 60);
        $po = $this->postulacion($cancelada, $p['abog_abierto'], PostulacionEstado::Cancelada, 60, validada: true, presentacion: true, aceptacion: true);
        $practica = $this->practica($po, 'ABOG', PracticaEstado::Cancelada, now()->subMonths(4), now()->addMonths(2), $this->profes['juridico_2']);
        // Baja parcial acreditada (cierre de la organización con un reporte validado): conserva sus 40 h,
        // su expediente sigue validado y puede postularse de nuevo (1 de 2 bajas)
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Validado, 40, now()->subMonths(4), now()->subMonths(3), true, 'Buen inicio.');
        $this->baja($practica, 'cierre_empresa', 'La organización cerró el área donde estaba el alumno.', now()->subMonths(2));

        // 6b. Bloqueado por límite: dos bajas (una sin reportes y otra parcial) → ya no puede postularse por su cuenta
        $bloqueado = $this->alumno('alumno.bloqueado@test.com', 'Bruno Bloqueado', 'ABOG', 'DEMO0010', $vigente, creditos: 75);
        $po = $this->postulacion($bloqueado, $p['abog_cerrado'], PostulacionEstado::Cancelada, 75, validada: true, presentacion: true, aceptacion: true);
        $practica = $this->practica($po, 'ABOG', PracticaEstado::Cancelada, now()->subMonths(8), now()->subMonths(4), $this->profes['juridico_1']);
        $this->baja($practica, 'cambio_proyecto', 'El proyecto terminó antes de su primer reporte.', now()->subMonths(7));
        $po = $this->postulacion($bloqueado, $p['abog_interno'], PostulacionEstado::Cancelada, 75, validada: true, presentacion: true, aceptacion: true);
        $practica = $this->practica($po, 'ABOG', PracticaEstado::Cancelada, now()->subMonths(5), now()->addMonth(), $this->profes['juridico_2']);
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Validado, 60, now()->subMonths(5), now()->subMonths(4), true, 'Cumplido.');
        $this->baja($practica, 'mutuo_acuerdo', 'El área receptora y el alumno acordaron terminar la práctica.', now()->subWeeks(3));

        // 6c. Bloqueado por sanción: abandono injustificado → horas invalidadas y bloqueo hasta que la coordinación lo levante
        $sancion = $this->alumno('alumno.sancion@test.com', 'Sergio Sancionado', 'CRIM', 'DEMO0011', $vigente, creditos: 70);
        $po = $this->postulacion($sancion, $p['crim_abierto'], PostulacionEstado::Cancelada, 70, validada: true, presentacion: true, aceptacion: true);
        $practica = $this->practica($po, 'CRIM', PracticaEstado::Cancelada, now()->subMonths(4), now()->addMonths(2), $this->profes['juridico_1']);
        $this->reporte($practica, ReporteTipo::Mensual, ReporteEstado::Validado, 35, now()->subMonths(4), now()->subMonths(3), true, 'Regular.');
        $this->baja($practica, 'abandono', 'La organización reportó que el alumno dejó de asistir sin avisar.', now()->subWeeks(2));

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
    /**
     * Declaraciones y datos notariales de las organizaciones de prueba (solo llena lo que falta).
     * Sociedades y asociaciones acreditan su existencia con escritura pública; las dependencias públicas, con su decreto.
     */
    private function datosLegalesDemo(): void
    {
        $notarios = [
            ['Lic. Ricardo Alatorre Gómez', '12', 'Guadalajara, Jalisco'],
            ['Lic. Mariana Ochoa Villaseñor', '45', 'Zapopan, Jalisco'],
            ['Lic. Fernando Íñiguez Ruiz', '7', 'Tlaquepaque, Jalisco'],
        ];

        // Solo organizaciones de prueba (@test.com): nunca toca las que se registren de verdad
        \App\Models\Empresa::whereHas('user', fn ($u) => $u->where('email', 'like', '%@test.com'))->get()->each(function (\App\Models\Empresa $e, int $i) use ($notarios) {
            $base = array_filter([
                'representante_cargo' => $e->representante_cargo ?: 'Director General',
                'domicilio_fiscal' => $e->domicilio_fiscal ?: 'Av. Juárez 976, Col. Centro, C.P. 44100, Guadalajara, Jalisco',
                'objeto_social' => $e->objeto_social ?: 'Prestar servicios profesionales relacionados con su giro y colaborar con instituciones educativas en la formación de practicantes.',
                'facultad_firmante' => $e->facultad_firmante ?: 'Cuenta con poder general para actos de administración, que no le ha sido revocado ni limitado a la fecha.',
            ]);

            if (\App\Support\DatosNotariales::usaEscritura($e->tipo_organismo)) {
                if (blank($e->escritura_numero)) {
                    [$notario, $notaria, $ciudad] = $notarios[$i % count($notarios)];
                    $base = \App\Support\DatosNotariales::preparar($base + [
                        'escritura_numero' => (string) (10200 + $e->id * 137),
                        'escritura_fecha' => now()->subYears(6 + $e->id)->startOfMonth()->toDateString(),
                        'notario_nombre' => $notario,
                        'notaria_numero' => $notaria,
                        'notaria_ciudad' => $ciudad,
                        'folio_mercantil' => $e->tipo_organismo === \App\Enums\TipoOrganismo::Privado ? 'N-' . (2010 + $e->id) . '0' . (4500 + $e->id) : null,
                    ], $e->tipo_organismo);
                }
            } elseif (blank($e->constitucion)) {
                $base['constitucion'] = 'el decreto publicado en el Periódico Oficial «El Estado de Jalisco» el ' . now()->subYears(10 + $e->id)->locale('es')->translatedFormat('j \d\e F \d\e Y');
            }

            $e->update($base);
        });
    }
    private function avisosDemo(): void
    {
        $validado = 'Tu coordinación validó tu kardex y tu constancia IMSS. ¡Ya puedes postularte a una vacante de tu carrera!';
        $avisos = [
            'vinculacion@test.com' => [
                ['Nueva solicitud de convenio: Bufete Jurídico Integral S.C.', '/admin/lista-convenios?estado=solicitado', 'Bufete Jurídico Integral S.C.'],
                ['La Coordinación de ABOG emitió dictamen aprobatorio para Comisión Estatal de Justicia. Faltan 1 carrera(s).', '/admin/lista-dictamenes', 'Comisión Estatal de Justicia'],
            ],
            'coord.abog@test.com' => [
                ['Empresa Demo S.A. de C.V. registró la vacante «Apoyo en litigio civil (por aprobar)». Revisa horario y actividades para aprobarla.', '/coordinacion/lista-programas?estado=pendiente_aprobacion', 'Apoyo en litigio civil'],
                ['Elena Elegible (ABOG) se postuló a «Auxiliar jurídico en archivo judicial». Su kardex e IMSS ya están validados: emite su carta de presentación.', '/coordinacion/lista-practicantes', 'Auxiliar jurídico en archivo judicial'],
                ['Rafael Restringido (ABOG) envió su kardex y su constancia IMSS para revisión.', '/coordinacion/lista-practicantes', null],
                ['Bruno Bloqueado (ABOG) llegó al límite de 2 bajas: ya no puede postularse sin un desbloqueo excepcional.', '/coordinacion/lista-practicantes', null],
            ],
            'coord.crim@test.com' => [
                ['La práctica de Sergio Sancionado se registró como baja por sanción (abandono injustificado). Quedó bloqueado hasta que levantes la restricción.', '/coordinacion/lista-practicantes', null],
            ],
            'empresa@test.com' => [
                ['Tu convenio quedó formalizado (folio CV-DEMO-001). Ya puedes proponer vacantes para las carreras aprobadas.', '/empresa/convenio', 'CV-DEMO-001'],
            ],
            'alumno.elegible@test.com' => [
                [$validado, '/alumno/oferta-programas', null],
            ],
            'alumno.tramite@test.com' => [
                [$validado, '/alumno/oferta-programas', null],
                ['Tu carta de presentación para «Auxiliar jurídico en archivo judicial» está generada: descárgala, preséntala en la organización y entrega tu carta de aceptación.', '/alumno/mis-documentos', null],
            ],
            'alumno.aceptacion@test.com' => [
                [$validado, '/alumno/oferta-programas', null],
                ['Tu carta de presentación para «Auxiliar jurídico en archivo judicial» está generada: descárgala, preséntala en la organización y entrega tu carta de aceptación.', '/alumno/mis-documentos', null],
                ['Tu coordinación registró tu carta de aceptación. Activará tu práctica en breve y te avisaremos cuando puedas reportar horas.', '/alumno/mis-documentos', null],
            ],
            'alumno.restringido@test.com' => [
                ['Tu coordinación te devolvió tu constancia IMSS: está vencida. Sube una vigente en Mis documentos para que revise tu expediente y puedas postularte.', '/alumno/mis-documentos', null],
            ],
            'alumno.practica@test.com' => [
                ['Tu práctica en «Auxiliar jurídico en archivo judicial» está activa. Entrega tus reportes firmados a tiempo para que cuenten tus horas.', '/alumno/mis-practicas', null],
            ],
            'alumno.cancelada@test.com' => [
                ['Tu práctica en «Auxiliar jurídico en archivo judicial» se dio de baja (Cierre o baja de la organización). Conservas tus 40 horas validadas: en tu siguiente plaza solo cubrirás las restantes. Ya puedes postularte a otra vacante.', '/alumno/mis-documentos', null],
            ],
            'alumno.bloqueado@test.com' => [
                ['Tu práctica en «Proyecto interno: clínica jurídica» se dio de baja (Mutuo acuerdo). Llegaste al límite de 2 bajas: acude a tu Coordinación de Carrera si tuviste una causa de fuerza mayor.', '/alumno/mis-documentos', null],
            ],
            'alumno.sancion@test.com' => [
                ['Tu práctica en «Analista de política criminal» se dio de baja por sanción (Abandono injustificado). Tus horas no se conservan y no puedes postularte hasta que tu coordinación levante la restricción.', '/alumno/mis-documentos', null],
            ],
            'alumno.acreditada@test.com' => [
                ['¡Tu práctica profesional quedó acreditada! Control Escolar capturó tu acreditación en SIIAU.', '/alumno/mis-practicas', null],
            ],
        ];

        // Avisos de demostración que ya no corresponden a las reglas vigentes
        DB::table('notifications')->whereIn('data->mensaje', [
            'Tu coordinación validó tu constancia IMSS.',
            'Elena Elegible (ABOG) se postuló a «Auxiliar jurídico en archivo judicial». Valida créditos e IMSS.',
        ])->delete();

        foreach ($avisos as $email => $lista) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                continue;
            }
            foreach ($lista as [$mensaje, $url, $detalle]) {
                if ($user->notifications()->where('data->mensaje', $mensaje)->exists()) {
                    continue;   // idempotente
                }
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
            // Dictamina la coordinación de la carrera (Vinculación solo da seguimiento)
            'dictaminado_por' => Personal::where('carrera_id', $this->carreras[$clave]->id)->value('id') ?? $this->lorena->id,
            'fecha_dictamen' => now()->subMonths(5)->toDateString(),
        ]);
    }

    private function seguimiento(Convenio $convenio, EtapaConvenio $etapa, SeguimientoEstado $estado, Carbon $registro, ?Carbon $resolucion, ?string $comentario = null): void
    {
        // Una etapa pendiente no se reabre si ya se trabajó en pantalla (volver a sembrar no la duplica)
        if ($estado === SeguimientoEstado::Pendiente && $convenio->seguimientos()->where('etapa', $etapa->value)->exists()) {
            return;
        }

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
     * $expediente: validado | en_revision | devuelto | imss_vencido | sin_enviar
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

        if ($expediente === 'imss_vencido') {
            // Subió una constancia ya vencida: la coordinación se la devolvió; el kardex espera revisión
            $this->adjuntar($alumno, 'kardex', 'kardex.pdf');
            $this->adjuntar($alumno, 'constancia_imss', 'constancia-imss.pdf', ValidacionEstado::Rechazada,
                'La constancia está vencida (vigencia al ' . $imss?->format('d/m/Y') . '). Descarga una vigente en el portal del IMSS.');
        } elseif ($imss && $expediente !== 'sin_enviar') {
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

    /** Baja formal: el tipo sale del motivo y de los reportes validados, como en la coordinación. */
    private function baja(Practica $practica, string $motivo, string $detalle, Carbon $fecha): void
    {
        $tipo = $practica->fresh()->resolverBaja($motivo);
        $practica->update([
            'motivo_baja' => $motivo,
            'detalle_baja' => $detalle,
            'tipo_baja' => $tipo,
            'conserva_horas' => $tipo === 'parcial_acreditada',
            'fecha_baja' => $fecha,
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