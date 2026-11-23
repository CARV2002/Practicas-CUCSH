<?php

/**
 * Reglas cerradas con el área (22/11/2026):
 *  1. El administrador supervisa convenios en SOLO LECTURA; solo Vinculación los opera.
 *  2. Horas, % de créditos y créditos otorgados: solo la Coordinación; el administrador crea la estructura.
 *  3. Los años de vigencia se congelan en el borrador (cláusula UNDÉCIMA); al formalizar no se eligen.
 *  4. Máximo 2 cuentas activas por coordinación; correo único; el relevo se hace desactivando, no borrando.
 */

use App\Enums\ConvenioEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\AuditLog;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function rcUsuario(Rol $rol, ?array $personal = null): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);
    if ($personal !== null) {
        Personal::create(['user_id' => $u->id] + $personal);
    }

    return $u;
}

function rcPdf(string $n = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($n, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed(RolesSeeder::class);

    $this->division = Division::create(['nombre' => 'División de Estudios Jurídicos']);
    $this->abog = Carrera::create(['division_id' => $this->division->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Licenciatura en Abogado',
        'horas_requeridas' => 350, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);

    $this->admin = rcUsuario(Rol::Administrador, ['puesto' => 'Coordinación de Servicios Académicos']);
    $this->lorena = rcUsuario(Rol::Vinculacion, ['puesto' => 'Vinculación']);

    $empresaUser = rcUsuario(Rol::Empresa);
    $this->empresa = Empresa::create(['user_id' => $empresaUser->id, 'rfc' => 'RCE010101AB1', 'razon_social' => 'Reglas S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto']);

    $this->enFirmas = function (int $anios = 2): Convenio {
        $c = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnFirmas, 'vigencia_anios' => $anios,
            'fecha_recepcion' => today(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
        $c->seguimientos()->create(['etapa' => 'archivo_distribucion', 'estado' => 'pendiente', 'fecha_registro' => today()]);

        return $c;
    };
});

/* ---------------------- 1. Administrador: solo lectura en convenios ---------------------- */

test('el administrador consulta los convenios pero no puede operar ninguna acción del trámite', function () {
    $c = ($this->enFirmas)();
    $acta = $this->empresa->addMedia(rcPdf('acta.pdf'))->toMediaCollection('acta_constitutiva');

    $this->actingAs($this->admin)->get(route('admin.lista-convenios'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.convenios.show', $c))->assertOk()
        ->assertSee('Supervisión de solo lectura')
        ->assertDontSee('Formalizar convenio')
        ->assertDontSee('Rechazar trámite');
    $this->actingAs($this->admin)->get(route('admin.documentos-fisicos'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.empresas.show', $this->empresa))->assertOk()
        ->assertSee('Consulta de solo lectura')->assertDontSee('Guardar cambios');

    $acciones = [
        route('admin.convenios.formalizar', $c) => ['folio' => 'X-1', 'fecha_inicio_vigencia' => today()->toDateString()],
        route('admin.convenios.rechazar', $c) => ['motivo' => 'Prueba'],
        route('admin.convenios.firmas', $c) => ['etapa' => 'firma_empresa'],
        route('admin.convenios.borrador.generar', $c) => [],
        route('admin.convenios.borrador', $c) => [],
        route('admin.convenios.juridica.turnar', $c) => [],
        route('admin.convenios.juridica.dictamen', $c) => ['resultado' => 'aprobado'],
        route('admin.convenios.validacion-academica', $c) => [],
        route('admin.convenios.turnos', $c) => [],
        route('admin.convenios.documentos.validar', $c) => [],
        route('admin.convenios.documentos.resolver', [$c, $acta]) => ['estado' => 'validada'],
        route('admin.convenios.fisicos.preparar', $c) => [],
        route('admin.convenios.fisicos.recordar', $c) => [],
        route('admin.empresas.activo', $this->empresa) => [],
    ];
    foreach ($acciones as $url => $datos) {
        $this->actingAs($this->admin)->post($url, $datos)->assertForbidden();
    }
    $this->actingAs($this->admin)->put(route('admin.empresas.update', $this->empresa), ['razon_social' => 'Otra'])->assertForbidden();

    expect($c->fresh()->estado)->toBe(ConvenioEstado::EnFirmas)
        ->and($this->empresa->fresh()->razon_social)->toBe('Reglas S.C.')
        ->and($this->admin->can('view', $c))->toBeTrue()
        ->and($this->admin->can('gestionar', $c))->toBeFalse()
        ->and($this->lorena->can('gestionar', $c))->toBeTrue();

    // Vinculación sí ve los controles
    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $c))->assertOk()
        ->assertSee('Formalizar convenio')->assertDontSee('Supervisión de solo lectura');
});

/* ---------------------- 2. Reglas académicas: solo la Coordinación ---------------------- */

test('el administrador crea la carrera con reglas iniciales y no puede cambiar horas ni créditos', function () {
    $this->actingAs($this->admin)->post(route('admin.catalogos.carreras.store'), [
        'division_id' => $this->division->id, 'carrera_clave' => 'crim', 'nombre' => 'Licenciatura en Criminología',
        'horas_requeridas' => 999, 'porcentaje_creditos_minimo' => 99, 'creditos_otorgados' => 40,
    ])->assertSessionHasNoErrors();

    $crim = Carrera::where('carrera_clave', 'CRIM')->firstOrFail();
    expect($crim->horas_requeridas)->toBe(300)
        ->and((float) $crim->porcentaje_creditos_minimo)->toBe(50.0)
        ->and($crim->creditos_otorgados)->toBe(8);

    // Editar como administrador: cambia nombre/clave, pero las reglas de la coordinación quedan intactas
    $this->actingAs($this->admin)->put(route('admin.catalogos.carreras.update', $this->abog), [
        'division_id' => $this->division->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Licenciatura en Abogado (Escolarizado)',
        'horas_requeridas' => 100, 'porcentaje_creditos_minimo' => 10, 'creditos_otorgados' => 1,
    ])->assertSessionHasNoErrors();
    $this->abog->refresh();
    expect($this->abog->nombre)->toBe('Licenciatura en Abogado (Escolarizado)')
        ->and($this->abog->horas_requeridas)->toBe(350);

    // El administrador no entra a las reglas de la coordinación
    $this->actingAs($this->admin)->put(route('coordinacion.reglas.update', $this->abog), ['horas_requeridas' => 100, 'porcentaje_creditos_minimo' => 10, 'creditos_otorgados' => 1])
        ->assertForbidden();

    // La coordinación sí las ajusta
    $coord = rcUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id, 'puesto' => 'Coordinación ABOG']);
    $this->actingAs($coord)->put(route('coordinacion.reglas.update', $this->abog), [
        'horas_requeridas' => 320, 'porcentaje_creditos_minimo' => 55, 'creditos_otorgados' => 9, 'esquema_reportes' => 'bimestral',
    ])->assertSessionHasNoErrors();
    expect($this->abog->refresh()->horas_requeridas)->toBe(320);

    $this->actingAs($this->admin)->get(route('admin.catalogos'))->assertOk()->assertDontSee('name="horas_requeridas"', false);
});

/* ---------------------- 3. Vigencia congelada en el borrador ---------------------- */

test('al formalizar se usan los años impresos en el borrador y no se aceptan otros', function () {
    $c = ($this->enFirmas)(3);

    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $c))->assertOk()
        ->assertSee('3 años · fijada en el borrador')->assertDontSee('id="fo-anios"', false);

    // Aunque llegue otra duración o una fecha de fin, manda la del borrador
    $this->actingAs($this->lorena)->post(route('admin.convenios.formalizar', $c), [
        'folio' => 'CV-RC-001', 'fecha_inicio_vigencia' => '2026-12-01', 'vigencia_anios' => 5, 'fecha_fin_vigencia' => '2031-12-01',
        'convenio_firmado' => rcPdf('firmado.pdf'),
    ])->assertSessionHasNoErrors();

    $c->refresh();
    expect($c->estado)->toBe(ConvenioEstado::Formalizado)
        ->and($c->vigencia_anios)->toBe(3)
        ->and($c->fecha_fin_vigencia->toDateString())->toBe('2029-11-30');
});

test('un convenio sin años en su borrador no se formaliza y el borrador propio exige la vigencia', function () {
    $c = ($this->enFirmas)();
    $c->update(['vigencia_anios' => null]);

    $this->actingAs($this->lorena)->post(route('admin.convenios.formalizar', $c), [
        'folio' => 'CV-RC-002', 'fecha_inicio_vigencia' => '2026-12-01', 'convenio_firmado' => rcPdf('firmado.pdf'),
    ])->assertSessionHasErrors('convenio');
    expect($c->fresh()->estado)->toBe(ConvenioEstado::EnFirmas);

    // Borrador redactado fuera del sistema: Lorena indica los años de su cláusula UNDÉCIMA
    $r = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnRevisionJuridica,
        'fecha_recepcion' => today(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $r->seguimientos()->create(['etapa' => 'redaccion_borrador', 'estado' => 'pendiente', 'fecha_registro' => today()]);

    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador', $r), ['borrador' => rcPdf('borrador.pdf')])
        ->assertSessionHasErrors('vigencia_anios');
    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador', $r), ['borrador' => rcPdf('borrador.pdf'), 'vigencia_anios' => 4])
        ->assertSessionHasNoErrors();
    expect($r->fresh()->vigencia_anios)->toBe(4);
});

/* ---------------------- 4. Máximo 2 cuentas activas por coordinación ---------------------- */

test('cada coordinación admite hasta 2 encargados activos; el relevo se hace desactivando', function () {
    $alta = fn (string $correo, array $alcance = []) => $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
        'name' => 'Persona de Prueba', 'email' => $correo, 'rol' => Rol::Coordinador->value,
    ] + ($alcance ?: ['carrera_id' => $this->abog->id]));

    $alta('titular@csh.udg.mx')->assertSessionHasNoErrors();
    $alta('asistente@csh.udg.mx')->assertSessionHasNoErrors();

    // Tercera cuenta: bloqueada
    $alta('tercera@csh.udg.mx')->assertSessionHasErrors([
        'carrera_id' => 'Esta coordinación ya alcanzó el límite máximo de 2 encargados activos. Desactiva una cuenta anterior si deseas registrar a alguien nuevo.',
    ]);
    expect(User::where('email', 'tercera@csh.udg.mx')->exists())->toBeFalse();

    // El formulario la muestra completa
    $this->actingAs($this->admin)->get(route('admin.lista-usuarios'))->assertOk()->assertSee('(2/2 activos · completa)');

    // Correo repetido: se frena con su mensaje
    $alta('titular@csh.udg.mx')->assertSessionHasErrors(['email' => 'Este correo ya se encuentra registrado en el sistema.']);

    // Relevo: se desactiva (no se borra) y el cupo queda libre
    $saliente = User::where('email', 'titular@csh.udg.mx')->firstOrFail();
    $this->actingAs($this->admin)->post(route('admin.usuarios.activo', $saliente))->assertSessionHasNoErrors();
    expect($saliente->fresh()->activo)->toBeFalse()
        ->and(AuditLog::where('accion', 'usuario.desactivado')->exists())->toBeTrue();

    $alta('nuevo.titular@csh.udg.mx')->assertSessionHasNoErrors();

    // Reactivar al saliente rebasaría el límite: también se bloquea
    $this->actingAs($this->admin)->post(route('admin.usuarios.activo', $saliente))->assertSessionHasErrors('usuario');
    expect($saliente->fresh()->activo)->toBeFalse()
        ->and(User::where('email', 'titular@csh.udg.mx')->exists())->toBeTrue();   // su historial sigue ahí
});

test('el límite cuenta por separado cada carrera y cada coordinación de división', function () {
    $crim = Carrera::create(['division_id' => $this->division->id, 'carrera_clave' => 'CRIM', 'nombre' => 'Criminología',
        'horas_requeridas' => 300, 'porcentaje_creditos_minimo' => 60, 'creditos_otorgados' => 10]);
    rcUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id, 'puesto' => 'A']);
    rcUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id, 'puesto' => 'B']);

    $alta = fn (string $correo, array $alcance) => $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
        'name' => 'Persona de Prueba', 'email' => $correo, 'rol' => Rol::Coordinador->value,
    ] + $alcance);

    $alta('crim@csh.udg.mx', ['carrera_id' => $crim->id])->assertSessionHasNoErrors();
    $alta('division1@csh.udg.mx', ['division_id' => $this->division->id])->assertSessionHasNoErrors();
    $alta('division2@csh.udg.mx', ['division_id' => $this->division->id])->assertSessionHasNoErrors();
    $alta('division3@csh.udg.mx', ['division_id' => $this->division->id])->assertSessionHasErrors('division_id');

    // Vinculación y administradores no tienen este límite
    $this->actingAs($this->admin)->post(route('admin.usuarios.store'), ['name' => 'Otra Persona', 'email' => 'vinc2@csh.udg.mx', 'rol' => Rol::Vinculacion->value])
        ->assertSessionHasNoErrors();
});
