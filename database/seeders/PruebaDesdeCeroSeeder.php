<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Enums\Turno;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\User;
use App\Services\Auditoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Escenario limpio para recorrer TODO el proceso a mano, desde cero (solo en local):
 * una organización recién registrada (sin expediente ni convenio) y un alumno recién registrado
 * (sin kardex ni IMSS), los dos de la carrera de Abogado. Contraseña: password123.
 *
 *   php artisan db:seed --class=PruebaDesdeCeroSeeder
 *
 * Cada vez que se corre BORRA lo que hayan hecho esas dos cuentas (convenio, vacantes, postulación,
 * práctica, reportes, archivos y avisos) y las deja otra vez como recién registradas.
 *
 * Recorrido sugerido (cuentas de PersonalSeeder):
 *   1. empresa.prueba@test.com     Mi convenio: datos legales, expediente y solicitud.
 *   2. vinculacion@test.com        Revisa expediente, turna a ABOG, borrador, Jurídica, firmas, formaliza.
 *   3. coord.abog@test.com         Dictamen académico de la carrera.
 *   4. empresa.prueba@test.com     Publica una vacante para Abogado.
 *   5. alumno.prueba@test.com      Mis documentos: kardex e IMSS.
 *   6. coord.abog@test.com         Valida el expediente.
 *   7. alumno.prueba@test.com      Se postula a la vacante de la organización de prueba.
 *   8. coord.abog@test.com         Emite la carta de presentación (formato + PDF firmado y sellado).
 *   9. alumno.prueba@test.com      Sube la carta de aceptación.
 *  10. coord.abog@test.com         Valida y activa la práctica (asigna supervisor).
 *  11. alumno / empresa / coord.   Reportes, horas, carta de término, oficio y acreditación.
 */
class PruebaDesdeCeroSeeder extends Seeder
{
    public const EMPRESA_CORREO = 'empresa.prueba@test.com';

    public const ALUMNO_CORREO = 'alumno.prueba@test.com';

    public const EMPRESA_RFC = 'OPC260101AB1';

    public const ALUMNO_CONTROL = '229990001';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command->warn('PruebaDesdeCeroSeeder solo corre en local.');

            return;
        }

        // Personal (Vinculación y coordinaciones) y catálogo: idempotentes
        $this->call([RolesSeeder::class, CarreraSeeder::class, PersonalSeeder::class]);

        Auditoria::sinRegistro(fn () => DB::transaction(function () {
            $this->limpiar();
            $this->crearEmpresa();
            $this->crearAlumno();
        }));

        $this->command->table(['Cuenta', 'Correo', 'Contraseña', 'Arranca en'], [
            ['Organización', self::EMPRESA_CORREO, 'password123', 'Recién registrada: sin expediente ni convenio'],
            ['Alumno (ABOG)', self::ALUMNO_CORREO, 'password123', 'Recién registrado: sin kardex ni IMSS'],
            ['Vinculación', 'vinculacion@test.com', 'password123', 'Revisa y lleva el convenio'],
            ['Coordinación ABOG', 'coord.abog@test.com', 'password123', 'Dictamen, expediente, cartas y cierre'],
        ]);
    }

    /** Deja las dos cuentas como recién registradas, borrando todo lo que generaron (con sus archivos). */
    private function limpiar(): void
    {
        if ($alumno = Alumno::whereHas('user', fn ($q) => $q->where('email', self::ALUMNO_CORREO))->first()) {
            $alumno->postulaciones()->get()->each(fn (Postulacion $po) => $this->borrarPostulacion($po));
            $alumno->clearMediaCollection('kardex');
            $alumno->clearMediaCollection('constancia_imss');
            $alumno->delete();
        }

        if ($empresa = Empresa::withTrashed()->where('rfc', self::EMPRESA_RFC)
            ->orWhereHas('user', fn ($q) => $q->where('email', self::EMPRESA_CORREO))->first()) {
            Convenio::withTrashed()->where('empresa_id', $empresa->id)->get()->each(function (Convenio $c) {
                $c->programas()->get()->each(function ($programa) {
                    $programa->postulaciones()->get()->each(fn (Postulacion $po) => $this->borrarPostulacion($po));
                    $programa->delete();
                });
                $c->seguimientos()->get()->each->delete();
                $c->dictamenes()->get()->each->delete();
                $c->documentosFisicos()->get()->each->delete();
                $c->forceDelete();
            });
            $empresa->forceDelete();
        }

        User::whereIn('email', [self::EMPRESA_CORREO, self::ALUMNO_CORREO])->get()->each(function (User $u) {
            $u->notifications()->delete();
            $u->delete();
        });
    }

    private function borrarPostulacion(Postulacion $po): void
    {
        if ($practica = Practica::withTrashed()->where('postulacion_id', $po->id)->first()) {
            $practica->reportes()->get()->each->delete();
            $practica->cartaTermino()->first()?->delete();
            $practica->oficioAcreditacion()->first()?->delete();
            $practica->forceDelete();
        }
        $po->delete();
    }

    /** Igual que el registro público de organizaciones: solo la cuenta; lo demás se llena en «Mi convenio». */
    private function crearEmpresa(): void
    {
        $user = $this->usuario(self::EMPRESA_CORREO, 'Mariela Prueba Torres', Rol::Empresa);

        $user->empresa()->create([
            'rfc' => self::EMPRESA_RFC,
            'razon_social' => 'Organización de Prueba S.C.',
            'tipo_organismo' => TipoOrganismo::SociedadCivil,
            'contacto_nombre' => $user->name,
            'contacto_cargo' => 'Coordinadora de Recursos Humanos',
            'contacto_telefono' => '3312345678',
        ]);
    }

    /** Igual que el registro público de alumnos: carrera, código, grado y turno; sin documentos. */
    private function crearAlumno(): void
    {
        $user = $this->usuario(self::ALUMNO_CORREO, 'Alan Prueba Gómez', Rol::Alumno);

        $user->alumno()->create([
            'carrera_id' => Carrera::where('carrera_clave', 'ABOG')->value('id'),
            'num_control' => self::ALUMNO_CONTROL,
            'grado' => 7,
            'turno' => Turno::Matutino,
        ]);
    }

    private function usuario(string $email, string $nombre, Rol $rol): User
    {
        $user = User::create([
            'name' => $nombre,
            'email' => $email,
            'password' => 'password123',   // el cast "hashed" la encripta
            'aviso_privacidad_at' => now(),
        ]);
        $user->markEmailAsVerified();
        $user->assignRole($rol->value);

        return $user;
    }
}
