<?php

namespace Database\Seeders;

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\Rol;
use App\Enums\SeguimientoEstado;
use App\Enums\TipoOrganismo;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConvenioSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $this->call([RolesSeeder::class, CarreraSeeder::class]);

        $lorena = Personal::firstOrCreate(
            ['user_id' => $this->usuario('admin@test.com', 'Administradora de Prácticas', Rol::Administrador)->id],
            ['puesto' => 'Coordinación de Prácticas Profesionales']
        );

        $abog = Carrera::where('carrera_clave', 'ABOG')->first();
        $crim = Carrera::where('carrera_clave', 'CRIM')->first();
        $hist = Carrera::where('carrera_clave', 'HIST')->first();

        if (! $abog || ! $crim || ! $hist) {
            return;
        }

        // 1. Solicitado (revisión de documentos)
        $c1 = $this->convenio(
            'ESO010101AB1', 'Bufete Jurídico Integral S.C.', TipoOrganismo::SociedadCivil, 'contacto1@test.com',
            ConvenioEstado::Solicitado, [$abog, $crim]
        );
        $this->etapa($c1, EtapaConvenio::RevisionDocumentos, SeguimientoEstado::Pendiente, now()->subDays(2), null, $lorena);

        // 2. En validación académica (dictámenes pendientes)
        $c2 = $this->convenio(
            'CEJ020202CD2', 'Comisión Estatal de Justicia', TipoOrganismo::Publico, 'contacto2@test.com',
            ConvenioEstado::EnValidacionAcademica, [$abog, $hist]
        );
        $this->etapa($c2, EtapaConvenio::RevisionDocumentos, SeguimientoEstado::Aprobado, now()->subDays(12), now()->subDays(10), $lorena);
        $this->etapa($c2, EtapaConvenio::TurnoAcademico, SeguimientoEstado::Pendiente, now()->subDays(10), null, $lorena);

        // 3. En revisión jurídica (excede espera documentada)
        $c3 = $this->convenio(
            'TEC030303EF3', 'Tecnologías Educativas del Occidente S.A. de C.V.', TipoOrganismo::Privado, 'contacto3@test.com',
            ConvenioEstado::EnRevisionJuridica, [$hist], [Dictamen::Aprobatorio]
        );
        $this->etapa($c3, EtapaConvenio::RevisionDocumentos, SeguimientoEstado::Aprobado, now()->subDays(50), now()->subDays(48), $lorena);
        $this->etapa($c3, EtapaConvenio::TurnoAcademico, SeguimientoEstado::Aprobado, now()->subDays(48), now()->subDays(30), $lorena);
        $this->etapa($c3, EtapaConvenio::RedaccionBorrador, SeguimientoEstado::Aprobado, now()->subDays(30), now()->subDays(28), $lorena);
        $this->etapa($c3, EtapaConvenio::DictamenJuridico, SeguimientoEstado::Pendiente, now()->subDays(28), null, $lorena);

        // 4. Formalizado y vigente
        $c4 = $this->convenio(
            'PJE040404GH4', 'Poder Judicial del Estado', TipoOrganismo::Publico, 'contacto4@test.com',
            ConvenioEstado::Formalizado, [$abog, $crim], [Dictamen::Aprobatorio, Dictamen::Aprobatorio],
            ['folio' => 'CV-2026-001', 'fecha_inicio_vigencia' => now()->subMonths(6), 'fecha_fin_vigencia' => now()->addYears(3)]
        );
        $this->etapa($c4, EtapaConvenio::ArchivoDistribucion, SeguimientoEstado::Aprobado, now()->subMonths(6), now()->subMonths(6), $lorena);

        // 5. Formalizado por vencer (< 60 días)
        $c5 = $this->convenio(
            'ACM050505IJ5', 'Asociación Civil Migrantes de Occidente', TipoOrganismo::SociedadCivil, 'contacto5@test.com',
            ConvenioEstado::Formalizado, [$hist], [Dictamen::Aprobatorio],
            ['folio' => 'CV-2023-014', 'fecha_inicio_vigencia' => now()->subYears(3)->addDays(30), 'fecha_fin_vigencia' => now()->addDays(30)]
        );

        // 6. Rechazado (todas las carreras en negativo)
        $c6 = $this->convenio(
            'NOT060606KL6', 'Notaría Pública No. 45', TipoOrganismo::Privado, 'contacto6@test.com',
            ConvenioEstado::Rechazado, [$hist], [Dictamen::Negativo]
        );
        $this->etapa($c6, EtapaConvenio::TurnoAcademico, SeguimientoEstado::Rechazado, now()->subDays(20), now()->subDays(5), $lorena, 'Todas las carreras emitieron dictamen negativo.');
    }

    /**
     * @param  Carrera[]  $carreras
     * @param  Dictamen[]  $dictamenes
     * @param  array<string,mixed>  $extra
     */
    private function convenio(
        string $rfc,
        string $razon,
        TipoOrganismo $tipo,
        string $email,
        ConvenioEstado $estado,
        array $carreras,
        array $dictamenes = [],
        array $extra = []
    ): Convenio {
        $user = $this->usuario($email, 'Contacto '.$razon, Rol::Empresa);

        $empresa = Empresa::firstOrCreate(['rfc' => $rfc], [
            'user_id' => $user->id,
            'razon_social' => $razon,
            'tipo_organismo' => $tipo,
            'contacto_nombre' => 'Contacto '.$razon,
        ]);

        $convenio = Convenio::firstOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'estado' => $estado,
                'fecha_recepcion' => now()->subDays(15),
                'objetivos' => 'Recibir practicantes de ciencias sociales y humanidades.',
                'actividades_propuestas' => 'Apoyo en archivo, atención a usuarios y elaboración de documentos.',
                'plazas_estimadas' => 5,
            ] + $extra
        );

        foreach ($carreras as $i => $carrera) {
            $dictamen = $dictamenes[$i] ?? Dictamen::Pendiente;

            $convenio->dictamenes()->firstOrCreate(
                ['carrera_id' => $carrera->id],
                [
                    'dictamen' => $dictamen,
                    'observaciones' => $dictamen === Dictamen::Negativo ? 'Sin actividades afines al perfil.' : null,
                    'fecha_dictamen' => $dictamen === Dictamen::Pendiente ? null : now()->subDays(10),
                ]
            );
        }

        return $convenio;
    }

    private function etapa(
        Convenio $convenio,
        EtapaConvenio $etapa,
        SeguimientoEstado $estado,
        $registro,
        $resolucion,
        Personal $por,
        ?string $comentario = null
    ): void {
        $convenio->seguimientos()->firstOrCreate(
            ['etapa' => $etapa->value, 'estado' => $estado->value],
            [
                'registrado_por' => $por->id,
                'comentario' => $comentario,
                'fecha_registro' => $registro->toDateString(),
                'fecha_resolucion' => $resolucion?->toDateString(),
            ]
        );
    }

    private function usuario(string $email, string $nombre, Rol $rol): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $nombre, 'password' => 'password123']);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->assignRole($rol->value);

        return $user;
    }
}