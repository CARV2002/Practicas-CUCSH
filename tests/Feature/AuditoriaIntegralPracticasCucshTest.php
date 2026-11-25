<?php

/**
 * AUDITORÍA INTEGRAL — Prácticas Profesionales CUCSH (de 0 a 100), por HTTP y como lo hace cada rol.
 *
 * Base de datos: SQLite en memoria (phpunit.xml: DB_CONNECTION=sqlite, DB_DATABASE=:memory:) con RefreshDatabase
 * (tests/Pest.php): nunca toca la base MySQL local. Los archivos van a un disco falso: el disco privado del
 * sistema se llama «local» (config/filesystems.php → storage/app/private), así que se finge ese.
 *
 * Correspondencias con el encargo (nombres reales del sistema):
 *  - «/empresa/vacantes/crear»            → POST empresa.programas.store (no existe una pantalla «crear»: es un formulario en «Mis vacantes»)
 *  - «convenio activo»                    → ConvenioEstado::Formalizado y vigente
 *  - «Coordinador sube la carta de presentación» → la GENERA el sistema (coordinacion.postulaciones.carta), en Word + PDF
 *  - estado final «concluido»             → PracticaEstado::Acreditada (tras SIIAU)
 *  - «Devuelto con observaciones»         → resultado «observado» del dictamen jurídico / EstadoDocumentoFisico::ConObservaciones
 *  - «eliminar división o carrera»        → el catálogo no tiene borrado (solo alta y edición): se verifica que no exista
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\EstadoDocumentoFisico as EF;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\Rol;
use App\Enums\TipoDocumentoFisico as TF;
use App\Enums\TipoOrganismo;
use App\Enums\ValidacionEstado;
use App\Helpers\MenuHelper;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Convenio;
use App\Models\ConvenioCarrera;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use App\Models\Programa;
use App\Models\User;
use App\Notifications\AvisoOperativo;
use App\Notifications\BorradorTurnadoJuridica;
use App\Notifications\ConfigurarContrasena;
use App\Notifications\NuevaSolicitudConvenio;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/* ================================ Ayudantes ================================ */

function aiPdf(string $nombre = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function aiUsuario(Rol $rol, array $personal = [], string $nombre = 'Usuario Prueba'): User
{
    $u = User::factory()->create(['name' => $nombre]);
    $u->assignRole($rol->value);
    if (in_array($rol, [Rol::Administrador, Rol::Vinculacion, Rol::Coordinador], true)) {
        Personal::create(['user_id' => $u->id, 'puesto' => $rol->value] + $personal);
    }

    return $u->fresh();
}

/** Texto plano de un .docx (cláusulas del convenio generado). */
function aiTextoDocx(string $ruta): string
{
    $zip = new ZipArchive;
    $zip->open($ruta);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    return html_entity_decode(strip_tags(str_replace('</w:p>', "\n", (string) $xml)));
}

/** Avisos (campana) recibidos por un usuario que contienen un texto. */
function aiAvisos(User $u, string $texto): int
{
    return Notification::sent($u, AvisoOperativo::class)->filter(fn ($n) => str_contains($n->mensaje, $texto))->count();
}

/** Convenio formalizado y vigente con las carreras aprobadas (precondición de las fases 4 y 5). */
function aiConvenioVigente(Empresa $empresa, array $carreras, ?Carbon $fin = null): Convenio
{
    $c = Convenio::create(['empresa_id' => $empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-AI-'.$empresa->id,
        'fecha_recepcion' => now()->subYear(), 'fecha_inicio_vigencia' => now()->subMonths(6), 'fecha_fin_vigencia' => $fin ?? now()->addYears(2),
        'objetivos' => 'Prácticas en litigio civil y análisis criminológico de casos.', 'actividades_propuestas' => 'Revisión de expedientes, redacción de escritos y audiencias.',
        'plazas_estimadas' => 4, 'medio_conocimiento' => 'Página del CUCSH', 'experiencia_previa' => 'Ninguno', 'vigencia_anios' => 2]);
    foreach ($carreras as $carrera) {
        $c->dictamenes()->create(['carrera_id' => $carrera->id, 'dictamen' => Dictamen::Aprobatorio]);
    }

    return $c;
}

/** Alumno con expediente (kardex + IMSS) ya validado por su coordinación. */
function aiAlumnoValidado(Carrera $carrera, string $control, float $creditos, User $coord): Alumno
{
    $u = aiUsuario(Rol::Alumno, nombre: 'Alumno '.$control);
    $a = Alumno::create(['user_id' => $u->id, 'carrera_id' => $carrera->id, 'num_control' => $control, 'grado' => 7, 'turno' => 'matutino',
        'vigencia_imss' => now()->addYear(), 'porcentaje_creditos' => $creditos]);
    foreach (['kardex', 'constancia_imss'] as $col) {
        $m = $a->addMedia(aiPdf("$col.pdf"))->toMediaCollection($col);
        $a->resolverDocumento($m, ValidacionEstado::Validada, null, $coord->id);
    }

    return $a->fresh();
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    Mail::fake();
    $this->seed(RolesSeeder::class);

    $this->admin = aiUsuario(Rol::Administrador, nombre: 'Mtra. Jefa Servicios');
    $this->division = Division::create(['nombre' => 'División de Estudios Jurídicos']);
    $this->abog = Carrera::create(['division_id' => $this->division->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Licenciatura en Abogado',
        'horas_requeridas' => 300, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);
    $this->crim = Carrera::create(['division_id' => $this->division->id, 'carrera_clave' => 'CRIM', 'nombre' => 'Licenciatura en Criminología',
        'horas_requeridas' => 300, 'porcentaje_creditos_minimo' => 60, 'creditos_otorgados' => 10]);
});

/* =============================================================================================
 * FASE 1 · ADMINISTRADOR GENERAL
 * ============================================================================================= */

test('FASE 1.1 · el administrador opera solo Auditoría, Divisiones y carreras, y Usuarios: altas con enlace de 72 h y máximo 2 activos por coordinación', function () {
    $admin = $this->admin;

    // Sus 3 pestañas de administración
    foreach (['admin.auditoria', 'admin.catalogos', 'admin.lista-usuarios'] as $ruta) {
        $this->actingAs($admin)->get(route($ruta))->assertOk();
    }
    $this->actingAs($admin)->post(route('admin.catalogos.divisiones.store'), ['nombre' => 'División de Estudios Políticos y Sociales'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('admin.catalogos.carreras.update', $this->crim), ['division_id' => $this->division->id, 'carrera_clave' => 'CRIM', 'nombre' => 'Licenciatura en Criminología'])
        ->assertSessionHasNoErrors();

    // Altas institucionales: sin contraseña fija, con enlace cifrado que vence en 72 horas
    $altas = [
        ['Lorena Vinculación Pérez', 'lorena@csh.udg.mx', Rol::Vinculacion->value, []],
        ['Dra. Laura Méndez Ochoa', 'coord.abog@csh.udg.mx', Rol::Coordinador->value, ['carrera_id' => $this->abog->id]],
        ['Dr. Javier Cervantes Lugo', 'coord.crim@csh.udg.mx', Rol::Coordinador->value, ['carrera_id' => $this->crim->id]],
    ];
    foreach ($altas as [$nombre, $email, $rol, $extra]) {
        $this->actingAs($admin)->post(route('admin.usuarios.store'), ['name' => $nombre, 'email' => $email, 'rol' => $rol] + $extra)->assertSessionHasNoErrors();
        $u = User::where('email', $email)->firstOrFail();
        Notification::assertSentTo($u, ConfigurarContrasena::class);
        expect($u->hasRole($rol))->toBeTrue()->and(Hash::check('password', $u->password))->toBeFalse();
    }
    expect(config('auth.passwords.invitaciones.expire'))->toBe(72 * 60);

    // Segundo encargado de Abogado: permitido; tercero activo: bloqueado con el mensaje institucional
    $this->actingAs($admin)->post(route('admin.usuarios.store'), ['name' => 'Mtro. Ricardo Pulido Arana', 'email' => 'apoyo.abog@csh.udg.mx', 'rol' => 'coordinador', 'carrera_id' => $this->abog->id])
        ->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.usuarios.store'), ['name' => 'Lic. Tercer Encargado', 'email' => 'tercero.abog@csh.udg.mx', 'rol' => 'coordinador', 'carrera_id' => $this->abog->id])
        ->assertSessionHasErrors(['carrera_id' => 'Esta coordinación ya alcanzó el límite máximo de 2 encargados activos. Desactiva una cuenta anterior si deseas registrar a alguien nuevo.']);
    expect(User::where('email', 'tercero.abog@csh.udg.mx')->exists())->toBeFalse();

    // Relevo: se marca una como inactiva (no se borra) y ya entra la nueva
    $saliente = User::where('email', 'apoyo.abog@csh.udg.mx')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.usuarios.activo', $saliente))->assertSessionHasNoErrors();
    expect($saliente->fresh()->activo)->toBeFalse();
    $this->actingAs($admin)->post(route('admin.usuarios.store'), ['name' => 'Lic. Tercer Encargado', 'email' => 'tercero.abog@csh.udg.mx', 'rol' => 'coordinador', 'carrera_id' => $this->abog->id])
        ->assertSessionHasNoErrors();
    // Correo duplicado: mensaje claro
    $this->actingAs($admin)->post(route('admin.usuarios.store'), ['name' => 'Otra Persona Igual', 'email' => 'lorena@csh.udg.mx', 'rol' => 'vinculacion'])
        ->assertSessionHasErrors(['email' => 'Este correo ya se encuentra registrado en el sistema.']);

    // Catálogo con historial: no hay borrado de divisiones ni carreras (solo alta y edición)
    expect(Route::has('admin.catalogos.divisiones.destroy'))->toBeFalse()
        ->and(Route::has('admin.catalogos.carreras.destroy'))->toBeFalse();
    $this->actingAs($admin)->delete('/admin/catalogos/divisiones/'.$this->division->id)->assertStatus(405);
    $this->actingAs($admin)->delete('/admin/catalogos/carreras/'.$this->abog->id)->assertStatus(405);
    expect(Division::find($this->division->id))->not->toBeNull()->and(Carrera::find($this->abog->id))->not->toBeNull();

    // La bitácora es de solo lectura: no hay rutas para editarla ni borrarla
    expect(collect(Route::getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'admin/auditoria') && ! in_array('GET', $r->methods(), true))->count())->toBe(0);
});

test('FASE 1.2 · el administrador supervisa Vinculación en solo lectura y no entra a las pestañas de Coordinación', function () {
    $empresa = Empresa::create(['user_id' => aiUsuario(Rol::Empresa)->id, 'rfc' => 'SUP010101AB1', 'razon_social' => 'Supervisada S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto Prueba']);
    $convenio = Convenio::create(['empresa_id' => $empresa->id, 'estado' => ConvenioEstado::EnFirmas, 'fecha_recepcion' => today(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1, 'vigencia_anios' => 2]);
    $convenio->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $convenio->prepararDocumentosFisicos(null);
    $media = $empresa->addMedia(aiPdf())->toMediaCollection('acta_constitutiva');
    $juego = $convenio->documentosFisicos()->first();

    // Lectura: Convenios, Dictámenes, Documentos físicos, Empresas e Informes
    foreach (['admin.lista-convenios', 'admin.lista-dictamenes', 'admin.documentos-fisicos', 'admin.lista-empresas', 'admin.informes'] as $ruta) {
        $this->actingAs($this->admin)->get(route($ruta))->assertOk();
    }
    $this->actingAs($this->admin)->get(route('admin.convenios.show', $convenio))->assertOk()->assertSee('Supervisión de solo lectura');

    // Cualquier escritura: 403
    $intentos = [
        ['post', route('admin.convenios.documentos.resolver', [$convenio, $media]), ['estado' => 'validada']],
        ['post', route('admin.convenios.borrador.generar', $convenio), []],
        ['post', route('admin.convenios.formalizar', $convenio), ['folio' => 'X-1', 'fecha_inicio_vigencia' => today()->toDateString()]],
        ['post', route('admin.convenios.rechazar', $convenio), ['motivo' => 'x']],
        ['post', route('admin.convenios.juridica.turnar', $convenio), []],
        ['post', route('admin.documentos-fisicos.mover', $juego), ['estado' => EF::ConEmpresa->value]],
        ['put', route('admin.empresas.update', $empresa), []],
        ['post', route('admin.empresas.activo', $empresa), []],
    ];
    foreach ($intentos as [$metodo, $url, $datos]) {
        $this->actingAs($this->admin)->{$metodo}($url, $datos)->assertForbidden();
    }
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnFirmas);

    // Pestañas operativas de Coordinación: ni en el menú ni por URL directa
    foreach (['coordinacion.lista-programas', 'coordinacion.lista-practicantes', 'coordinacion.lista-reportes', 'coordinacion.lista-cierres', 'coordinacion.reglas'] as $ruta) {
        $this->actingAs($this->admin)->get(route($ruta))->assertForbidden();
    }
    $menu = collect(MenuHelper::getMenuGroups())->flatMap(fn ($g) => $g['items'])->pluck('path')->implode(' ');
    expect($menu)->not->toContain('/coordinacion/');
});

/* =============================================================================================
 * FASE 2 · EMPRESA
 * ============================================================================================= */

test('FASE 2 · registro de empresa con validaciones, candado de vacantes sin convenio y solicitud completa', function () {
    $lorena = aiUsuario(Rol::Vinculacion, nombre: 'Lorena Vinculación');
    $registro = [
        'name' => 'Lic. Mariana Esquivel Rangel', 'contacto_cargo' => 'Coordinadora administrativa', 'contacto_telefono' => '3312345678',
        'email' => 'rh@bufete.test', 'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026',
        'razon_social' => 'Bufete Jurídico Integral S.C.', 'tipo_organismo' => 'sociedad_civil', 'aviso_privacidad' => '1',
    ];

    // RFC inválido (longitud, formato, fecha o genérico): se rechaza y se conserva lo capturado
    foreach (['BJI1503', 'BJI150312AB1XYZ', 'B1I150312AB1', 'BJI151312AB1', 'XAXX010101000', 'XEXX010101000'] as $rfc) {
        $this->post('/registro/empresa', $registro + ['rfc' => $rfc])->assertSessionHasErrors('rfc')->assertSessionHasInput('razon_social', 'Bufete Jurídico Integral S.C.');
    }
    expect(User::where('email', 'rh@bufete.test')->exists())->toBeFalse();

    $this->post('/registro/empresa', $registro + ['rfc' => 'bji-150312-ab1'])->assertSessionHasNoErrors();
    $empresaUser = User::where('email', 'rh@bufete.test')->firstOrFail();
    $empresaUser->markEmailAsVerified();
    $empresa = $empresaUser->empresa;
    expect($empresa->rfc)->toBe('BJI150312AB1');
    auth()->logout();

    // Sin convenio formalizado no puede crear vacantes (ni por URL directa)
    $this->actingAs($empresaUser)->get(route('empresa.lista-programas'))->assertOk()->assertSee('Aún no puedes proponer vacantes');
    $this->actingAs($empresaUser)->post(route('empresa.programas.store'), [
        'convenio_id' => 999, 'carrera_id' => $this->abog->id, 'ciclo' => '2027A', 'titulo' => 'Vacante sin convenio', 'descripcion' => 'x',
        'actividades' => "Una\nDos", 'perfil_requerido' => 'x', 'cupos_totales' => 1,
    ])->assertSessionHasErrors('programa');
    expect(Programa::count())->toBe(0);

    // Datos generales, declaraciones notariales y perfiles (Abogado + Criminología)
    $this->actingAs($empresaUser)->put(route('empresa.convenio.seccion', 'generales'), [
        'razon_social' => 'Bufete Jurídico Integral S.C.', 'giro' => 'Despacho jurídico', 'descripcion' => 'Despacho que asesora a pequeñas empresas y familias desde 2015.',
        'proyectos_principales' => 'Litigio civil y familiar', 'calle' => 'Av. Chapultepec', 'numero_exterior' => '480', 'colonia' => 'Americana',
        'codigo_postal' => '44160', 'municipio' => 'Guadalajara', 'entidad' => 'Jalisco',
        'contacto_nombre' => 'Lic. Mariana Esquivel Rangel', 'contacto_cargo' => 'Coordinadora administrativa', 'contacto_telefono' => '3312345678',
    ])->assertSessionHasNoErrors();
    $this->actingAs($empresaUser)->put(route('empresa.convenio.seccion', 'legales'), [
        'representante_legal' => 'Lic. Arturo Esquivel Rangel', 'representante_cargo' => 'Socio director', ...datosNotarialesPrueba(),
        'objeto_social' => 'la prestación de servicios jurídicos', 'facultad_origen' => 'acta',
    ])->assertSessionHasNoErrors();
    $this->actingAs($empresaUser)->put(route('empresa.convenio.seccion', 'perfiles'), [
        'carreras' => [$this->abog->id, $this->crim->id], 'objetivos' => 'Que los practicantes apliquen la teoría procesal en casos reales.',
        'actividades_propuestas' => 'Revisión de expedientes, redacción de escritos y acompañamiento a audiencias.', 'plazas_estimadas' => 4,
        'medio_conocimiento' => 'Página del CUCSH', 'experiencia_previa' => 'Ninguno',
    ])->assertSessionHasNoErrors();
    $empresa->refresh();
    expect($empresa->constitucion)->toContain('escritura pública número 1234')->and($empresa->folio_mercantil)->toBe('N-2010001');

    // Documentos: no-PDF o > 5 MB se rechazan; los 4 PDFs obligatorios se aceptan
    $obligatorias = $empresa->obligatorias();
    // El sistema exige 5 PDFs a una sociedad civil: acta constitutiva, currículum de la instancia, constancia de situación
    // fiscal, identificación del representante y comprobante de domicilio (+ poder notarial si firma por poder)
    expect($obligatorias)->toHaveCount(5)->toContain('acta_constitutiva', 'cv_instancia', 'constancia_situacion_fiscal', 'identificacion_representante', 'comprobante_domicilio');
    $this->actingAs($empresaUser->fresh())->post(route('empresa.documentos.subir'), ['archivos' => [$obligatorias[0] => UploadedFile::fake()->create('acta.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]])
        ->assertSessionHasErrors("archivos.{$obligatorias[0]}");
    $this->actingAs($empresaUser->fresh())->post(route('empresa.documentos.subir'), ['archivos' => [$obligatorias[0] => UploadedFile::fake()->create('acta.pdf', 6 * 1024, 'application/pdf')]])
        ->assertSessionHasErrors("archivos.{$obligatorias[0]}");
    $this->actingAs($empresaUser->fresh())->post(route('empresa.documentos.subir'), ['archivos' => collect($obligatorias)->mapWithKeys(fn ($c) => [$c => aiPdf("$c.pdf")])->all()])
        ->assertSessionHasNoErrors();

    $this->actingAs($empresaUser->fresh())->post(route('empresa.convenio.solicitar'))->assertSessionHasNoErrors();
    $convenio = Convenio::where('empresa_id', $empresa->id)->firstOrFail();
    expect($convenio->estado)->toBe(ConvenioEstado::Solicitado)->and($convenio->dictamenes()->count())->toBe(2);
    Notification::assertSentTo($lorena, NuevaSolicitudConvenio::class);
});

/* =============================================================================================
 * FASE 3 · VINCULACIÓN + DICTÁMENES CRUZADOS + JURÍDICA + DOCUMENTOS FÍSICOS + RENOVACIÓN
 * ============================================================================================= */

test('FASE 3 · del expediente devuelto al convenio formalizado: dictámenes cruzados, v1→v2, juegos finales y papeles concluidos', function () {
    $lorena = aiUsuario(Rol::Vinculacion, nombre: 'Lorena Vinculación');
    $coordAbog = aiUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id], 'Dra. Laura Méndez');
    $coordCrim = aiUsuario(Rol::Coordinador, ['carrera_id' => $this->crim->id], 'Dr. Javier Cervantes');
    $empresaUser = aiUsuario(Rol::Empresa, nombre: 'Lic. Mariana Esquivel');
    $empresa = solicitudLista(Empresa::create(['user_id' => $empresaUser->id, 'rfc' => 'BJI150312AB1', 'razon_social' => 'Bufete Jurídico Integral S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Lic. Mariana Esquivel']), [$this->abog->id, $this->crim->id]);
    foreach ($empresa->obligatorias() as $col) {
        $empresa->addMedia(aiPdf("$col.pdf"))->toMediaCollection($col);
    }
    $this->actingAs($empresaUser->fresh())->post(route('empresa.convenio.solicitar'))->assertSessionHasNoErrors();
    $convenio = Convenio::where('empresa_id', $empresa->id)->firstOrFail();

    // 3.1 Vinculación devuelve un documento ilegible; la empresa reemplaza SOLO ese y Vinculación aprueba todo → turno académico
    $acta = $empresa->fresh()->documentoVigente('acta_constitutiva');
    $this->actingAs($lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $acta]), ['estado' => 'rechazada', 'motivo' => 'El acta está ilegible en las páginas 3 y 4.'])
        ->assertSessionHasNoErrors();
    Notification::assertSentTo($empresaUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'ilegible') && str_contains($n->mensaje, 'Qué hacer'));
    $this->actingAs($empresaUser->fresh())->get(route('empresa.documentos'))->assertOk()->assertSee('El acta está ilegible en las páginas 3 y 4.');
    $this->actingAs($empresaUser->fresh())->post(route('empresa.documentos.subir'), ['archivos' => ['acta_constitutiva' => aiPdf('acta-legible.pdf')]])->assertSessionHasNoErrors();
    expect($empresa->fresh()->documentoVigente('acta_constitutiva')->file_name)->toBe('acta-legible.pdf');
    foreach ($empresa->fresh()->obligatorias() as $col) {
        $m = $empresa->fresh()->documentoVigente($col);
        if ($m->getCustomProperty('estado') !== 'validada') {
            $this->actingAs($lorena)->post(route('admin.convenios.documentos.resolver', [$convenio, $m]), ['estado' => 'validada'])->assertSessionHasNoErrors();
        }
    }
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnValidacionAcademica);

    // 3.2 Decisión cruzada: Criminología rechaza (con motivo), Abogado aprueba con su dictamen firmado
    $dCrim = ConvenioCarrera::where('convenio_id', $convenio->id)->where('carrera_id', $this->crim->id)->firstOrFail();
    $dAbog = ConvenioCarrera::where('convenio_id', $convenio->id)->where('carrera_id', $this->abog->id)->firstOrFail();
    $this->actingAs($coordCrim)->post(route('coordinacion.dictamenes.registrar', $dCrim), ['dictamen' => 'negativo', 'documento' => aiPdf('dictamen-crim.pdf')])
        ->assertSessionHasErrors('observaciones');   // negativo sin motivo: no
    $this->actingAs($coordCrim)->post(route('coordinacion.dictamenes.registrar', $dCrim), ['dictamen' => 'negativo', 'observaciones' => 'Las actividades no son criminológicas.', 'documento' => aiPdf('dictamen-crim.pdf')])
        ->assertSessionHasNoErrors();
    $this->actingAs($coordAbog)->post(route('coordinacion.dictamenes.registrar', $dAbog), ['dictamen' => 'aprobatorio', 'documento' => aiPdf('dictamen-abog.pdf')])
        ->assertSessionHasNoErrors();
    expect($dAbog->fresh()->getMedia('dictamen_pertinencia'))->toHaveCount(1)
        ->and($convenio->fresh()->estado)->toBe(ConvenioEstado::EnValidacionAcademica);   // sigue: Abogado aprobó (redacción)

    // 3.3 Borrador v1 (3 años) → Jurídica (con copia al correo institucional)
    $this->actingAs($lorena)->post(route('admin.convenios.borrador', $convenio), ['borrador' => aiPdf('convenio-v1.pdf'), 'vigencia_anios' => 3])->assertSessionHasNoErrors();
    $this->actingAs($lorena)->post(route('admin.convenios.juridica.turnar', $convenio), ['enviar_correo' => '1'])->assertSessionHasNoErrors();
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnRevisionJuridica);
    Notification::assertSentOnDemand(BorradorTurnadoJuridica::class, fn ($n, $canales, $dest) => $dest->routes['mail'] === 'juridico@csh.udg.mx');

    // Jurídica devuelve con observaciones → v2 corregida (2 años) generada desde el Formato
    $this->actingAs($lorena)->post(route('admin.convenios.juridica.dictamen', $convenio), ['resultado' => 'observado', 'comentario' => 'Precisar el seguro de los practicantes.'])
        ->assertSessionHasNoErrors();
    $this->actingAs($lorena)->get(route('admin.convenios.show', $convenio))->assertOk()->assertSee('La Unidad Jurídica devolvió el borrador v1 con observaciones');
    $expediente = $convenio->documentosFisicos()->where('tipo', TF::DictamenJuridico->value)->firstOrFail();
    expect($expediente->estado)->toBe(EF::ConObservaciones);
    $this->actingAs($lorena)->post(route('admin.documentos-fisicos.mover', $expediente), ['estado' => EF::EnVinculacion->value])->assertSessionHasErrors('estado');
    $this->actingAs($lorena)->post(route('admin.documentos-fisicos.mover', $expediente), ['estado' => EF::EnJuridica->value])->assertSessionHasNoErrors();

    $e = $empresa->fresh();
    $datosBorrador = ['vigencia_anios' => 2, 'representante_nombre' => $e->representante_legal, 'representante_cargo' => $e->representante_cargo,
        'constitucion' => $e->constitucion, 'atribuciones' => $e->objeto_social, 'facultad_firmante' => $e->facultad_firmante,
        'domicilio_legal' => $e->domicilio_fiscal, 'lugar_firma' => 'Guadalajara'];
    $this->actingAs($lorena)->post(route('admin.convenios.borrador.generar', $convenio), $datosBorrador)->assertSessionHasNoErrors();
    expect($convenio->fresh()->vigencia_anios)->toBe(2);
    $v1 = $convenio->fresh()->versionesBorrador()->first()['archivo'];
    $this->actingAs($lorena)->get(route('admin.convenios.archivos.descargar', [$convenio, $v1]))->assertRedirect()->assertSessionHasErrors();   // v1 obsoleta: no se imprime

    // Jurídica aprueba → juegos finales con fecha real: solo Abogado, declaraciones notariales y 2 años
    $this->actingAs($lorena)->post(route('admin.convenios.juridica.turnar', $convenio))->assertSessionHasNoErrors();
    $this->actingAs($lorena)->post(route('admin.convenios.juridica.dictamen', $convenio), ['resultado' => 'aprobado'])->assertSessionHasNoErrors();
    expect($convenio->fresh()->estado)->toBe(ConvenioEstado::EnFirmas);
    $firma = today();
    $this->actingAs($lorena)->post(route('admin.convenios.juegos-finales', $convenio), ['fecha_firma' => $firma->toDateString()])->assertSessionHasNoErrors();
    $final = $convenio->fresh()->getMedia('borrador_convenio')->sortByDesc('id')->first();
    $texto = aiTextoDocx($final->getPath());
    expect($texto)->toContain('Licenciatura en Abogado')->not->toContain('Criminología')
        ->toContain('escritura pública número 1234')->toContain('Lic. Juan Notario')
        ->toContain('dos años')->toContain($firma->locale('es')->translatedFormat('j \d\e F \d\e Y'));

    // Firmas (3 juegos), Rectoría con el escaneo y formalización: los años no se pueden cambiar
    foreach ($convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->get() as $j) {
        $this->actingAs($lorena)->post(route('admin.documentos-fisicos.mover', $j), ['estado' => EF::ConEmpresa->value])->assertSessionHasNoErrors();
    }
    $this->actingAs($lorena)->post(route('admin.convenios.firmas', $convenio), ['etapa' => 'firma_empresa', 'evidencia' => aiPdf('ultima-hoja.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($lorena)->post(route('admin.convenios.firmas', $convenio), ['etapa' => 'firma_rectoria', 'evidencia' => aiPdf('convenio-firmado.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($lorena)->post(route('admin.convenios.formalizar', $convenio), ['folio' => 'cv-2026-031', 'fecha_inicio_vigencia' => $firma->copy()->addDay()->toDateString()])
        ->assertSessionHasErrors('fecha_inicio_vigencia');   // distinta a la impresa en los juegos
    $this->actingAs($lorena)->post(route('admin.convenios.formalizar', $convenio), ['folio' => 'cv-2026-031', 'fecha_inicio_vigencia' => $firma->toDateString(), 'vigencia_anios' => 5])
        ->assertSessionHasNoErrors();
    $convenio->refresh();
    expect($convenio->estado)->toBe(ConvenioEstado::Formalizado)->and($convenio->folio)->toBe('CV-2026-031')
        ->and($convenio->vigencia_anios)->toBe(2)
        ->and($convenio->fecha_fin_vigencia->toDateString())->toBe($firma->copy()->addYears(2)->subDay()->toDateString());

    // Papeles: expediente y juego de Vinculación concluyen solos; al entregar los otros dos, todo queda en Concluidos
    foreach ($convenio->documentosFisicos()->where('tipo', TF::JuegoConvenio->value)->whereIn('juego', [2, 3])->get() as $j) {
        $this->actingAs($lorena)->post(route('admin.documentos-fisicos.mover', $j), ['estado' => EF::Entregado->value])->assertSessionHasNoErrors();
    }
    expect($convenio->documentosFisicos()->get()->every(fn ($d) => $d->estado->concluido()))->toBeTrue();
    $this->actingAs($lorena)->get(route('admin.documentos-fisicos', ['vista' => 'concluidos']))->assertOk()->assertSee('Bufete Jurídico Integral S.C.');
});

test('FASE 3 · alertas de vencimiento a 60 y 30 días y renovación precargada que no se envía incompleta', function () {
    $lorena = aiUsuario(Rol::Vinculacion, nombre: 'Lorena Vinculación');
    $e60 = Empresa::create(['user_id' => aiUsuario(Rol::Empresa, nombre: 'Contacto Sesenta')->id, 'rfc' => 'SES010101AB1', 'razon_social' => 'Sesenta Días S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto Sesenta']);
    $empresaUser = aiUsuario(Rol::Empresa, nombre: 'Contacto Treinta');
    $e30 = solicitudLista(Empresa::create(['user_id' => $empresaUser->id, 'rfc' => 'TRE010101AB1', 'razon_social' => 'Treinta Días S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto Treinta']), [$this->abog->id]);
    $e30->update(['solicitud_borrador' => null]);   // la renovación empieza sin perfiles guardados
    foreach ($e30->obligatorias() as $col) {
        $m = $e30->addMedia(aiPdf("$col.pdf"))->toMediaCollection($col);
        $e30->resolverDocumento($m, ValidacionEstado::Validada, null, $lorena->id);
        in_array($col, Empresa::CON_VIGENCIA, true) && $m->setCustomProperty('fecha_emision', today()->subWeek()->toDateString())->save();
    }
    aiConvenioVigente($e60, [$this->abog], now()->addDays(55));
    $vigente = aiConvenioVigente($e30, [$this->abog], now()->addDays(25));

    $this->artisan('practicas:avisos-programados')->assertSuccessful();
    Notification::assertSentTo($e60->user, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'vence'));
    Notification::assertSentTo($empresaUser, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'vence'));
    Notification::assertSentTo($lorena, AvisoOperativo::class, fn ($n) => str_contains($n->mensaje, 'Treinta Días S.C.'));
    // (Que no se repitan al día siguiente lo controla la clave del aviso guardado en la base; con Notification::fake()
    //  no se guarda, así que esa parte se prueba en NotificacionesTest con avisos reales.)

    // Renovación: perfiles precargados con el convenio vigente; incompleta no se envía ni avisa a Lorena
    $this->actingAs($empresaUser->fresh())->get(route('empresa.convenio', ['seccion' => 'perfiles']))->assertOk()
        ->assertSee('Solicitud de renovación')->assertSee('Precargamos los perfiles de tu convenio '.$vigente->folio)
        ->assertSee($vigente->actividades_propuestas);
    $antes = Notification::sent($lorena, NuevaSolicitudConvenio::class)->count();
    $this->actingAs($empresaUser->fresh())->post(route('empresa.convenio.solicitar'))->assertSessionHasErrors();
    expect(Notification::sent($lorena, NuevaSolicitudConvenio::class)->count())->toBe($antes);

    $this->actingAs($empresaUser->fresh())->put(route('empresa.convenio.seccion', 'perfiles'), [
        'carreras' => [$this->abog->id], 'objetivos' => $vigente->objetivos, 'actividades_propuestas' => $vigente->actividades_propuestas,
        'plazas_estimadas' => 4, 'medio_conocimiento' => 'Página del CUCSH', 'experiencia_previa' => 'Ninguno',
    ])->assertSessionHasNoErrors();
    $this->actingAs($empresaUser->fresh())->post(route('empresa.convenio.solicitar'))->assertSessionHasNoErrors();
    Notification::assertSentTo($lorena, NuevaSolicitudConvenio::class);
    expect($e30->convenios()->where('estado', ConvenioEstado::Solicitado->value)->exists())->toBeTrue();
});

/* =============================================================================================
 * FASE 4 · REGLAS, PROFESORES, VACANTES Y POSTULACIÓN DEL ESTUDIANTE
 * ============================================================================================= */

test('FASE 4.1 · reglas de la carrera, catálogo de profesores y vacantes externas que la coordinación solo aprueba', function () {
    $coord = aiUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id], 'Dra. Laura Méndez');
    $empresaUser = aiUsuario(Rol::Empresa, nombre: 'Lic. Jorge Ramírez');
    $empresa = Empresa::create(['user_id' => $empresaUser->id, 'rfc' => 'DRA120601KP3', 'razon_social' => 'Despacho Ruiz & Asociados S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Lic. Jorge Ramírez']);
    $convenio = aiConvenioVigente($empresa, [$this->abog]);

    $this->actingAs($coord)->put(route('coordinacion.reglas.update', $this->abog), [
        'horas_requeridas' => 350, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8, 'esquema_reportes' => 'mensual',
    ])->assertSessionHasNoErrors();
    expect($this->abog->fresh()->horas_requeridas)->toBe(350)->and($this->abog->fresh()->esquema_reportes->value)->toBe('mensual');

    // Profesor supervisor: registro administrativo, sin cuenta de acceso
    $this->actingAs($coord)->post(route('coordinacion.profesores.store'), ['nombre' => 'Dr. Ernesto Salcedo Rivas', 'email' => 'ernesto.salcedo@academicos.udg.mx', 'telefono' => '3312450981'])
        ->assertSessionHasNoErrors();
    $profesor = Profesor::firstOrFail();
    expect($profesor->division_id)->toBe($this->division->id)->and(User::where('email', 'ernesto.salcedo@academicos.udg.mx')->exists())->toBeFalse();

    // Empresa (ya con convenio) crea la vacante → la coordinación la revisa y publica
    $this->actingAs($empresaUser)->post(route('empresa.programas.store'), [
        'convenio_id' => $convenio->id, 'carrera_id' => $this->abog->id, 'ciclo' => '2027A', 'titulo' => 'Auxiliar jurídico en litigio civil',
        'descripcion' => 'Apoyo en juicios civiles', 'actividades' => "Revisar expedientes\nRedactar escritos", 'perfil_requerido' => 'Responsable',
        'horario' => 'Lunes a viernes, 9:00 a 14:00', 'sede' => 'Av. Chapultepec 480', 'cupos_totales' => 3,
    ])->assertSessionHasNoErrors();
    $vacante = Programa::firstOrFail();
    expect($vacante->estado)->toBe(ProgramaEstado::PendienteAprobacion);
    $this->actingAs($coord)->get(route('coordinacion.lista-programas'))->assertOk()->assertSee('Revisar vacante')->assertSee('Redactar escritos');
    $this->actingAs($coord)->post(route('coordinacion.programas.aprobar', $vacante))->assertSessionHasNoErrors();
    expect($vacante->fresh()->estado)->toBe(ProgramaEstado::Publicado);

    // La coordinación no edita, no borra ni reabre vacantes externas
    $this->actingAs($coord)->put(route('coordinacion.programas.update', $vacante), ['titulo' => 'Cambiado'])->assertSessionHasErrors('programa');
    $this->actingAs($coord)->delete(route('coordinacion.programas.destroy', $vacante))->assertSessionHasErrors('programa');
    $this->actingAs($empresaUser)->post(route('empresa.programas.estado', $vacante), ['estado' => 'cerrado'])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.programas.estado', $vacante), ['estado' => 'publicado'])->assertSessionHasErrors('programa');
    expect($vacante->fresh()->estado)->toBe(ProgramaEstado::Cerrado)->and(Programa::find($vacante->id))->not->toBeNull();
});

test('FASE 4.2 · registro del alumno (9 dígitos), postulación con SIIAU y créditos, una empresa a la vez', function () {
    $coord = aiUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id], 'Dra. Laura Méndez');
    $empresa = Empresa::create(['user_id' => aiUsuario(Rol::Empresa)->id, 'rfc' => 'DRA120601KP3', 'razon_social' => 'Despacho Ruiz & Asociados S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto Despacho']);
    $convenio = aiConvenioVigente($empresa, [$this->abog]);
    $base = ['convenio_id' => $convenio->id, 'carrera_id' => $this->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A', 'descripcion' => 'x',
        'actividades' => ['a'], 'perfil_requerido' => 'p', 'cupos_totales' => 3, 'estado' => ProgramaEstado::Publicado];
    $v1 = Programa::create($base + ['titulo' => 'Auxiliar jurídico en litigio civil']);
    $v2 = Programa::create($base + ['titulo' => 'Asistente en derecho familiar']);

    // Código UdeG: exactamente 9 dígitos (respeta ceros a la izquierda)
    $registro = ['name' => 'Luis Ortega Ramos', 'email' => 'luis@alumnos.udg.mx', 'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026',
        'carrera_id' => $this->abog->id, 'grado' => 7, 'turno' => 'matutino', 'aviso_privacidad' => '1'];
    foreach (['22012345', '2201234567', 'A20123456', 'ABCDEFGHI'] as $codigo) {
        $this->post('/registro/alumno', $registro + ['num_control' => $codigo])->assertSessionHasErrors('num_control');
    }
    $this->post('/registro/alumno', $registro + ['num_control' => '012345678'])->assertSessionHasNoErrors();
    expect(Alumno::firstOrFail()->num_control)->toBe('012345678');
    auth()->logout();

    // 45 % de créditos (< 50 % de Abogado): la coordinación no puede autorizar su expediente ni él postularse
    $bajo = aiAlumnoValidado($this->abog, '220000045', 45, $coord);
    $this->actingAs($bajo->user)->post(route('alumno.oferta-programas.postular', $v1), ['siiau' => '1'])->assertSessionHasErrors('postulacion');
    expect($bajo->postulaciones()->count())->toBe(0);

    // Alumno válido (220123456, 55 %): entrega kardex + IMSS en PDF y su coordinación los autoriza
    $u = aiUsuario(Rol::Alumno, nombre: 'Daniela Guzmán Ríos');
    $alumno = Alumno::create(['user_id' => $u->id, 'carrera_id' => $this->abog->id, 'num_control' => '220123456', 'grado' => 7, 'turno' => 'matutino']);
    $this->actingAs($u)->post(route('alumno.documentos.expediente'), [
        'porcentaje_creditos' => 55, 'kardex' => aiPdf('kardex.pdf'), 'imss' => aiPdf('imss.pdf'), 'vigencia_imss' => now()->addYear()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.alumnos.expediente', $alumno), ['decision' => 'autorizar'])->assertSessionHasNoErrors();

    // Sin confirmar SIIAU no hay postulación
    $this->actingAs($u->fresh())->post(route('alumno.oferta-programas.postular', $v1), [])->assertSessionHasErrors('siiau');
    $this->actingAs($u->fresh())->post(route('alumno.oferta-programas.postular', $v1), ['siiau' => '1'])->assertSessionHasNoErrors();
    // Una empresa a la vez
    $this->actingAs($u->fresh())->post(route('alumno.oferta-programas.postular', $v2), ['siiau' => '1'])->assertSessionHasErrors('postulacion');
    expect($alumno->postulaciones()->count())->toBe(1);
});

test('FASE 4.3 · bajas: sin reportes pierde horas, justificada conserva, y 2 bajas bloquean hasta el desbloqueo excepcional', function () {
    $coord = aiUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id], 'Dra. Laura Méndez');
    $empresa = Empresa::create(['user_id' => aiUsuario(Rol::Empresa)->id, 'rfc' => 'DRA120601KP3', 'razon_social' => 'Despacho Ruiz & Asociados S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Contacto Despacho']);
    $convenio = aiConvenioVigente($empresa, [$this->abog]);
    $nueva = fn (string $titulo) => Programa::create(['convenio_id' => $convenio->id, 'carrera_id' => $this->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => $titulo, 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p', 'cupos_totales' => 5, 'estado' => ProgramaEstado::Publicado]);
    $alumno = aiAlumnoValidado($this->abog, '220123457', 70, $coord);
    $enCurso = function (Programa $programa) use ($alumno, $coord) {
        $po = Postulacion::create(['alumno_id' => $alumno->id, 'programa_id' => $programa->id, 'estado' => PostulacionEstado::Aceptada,
            'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);

        return Practica::create(['postulacion_id' => $po->id, 'autorizado_por' => $coord->personal->id, 'modalidad' => 'externa',
            'fecha_inicio' => today()->subMonths(2), 'fecha_fin' => today()->addMonths(2), 'horas_requeridas' => 300, 'estado' => PracticaEstado::EnCurso]);
    };

    // Baja sin reportes validados: horas en 0
    $p1 = $enCurso($nueva('Vacante uno'));
    $this->actingAs($coord)->post(route('coordinacion.practicas.cancelar', $p1), ['motivo_baja' => 'cambio_proyecto', 'detalle_baja' => 'El proyecto terminó antes de tiempo.'])
        ->assertSessionHasNoErrors();
    expect($p1->fresh()->tipo_baja)->toBe('sin_reportes')->and($p1->fresh()->horasQueConserva())->toBe(0);

    // Baja justificada con un reporte validado: conserva sus horas
    $p2 = $enCurso($nueva('Vacante dos'));
    $r = $p2->reportes()->create(['tipo' => 'mensual', 'numero' => 1, 'periodo_inicio' => today()->subMonth(), 'periodo_fin' => today(), 'horas_reportadas' => 80, 'estado' => ReporteEstado::Validado]);
    $r->addMedia(aiPdf())->toMediaCollection('reporte_firmado');
    $this->actingAs($coord)->post(route('coordinacion.practicas.cancelar', $p2), ['motivo_baja' => 'cierre_empresa', 'detalle_baja' => 'La organización cerró el área.'])
        ->assertSessionHasNoErrors();
    expect($p2->fresh()->tipo_baja)->toBe('parcial_acreditada')->and($p2->fresh()->horasQueConserva())->toBe(80);

    // 2 bajas: bloqueado para una tercera postulación
    $tercera = $nueva('Vacante tres');
    expect($alumno->fresh()->estaBloqueado())->toBeTrue();
    $this->actingAs($alumno->user)->post(route('alumno.oferta-programas.postular', $tercera), ['siiau' => '1'])->assertSessionHasErrors('postulacion');

    // Desbloqueo excepcional (con justificación) → ya puede postularse
    $this->actingAs($coord)->post(route('coordinacion.alumnos.desbloquear', $alumno), ['justificacion' => 'x'])->assertSessionHasErrors('justificacion');
    $this->actingAs($coord)->post(route('coordinacion.alumnos.desbloquear', $alumno), ['justificacion' => 'Las dos organizaciones cerraron de forma definitiva; presentó comprobantes.'])
        ->assertSessionHasNoErrors();
    $this->actingAs($alumno->user->fresh())->post(route('alumno.oferta-programas.postular', $tercera), ['siiau' => '1'])->assertSessionHasNoErrors();
});

/* =============================================================================================
 * FASE 5 · CICLO ACADÉMICO HASTA LA ACREDITACIÓN (100 %)
 * ============================================================================================= */

test('FASE 5 · expediente devuelto y corregido, carta de presentación, aceptación, reportes hasta 350 h y acreditación en SIIAU', function () {
    $coord = aiUsuario(Rol::Coordinador, ['carrera_id' => $this->abog->id], 'Dra. Laura Méndez');
    $this->abog->update(['horas_requeridas' => 350]);
    $empresaUser = aiUsuario(Rol::Empresa, nombre: 'Lic. Jorge Ramírez');
    $empresa = Empresa::create(['user_id' => $empresaUser->id, 'rfc' => 'DRA120601KP3', 'razon_social' => 'Despacho Ruiz & Asociados S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Lic. Jorge Ramírez']);
    $convenio = aiConvenioVigente($empresa, [$this->abog]);
    $vacante = Programa::create(['convenio_id' => $convenio->id, 'carrera_id' => $this->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => 'Auxiliar jurídico en litigio civil', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p', 'cupos_totales' => 3, 'estado' => ProgramaEstado::Publicado]);
    $profesor = Profesor::create(['nombre' => 'Dr. Ernesto Salcedo Rivas', 'division_id' => $this->division->id, 'activo' => true]);
    $u = aiUsuario(Rol::Alumno, nombre: 'Daniela Guzmán Ríos');
    $alumno = Alumno::create(['user_id' => $u->id, 'carrera_id' => $this->abog->id, 'num_control' => '220123456', 'grado' => 7, 'turno' => 'matutino']);

    // Expediente: el IMSS se devuelve, el alumno corrige solo ese PDF y la coordinación autoriza
    $this->actingAs($u)->post(route('alumno.documentos.expediente'), [
        'porcentaje_creditos' => 55, 'kardex' => aiPdf('kardex.pdf'), 'imss' => aiPdf('imss.pdf'), 'vigencia_imss' => now()->addYear()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.alumnos.expediente', $alumno), ['decision' => 'devolver', 'documentos' => ['imss'], 'motivo' => 'El IMSS aparece dado de baja.'])
        ->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.alumnos.expediente', $alumno), ['decision' => 'autorizar'])->assertSessionHasErrors('revision');
    $this->actingAs($u->fresh())->post(route('alumno.documentos.imss'), ['archivo' => aiPdf('imss-vigente.pdf'), 'vigencia_imss' => now()->addYear()->toDateString()])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.alumnos.expediente', $alumno), ['decision' => 'autorizar'])->assertSessionHasNoErrors();

    // Postulación → carta de presentación (la genera el sistema) → aceptación → activa con su profesor supervisor
    $this->actingAs($u->fresh())->post(route('alumno.oferta-programas.postular', $vacante), ['siiau' => '1'])->assertSessionHasNoErrors();
    $postulacion = Postulacion::firstOrFail();
    $this->actingAs($coord)->post(route('coordinacion.postulaciones.carta', $postulacion), ['carta' => aiPdf('carta-firmada.pdf')])->assertSessionHasNoErrors();
    $carta = $postulacion->fresh()->cartaPresentacion();
    expect($carta)->not->toBeNull();
    $this->actingAs($u->fresh())->get(route('alumno.documentos.descargar', $carta))->assertOk();
    $this->actingAs($u->fresh())->post(route('alumno.documentos.aceptacion', $postulacion), ['archivo' => aiPdf('aceptacion-sellada.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.postulaciones.aceptacion', $postulacion))->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.postulaciones.activar', $postulacion), [
        'fecha_inicio' => today()->subMonths(4)->toDateString(), 'fecha_fin' => today()->addMonth()->toDateString(), 'profesor_id' => $profesor->id,
    ])->assertSessionHasNoErrors();
    $practica = $postulacion->fresh()->practica;
    expect($practica->estado)->toBe(PracticaEstado::EnCurso)->and($practica->profesor_supervisor_id)->toBe($profesor->id)->and($practica->horas_requeridas)->toBe(350);

    // Reporte redactado sin escaneo: la coordinación NO puede validar ni rechazar
    $this->actingAs($u->fresh())->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => 'mensual', 'periodo_inicio' => today()->subMonths(4)->toDateString(), 'periodo_fin' => today()->subMonths(2)->toDateString(), 'horas_reportadas' => 175,
        'actividades' => 'Revisión de expedientes civiles, redacción de demandas y asistencia a audiencias de conciliación.',
    ])->assertSessionHasNoErrors();
    $r1 = $practica->reportes()->firstOrFail();
    $this->actingAs($coord)->get(route('coordinacion.lista-reportes'))->assertOk()->assertSee('Esperando documento del alumno');
    $this->actingAs($coord)->post(route('coordinacion.reportes.resolver', $r1), ['estado' => 'validado'])->assertSessionHasErrors('reporte');
    $this->actingAs($coord)->post(route('coordinacion.reportes.resolver', $r1), ['estado' => 'rechazado', 'evaluacion_profesor' => 'x'])->assertSessionHasErrors('reporte');

    // Sube el escaneo firmado → se valida; con un segundo reporte llega a 350 h (100 %)
    $this->actingAs($u->fresh())->post(route('alumno.documentos.reporte', $r1), ['archivo' => aiPdf('reporte-1-firmado.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.reportes.resolver', $r1), ['estado' => 'validado'])->assertSessionHasNoErrors();
    $this->actingAs($u->fresh())->post(route('alumno.documentos.reporte.crear', $practica), [
        'tipo' => 'mensual', 'periodo_inicio' => today()->subMonths(2)->toDateString(), 'periodo_fin' => today()->toDateString(), 'horas_reportadas' => 175,
        'archivo' => aiPdf('reporte-2-firmado.pdf'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.reportes.resolver', $practica->reportes()->latest('id')->first()), ['estado' => 'validado'])->assertSessionHasNoErrors();
    expect($practica->fresh()->horasCompletas())->toBeTrue()->and((int) Practica::conHorasAcumuladas()->find($practica->id)->horas_acumuladas)->toBe(350);

    // Cierre: carta de término → oficio de acreditación → Control Escolar → SIIAU = acreditada
    $this->actingAs($u->fresh())->post(route('alumno.documentos.termino', $practica), ['archivo' => aiPdf('carta-termino.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.cierres.carta', $practica), ['estado' => 'validada'])->assertSessionHasNoErrors();
    expect($practica->fresh()->estado)->toBe(PracticaEstado::EnCierre);
    $this->actingAs($coord)->post(route('coordinacion.cierres.siiau', $practica))->assertSessionHasErrors();   // sin oficio enviado: no
    $this->actingAs($coord)->post(route('coordinacion.cierres.oficio', $practica), ['archivo' => aiPdf('oficio-acreditacion.pdf')])->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.cierres.enviar', $practica))->assertSessionHasNoErrors();
    $this->actingAs($coord)->post(route('coordinacion.cierres.siiau', $practica))->assertSessionHasNoErrors();

    expect($practica->fresh()->estado)->toBe(PracticaEstado::Acreditada)->and($alumno->fresh()->yaAcreditado())->toBeTrue();
    expect(aiAvisos($u, 'quedó acreditada'))->toBeGreaterThanOrEqual(1);
    // Ya acreditado: no puede volver a postularse
    $this->actingAs($u->fresh())->post(route('alumno.oferta-programas.postular', $vacante), ['siiau' => '1'])->assertSessionHasErrors('postulacion');
    // Ninguna pantalla del recorrido responde con error 500
    foreach ([[$u, 'alumno.dashboard'], [$u, 'alumno.mis-practicas'], [$u, 'alumno.mis-documentos'], [$coord, 'coordinacion.lista-cierres'], [$empresaUser, 'empresa.lista-practicantes']] as [$quien, $ruta]) {
        $this->actingAs($quien->fresh())->get(route($ruta))->assertOk();
    }
});

/* =============================================================================================
 * SEGURIDAD · encabezados HTTP
 * ============================================================================================= */

test('todas las respuestas llevan encabezados de seguridad, y la CSP solo en producción', function () {
    $r = $this->get('/');
    $r->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeaderMissing('Content-Security-Policy');

    $this->app['env'] = 'production';
    $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")->toContain("frame-ancestors 'self'")->toContain("object-src 'none'")
        ->toContain('https://cdnjs.cloudflare.com');
    $this->actingAs($this->admin)->get(route('admin.lista-usuarios'))->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});
