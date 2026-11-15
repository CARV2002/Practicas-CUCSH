<?php

use App\Enums\ConvenioEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Alumno;
use App\Models\AuditLog;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use App\Services\Auditoria;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function audPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function audUser(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed(RolesSeeder::class);

    $div = Division::create(['nombre' => 'División de prueba']);
    $this->abog = Carrera::create(['division_id' => $div->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Abogado',
        'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);

    $this->adminUser = audUser(Rol::Administrador);
    Personal::create(['user_id' => $this->adminUser->id, 'puesto' => 'Coordinación']);

    $this->coordUser = audUser(Rol::Administrador);
    Personal::create(['user_id' => $this->coordUser->id, 'carrera_id' => $this->abog->id, 'puesto' => 'Coordinador ABOG']);

    $this->empresaUser = audUser(Rol::Empresa);
    $this->empresa = Empresa::create([
        'user_id' => $this->empresaUser->id, 'rfc' => 'EPA010101AB1', 'razon_social' => 'Empresa de Prueba',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto',
    ]);

    $this->convenio = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Solicitado, 'fecha_recepcion' => today(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 3,
    ]);
    $this->convenio->dictamenes()->create(['carrera_id' => $this->abog->id]);

    $this->anaUser = audUser(Rol::Alumno);
    $this->ana = Alumno::create(['user_id' => $this->anaUser->id, 'carrera_id' => $this->abog->id, 'num_control' => '215000001']);
});

test('un cambio de estado queda con actor, antes y después', function () {
    $this->actingAs($this->adminUser)
        ->post(route('admin.convenios.rechazar', $this->convenio), ['motivo' => 'No aplica'])
        ->assertSessionHasNoErrors();

    $log = AuditLog::where('accion', 'convenio.actualizado')->latest('id')->firstOrFail();

    expect((int) $log->user_id)->toBe($this->adminUser->id)
        ->and($log->actor_rol)->toBe('administrador')
        ->and($log->entidad_tipo)->toBe('Convenio')
        ->and((int) $log->entidad_id)->toBe($this->convenio->id)
        ->and($log->cambios['estado'])->toBe(['antes' => 'solicitado', 'despues' => 'rechazado']);
});

test('validar o rechazar un documento deja el motivo y la subida queda registrada', function () {
    $media = $this->empresa->addMedia(audPdf())->toMediaCollection('acta_constitutiva');

    expect(AuditLog::where('accion', 'documento.subido')->exists())->toBeTrue();

    $this->actingAs($this->adminUser)
        ->post(route('admin.convenios.documentos.resolver', [$this->convenio, $media]), ['estado' => 'rechazada', 'motivo' => 'Ilegible'])
        ->assertSessionHasNoErrors();

    $log = AuditLog::where('accion', 'documento.rechazada')->firstOrFail();

    expect($log->entidad_tipo)->toBe('Empresa')
        ->and($log->cambios['motivo'])->toBe('Ilegible')
        ->and((int) $log->user_id)->toBe($this->adminUser->id);
});

test('las contraseñas nunca se guardan, ni siquiera cifradas', function () {
    $otro = audUser(Rol::Alumno);

    $this->actingAs($this->adminUser)->post(route('admin.usuarios.password', $otro), [
        'password' => 'clave-nueva-segura-1', 'password_confirmation' => 'clave-nueva-segura-1',
    ])->assertSessionHasNoErrors();

    $log = AuditLog::where('accion', 'usuario.actualizado')->where('entidad_id', $otro->id)->firstOrFail();

    expect($log->cambios['password'])->toBe(['redactado' => true])
        ->and(json_encode($log->cambios))->not->toContain('$2y$')->not->toContain('clave-nueva');
});

test('la bitácora es de solo inserción', function () {
    Auditoria::registrar('prueba.manual', 'registro de prueba');
    $log = AuditLog::firstOrFail();

    expect(fn () => $log->update(['descripcion' => 'alterado']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

test('registra accesos fallidos, exitosos y de cuentas desactivadas', function () {
    $this->post('/signin', ['email' => 'nadie@example.com', 'password' => 'mala'])->assertSessionHasErrors('email');

    $fallido = AuditLog::where('accion', 'acceso.fallido')->firstOrFail();
    expect($fallido->user_id)->toBeNull()->and($fallido->descripcion)->toContain('nadie@example.com');

    $inactivo = audUser(Rol::Alumno);
    $inactivo->update(['activo' => false]);
    $this->post('/signin', ['email' => $inactivo->email, 'password' => 'password']);

    expect(AuditLog::where('accion', 'acceso.cuenta_inactiva')->where('user_id', $inactivo->id)->exists())->toBeTrue();

    $this->post('/signin', ['email' => $this->anaUser->email, 'password' => 'password']);

    expect(AuditLog::where('accion', 'acceso.inicio_sesion')->where('user_id', $this->anaUser->id)->exists())->toBeTrue();
});

test('se registran las descargas del personal pero no las del alumno con sus propios archivos', function () {
    $acta = $this->empresa->addMedia(audPdf())->toMediaCollection('acta_constitutiva');
    $imss = $this->ana->addMedia(audPdf())->toMediaCollection('constancia_imss');

    $this->actingAs($this->adminUser)->get(route('admin.convenios.archivos.descargar', [$this->convenio, $acta]))->assertOk();

    expect(AuditLog::where('accion', 'documento.descargado')->where('user_id', $this->adminUser->id)->count())->toBe(1);

    $this->actingAs($this->anaUser)->get(route('alumno.documentos.descargar', $imss))->assertOk();

    expect(AuditLog::where('accion', 'documento.descargado')->count())->toBe(1);
});

test('solo la coordinación con alcance global consulta la auditoría', function () {
    $this->actingAs($this->adminUser)
        ->post(route('admin.convenios.rechazar', $this->convenio), ['motivo' => 'No aplica']);

    $this->actingAs($this->adminUser)->get(route('admin.auditoria'))->assertOk()->assertSee('convenio.actualizado');
    $this->actingAs($this->adminUser)
        ->get(route('admin.auditoria', ['entidad_tipo' => 'Convenio', 'entidad_id' => $this->convenio->id]))
        ->assertOk()->assertSee('Convenio #'.$this->convenio->id);

    $this->actingAs($this->coordUser)->get(route('admin.auditoria'))->assertForbidden();
    $this->actingAs($this->empresaUser)->get(route('admin.auditoria'))->assertForbidden();
    $this->actingAs($this->anaUser)->get(route('admin.auditoria'))->assertForbidden();
});