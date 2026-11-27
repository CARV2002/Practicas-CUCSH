<?php

/*
 * Validaciones generales: teléfonos reales, fechas que existen y en orden lógico (turnos de Secretaría
 * Académica y División), mensajes de fecha legibles, colores de la solicitud (ámbar = falta, rojo = corregir)
 * y el nombre de la pestaña de dictamen en Coordinación.
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EtapaConvenio;
use App\Enums\Rol;
use App\Enums\SeguimientoEstado;
use App\Enums\TipoOrganismo;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioSeguimiento;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use App\Support\SolicitudEmpresa;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

function vgUsuario(Rol $rol, array $personal = []): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);
    if ($rol !== Rol::Empresa) {
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

    $this->empresaUser = vgUsuario(Rol::Empresa);
    $this->empresa = Empresa::create(['user_id' => $this->empresaUser->id, 'rfc' => 'ABC010101XYZ', 'razon_social' => 'Organización Ejemplo S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Nombre Apellido']);
});

test('el teléfono debe ser real: ni ceros, ni el mismo número repetido, ni empezar con 0 o 1', function () {
    $base = ['razon_social' => 'Organización Ejemplo S.C.', 'contacto_nombre' => 'Nombre Apellido'];

    foreach (['0000000000', '3300000000', '5555555555', '1234567890'] as $falso) {
        $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'generales'), $base + ['contacto_telefono' => $falso])
            ->assertSessionHasErrors(['contacto_telefono' => 'Escribe un teléfono real de 10 números (ej. 3312345678).']);
    }
    expect($this->empresa->fresh()->contacto_telefono)->toBeNull();

    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'generales'), $base + ['contacto_telefono' => '33 1234 5678'])
        ->assertSessionHasNoErrors();
    expect($this->empresa->fresh()->contacto_telefono)->toBe('3312345678');
});

test('los mensajes de fecha dicen «hoy» y la fecha en formato día/mes/año', function () {
    $v = Validator::make(
        ['vigencia_imss' => '2020-01-01', 'fecha_inicio' => '2030-01-01', 'periodo_inicio' => '2026-02-31'],
        ['vigencia_imss' => 'after:today', 'fecha_inicio' => 'before_or_equal:2026-11-20', 'periodo_inicio' => 'date'],
    );

    expect($v->errors()->first('vigencia_imss'))->toBe('Vigencia del IMSS: debe ser después de hoy.')
        ->and($v->errors()->first('fecha_inicio'))->toBe('Fecha de inicio: no puede ser después del 20/11/2026.')
        ->and($v->errors()->first('periodo_inicio'))->toBe('Inicio del periodo: esa fecha no existe. Revisa el día y el mes.');
});

test('turnos de Secretaría Académica y División: fechas reales o estimadas, desde el envío, con tope y en orden', function () {
    $lorena = vgUsuario(Rol::Vinculacion);
    $convenio = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnValidacionAcademica, 'fecha_recepcion' => today()->subMonth(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $convenio->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Pendiente]);
    ConvenioSeguimiento::create(['convenio_id' => $convenio->id, 'etapa' => EtapaConvenio::TurnoAcademico, 'estado' => SeguimientoEstado::Pendiente,
        'fecha_registro' => today()->subDays(10)]);
    $enviar = fn (array $datos) => $this->actingAs($lorena)->post(route('admin.convenios.turnos', $convenio), $datos);
    $envio = today()->subDays(10)->format('d/m/Y');

    $enviar(['fecha_turno_secretaria' => today()->subDays(11)->toDateString()])
        ->assertSessionHasErrors(['fecha_turno_secretaria' => "Secretaría Académica no pudo recibirlo antes de que lo enviaras a las carreras (el {$envio})."]);
    // Estimada: puede ir a futuro, pero no más de 90 días después del envío
    $tope = today()->subDays(10)->addDays(90)->format('d/m/Y');
    $enviar(['fecha_turno_secretaria' => today()->addDays(81)->toDateString()])
        ->assertSessionHasErrors(['fecha_turno_secretaria' => "Puede ser una fecha estimada, pero no más allá del {$tope} (90 días después del envío)."]);
    $enviar(['fecha_turno_secretaria' => '2026-02-31'])
        ->assertSessionHasErrors(['fecha_turno_secretaria' => 'Esa fecha no existe: elígela en el calendario.']);
    $enviar(['fecha_turno_division' => today()->subDays(2)->toDateString()])
        ->assertSessionHasErrors(['fecha_turno_secretaria' => 'Anota primero la fecha de Secretaría Académica: la División lo recibe después.']);
    $enviar(['fecha_turno_secretaria' => today()->subDays(3)->toDateString(), 'fecha_turno_division' => today()->subDays(5)->toDateString()])
        ->assertSessionHasErrors(['fecha_turno_division' => 'La División lo recibe el mismo día o después que Secretaría Académica.']);
    expect($convenio->fresh()->fecha_turno_secretaria)->toBeNull();

    $enviar(['fecha_turno_secretaria' => today()->subDays(8)->toDateString(), 'fecha_turno_division' => today()->subDays(6)->toDateString()])
        ->assertSessionHasNoErrors();
    expect($convenio->fresh()->fecha_turno_division->toDateString())->toBe(today()->subDays(6)->toDateString());

    // Fechas estimadas a futuro (aún no llega a la División)
    $enviar(['fecha_turno_secretaria' => today()->subDays(4)->toDateString(), 'fecha_turno_division' => today()->addDays(3)->toDateString()])
        ->assertSessionHasNoErrors();
    $this->actingAs($lorena)->get(route('admin.convenios.show', $convenio))->assertOk()
        ->assertSee('(estimada)')->assertSee('Usar fechas estimadas');
});

test('la solicitud separa lo que falta llenar (ámbar) de lo que hay que corregir (rojo)', function () {
    $this->empresa->update(['contacto_telefono' => '0000000000']);   // guardado antes de la regla nueva

    $estado = SolicitudEmpresa::estadoCampos($this->empresa->fresh())['generales'];
    expect($estado['corregir'])->toHaveKey('contacto_telefono')
        ->and($estado['vacios'])->toContain('descripcion')
        ->and($estado['vacios'])->not->toContain('contacto_telefono');

    $this->actingAs($this->empresaUser)->get(route('empresa.convenio', ['seccion' => 'generales']))->assertOk()
        ->assertSee('Corrige lo marcado en rojo')->assertSee('Escribe un teléfono real de 10 números')
        ->assertSee('Para enviar la solicitud te falta llenar');
});

test('Coordinación ve la pestaña «Dictamen de convenios»', function () {
    $coord = vgUsuario(Rol::Coordinador, ['carrera_id' => $this->carrera->id]);

    $this->actingAs($coord)->get(route('coordinacion.lista-dictamenes'))->assertOk()->assertSee('Dictamen de convenios');
});
