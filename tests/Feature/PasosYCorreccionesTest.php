<?php

/*
 * Solicitud de convenio en pasos sucesivos (con confirmación antes de seguir) y correcciones de datos que pide
 * Vinculación: solo esos campos se abren para la empresa, y cada parte recibe su aviso.
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\PostulacionEstado;
use App\Enums\ProgramaEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Programa;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Notifications\ConvenioActualizado;
use App\Services\ConvenioWorkflow;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function pcUsuarioPaso(Rol $rol, array $personal = []): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);
    if (in_array($rol, [Rol::Vinculacion, Rol::Coordinador], true)) {
        Personal::create(['user_id' => $u->id, 'puesto' => $rol->value] + $personal);
    }

    return $u->fresh();
}

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesSeeder::class);
    $division = Division::create(['nombre' => 'División de Prueba']);
    $this->carrera = Carrera::create(['division_id' => $division->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Abogado', 'horas_requeridas' => 300,
        'porcentaje_creditos_minimo' => 60, 'creditos_otorgados' => 8]);
    $this->lorena = pcUsuarioPaso(Rol::Vinculacion);
    $this->empresaUser = pcUsuarioPaso(Rol::Empresa);
    $this->empresa = Empresa::create(['user_id' => $this->empresaUser->id, 'rfc' => 'ABC010101XYZ', 'razon_social' => 'Organización Ejemplo S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Nombre Apellido']);
});

test('la solicitud va en orden y pide verificar los datos antes de seguir', function () {
    // Sin «Datos generales» completos no se guardan las «Declaraciones legales»
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'legales'), ['representante_legal' => 'Nombre Apellido Apellido'])
        ->assertSessionHasErrors(['convenio' => 'Primero completa «Datos generales y fiscales»: los pasos van en orden.']);
    expect($this->empresa->fresh()->representante_legal)->toBeNull();

    $this->actingAs($this->empresaUser)->get(route('empresa.convenio', ['seccion' => 'perfiles']))->assertOk()
        ->assertSee('Completa el paso anterior')
        ->assertSee('Todo bien: siguiente paso')
        ->assertSee('Antes de continuar, revisa que tus datos estén completos y bien escritos.');
});

test('Vinculación pide corregir datos: solo esos se abren, se corrigen y cada parte recibe su aviso', function () {
    solicitudLista($this->empresa, [$this->carrera->id]);
    $convenio = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Solicitado, 'fecha_recepcion' => today(),
        'objetivos' => 'Objetivos originales de la propuesta.', 'actividades_propuestas' => 'Actividades de la propuesta.', 'plazas_estimadas' => 2]);
    $convenio->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Pendiente]);
    $this->empresa->update(['solicitud_borrador' => null]);

    $this->actingAs($this->lorena)->post(route('admin.convenios.correcciones', $convenio), [
        'campos' => ['razon_social', 'objetivos'], 'motivo_correccion' => 'No coincide con la constancia fiscal.',
    ])->assertSessionHasNoErrors();

    // La empresa recibe correo y aviso con los datos a corregir
    Notification::assertSentTo($this->empresaUser, ConvenioActualizado::class, fn ($n) => $n->correo && str_contains($n->mensaje, 'razón social y objetivos'));
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()
        ->assertSee('Vinculación te pidió corregir 2 datos')->assertSee('No coincide con la constancia fiscal.');

    // Mientras no corrija, la solicitud no pasa a las carreras y las secciones siguen cerradas
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'generales'), ['razon_social' => 'Otra S.C.', 'contacto_nombre' => 'Nombre Apellido'])
        ->assertSessionHasErrors('convenio');

    // Corrige: solo se guardan los datos pedidos (lo demás se ignora)
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.corregir'), [
        'razon_social' => 'Organización Ejemplo Corregida S.C.', 'objetivos' => 'Objetivos corregidos según el perfil de la carrera.', 'giro' => 'Intento de cambio',
    ])->assertSessionHasNoErrors();

    $e = $this->empresa->fresh();
    expect($e->razon_social)->toBe('Organización Ejemplo Corregida S.C.')
        ->and($e->giro)->toBe('Servicios profesionales')
        ->and($convenio->fresh()->objetivos)->toBe('Objetivos corregidos según el perfil de la carrera.')
        ->and($convenio->fresh()->tieneCorreccionesPendientes())->toBeFalse();
    Notification::assertSentTo($this->lorena, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'corrigió') && str_contains($n->mensaje, 'Ya no tiene datos pendientes'));
});

test('con datos por corregir la solicitud no pasa a las carreras', function () {
    solicitudLista($this->empresa, [$this->carrera->id]);
    $convenio = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Solicitado, 'fecha_recepcion' => today(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1,
        'correcciones' => ['giro' => ['seccion' => 'generales', 'motivo' => 'Falta precisar.', 'pedido_at' => now()->toIso8601String(), 'corregido_at' => null]]]);

    expect(fn () => app(ConvenioWorkflow::class)->enviarAValidacionAcademica($convenio, $this->lorena->personal))
        ->toThrow(ValidationException::class, 'aún no corrige');
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::Solicitado);
});

test('si se rechaza una postulación con carta emitida, la organización recibe aviso', function () {
    $coord = pcUsuarioPaso(Rol::Coordinador, ['carrera_id' => $this->carrera->id]);
    $convenio = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-PC-1', 'fecha_recepcion' => now()->subYear(),
        'fecha_inicio_vigencia' => today()->subMonth(), 'fecha_fin_vigencia' => today()->addYear(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $convenio->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Aprobatorio]);
    $programa = Programa::create(['convenio_id' => $convenio->id, 'carrera_id' => $this->carrera->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Auxiliar jurídico', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p', 'cupos_totales' => 1, 'estado' => ProgramaEstado::Publicado]);
    $alumnoUser = pcUsuarioPaso(Rol::Alumno);
    $alumno = Alumno::create(['user_id' => $alumnoUser->id, 'carrera_id' => $this->carrera->id, 'num_control' => '219000001', 'grado' => 7, 'turno' => 'matutino']);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::CartaPresentacionEmitida,
        'porcentaje_creditos_declarado' => 80, 'fecha_postulacion' => today()]);

    $this->actingAs($coord)->post(route('coordinacion.postulaciones.rechazar', $po))->assertSessionHasNoErrors();

    Notification::assertSentTo($this->empresaUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'ya no se presentará'));
    Notification::assertSentTo($alumnoUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'fue rechazada'));
});
