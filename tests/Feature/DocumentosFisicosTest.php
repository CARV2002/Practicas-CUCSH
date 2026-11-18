<?php

/**
 * Trámite híbrido: control de los papeles (oficio de turno, dictamen jurídico y 3 juegos),
 * estado detallado del convenio y recordatorios automáticos.
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EstadoDocumentoFisico as EF;
use App\Enums\EtapaConvenio;
use App\Enums\Rol;
use App\Enums\SeguimientoEstado;
use App\Enums\TipoDocumentoFisico as TF;
use App\Enums\TipoOrganismo;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioSeguimiento;
use App\Models\Division;
use App\Models\DocumentoFisico;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Services\ConvenioWorkflow;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function dfUsuario(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);

    $div = Division::create(['nombre' => 'División de Estudios Jurídicos']);
    $this->abog = Carrera::create(['division_id' => $div->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Abogado', 'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);

    $this->coord = dfUsuario(Rol::Coordinador);
    Personal::create(['user_id' => $this->coord->id, 'carrera_id' => $this->abog->id, 'puesto' => 'Coordinación ABOG']);

    $this->lorena = dfUsuario(Rol::Vinculacion);
    $this->personal = Personal::create(['user_id' => $this->lorena->id, 'puesto' => 'Vinculación']);

    $this->empresaUser = dfUsuario(Rol::Empresa);
    $this->empresa = Empresa::create([
        'user_id' => $this->empresaUser->id, 'rfc' => 'DFI010101AB1', 'razon_social' => 'Papeles S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto',
    ]);

    $this->convenio = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnRevisionJuridica,
        'fecha_recepcion' => now()->subMonth(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 2,
    ]);
    $this->convenio->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    ConvenioSeguimiento::create([
        'convenio_id' => $this->convenio->id, 'etapa' => EtapaConvenio::DictamenJuridico,
        'estado' => SeguimientoEstado::Pendiente, 'fecha_registro' => today(),
    ]);
});

test('el dictamen jurídico aprobado crea los 3 juegos y las firmas los actualizan', function () {
    Notification::fake();
    $this->convenio->prepararDocumentosFisicos($this->personal);
    $flujo = app(ConvenioWorkflow::class);

    expect($this->convenio->documentosFisicos()->where('tipo', TF::DictamenJuridico->value)->first()->estado)->toBe(EF::EnJuridica);

    $flujo->registrarDictamenJuridico($this->convenio, SeguimientoEstado::Aprobado, null, $this->personal);

    $juegos = $this->convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->get();
    expect($juegos)->toHaveCount(3)
        ->and($juegos->pluck('estado')->unique()->all())->toBe([EF::PorElaborar])
        ->and($this->convenio->documentosFisicos()->where('tipo', TF::DictamenJuridico->value)->first()->estado)->toBe(EF::EnVinculacion)
        ->and($this->convenio->fresh()->estadoDetallado()['texto'])->toBe('Imprimir los 3 juegos');

    // Lorena entrega los juegos a la empresa: plazo de regreso de 7 días hábiles
    $juegos->each(fn ($j) => $this->actingAs($this->lorena)
        ->post(route('admin.documentos-fisicos.mover', $j), ['estado' => EF::ConEmpresa->value])->assertSessionHasNoErrors());

    $j = $juegos->first()->fresh();
    expect($j->estado)->toBe(EF::ConEmpresa)
        ->and($j->fecha_compromiso->toDateString())->toBe(today()->addWeekdays(7)->toDateString())
        ->and($j->movimientos()->count())->toBe(2)
        ->and($this->convenio->fresh()->estadoDetallado()['responsable'])->toBe('Empresa');

    $flujo->registrarFirma($this->convenio->fresh(), EtapaConvenio::FirmaEmpresa, $this->personal);
    expect($this->convenio->documentosFisicos()->where('estado', EF::FirmadoEmpresa->value)->count())->toBe(3);

    $flujo->registrarFirma($this->convenio->fresh(), EtapaConvenio::FirmaRectoria, $this->personal);
    expect($this->convenio->documentosFisicos()->where('estado', EF::Firmado->value)->count())->toBe(3)
        ->and($this->convenio->fresh()->estadoDetallado()['texto'])->toBe('Por formalizar');
});

test('un estado que no aplica al papel o sin nota cuando se extravía se rechaza', function () {
    $this->convenio->prepararDocumentosFisicos($this->personal);
    $oficio = $this->convenio->documentosFisicos()->where('tipo', TF::OficioTurno->value)->first();

    $this->actingAs($this->lorena)->post(route('admin.documentos-fisicos.mover', $oficio), ['estado' => EF::ConEmpresa->value])
        ->assertSessionHasErrors('estado');
    $this->actingAs($this->lorena)->post(route('admin.documentos-fisicos.mover', $oficio), ['estado' => EF::Extraviado->value])
        ->assertSessionHasErrors('nota');

    $this->actingAs($this->coord)->post(route('admin.documentos-fisicos.mover', $oficio), ['estado' => EF::Archivado->value])
        ->assertForbidden();
});

test('la pestaña muestra los papeles y los vencidos', function () {
    $this->convenio->prepararDocumentosFisicos($this->personal);
    $this->convenio->documentosFisicos()->where('tipo', TF::DictamenJuridico->value)->first()
        ->update(['fecha_compromiso' => today()->subDays(3)]);

    $this->actingAs($this->lorena)->get(route('admin.documentos-fisicos', ['vista' => 'vencidos']))
        ->assertOk()->assertSee('Papeles S.C.')->assertSee('Dictamen de viabilidad jurídica')->assertSee('Vencido');

    $this->actingAs($this->empresaUser)->get(route('admin.documentos-fisicos'))->assertForbidden();
});

test('los recordatorios avisan a Vinculación, a la empresa y a la coordinación una sola vez', function () {
    Notification::fake();
    $this->convenio->update(['estado' => ConvenioEstado::EnFirmas]);
    $this->convenio->prepararDocumentosFisicos($this->personal);
    $this->convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->get()
        ->each(fn (DocumentoFisico $d) => $d->mover(EF::ConEmpresa, $this->personal, null, today()->subDay(), today()->subWeekdays(8)));

    // Otro convenio con un dictamen de carrera pendiente desde hace 6 días hábiles
    $otro = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnValidacionAcademica,
        'fecha_recepcion' => now()->subMonth(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1,
    ]);
    $otro->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Pendiente]);
    ConvenioSeguimiento::create(['convenio_id' => $otro->id, 'etapa' => EtapaConvenio::TurnoAcademico, 'estado' => SeguimientoEstado::Pendiente, 'fecha_registro' => today()->subWeekdays(6)]);

    $this->artisan('practicas:avisos-programados')->assertSuccessful();

    Notification::assertSentTo($this->lorena, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'debían regresar'));
    Notification::assertSentTo($this->empresaUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, '3 juegos originales'));
    Notification::assertSentTo($this->coord, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'dictamen de pertinencia'));

    // La empresa ve el aviso en su página de convenio
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()->assertSee('juegos originales del convenio por firmar');
});

test('el estado detallado explica la revisión de documentos', function () {
    $this->convenio->update(['estado' => ConvenioEstado::Solicitado]);

    $d = $this->convenio->fresh()->estadoDetallado();

    expect($d['texto'])->toBe('Expediente incompleto')->and($d['responsable'])->toBe('Empresa');
});

test('registrar el turno a la División mueve el oficio físico sin capturarlo dos veces', function () {
    $this->convenio->update(['estado' => ConvenioEstado::EnValidacionAcademica, 'fecha_recepcion' => today()->subDays(10)]);
    $this->convenio->prepararDocumentosFisicos($this->personal);

    app(ConvenioWorkflow::class)->registrarTurnos($this->convenio, $this->personal, today()->subDays(5), today()->subDays(2));

    $oficio = $this->convenio->documentosFisicos()->where('tipo', TF::OficioTurno->value)->first();
    expect($oficio->estado)->toBe(EF::EnDivision)
        ->and($oficio->fecha_estado->toDateString())->toBe(today()->subDays(2)->toDateString());
});

test('un convenio rechazado ya no genera recordatorios de sus papeles', function () {
    Notification::fake();
    $this->convenio->update(['estado' => ConvenioEstado::EnFirmas]);
    $this->convenio->prepararDocumentosFisicos($this->personal);
    $this->convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->get()
        ->each(fn (DocumentoFisico $d) => $d->mover(EF::ConEmpresa, $this->personal, null, today()->subDay(), today()->subWeekdays(8)));

    app(ConvenioWorkflow::class)->rechazar($this->convenio, $this->personal, 'La empresa desistió.');
    $this->artisan('practicas:avisos-programados')->assertSuccessful();

    Notification::assertNotSentTo($this->empresaUser, AvisoOperativo::class);
    Notification::assertNotSentTo($this->lorena, AvisoOperativo::class);
});
