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
use App\Models\Configuracion;
use App\Models\Convenio;
use App\Models\ConvenioSeguimiento;
use App\Models\Division;
use App\Models\DocumentoFisico;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Notifications\ConvenioActualizado;
use App\Services\ConvenioWorkflow;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
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
        ->and($this->convenio->documentosFisicos()->where('tipo', TF::DictamenJuridico->value)->first()->estado)->toBe(EF::Archivado)
        ->and($this->convenio->documentosFisicos()->activos()->pluck('juego')->sort()->values()->all())->toBe([1, 2, 3])
        ->and($this->convenio->fresh()->estadoDetallado()['texto'])->toBe('Imprimir los 3 juegos');

    // Lorena entrega los 3 juegos a la empresa de un solo movimiento: plazo de regreso de 7 días hábiles
    $this->actingAs($this->lorena)
        ->post(route('admin.documentos-fisicos.mover', $juegos->first()), ['estado' => EF::ConEmpresa->value, 'juntos' => '1'])
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'Juegos 1, 2 y 3: Con la empresa para firma.');
    expect($this->convenio->documentosFisicos()->where('estado', EF::ConEmpresa->value)->count())->toBe(3);

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

    // Formalizar exige el escaneo del convenio firmado
    Storage::fake('local');
    $this->convenio->addMedia(UploadedFile::fake()->createWithContent('convenio.pdf', '%PDF-1.4
%%EOF'))->toMediaCollection('convenio_firmado');
    $flujo->formalizar($this->convenio->fresh(), $this->personal, 'CV-2026-099', today(), today()->addYears(2)->subDay());
    $finales = $this->convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->orderBy('juego')->get();
    expect($finales->pluck('estado')->all())->toBe([EF::Archivado, EF::Entregado, EF::Entregado])
        ->and($finales->pluck('destino')->all())->toBe(['Archivo de Vinculación (CUCSH)', 'Empresa', 'Archivo de la Unidad Jurídica'])
        ->and($this->convenio->documentosFisicos()->activos()->count())->toBe(0);
});

test('sin marcar «juntos» solo se mueve el juego elegido, y los que están en otro lugar nunca se arrastran', function () {
    $this->convenio->update(['estado' => ConvenioEstado::EnFirmas]);
    $this->convenio->prepararDocumentosFisicos($this->personal);
    [$j1, $j2, $j3] = $this->convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->orderBy('juego')->get()->all();

    $this->actingAs($this->lorena)->post(route('admin.documentos-fisicos.mover', $j1), ['estado' => EF::EnVinculacion->value])->assertSessionHasNoErrors();
    expect($j1->fresh()->estado)->toBe(EF::EnVinculacion)->and($j2->fresh()->estado)->toBe(EF::PorElaborar);

    // «Juntos» desde el juego 2 arrastra al 3 (mismo lugar), no al 1 (ya está en otro)
    $this->actingAs($this->lorena)->post(route('admin.documentos-fisicos.mover', $j2), ['estado' => EF::EnVinculacion->value, 'juntos' => '1'])->assertSessionHasNoErrors();
    expect($j3->fresh()->estado)->toBe(EF::EnVinculacion)
        ->and($j1->fresh()->movimientos()->count())->toBe(2);
});

test('un estado que no aplica al papel o sin nota cuando se extravía se rechaza', function () {
    $this->convenio->update(['estado' => ConvenioEstado::EnRevisionJuridica]);
    $this->convenio->prepararDocumentosFisicos($this->personal);
    $oficio = $this->convenio->documentosFisicos()->where('tipo', TF::DictamenJuridico->value)->first();

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
        ->assertOk()->assertSee('Papeles S.C.')->assertSee('Expediente / borrador en Unidad Jurídica')->assertSee('Vencido');

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

test('el turno a las carreras es digital: no crea ni rastrea un oficio en papel', function () {
    $this->convenio->update(['estado' => ConvenioEstado::EnValidacionAcademica, 'fecha_recepcion' => today()->subDays(10)]);
    $this->convenio->prepararDocumentosFisicos($this->personal);

    app(ConvenioWorkflow::class)->registrarTurnos($this->convenio, $this->personal, today()->subDays(5), today()->subDays(2));

    expect($this->convenio->documentosFisicos()->count())->toBe(0)
        ->and($this->convenio->fresh()->fecha_turno_division->toDateString())->toBe(today()->subDays(2)->toDateString());

    // Al pasar a Jurídica aparece el primer papel: el expediente que se lleva en carpeta
    $this->convenio->update(['estado' => ConvenioEstado::EnRevisionJuridica]);
    $this->convenio->prepararDocumentosFisicos($this->personal);
    expect($this->convenio->documentosFisicos()->pluck('tipo')->map->value->all())->toBe([TF::DictamenJuridico->value]);
    $this->actingAs($this->lorena)->get(route('admin.documentos-fisicos'))->assertOk()
        ->assertSee('Expediente / borrador en Unidad Jurídica')->assertDontSee('Oficio de turno académico');
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

/** Lleva el convenio del dictamen favorable hasta «Por formalizar», con el PDF firmado adjunto. */
function dfHastaPorFormalizar($test): void
{
    $flujo = app(ConvenioWorkflow::class);
    $test->convenio->prepararDocumentosFisicos($test->personal);
    $flujo->registrarDictamenJuridico($test->convenio, SeguimientoEstado::Aprobado, null, $test->personal);
    $flujo->registrarFirma($test->convenio->fresh(), EtapaConvenio::FirmaEmpresa, $test->personal);
    $flujo->registrarFirma($test->convenio->fresh(), EtapaConvenio::FirmaRectoria, $test->personal);
    $test->convenio->addMedia(UploadedFile::fake()->createWithContent('convenio.pdf', "%PDF-1.4\n%%EOF"))->toMediaCollection('convenio_firmado');
}

test('el dictamen favorable avisa a la empresa por correo y en su panel que recoja los 3 juegos y el plazo', function () {
    Notification::fake();
    $this->convenio->prepararDocumentosFisicos($this->personal);

    app(ConvenioWorkflow::class)->registrarDictamenJuridico($this->convenio, SeguimientoEstado::Aprobado, null, $this->personal);

    Notification::assertSentTo($this->empresaUser, ConvenioActualizado::class, fn ($n, $canales) => $canales === ['database', 'mail']
        && str_contains($n->mensaje, 'pasar a Vinculación (CUCSH) a recoger los 3 juegos originales')
        && str_contains($n->mensaje, '7 días hábiles'));
});

test('los 3 juegos se mueven juntos a la empresa y a Rectoría con la misma fecha de regreso', function () {
    Notification::fake();
    $flujo = app(ConvenioWorkflow::class);
    $this->convenio->prepararDocumentosFisicos($this->personal);
    $flujo->registrarDictamenJuridico($this->convenio, SeguimientoEstado::Aprobado, null, $this->personal);
    $juegos = fn () => $this->convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->get();

    $salida = today()->subDays(2);
    $this->actingAs($this->lorena)->post(route('admin.documentos-fisicos.mover', $juegos()->first()), [
        'estado' => EF::ConEmpresa->value, 'fecha' => $salida->toDateString(), 'juntos' => '1',
    ])->assertSessionHasNoErrors();
    expect($juegos()->pluck('estado')->unique()->all())->toBe([EF::ConEmpresa])
        ->and($juegos()->map(fn ($d) => $d->fecha_compromiso->toDateString())->unique()->all())->toBe([$salida->copy()->addWeekdays(7)->toDateString()]);

    $flujo->registrarFirma($this->convenio->fresh(), EtapaConvenio::FirmaEmpresa, $this->personal);
    $this->actingAs($this->lorena)->post(route('admin.documentos-fisicos.mover', $juegos()->last()), [
        'estado' => EF::EnRectoria->value, 'juntos' => '1',
    ])->assertSessionHasNoErrors();
    expect($juegos()->pluck('estado')->unique()->all())->toBe([EF::EnRectoria])
        ->and($juegos()->pluck('fecha_compromiso')->map->toDateString()->unique())->toHaveCount(1);
});

test('al formalizar: juegos concluidos salvo los pendientes, PDF para todos y aviso a Jurídica', function () {
    Notification::fake();
    Configuracion::guardar(Configuracion::CORREO_JURIDICA, 'juridica@cucsh.test');
    dfHastaPorFormalizar($this);

    app(ConvenioWorkflow::class)->formalizar($this->convenio->fresh(), $this->personal, 'CV-2026-120', today(), today()->addYears(2)->subDay(), null, [2]);

    // El juego 2 (de la empresa) sigue en seguimiento hasta que se entregue; los otros concluyen
    $finales = $this->convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->orderBy('juego')->get();
    expect($finales->pluck('estado')->all())->toBe([EF::Archivado, EF::Firmado, EF::Entregado])
        ->and($this->convenio->documentosFisicos()->activos()->pluck('juego')->all())->toBe([2])
        ->and($finales[1]->movimientos()->latest('id')->first()->nota)->toContain('pendiente de entrega física');

    // La empresa recibe correo y aviso; Jurídica, un correo con folio y vigencia
    Notification::assertSentTo($this->empresaUser, ConvenioActualizado::class, fn ($n, $canales) => in_array('mail', $canales, true)
        && str_contains($n->mensaje, 'folio CV-2026-120') && str_contains($n->mensaje, 'Mi convenio'));
    Notification::assertSentOnDemand(AvisoOperativo::class, fn ($n, $canales, $destino) => $destino->routes['mail'] === 'juridica@cucsh.test'
        && str_contains($n->mensaje, 'folio CV-2026-120') && str_contains($n->mensaje, 'vigente del'));

    // El PDF firmado: Vinculación, Administración y la empresa (en Mi convenio, con su misma cuenta)
    $pdf = $this->convenio->fresh()->getFirstMedia('convenio_firmado');
    $admin = dfUsuario(Rol::Administrador);
    Personal::create(['user_id' => $admin->id, 'puesto' => 'Administración']);
    $this->actingAs($this->lorena)->get(route('admin.convenios.archivos.descargar', [$this->convenio, $pdf]))->assertOk();
    $this->actingAs($admin)->get(route('admin.convenios.archivos.descargar', [$this->convenio, $pdf]))->assertOk();
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()->assertSee('Convenio firmado');
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio.archivo', $this->convenio))->assertOk();
});

test('sin correo de Jurídica configurado no se envía ese aviso', function () {
    Notification::fake();
    Configuracion::guardar(Configuracion::CORREO_JURIDICA, null);
    dfHastaPorFormalizar($this);

    app(ConvenioWorkflow::class)->formalizar($this->convenio->fresh(), $this->personal, 'CV-2026-121', today(), today()->addYears(2)->subDay());

    Notification::assertSentOnDemandTimes(AvisoOperativo::class, 0);
    expect($this->convenio->documentosFisicos()->activos()->count())->toBe(0);
});
