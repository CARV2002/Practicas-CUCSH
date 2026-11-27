<?php

/*
 * Vinculación ve el trámite en orden (cada sección aparece en su etapa) y sabe cuándo una solicitud es un
 * reenvío tras un rechazo o una renovación: en la lista, en el detalle, en el estado y en su aviso.
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
use App\Notifications\NuevaSolicitudConvenio;
use App\Services\ConvenioWorkflow;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesSeeder::class);
    $division = Division::create(['nombre' => 'División de Prueba']);
    $this->carrera = Carrera::create(['division_id' => $division->id, 'carrera_clave' => 'LETR', 'nombre' => 'Letras', 'horas_requeridas' => 300,
        'porcentaje_creditos_minimo' => 60, 'creditos_otorgados' => 8]);

    $this->lorena = User::factory()->create();
    $this->lorena->assignRole(Rol::Vinculacion->value);
    Personal::create(['user_id' => $this->lorena->id, 'puesto' => 'Vinculación']);

    $u = User::factory()->create();
    $u->assignRole(Rol::Empresa->value);
    $this->empresa = Empresa::create(['user_id' => $u->id, 'rfc' => 'ABC010101XYZ', 'razon_social' => 'Organización Ejemplo S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Nombre Apellido']);

    $this->convenio = fn (ConvenioEstado $estado, array $extra = []) => Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => $estado,
        'fecha_recepcion' => today()->subWeek(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1] + $extra);
});

test('un reenvío tras el rechazo del dictamen académico se identifica en el detalle, la lista y el estado', function () {
    $anterior = ($this->convenio)(ConvenioEstado::Rechazado);
    $anterior->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Negativo, 'observaciones' => 'Sin actividades afines.']);
    ConvenioSeguimiento::create(['convenio_id' => $anterior->id, 'etapa' => EtapaConvenio::TurnoAcademico, 'estado' => SeguimientoEstado::Rechazado,
        'comentario' => 'Todas las carreras emitieron dictamen negativo.', 'fecha_registro' => today()->subWeek(), 'fecha_resolucion' => today()->subWeek()]);
    $nuevo = ($this->convenio)(ConvenioEstado::Solicitado);
    $nuevo->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Pendiente]);

    expect($nuevo->antecedente()['tipo'])->toBe('reenvio')
        ->and($nuevo->antecedente()['carrerasNegativas'])->toBe(['LETR'])
        ->and($nuevo->estadoDetallado()['texto'])->toBe('Expediente incompleto');   // sin documentos aún: le toca a la empresa

    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $nuevo))->assertOk()
        ->assertSee('Reenvío: la organización vuelve a solicitar el convenio')
        ->assertSee('el dictamen académico de las coordinaciones')->assertSee('no aprobaron: LETR')
        ->assertSee('Todas las carreras emitieron dictamen negativo.')
        // Pasos en orden: en revisión de documentos todavía no aparece el dictamen académico
        ->assertDontSee('Dictamen académico por carrera');

    $this->actingAs($this->lorena)->get(route('admin.lista-convenios'))->assertOk()->assertSee('Reenvío tras rechazo');
    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $anterior))->assertOk()
        ->assertSee('Trámite cerrado como rechazado: «Todas las carreras emitieron dictamen negativo.»');
});

test('el dictamen académico aparece al turnar a las carreras', function () {
    $c = ($this->convenio)(ConvenioEstado::EnValidacionAcademica);
    $c->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Pendiente]);

    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $c))->assertOk()->assertSee('Dictamen académico por carrera');
});

test('el aviso a Vinculación dice si es reenvío o renovación', function () {
    solicitudLista($this->empresa, [$this->carrera->id]);
    ($this->convenio)(ConvenioEstado::Rechazado);

    $nuevo = app(ConvenioWorkflow::class)->solicitar($this->empresa->fresh(), $this->empresa->fresh()->solicitud_borrador, [$this->carrera->id]);

    Notification::assertSentTo($this->lorena, NuevaSolicitudConvenio::class, fn ($n) => str_contains($n->toArray($this->lorena)['mensaje'], 'volvió a enviar su solicitud'));

    // Renovación: la anterior quedó formalizada
    $nuevo->update(['estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-2026-001', 'fecha_inicio_vigencia' => today()->subYear(), 'fecha_fin_vigencia' => today()->addDays(30)]);
    $renovacion = ($this->convenio)(ConvenioEstado::Solicitado);
    expect((new NuevaSolicitudConvenio($renovacion))->toArray($this->lorena)['mensaje'])->toContain('envió la renovación de su convenio CV-2026-001');
    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $renovacion))->assertOk()->assertSee('Renovación')->assertSee('CV-2026-001');
});
