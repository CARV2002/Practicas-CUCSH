<?php

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
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
use App\Models\Practica;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\NuevaSolicitudConvenio;

function notPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function notUser(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

function notAdmin(?int $carreraId = null): User
{
    $u = notUser(Rol::Administrador);
    Personal::create(['user_id' => $u->id, 'carrera_id' => $carreraId, 'puesto' => 'Coordinación']);

    return $u;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);

    $div = Division::create(['nombre' => 'División de prueba']);
    $datos = ['division_id' => $div->id, 'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8];
    $this->abog = Carrera::create($datos + ['carrera_clave' => 'ABOG', 'nombre' => 'Abogado']);
    $this->hist = Carrera::create($datos + ['carrera_clave' => 'HIST', 'nombre' => 'Historia']);

    $this->adminGlobal = notAdmin();
    $this->adminAbog = notAdmin($this->abog->id);
    $this->adminHist = notAdmin($this->hist->id);

    $this->userEmpresa = notUser(Rol::Empresa);
    $this->empresa = Empresa::create([
        'user_id' => $this->userEmpresa->id, 'rfc' => 'EPA010101AB1', 'razon_social' => 'Empresa de Prueba',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto',
    ]);

    $this->convenio = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-N-001',
        'fecha_recepcion' => now()->subMonths(3), 'fecha_inicio_vigencia' => now()->subMonth(),
        'fecha_fin_vigencia' => now()->addYears(3), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 5,
    ]);
    $this->convenio->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);

    $this->programa = Programa::create([
        'convenio_id' => $this->convenio->id, 'carrera_id' => $this->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Auxiliar jurídico', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 2, 'estado' => ProgramaEstado::Publicado,
    ]);

    $this->anaUser = notUser(Rol::Alumno);
    $this->ana = Alumno::create([
        'user_id' => $this->anaUser->id, 'carrera_id' => $this->abog->id, 'num_control' => '215000001',
        'vigencia_imss' => now()->addYear()->toDateString(),
    ]);
});

test('una postulación avisa solo a la coordinación con alcance sobre la carrera', function () {
    Notification::fake();

    $this->actingAs($this->anaUser)->post(route('alumno.oferta-programas.postular', $this->programa), [
        'porcentaje_creditos_declarado' => 70, 'kardex' => notPdf(),
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($this->adminGlobal, AvisoOperativo::class);
    Notification::assertSentTo($this->adminAbog, AvisoOperativo::class);
    Notification::assertNotSentTo($this->adminHist, AvisoOperativo::class);   // Historia no tiene nada que ver
    Notification::assertNotSentTo($this->anaUser, AvisoOperativo::class);
});

test('al emitir la carta de presentación se avisa por correo al alumno y en plataforma a la empresa', function () {
    $po = Postulacion::create(['alumno_id' => $this->ana->id, 'programa_id' => $this->programa->id,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);

    Notification::fake();

    $this->actingAs($this->adminGlobal)->post(route('admin.postulaciones.carta', $po))->assertSessionHasNoErrors();

    Notification::assertSentTo($this->anaUser, AvisoOperativo::class, fn ($n, $canales) => in_array('mail', $canales, true));
    Notification::assertSentTo($this->userEmpresa, AvisoOperativo::class, fn ($n, $canales) => $canales === ['database']);
});

test('las cuentas desactivadas no reciben avisos', function () {
    $po = Postulacion::create(['alumno_id' => $this->ana->id, 'programa_id' => $this->programa->id,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $this->userEmpresa->update(['activo' => false]);

    Notification::fake();

    $this->actingAs($this->adminGlobal)->post(route('admin.postulaciones.carta', $po))->assertSessionHasNoErrors();

    Notification::assertSentTo($this->anaUser, AvisoOperativo::class);
    Notification::assertNotSentTo($this->userEmpresa, AvisoOperativo::class);
});

test('un reporte rechazado avisa por correo y uno validado solo en la plataforma', function () {
    $po = Postulacion::create(['alumno_id' => $this->ana->id, 'programa_id' => $this->programa->id,
        'estado' => PostulacionEstado::Aceptada, 'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $practica = Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(5), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso]);

    $base = ['practica_id' => $practica->id, 'tipo' => 'mensual', 'periodo_inicio' => today()->subWeeks(2),
        'periodo_fin' => today(), 'horas_reportadas' => 40];
    $malo = Reporte::create($base);
    $bueno = Reporte::create($base);

    Notification::fake();

    $this->actingAs($this->adminGlobal)
        ->post(route('admin.reportes.resolver', $malo), ['estado' => 'rechazado', 'evaluacion_profesor' => 'Horas inconsistentes'])
        ->assertSessionHasNoErrors();
    Notification::assertSentTo($this->anaUser, AvisoOperativo::class, fn ($n, $c) => in_array('mail', $c, true));

    $this->actingAs($this->adminGlobal)
        ->post(route('admin.reportes.resolver', $bueno), ['estado' => 'validado'])
        ->assertSessionHasNoErrors();
    Notification::assertSentTo($this->anaUser, AvisoOperativo::class, fn ($n, $c) => $c === ['database']);
});

test('el aviso de vencimiento se envía una sola vez y respeta el alcance', function () {
    $this->convenio->update(['fecha_fin_vigencia' => now()->addDays(25)]);

    $this->artisan('practicas:avisos-programados')->assertSuccessful();

    expect($this->userEmpresa->notifications()->count())->toBe(1)
        ->and($this->adminGlobal->notifications()->count())->toBe(1)
        ->and($this->adminAbog->notifications()->count())->toBe(1)    // su carrera participa en el convenio
        ->and($this->adminHist->notifications()->count())->toBe(0);

    $this->artisan('practicas:avisos-programados')->assertSuccessful();   // segunda corrida: sin duplicados

    expect($this->userEmpresa->notifications()->count())->toBe(1)
        ->and($this->adminGlobal->notifications()->count())->toBe(1);
});

test('no se avisa a la empresa del vencimiento si ya está renovando', function () {
    $this->convenio->update(['fecha_fin_vigencia' => now()->addDays(25)]);
    Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Solicitado, 'fecha_recepcion' => today(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);

    $this->artisan('practicas:avisos-programados')->assertSuccessful();

    expect($this->userEmpresa->notifications()->count())->toBe(0)
        ->and($this->adminGlobal->notifications()->count())->toBe(1);
});

test('la campana abre el destino del aviso y nunca un dominio externo', function () {
    $this->anaUser->notify(new AvisoOperativo('Interno', '/alumno/mis-practicas'));
    $this->anaUser->notify(new AvisoOperativo('Externo', 'https://sitio-malo.test/x'));

    $interno = $this->anaUser->notifications()->where('data->mensaje', 'Interno')->firstOrFail();
    $externo = $this->anaUser->notifications()->where('data->mensaje', 'Externo')->firstOrFail();

    $this->actingAs($this->anaUser)->get(route('notificaciones.abrir', $interno->id))->assertRedirect('/alumno/mis-practicas');
    $this->actingAs($this->anaUser)->get(route('notificaciones.abrir', $externo->id))->assertRedirect(route('notificaciones.index'));
});

test('una solicitud de convenio avisa por correo y campana solo a la coordinación global activa', function () {
    $inactivo = notAdmin();
    $inactivo->update(['activo' => false]);

    $u = notUser(Rol::Empresa);
    $nueva = Empresa::create(['user_id' => $u->id, 'rfc' => 'EPB020202CD2', 'razon_social' => 'Empresa Nueva',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto']);

    Notification::fake();

    $this->actingAs($u)->post(route('empresa.convenio.solicitar'), [
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 3, 'carreras' => [$this->abog->id],
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($this->adminGlobal, NuevaSolicitudConvenio::class,
        fn ($n, $canales) => in_array('mail', $canales, true) && in_array('database', $canales, true));
    Notification::assertNotSentTo($inactivo, NuevaSolicitudConvenio::class);
    Notification::assertNotSentTo($this->adminAbog, NuevaSolicitudConvenio::class);   // coordinador de una carrera: no es su trámite

    $convenio = Convenio::where('empresa_id', $nueva->id)->firstOrFail();
    $mail = (new NuevaSolicitudConvenio($convenio))->toMail($this->adminGlobal);

    expect($mail->subject)->toContain('Empresa Nueva')
        ->and(collect($mail->introLines)->join(' '))->toContain('Empresa Nueva')->toContain('incompleto');
});