<?php

/**
 * Ajustes de operación (23/11/2026):
 *  - Duplicados normalizados (división, carrera, empresa).
 *  - Convenios por vencer con acceso directo a la empresa.
 *  - Jurídica: versiones (solo la vigente se imprime), vigencia precargada, correo configurable al turnar.
 *  - Empresa: aviso por correo cuando el dictamen académico la rechaza o excluye carreras.
 *  - Coordinación: vista de dictamen con pestañas y cancelar; catálogo de profesores.
 *  - Alumno: reporte redactado en pantalla → PDF institucional → escaneo firmado.
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EstadoDocumentoFisico;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Configuracion;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Notifications\BorradorTurnadoJuridica;
use App\Notifications\ConvenioActualizado;
use App\Support\SolicitudEmpresa;
use App\Support\TextoAviso;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function ajUsuario(Rol $rol, ?array $personal = null): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);
    if ($personal !== null) {
        Personal::create(['user_id' => $u->id] + $personal);
    }

    return $u;
}

function ajPdf(string $n = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($n, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed(RolesSeeder::class);

    $this->division = Division::create(['nombre' => 'División de Estudios Jurídicos']);
    $this->abog = Carrera::create(['division_id' => $this->division->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Licenciatura en Abogado',
        'horas_requeridas' => 200, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);
    $this->crim = Carrera::create(['division_id' => $this->division->id, 'carrera_clave' => 'CRIM', 'nombre' => 'Licenciatura en Criminología',
        'horas_requeridas' => 200, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);

    $this->admin = ajUsuario(Rol::Administrador, ['puesto' => 'Administración']);
    $this->lorena = ajUsuario(Rol::Vinculacion, ['puesto' => 'Vinculación']);
    $this->coordAbog = ajUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id, 'puesto' => 'Coordinación ABOG']);
    $this->coordCrim = ajUsuario(Rol::Coordinador, ['carrera_id' => $this->crim->id, 'puesto' => 'Coordinación CRIM']);

    $this->empresaUser = ajUsuario(Rol::Empresa);
    $this->empresa = Empresa::create(['user_id' => $this->empresaUser->id, 'rfc' => 'AJU010101AB1', 'razon_social' => 'Ajustes Jurídicos S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto']);
});

/* ------------------------------- Duplicados ------------------------------- */

test('no se repiten divisiones, carreras ni empresas aunque cambien mayúsculas o espacios', function () {
    $this->actingAs($this->admin)->post(route('admin.catalogos.divisiones.store'), ['nombre' => '  división de  estudios JURÍDICOS '])
        ->assertSessionHasErrors(['nombre' => 'Ya existe una división con ese nombre.']);
    $this->actingAs($this->admin)->post(route('admin.catalogos.carreras.store'), ['division_id' => $this->division->id, 'carrera_clave' => 'ABO2', 'nombre' => 'licenciatura en abogado'])
        ->assertSessionHasErrors(['nombre' => 'Ya existe una carrera con ese nombre.']);
    $this->actingAs($this->admin)->post(route('admin.catalogos.carreras.store'), ['division_id' => $this->division->id, 'carrera_clave' => 'abog', 'nombre' => 'Otra carrera'])
        ->assertSessionHasErrors('carrera_clave');
    // Renombrar una división a su mismo nombre sí se permite
    $this->actingAs($this->admin)->put(route('admin.catalogos.divisiones.update', $this->division), ['nombre' => 'División de Estudios Jurídicos'])
        ->assertSessionHasNoErrors();

    auth()->logout();
    $this->post(route('registro.empresa'), [
        'razon_social' => 'AJUSTES  jurídicos s.c.', 'rfc' => 'NUE010101AB1', 'tipo_organismo' => 'privado',
        'name' => 'Persona Contacto', 'contacto_cargo' => 'Gerente', 'contacto_telefono' => '3312345678',
        'email' => 'nueva@empresa.mx', 'password' => 'Clave-Segura-2026', 'password_confirmation' => 'Clave-Segura-2026', 'aviso_privacidad' => '1',
    ])->assertSessionHasErrors('razon_social');
});

/* ------------------------------- Por vencer ------------------------------- */

test('Vinculación tiene acceso directo a los convenios por vencer y de ahí a cada empresa', function () {
    $c = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-AJ-1',
        'fecha_recepcion' => now()->subYears(2), 'fecha_inicio_vigencia' => now()->subYears(2), 'fecha_fin_vigencia' => now()->addDays(20),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);

    // «Por vencer» es una pestaña de la bandeja, con liga directa a la empresa
    $otro = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Solicitado, 'fecha_recepcion' => today(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $this->actingAs($this->lorena)->get(route('admin.lista-convenios', ['estado' => 'por_vencer']))->assertOk()
        ->assertSee('CV-AJ-1')->assertSee('para vencer')->assertSee('Renovación en trámite')
        ->assertSee(route('admin.empresas.show', $this->empresa), false)
        ->assertDontSee(route('admin.convenios.show', $otro), false);
    $this->actingAs($this->lorena)->get(route('admin.lista-convenios'))->assertOk()
        ->assertSee('Por vencer')->assertSee('estado=por_vencer', false);
    $this->actingAs($this->lorena)->get(route('admin.convenios.por-vencer'))->assertRedirect(route('admin.lista-convenios', ['estado' => 'por_vencer']));
    $this->actingAs($this->lorena)->get(route('admin.dashboard'))->assertOk()
        ->assertSee(route('admin.empresas.show', $this->empresa), false);

    // El aviso diario lleva directo a la empresa
    $this->artisan('practicas:avisos-programados')->assertSuccessful();
    Notification::assertSentTo($this->lorena, AvisoOperativo::class,
        fn ($n) => str_ends_with($n->url, '/admin/empresas/'.$this->empresa->id));
});

/* ------------------------------- Jurídica ------------------------------- */

function ajConvenioEnRedaccion($test): Convenio
{
    $c = Convenio::create(['empresa_id' => $test->empresa->id, 'estado' => ConvenioEstado::EnValidacionAcademica,
        'fecha_recepcion' => today(), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1, 'vigencia_anios' => 2]);
    $c->dictamenes()->create(['carrera_id' => $test->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $c->seguimientos()->create(['etapa' => 'redaccion_borrador', 'estado' => 'pendiente', 'fecha_registro' => today()]);

    return $c;
}

test('cada borrador es una versión; solo la vigente se imprime y la vigencia se precarga', function () {
    $c = ajConvenioEnRedaccion($this);

    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador', $c), ['borrador' => ajPdf('v1.pdf'), 'vigencia_anios' => 2])->assertSessionHasNoErrors();
    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador', $c), ['borrador' => ajPdf('v2-juridico.pdf'), 'vigencia_anios' => 2])->assertSessionHasNoErrors();

    $versiones = $c->fresh()->versionesBorrador();
    expect($versiones->pluck('version')->all())->toBe([1, 2])
        ->and($versiones->last()['vigente'])->toBeTrue();

    $v1 = $versiones->first()['archivo'];
    $v2 = $versiones->last()['archivo'];

    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $c))->assertOk()
        ->assertSee('Obsoleta')->assertSee('Vigente')->assertSee('La versión anterior dice 2 años')
        ->assertSee(route('admin.convenios.archivos.descargar', [$c, $v2]), false)
        ->assertDontSee(route('admin.convenios.archivos.descargar', [$c, $v1]), false);

    // La impresión de la v1 ya no se entrega; la vigente sí
    $this->actingAs($this->lorena)->get(route('admin.convenios.archivos.descargar', [$c, $v1]))->assertSessionHasErrors('convenio');
    $this->actingAs($this->lorena)->get(route('admin.convenios.archivos.descargar', [$c, $v2]))->assertOk();
});

test('al turnar se congela la versión, queda en la bitácora y se envía al correo configurado de Jurídica', function () {
    $c = ajConvenioEnRedaccion($this);
    $this->empresa->addMedia(ajPdf('acta.pdf'))->toMediaCollection('acta_constitutiva');
    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador', $c), ['borrador' => ajPdf('v1.pdf'), 'vigencia_anios' => 2]);

    // Correo de Jurídica: lo configura Vinculación; el administrador no
    $this->actingAs($this->admin)->put(route('admin.configuracion.juridica'), ['correo_juridica' => 'juridico@csh.udg.mx'])->assertForbidden();
    $this->actingAs($this->lorena)->put(route('admin.configuracion.juridica'), ['correo_juridica' => 'no-es-correo'])->assertSessionHasErrors('correo_juridica');
    $this->actingAs($this->lorena)->put(route('admin.configuracion.juridica'), ['correo_juridica' => ' Juridico@CSH.udg.mx '])->assertSessionHasNoErrors();
    expect(Configuracion::valor(Configuracion::CORREO_JURIDICA))->toBe('juridico@csh.udg.mx');

    $this->actingAs($this->lorena)->post(route('admin.convenios.juridica.turnar', $c))->assertSessionHasNoErrors()
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'enviado por correo a juridico@csh.udg.mx'));

    expect($c->fresh()->estado)->toBe(ConvenioEstado::EnRevisionJuridica)
        ->and($c->etapasAbiertas()->first()->comentario)->toContain('versión v1')->toContain('juridico@csh.udg.mx');

    Notification::assertSentOnDemand(BorradorTurnadoJuridica::class, function ($n, $canales, AnonymousNotifiable $dest) {
        $correo = $n->toMail($dest);

        return $dest->routes['mail'] === 'juridico@csh.udg.mx' && $n->version === 1 && count($correo->rawAttachments) + count($correo->attachments) >= 2;
    });

    // Congelado: ya no se adjuntan versiones hasta el dictamen
    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador', $c), ['borrador' => ajPdf('otra.pdf'), 'vigencia_anios' => 2])
        ->assertSessionHasErrors('convenio');
});

test('el correo de Jurídica viene precargado y, si se quita, turnar solo registra el envío', function () {
    expect(Configuracion::valor(Configuracion::CORREO_JURIDICA))->toBe('juridico@csh.udg.mx');
    Configuracion::guardar(Configuracion::CORREO_JURIDICA, null);
    $c = ajConvenioEnRedaccion($this);
    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador', $c), ['borrador' => ajPdf('v1.pdf'), 'vigencia_anios' => 2]);

    $this->actingAs($this->lorena)->post(route('admin.convenios.juridica.turnar', $c))->assertSessionHasNoErrors();

    Notification::assertNothingSentTo(new AnonymousNotifiable);
    expect($c->etapasAbiertas()->first()->comentario)->toContain('correo propio o en carpeta física');
});

/* ------------------------------- Dictamen académico ------------------------------- */

test('la empresa recibe correo con las observaciones cuando una carrera la excluye', function () {
    $c = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnValidacionAcademica,
        'fecha_recepcion' => today(), 'objetivos' => 'Apoyo jurídico', 'actividades_propuestas' => 'Archivo y captura', 'plazas_estimadas' => 2]);
    $c->seguimientos()->create(['etapa' => 'turno_academico', 'estado' => 'pendiente', 'fecha_registro' => today()]);
    $dAbog = $c->dictamenes()->create(['carrera_id' => $this->abog->id]);
    $dCrim = $c->dictamenes()->create(['carrera_id' => $this->crim->id]);

    // La vista de la coordinación muestra la propuesta en pestañas y el formulario con Cancelar
    $this->actingAs($this->coordCrim)->get(route('coordinacion.lista-dictamenes'))->assertOk()
        ->assertSee('Objetivos y actividades')->assertSee('La organización')->assertSee('Emitir dictamen')
        ->assertSee('Archivo y captura')->assertSee('Cancelar')->assertSee('Revisar y dictaminar');

    $this->actingAs($this->coordCrim)->post(route('coordinacion.dictamenes.registrar', $dCrim), [
        'dictamen' => 'negativo', 'observaciones' => 'Las actividades no corresponden al perfil criminológico', 'documento' => ajPdf('dict.pdf'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->coordAbog)->post(route('coordinacion.dictamenes.registrar', $dAbog), [
        'dictamen' => 'aprobatorio', 'documento' => ajPdf('dict.pdf'),
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($this->empresaUser, ConvenioActualizado::class, fn ($n, $canales) => in_array('mail', $canales, true)
        && str_contains($n->mensaje, 'Licenciatura en Criminología: Las actividades no corresponden al perfil criminológico')
        && str_contains($n->mensaje, 'ajusta tus objetivos y actividades'));
});

/* ------------------------------- Profesores ------------------------------- */

test('la coordinación administra su catálogo de profesores y solo asigna activos', function () {
    $otraDiv = Division::create(['nombre' => 'División de Estudios Históricos']);

    // La coordinación es de una sola división: ni la tabla ni el formulario la piden
    $this->actingAs($this->coordAbog)->get(route('coordinacion.profesores'))->assertOk()->assertSee('Agregar profesor')
        ->assertDontSee('name="division_id" x-model', false)
        ->assertSee('la división de tu coordinación');

    $this->actingAs($this->coordAbog)->post(route('coordinacion.profesores.store'), ['nombre' => 'Dra. Ana López Ruiz', 'email' => 'ana@academicos.udg.mx'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->coordAbog)->post(route('coordinacion.profesores.store'), ['nombre' => 'dra. ana  lópez ruiz'])
        ->assertSessionHasErrors(['nombre' => 'Ese profesor ya está en el catálogo.']);
    // Aunque se manipule el formulario, el profesor queda en la división de la coordinación
    $this->actingAs($this->coordAbog)->post(route('coordinacion.profesores.store'), ['nombre' => 'Mtro. Otro Profesor', 'division_id' => $otraDiv->id])
        ->assertSessionHasNoErrors();
    expect(Profesor::where('nombre', 'Mtro. Otro Profesor')->value('division_id'))->toBe($this->division->id);

    $prof = Profesor::where('email', 'ana@academicos.udg.mx')->firstOrFail();
    expect($prof->division_id)->toBe($this->division->id);
    $this->actingAs($this->coordAbog)->put(route('coordinacion.profesores.update', $prof), ['nombre' => 'Dra. Ana López Ruiz', 'email' => 'ana.lopez@academicos.udg.mx', 'telefono' => '33 1234-5678', 'extension' => '23549'])
        ->assertSessionHasNoErrors();
    expect($prof->fresh()->telefono)->toBe('3312345678 ext. 23549');
    // Solo números: letras o un teléfono incompleto se rechazan
    $this->actingAs($this->coordAbog)->put(route('coordinacion.profesores.update', $prof), ['nombre' => 'Dra. Ana López Ruiz', 'telefono' => '33abc45678'])
        ->assertSessionHasErrors('telefono');
    $this->actingAs($this->coordAbog)->put(route('coordinacion.profesores.update', $prof), ['nombre' => 'Dra. Ana López Ruiz', 'extension' => '12a'])
        ->assertSessionHasErrors('extension');

    // Desactivar: ya no aparece para asignar
    $this->actingAs($this->coordAbog)->post(route('coordinacion.profesores.activo', $prof))->assertSessionHasNoErrors();
    expect($prof->fresh()->activo)->toBeFalse()
        ->and(Profesor::activos()->pluck('nombre')->all())->toBe(['Mtro. Otro Profesor']);

    // Otra coordinación (otra división) no lo toca
    $hist = Carrera::create(['division_id' => $otraDiv->id, 'carrera_clave' => 'HIST', 'nombre' => 'Historia', 'horas_requeridas' => 1, 'porcentaje_creditos_minimo' => 1, 'creditos_otorgados' => 1]);
    $coordHist = ajUsuario(Rol::Coordinador, ['carrera_id' => $hist->id, 'puesto' => 'HIST']);
    $this->actingAs($coordHist)->post(route('coordinacion.profesores.activo', $prof))->assertForbidden();
});

test('la coordinación revisa la vacante completa en una ventana antes de aprobarla o rechazarla', function () {
    $c = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-AJ-7',
        'fecha_recepcion' => now()->subMonth(), 'fecha_inicio_vigencia' => now()->subMonth(), 'fecha_fin_vigencia' => now()->addYears(2),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $c->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $vacante = Programa::create(['convenio_id' => $c->id, 'carrera_id' => $this->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Auxiliar de litigio', 'descripcion' => 'Apoyo en el área contenciosa', 'actividades' => ['Redactar demandas', 'Acudir a juzgados'],
        'perfil_requerido' => 'Séptimo semestre', 'horario' => 'Lun a vie, 9:00 a 13:00', 'sede' => 'Av. Juárez 100, Guadalajara',
        'cupos_totales' => 3, 'estado' => ProgramaEstado::PendienteAprobacion]);

    $this->actingAs($this->coordAbog)->get(route('coordinacion.lista-programas'))->assertOk()
        ->assertSee('Revisar vacante')
        ->assertSee('Horario y días de asistencia')
        ->assertSee('Redactar demandas', false)
        ->assertSee('100, Guadalajara', false)
        ->assertSee('CV-AJ-7', false);

    // Rechazar exige motivo
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.rechazar', $vacante), ['motivo' => ''])->assertSessionHasErrors('motivo');
    $this->actingAs($this->coordAbog)->post(route('coordinacion.programas.rechazar', $vacante), ['motivo' => 'Las actividades no son de la carrera.'])
        ->assertSessionHasNoErrors();
    expect($vacante->fresh()->estado)->toBe(ProgramaEstado::Rechazado);
});

/* ------------------------------- Reporte del alumno ------------------------------- */

function ajPracticaEnCurso($test): Practica
{
    $alumnoUser = ajUsuario(Rol::Alumno);
    $alumno = Alumno::create(['user_id' => $alumnoUser->id, 'carrera_id' => $test->abog->id, 'num_control' => '219000001', 'grado' => 7, 'turno' => 'matutino']);
    $c = Convenio::create(['empresa_id' => $test->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-AJ-9',
        'fecha_recepcion' => now()->subMonth(), 'fecha_inicio_vigencia' => now()->subMonth(), 'fecha_fin_vigencia' => now()->addYears(2),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $c->dictamenes()->create(['carrera_id' => $test->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $programa = Programa::create(['convenio_id' => $c->id, 'carrera_id' => $test->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Auxiliar jurídico', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p', 'cupos_totales' => 1, 'estado' => ProgramaEstado::Publicado]);
    $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()->subMonth()]);

    return Practica::create(['postulacion_id' => $po->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(4), 'horas_requeridas' => 200, 'estado' => PracticaEstado::EnCurso]);
}

test('el alumno redacta su reporte, descarga el PDF institucional y lo sube firmado para validarlo', function () {
    $practica = ajPracticaEnCurso($this);
    $alumnoUser = $practica->postulacion->alumno->user;

    $this->actingAs($alumnoUser)->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => 'mensual', 'periodo_inicio' => today()->subWeeks(3)->toDateString(), 'periodo_fin' => today()->toDateString(), 'horas_reportadas' => 60,
    ])->assertSessionHasErrors('actividades');

    $this->actingAs($alumnoUser)->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => 'mensual', 'periodo_inicio' => today()->subWeeks(3)->toDateString(), 'periodo_fin' => today()->toDateString(), 'horas_reportadas' => 60,
        'actividades' => 'Elaboré tres demandas laborales y acompañé dos audiencias en la junta.', 'logros' => 'Aprendí a integrar un expediente.',
    ])->assertSessionHasNoErrors();

    $reporte = Reporte::firstOrFail();
    expect($reporte->numero)->toBe(1)->and($reporte->getMedia('reporte_firmado'))->toHaveCount(0);

    $this->actingAs($alumnoUser)->get(route('alumno.mis-documentos'))->assertOk()
        ->assertSee(route('alumno.documentos.reporte.formato', $reporte), false)->assertSee('Registrar el reporte 2');

    $pdf = $this->actingAs($alumnoUser)->get(route('alumno.documentos.reporte.formato', $reporte));
    $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(str_starts_with($pdf->getContent(), '%PDF'))->toBeTrue();

    // Nadie más descarga su formato
    $otro = ajUsuario(Rol::Alumno);
    Alumno::create(['user_id' => $otro->id, 'carrera_id' => $this->abog->id, 'num_control' => '219000002', 'grado' => 7, 'turno' => 'matutino']);
    $this->actingAs($otro)->get(route('alumno.documentos.reporte.formato', $reporte))->assertForbidden();

    // Sin escaneo firmado, la coordinación no puede validarlo ni rechazarlo: solo ve «Esperando documento del alumno»
    $this->actingAs($this->coordAbog)->post(route('coordinacion.reportes.resolver', $reporte), ['estado' => 'validado'])->assertSessionHasErrors('reporte');
    $this->actingAs($this->coordAbog)->post(route('coordinacion.reportes.resolver', $reporte), ['estado' => 'rechazado', 'evaluacion_profesor' => 'x'])->assertSessionHasErrors('reporte');
    $this->actingAs($this->coordAbog)->get(route('coordinacion.lista-reportes'))->assertOk()
        ->assertSee('Esperando documento del alumno')->assertDontSee('value="validado"', false);

    $this->actingAs($alumnoUser)->post(route('alumno.documentos.reporte', $reporte), ['archivo' => ajPdf('reporte-1-firmado.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($this->coordAbog)->get(route('coordinacion.lista-reportes'))->assertOk()
        ->assertDontSee('Esperando documento del alumno')->assertSee('value="validado"', false)->assertSee('Validar el reporte', false);
    // Rechazar exige motivo
    $this->actingAs($this->coordAbog)->post(route('coordinacion.reportes.resolver', $reporte), ['estado' => 'rechazado'])->assertSessionHasErrors('evaluacion_profesor');
    $this->actingAs($this->coordAbog)->post(route('coordinacion.reportes.resolver', $reporte), ['estado' => 'validado'])->assertSessionHasNoErrors();
    expect($reporte->fresh()->estado)->toBe(ReporteEstado::Validado);
});

test('con formato propio de la carrera el alumno sube su PDF firmado y el sistema solo lleva las horas', function () {
    $practica = ajPracticaEnCurso($this);

    $this->actingAs($practica->postulacion->alumno->user)->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => 'mensual', 'periodo_inicio' => today()->subWeeks(2)->toDateString(), 'periodo_fin' => today()->toDateString(), 'horas_reportadas' => 40,
        'archivo' => ajPdf('formato-de-mi-carrera.pdf'),
    ])->assertSessionHasNoErrors();

    expect(Reporte::firstOrFail()->getMedia('reporte_firmado'))->toHaveCount(1);
});

/* ------------------------------- Captura: solo números donde van números ------------------------------- */

test('teléfonos, folio y clave solo aceptan su formato en todo el sistema', function () {
    // Empresa (Vinculación): teléfono de 10 números; se limpian espacios y guiones pegados
    $this->actingAs($this->lorena)->put(route('admin.empresas.update', $this->empresa), [
        'rfc' => 'AJU010101AB1', 'razon_social' => 'Ajustes Jurídicos S.C.', 'tipo_organismo' => TipoOrganismo::SociedadCivil->value,
        'contacto_nombre' => 'Laura Pérez', 'contacto_telefono' => '33abc',
    ])->assertSessionHasErrors('contacto_telefono');
    $this->actingAs($this->lorena)->put(route('admin.empresas.update', $this->empresa), [
        'rfc' => 'AJU010101AB1', 'razon_social' => 'Ajustes Jurídicos S.C.', 'tipo_organismo' => TipoOrganismo::SociedadCivil->value,
        'contacto_nombre' => 'Laura Pérez', 'contacto_telefono' => '33 1234-5678',
    ])->assertSessionHasNoErrors();
    expect($this->empresa->fresh()->contacto_telefono)->toBe('3312345678');

    // Coordinación: teléfono y extensión por separado, se guardan juntos
    $this->actingAs($this->coordAbog)->put(route('coordinacion.reglas.update', $this->abog), [
        'horas_requeridas' => 300, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8,
        'coordinacion_telefono' => '3338193300', 'coordinacion_extension' => 'ext1',
    ])->assertSessionHasErrors('coordinacion_extension');
    $this->actingAs($this->coordAbog)->put(route('coordinacion.reglas.update', $this->abog), [
        'horas_requeridas' => 300, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8,
        'coordinacion_telefono' => '3338193300', 'coordinacion_extension' => '23549',
    ])->assertSessionHasNoErrors();
    expect($this->abog->fresh()->coordinacion_telefono)->toBe('3338193300 ext. 23549');
    $this->actingAs($this->coordAbog)->get(route('coordinacion.reglas'))->assertOk()
        ->assertSee('data-solo="digitos"', false)->assertSee('name="coordinacion_extension"', false);

    // Clave de carrera: mayúsculas, números y guiones
    $this->actingAs($this->admin)->post(route('admin.catalogos.carreras.store'), ['division_id' => $this->division->id, 'carrera_clave' => 'AB OG!', 'nombre' => 'Otra licenciatura'])
        ->assertSessionHasErrors('carrera_clave');

    // Los formularios marcan el filtro de captura en el navegador
    $this->actingAs($this->lorena)->get(route('admin.empresas.show', $this->empresa))->assertOk()
        ->assertSee('name="contacto_telefono"', false)->assertSee('data-solo="rfc"', false);
});
/* ------------------------------- Rutas protegidas ------------------------------- */

test('sin sesión ninguna ruta privada responde: todas llevan al inicio de sesión', function () {
    $revisadas = 0;
    foreach (Route::getRoutes() as $ruta) {
        if (! in_array('auth', $ruta->gatherMiddleware(), true)) {
            continue;
        }
        $url = '/'.preg_replace('/\{[^}]+\}/', '1', $ruta->uri());
        $metodo = collect($ruta->methods())->first(fn ($x) => $x !== 'HEAD');
        $r = $this->call($metodo, $url);
        // Una ruta con formato fijo en su parámetro ({seccion}, {cp}…) responde 404 antes de pedir sesión: tampoco revela nada
        $aLogin = $r->status() === 302 && str_contains((string) $r->headers->get('Location'), route('login'));
        expect($aLogin || ($r->status() === 404 && $ruta->wheres !== []))
            ->toBeTrue("{$metodo} {$url} respondió {$r->status()} sin sesión");
        $this->app['auth']->forgetGuards();
        $revisadas++;
    }
    expect($revisadas)->toBeGreaterThan(80);
});

test('por URL directa nadie abre el detalle de algo ajeno', function () {
    $practica = ajPracticaEnCurso($this);
    $programa = $practica->postulacion->programa;
    $alumno = $practica->postulacion->alumno;
    $kardex = $alumno->addMedia(ajPdf('kardex.pdf'))->toMediaCollection('kardex');

    // Otra empresa
    $otraUser = ajUsuario(Rol::Empresa);
    Empresa::create(['user_id' => $otraUser->id, 'rfc' => 'OTR010101AB1', 'razon_social' => 'Otra Organización S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto Otro']);
    $this->actingAs($otraUser)->get(route('empresa.practicantes.show', $practica))->assertForbidden();
    $this->actingAs($otraUser)->get(route('empresa.programas.postulantes', $programa))->assertForbidden();

    // Otro alumno
    $otroAlumno = ajUsuario(Rol::Alumno);
    Alumno::create(['user_id' => $otroAlumno->id, 'carrera_id' => $this->abog->id, 'num_control' => '219000009', 'grado' => 7, 'turno' => 'matutino']);
    expect($this->actingAs($otroAlumno)->get(route('alumno.documentos.descargar', $kardex))->status())->toBeIn([403, 404]);

    // Coordinación de otra carrera
    expect($this->actingAs($this->coordCrim)->get(route('coordinacion.alumnos.archivos', [$alumno, $kardex]))->status())->toBeIn([403, 404]);
    $this->actingAs($this->coordAbog)->get(route('coordinacion.alumnos.archivos', [$alumno, $kardex]))->assertOk();

    // Notificación de otra persona
    $id = (string) Str::uuid();
    $alumno->user->notifications()->create(['id' => $id, 'type' => AvisoOperativo::class, 'data' => ['mensaje' => 'Privado', 'url' => '/alumno/dashboard']]);
    $this->actingAs($otroAlumno)->get(route('notificaciones.abrir', $id))->assertNotFound();

    // Una cuenta desactivada ya no entra
    $alumno->user->update(['activo' => false]);
    $this->actingAs($alumno->user)->get(route('alumno.dashboard'))->assertRedirect();
});

/* ------------------------------- Jurídica devuelve el borrador · fecha de firma ------------------------------- */

test('si Jurídica devuelve el borrador, Lorena ve en rojo qué corregir y el expediente solo puede volver a Jurídica', function () {
    $c = ajConvenioEnRedaccion($this);
    $datos = ['vigencia_anios' => 2, 'representante_nombre' => 'Laura Pérez Gómez', 'representante_cargo' => 'Directora', 'constitucion' => 'Escritura 123',
        'atribuciones' => 'Servicios jurídicos', 'facultad_firmante' => 'Poder notarial 45', 'domicilio_legal' => 'Av. Juárez 100', 'lugar_firma' => 'Guadalajara'];

    // Sin fecha de firma: el borrador sale con la fecha en blanco
    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador.generar', $c), $datos)->assertSessionHasNoErrors();
    $v1 = $c->fresh()->getMedia('borrador_convenio')->last();
    expect($v1->getCustomProperty('fecha_firma'))->toBeNull()->and($v1->getCustomProperty('datos'))->toHaveKey('lugar_firma');

    $this->actingAs($this->lorena)->post(route('admin.convenios.juridica.turnar', $c))->assertSessionHasNoErrors();
    $this->actingAs($this->lorena)->post(route('admin.convenios.juridica.dictamen', $c), ['resultado' => 'observado', 'comentario' => 'Corregir la cláusula SEXTA sobre seguros.'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $c))->assertOk()
        ->assertSee('La Unidad Jurídica devolvió el borrador v1 con observaciones')
        ->assertSee('Corregir la cláusula SEXTA sobre seguros.')->assertSee('Qué hacer:')
        ->assertSee('Notificación activa')->assertSee('juridico@csh.udg.mx');

    // Documentos físicos: devuelto con observaciones → solo puede volver a la Unidad Jurídica
    $expediente = $c->documentosFisicos()->where('tipo', 'dictamen_juridico')->first();
    expect($expediente->estado)->toBe(EstadoDocumentoFisico::ConObservaciones);
    $this->actingAs($this->lorena)->post(route('admin.documentos-fisicos.mover', $expediente), ['estado' => 'en_vinculacion'])
        ->assertSessionHasErrors(['estado' => 'Jurídica aún no emite el dictamen favorable: el expediente solo puede volver a la Unidad Jurídica.']);
    $this->actingAs($this->lorena)->post(route('admin.documentos-fisicos.mover', $expediente), ['estado' => 'en_juridica'])->assertSessionHasNoErrors();
    expect($expediente->fresh()->fecha_estado->isToday())->toBeTrue();

    // Ya corregido (v2): el aviso cambia a «en revisión»
    $this->actingAs($this->lorena)->post(route('admin.convenios.borrador.generar', $c), $datos)->assertSessionHasNoErrors();
    $this->actingAs($this->lorena)->get(route('admin.convenios.show', $c))->assertDontSee('devolvió el borrador');   // ya corregido: el aviso rojo desaparece

    // Dictamen favorable → firmas: los juegos finales llevan la fecha real y la vigencia debe empezar ese día
    $this->actingAs($this->lorena)->post(route('admin.convenios.juridica.turnar', $c))->assertSessionHasNoErrors();
    $this->actingAs($this->lorena)->post(route('admin.convenios.juridica.dictamen', $c), ['resultado' => 'aprobado'])->assertSessionHasNoErrors();
    $firma = today()->addWeek();
    $this->actingAs($this->lorena)->post(route('admin.convenios.juegos-finales', $c), ['fecha_firma' => $firma->toDateString()])->assertSessionHasNoErrors();
    expect($c->fresh()->fechaFirmaImpresa()->isSameDay($firma))->toBeTrue();

    $this->actingAs($this->lorena)->post(route('admin.convenios.formalizar', $c), ['folio' => 'CV-AJ-77', 'fecha_inicio_vigencia' => today()->toDateString()])
        ->assertSessionHasErrors('fecha_inicio_vigencia');
});

/* ------------------------------- Renovación con perfiles precargados ------------------------------- */

test('al renovar, «Perfiles requeridos» viene precargado con el convenio vigente y basta guardarlo', function () {
    $vigente = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-2023-014',
        'fecha_recepcion' => now()->subYears(3), 'fecha_inicio_vigencia' => now()->subYears(3), 'fecha_fin_vigencia' => now()->addDays(24),
        'objetivos' => 'Que el practicante aplique la investigación histórica en archivos.', 'actividades_propuestas' => 'Catalogación de fondos, digitalización y atención a investigadores.',
        'plazas_estimadas' => 3, 'medio_conocimiento' => 'Por un egresado', 'experiencia_previa' => 'Ninguno']);
    $vigente->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $vigente->dictamenes()->create(['carrera_id' => $this->crim->id, 'dictamen' => Dictamen::Negativo]);

    $pagina = $this->actingAs($this->empresaUser)->get(route('empresa.convenio', ['seccion' => 'perfiles']))->assertOk()
        ->assertSee('Precargamos los perfiles de tu convenio CV-2023-014')->assertSee('Precargada · revisa y guarda')
        ->assertSee('Catalogación de fondos, digitalización y atención a investigadores.')
        ->assertSee('Por un egresado')
        ->assertDontSee('Para enviar la solicitud te falta:</p>'."\n".'                <p class="mt-0.5">carreras', false);
    // Solo las carreras que ya tenía autorizadas
    expect(preg_match('/elegidas: JSON\.parse\(\'\[(\d+)\]\'\)/', $pagina->getContent(), $m))->toBe(1)->and((int) $m[1])->toBe($this->abog->id);

    // Si renueva igual, guarda lo precargado y la sección queda completa
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'perfiles'), [
        '_seccion' => 'perfiles', 'carreras' => [$this->abog->id], 'objetivos' => $vigente->objetivos, 'actividades_propuestas' => $vigente->actividades_propuestas,
        'plazas_estimadas' => 3, 'medio_conocimiento' => 'Por un egresado', 'experiencia_previa' => 'Ninguno',
    ])->assertSessionHasNoErrors();
    expect(SolicitudEmpresa::faltantes($this->empresa->fresh())['perfiles'])->toBe([]);
});

/* ------------------------------- Los avisos de corrección se van al corregir ------------------------------- */

test('la empresa rechazada recibe aviso y el recuadro rojo desaparece en cuanto corrige su propuesta', function () {
    $rechazado = Convenio::create(['empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnValidacionAcademica, 'fecha_recepcion' => today()->subWeek(),
        'objetivos' => 'Objetivos originales de la propuesta.', 'actividades_propuestas' => 'Actividades originales que no fueron afines al perfil.', 'plazas_estimadas' => 2,
        'medio_conocimiento' => 'Página del CUCSH', 'experiencia_previa' => 'Ninguno']);
    $d = $rechazado->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Pendiente]);
    $rechazado->seguimientos()->create(['etapa' => 'turno_academico', 'estado' => 'pendiente', 'fecha_registro' => today()]);
    $this->actingAs($this->coordAbog)->post(route('coordinacion.dictamenes.registrar', $d), ['dictamen' => 'negativo', 'observaciones' => 'Sin actividades afines al perfil.', 'documento' => ajPdf('dictamen.pdf')])
        ->assertSessionHasNoErrors();

    // Aviso (campana + correo) con qué y dónde corregir
    Notification::assertSentTo($this->empresaUser, ConvenioActualizado::class, fn ($n) => $n->correo && str_contains($n->mensaje, 'Perfiles requeridos'));
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()
        ->assertSee('Tu última solicitud fue rechazada')->assertSee('Sin actividades afines al perfil.')->assertSee('Corrige aquí:');

    // Corrige y guarda «Perfiles requeridos»: el recuadro rojo se va
    $this->travel(1)->minutes();
    $this->actingAs($this->empresaUser)->put(route('empresa.convenio.seccion', 'perfiles'), [
        '_seccion' => 'perfiles', 'carreras' => [$this->abog->id], 'objetivos' => 'Objetivos ajustados al plan de estudios de Abogado.',
        'actividades_propuestas' => 'Redacción de demandas, investigación jurídica y audiencias acompañadas.', 'plazas_estimadas' => 2,
        'medio_conocimiento' => 'Página del CUCSH', 'experiencia_previa' => 'Ninguno',
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->empresaUser)->get(route('empresa.convenio'))->assertOk()
        ->assertDontSee('Tu última solicitud fue rechazada')->assertDontSee('Corrige aquí:')->assertSee('Corregiste tu propuesta');
});

/* ------------------------------- Notificaciones: título, detalle y archivo ------------------------------- */

test('las notificaciones largas se muestran con título y detalle, y las leídas antiguas solo en «Archivadas»', function () {
    $largo = 'Tu coordinación te devolvió el reporte del 01/11 al 30/11/2026. Motivo: «Las horas no coinciden». Qué hacer: entra a «Mis documentos › Reportes de horas» y súbelo de nuevo.';
    expect(TextoAviso::partes($largo))->toBe([
        'titulo' => 'Tu coordinación te devolvió el reporte del 01/11 al 30/11/2026',
        'detalle' => 'Motivo: «Las horas no coinciden». Qué hacer: entra a «Mis documentos › Reportes de horas» y súbelo de nuevo.',
    ]);

    $u = $this->empresaUser;
    $u->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'x', 'data' => ['mensaje' => $largo, 'url' => '/x']]);
    $u->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'x', 'data' => ['mensaje' => 'Aviso viejo ya leído.', 'url' => '/x'], 'read_at' => now()->subDays(20), 'created_at' => now()->subDays(25)]);

    $this->actingAs($u)->getJson(route('notificaciones.resumen'))->assertOk()
        ->assertJsonCount(1, 'items')->assertJsonPath('items.0.titulo', 'Tu coordinación te devolvió el reporte del 01/11 al 30/11/2026')
        ->assertJsonMissingPath('pendientes');
    $this->actingAs($u)->get(route('notificaciones.index'))->assertOk()->assertSee('Tu coordinación te devolvió el reporte')->assertDontSee('Aviso viejo ya leído');
    $this->actingAs($u)->get(route('notificaciones.index', ['ver' => 'archivadas']))->assertOk()->assertSee('Aviso viejo ya leído');
});
