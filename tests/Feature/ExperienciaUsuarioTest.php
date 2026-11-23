<?php

/**
 * Experiencia de usuario: mensajes en español, avisos por rol y acciones bloqueadas con su motivo.
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\PostulacionEstado;
use App\Enums\ProgramaEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Enums\ValidacionEstado;
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
use App\Notifications\NuevaSolicitudConvenio;
use App\Services\ConvenioWorkflow;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function uxPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function uxUsuario(Rol $rol, array $datos = []): User
{
    $u = User::factory()->create($datos);
    $u->assignRole($rol->value);

    return $u;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);

    $div = Division::create(['nombre' => 'División de prueba']);
    $this->carrera = Carrera::create(['division_id' => $div->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Abogado',
        'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);

    $this->coord = uxUsuario(Rol::Coordinador);
    Personal::create(['user_id' => $this->coord->id, 'carrera_id' => $this->carrera->id]);

    $this->lorena = uxUsuario(Rol::Vinculacion, ['email' => 'lorena@csh.udg.mx']);
    Personal::create(['user_id' => $this->lorena->id]);

    $this->empresaUser = uxUsuario(Rol::Empresa);
    $this->empresa = Empresa::create(['user_id' => $this->empresaUser->id, 'rfc' => 'EUX010101AB1', 'razon_social' => 'Empresa UX',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto', 'representante_legal' => 'Lic. Firma', 'representante_cargo' => 'Director', 'domicilio_fiscal' => 'Av. 1', 'constitucion' => 'escritura 1', 'objeto_social' => 'servicios', 'facultad_firmante' => 'poder 2']);
});

/* ----------------------------- Mensajes en español ----------------------------- */

test('el inicio de sesión y el registro responden en español claro', function () {
    $u = uxUsuario(Rol::Alumno, ['email' => 'ana@alumnos.udg.mx', 'password' => 'secreta-123']);

    $this->post(route('login'), ['email' => 'ana@alumnos.udg.mx', 'password' => 'otra'])
        ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos. Revísalos e inténtalo de nuevo.']);

    $this->post('/registro/alumno', [
        'name' => 'Ana', 'email' => 'ana@alumnos.udg.mx', 'password' => 'corta', 'password_confirmation' => 'otra',
        'carrera_id' => $this->carrera->id, 'num_control' => '215', 'grado' => 6, 'turno' => 'matutino', 'aviso_privacidad' => '1',
    ])->assertSessionHasErrors(['email']);
    expect(session('errors')->get('email'))->toContain('Ya existe una cuenta con ese correo. Inicia sesión o recupera tu contraseña.');

    $errores = session('errors')->get('password');
    expect($errores)->toContain('Tu contraseña debe tener al menos 8 caracteres.')
        ->toContain('Las contraseñas no coinciden. Escribe la misma en ambos campos.');

    // Ningún mensaje queda en inglés ni con el nombre técnico del campo
    expect(implode(' ', session('errors')->all()))->not->toContain('The ')->not->toContain('num_control');
});

test('las pantallas entregan los errores del servidor para mostrarlos junto a cada campo', function () {
    $this->from('/signin')->post(route('login'), ['email' => 'no-es-correo', 'password' => '']);

    $this->get('/signin')->assertOk()
        ->assertSee('window.__erroresServidor', false)
        ->assertSee('Escribe un correo válido');
});

/* ------------------------------ Campana y correo por rol ------------------------------ */

test('una solicitud nueva llega a la campana de Lorena y al buzón de Prácticas; la empresa solo ve su campana', function () {
    Notification::fake();
    config(['practicas.contacto.correo' => 'practicas.profesionales.cucsh@csh.udg.mx']);

    app(ConvenioWorkflow::class)->solicitar($this->empresa, [
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 2,
    ], [$this->carrera->id]);

    Notification::assertSentTo($this->lorena, NuevaSolicitudConvenio::class,
        fn ($n, $canales) => in_array('database', $canales, true) && in_array('mail', $canales, true));
    Notification::assertSentOnDemand(NuevaSolicitudConvenio::class,
        fn ($n, $canales, AnonymousNotifiable $a) => $a->routes['mail'] === 'practicas.profesionales.cucsh@csh.udg.mx');

    // La empresa: aviso de cada etapa solo en la campana
    Notification::assertSentTo($this->empresaUser, ConvenioActualizado::class, fn ($n, $canales) => $canales === ['database']);
    Notification::assertNotSentTo($this->coord, NuevaSolicitudConvenio::class);
});

test('el alumno recibe aviso cuando su coordinación valida sus documentos', function () {
    $alumnoUser = uxUsuario(Rol::Alumno);
    $alumno = Alumno::create(['user_id' => $alumnoUser->id, 'carrera_id' => $this->carrera->id, 'num_control' => '215000099',
        'grado' => 6, 'turno' => 'matutino', 'vigencia_imss' => now()->addYear()]);
    $imss = $alumno->addMedia(uxPdf())->toMediaCollection('constancia_imss');

    Notification::fake();
    $this->actingAs($this->coord)->post(route('coordinacion.documentos.resolver', $imss), ['estado' => 'validada']);

    Notification::assertSentTo($alumnoUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'validó tu constancia IMSS'));
});

test('la campana muestra a cada usuario solo sus propios avisos', function () {
    $this->empresaUser->notify(new AvisoOperativo('Tu vacante fue aprobada.', '/empresa/lista-programas'));
    $this->lorena->notify(new AvisoOperativo('Nueva solicitud de convenio.', '/admin/lista-convenios'));

    $this->actingAs($this->empresaUser)->getJson(route('notificaciones.resumen'))
        ->assertOk()->assertJsonPath('no_leidas', 1)->assertJsonPath('items.0.titulo', 'Tu vacante fue aprobada')
        ->assertJsonCount(1, 'items');

    $this->actingAs($this->lorena)->getJson(route('notificaciones.resumen'))
        ->assertOk()->assertJsonPath('items.0.titulo', 'Nueva solicitud de convenio')->assertJsonCount(1, 'items');

    $this->actingAs($this->coord)->getJson(route('notificaciones.resumen'))->assertOk()->assertJsonPath('no_leidas', 0);
});

/* ------------------------- Acciones y pestañas bloqueadas ------------------------- */

test('sin convenio vigente la empresa ve bloqueadas sus vacantes y practicantes, con el motivo', function () {
    $this->actingAs($this->empresaUser)->get(route('empresa.dashboard'))->assertOk()
        ->assertSee('Necesitas un convenio formalizado y vigente para proponer vacantes.')
        ->assertSee('Se habilita cuando tengas un convenio formalizado y alumnos asignados.');

    $this->actingAs($this->empresaUser)->get(route('empresa.lista-programas'))->assertOk()
        ->assertSee('aria-disabled="true"', false);

    $this->actingAs($this->empresaUser)->post(route('empresa.programas.store'), ['titulo' => 'x'])
        ->assertSessionHasErrors(['programa' => 'Necesitas un convenio formalizado y vigente para proponer vacantes.']);
});

test('la empresa ve deshabilitado «Enviar solicitud» mientras le falten documentos', function () {
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()
        ->assertSee('Documentos en PDF')->assertSee('Cuando las 4 secciones estén completas podrás enviar la solicitud a Vinculación.');
});

test('el alumno sin grado/turno o sin IMSS no puede postularse y se le dice qué hacer', function () {
    $u = uxUsuario(Rol::Alumno);
    $alumno = Alumno::create(['user_id' => $u->id, 'carrera_id' => $this->carrera->id, 'num_control' => '215000100']);

    $convenio = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-UX',
        'fecha_recepcion' => now()->subMonth(), 'fecha_inicio_vigencia' => now()->subDay(), 'fecha_fin_vigencia' => now()->addYear(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 2]);
    $convenio->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Aprobatorio]);
    $programa = Programa::create(['convenio_id' => $convenio->id, 'carrera_id' => $this->carrera->id, 'modalidad' => 'externa',
        'ciclo' => '2027A', 'titulo' => 'Vacante UX', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 2, 'estado' => ProgramaEstado::Publicado]);

    // Sin expediente validado la oferta está con candado y explica qué hacer
    $this->actingAs($u)->get(route('alumno.oferta-programas'))->assertOk()
        ->assertSee('Primero entrega tu kardex y tu constancia IMSS a tu coordinación');
    $this->actingAs($u)->post(route('alumno.oferta-programas.postular', $programa), ['siiau' => '1'])
        ->assertSessionHasErrors('postulacion');

    // Con el expediente validado pero sin grado y turno, tampoco (van en la carta)
    $alumno = expedienteDe($alumno, 70);
    $u->unsetRelation('alumno');
    $this->actingAs($u)->post(route('alumno.oferta-programas.postular', $programa), ['siiau' => '1'])
        ->assertSessionHasErrors(['postulacion' => 'Captura tu grado y turno en «Mi perfil» antes de postularte (van en tu carta de presentación).']);

    $alumno->update(['grado' => 6, 'turno' => 'matutino']);
    $u->unsetRelation('alumno');
    $this->actingAs($u)->get(route('alumno.oferta-programas'))->assertSee('Vacante UX')->assertSee('Confirma tu postulación');

    // «Mis prácticas» aparece bloqueada hasta que se postule
    $this->actingAs($u)->get(route('alumno.dashboard'))->assertSee('Se habilita cuando te postules a un programa.');
});


test('la coordinación ve qué falta para emitir la carta y Vinculación qué falta para turnar', function () {
    $u = uxUsuario(Rol::Alumno);
    $alumno = Alumno::create(['user_id' => $u->id, 'carrera_id' => $this->carrera->id, 'num_control' => '215000101',
        'grado' => 6, 'turno' => 'matutino', 'vigencia_imss' => now()->addYear()]);
    $alumno->addMedia(uxPdf())->toMediaCollection('constancia_imss');   // subida, sin validar

    // Solo subió el IMSS: su expediente aparece por revisar, pero no se puede autorizar sin el kardex
    $this->actingAs($this->coord)->get(route('coordinacion.lista-practicantes'))->assertOk()
        ->assertSee('Expedientes por revisar')->assertSee('Falta un documento del alumno.');


    $convenio = app(ConvenioWorkflow::class)->solicitar($this->empresa, ['objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 2], [$this->carrera->id]);
    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $convenio))->assertOk()
        ->assertSee('Valida primero todo el expediente de la empresa');
});
