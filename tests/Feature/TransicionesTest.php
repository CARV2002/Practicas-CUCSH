<?php

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\PostulacionEstado;
use App\Enums\PracticaEstado;
use App\Enums\ProgramaEstado;
use App\Enums\ReporteEstado;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\CartaTermino;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\OficioAcreditacion;
use App\Models\Personal;
use App\Models\Postulacion;
use App\Models\Practica;
use App\Models\Profesor;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\ConvenioWorkflow;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function trnPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function trnUser(Rol $rol): User
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
    $datos = ['division_id' => $div->id, 'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8];
    $this->abog = Carrera::create($datos + ['carrera_clave' => 'ABOG', 'nombre' => 'Abogado']);
    $this->hist = Carrera::create($datos + ['carrera_clave' => 'HIST', 'nombre' => 'Historia']);

    $this->adminUser = trnUser(Rol::Administrador);
    $this->admin = Personal::create(['user_id' => $this->adminUser->id, 'puesto' => 'Coordinación']);

    $this->empresaUser = trnUser(Rol::Empresa);
    $this->empresa = Empresa::create([
        'user_id' => $this->empresaUser->id, 'rfc' => 'EPA010101AB1', 'razon_social' => 'Empresa de Prueba',
        'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto',
    ]);

    $this->convenio = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'CV-T-001',
        'fecha_recepcion' => now()->subMonths(3), 'fecha_inicio_vigencia' => now()->subMonth(),
        'fecha_fin_vigencia' => now()->addYears(3), 'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 5,
    ]);
    $this->convenio->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);

    $this->nuevoPrograma = fn (string $titulo, int $cupos = 2) => Programa::create([
        'convenio_id' => $this->convenio->id, 'carrera_id' => $this->abog->id, 'modalidad' => 'externa', 'ciclo' => '2027A',
        'titulo' => $titulo, 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => $cupos, 'estado' => ProgramaEstado::Publicado,
    ]);
    $this->programa = ($this->nuevoPrograma)('Auxiliar jurídico', 1);

    $this->anaUser = trnUser(Rol::Alumno);
    $this->ana = Alumno::create([
        'user_id' => $this->anaUser->id, 'carrera_id' => $this->abog->id, 'num_control' => '215000001',
        'vigencia_imss' => now()->addYear()->toDateString(),
    ]);

    $this->poAna = Postulacion::create([
        'alumno_id' => $this->ana->id, 'programa_id' => $this->programa->id, 'estado' => PostulacionEstado::Aceptada,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today(),
    ]);
    $this->practica = Practica::create([
        'postulacion_id' => $this->poAna->id, 'modalidad' => 'externa', 'fecha_inicio' => today()->subMonth(),
        'fecha_fin' => today()->addMonths(5), 'horas_requeridas' => 250, 'estado' => PracticaEstado::EnCurso,
    ]);

    $this->reporte = fn (ReporteEstado $estado, int $horas) => Reporte::create([
        'practica_id' => $this->practica->id, 'tipo' => 'mensual', 'periodo_inicio' => today()->subWeeks(2),
        'periodo_fin' => today(), 'horas_reportadas' => $horas, 'estado' => $estado,
    ]);
});

test('las postulaciones solo siguen transiciones válidas', function () {
    $otro = ($this->nuevoPrograma)('Otro programa');
    $po = Postulacion::create([
        'alumno_id' => $this->ana->id, 'programa_id' => $otro->id, 'estado' => PostulacionEstado::Pendiente,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today(),
    ]);

    expect(fn () => $po->update(['estado' => PostulacionEstado::Aceptada]))->toThrow(ValidationException::class);

    $po->update(['estado' => PostulacionEstado::Rechazada]);

    expect(fn () => $po->update(['estado' => PostulacionEstado::Pendiente]))->toThrow(ValidationException::class)
        ->and(fn () => $this->poAna->update(['estado' => PostulacionEstado::Rechazada]))->toThrow(ValidationException::class);
});

test('cancelar una práctica libera el cupo y anula solo los reportes pendientes', function () {
    $pendiente = ($this->reporte)(ReporteEstado::Pendiente, 40);
    $validado = ($this->reporte)(ReporteEstado::Validado, 80);

    expect($this->programa->refresh()->cupos_disponibles)->toBe(0);

    $this->actingAs($this->adminUser)->post(route('admin.practicas.cancelar', $this->practica))->assertSessionHasNoErrors();

    expect($this->practica->refresh()->estado)->toBe(PracticaEstado::Cancelada)
        ->and($this->poAna->refresh()->estado)->toBe(PostulacionEstado::Cancelada)
        ->and($this->programa->refresh()->cupos_disponibles)->toBe(1)
        ->and($pendiente->refresh()->estado)->toBe(ReporteEstado::Anulado)
        ->and($validado->refresh()->estado)->toBe(ReporteEstado::Validado);
});

test('no se cancela una práctica cuyo oficio ya se envió a Control Escolar', function () {
    $this->practica->update(['estado' => PracticaEstado::EnCierre]);
    OficioAcreditacion::create(['practica_id' => $this->practica->id, 'emitido_por' => $this->admin->id,
        'estado_validacion' => 'validada', 'enviado_control_escolar_at' => now()]);

    $this->actingAs($this->adminUser)->post(route('admin.practicas.cancelar', $this->practica))
        ->assertSessionHasErrors('practica');

    expect($this->practica->refresh()->estado)->toBe(PracticaEstado::EnCierre);
});

test('con la práctica en cierre no se tocan reportes ni se crean nuevos', function () {
    $validado = ($this->reporte)(ReporteEstado::Validado, 250);
    $this->practica->update(['estado' => PracticaEstado::EnCierre]);

    $this->actingAs($this->adminUser)->post(route('admin.reportes.resolver', $validado), ['estado' => 'rechazado'])
        ->assertSessionHasErrors('reporte');
    expect($validado->refresh()->estado)->toBe(ReporteEstado::Validado);

    $this->actingAs($this->anaUser)->post(route('alumno.documentos.reporte.crear', $this->practica), [
        'tipo' => 'mensual', 'periodo_inicio' => today()->subWeek()->toDateString(), 'periodo_fin' => today()->toDateString(),
        'horas_reportadas' => 10, 'archivo' => trnPdf(),
    ])->assertSessionHasErrors('archivo');
});

test('la carta de término no se valida mientras haya reportes pendientes', function () {
    ($this->reporte)(ReporteEstado::Validado, 250);
    $pendiente = ($this->reporte)(ReporteEstado::Pendiente, 10);

    $carta = CartaTermino::create(['practica_id' => $this->practica->id]);
    $carta->addMedia(trnPdf())->toMediaCollection('carta_termino');

    $admin = $this->actingAs($this->adminUser);

    $admin->post(route('admin.cierres.carta', $this->practica), ['estado' => 'validada'])->assertSessionHasErrors('carta');
    expect($this->practica->refresh()->estado)->toBe(PracticaEstado::EnCurso);

    $admin->post(route('admin.reportes.resolver', $pendiente), ['estado' => 'rechazado'])->assertSessionHasNoErrors();
    $admin->post(route('admin.cierres.carta', $this->practica), ['estado' => 'validada'])->assertSessionHasNoErrors();

    expect($this->practica->refresh()->estado)->toBe(PracticaEstado::EnCierre);
});

test('un dictamen ya emitido no se puede cambiar', function () {
    $convenio = Convenio::create([
        'empresa_id' => $this->empresa->id, 'estado' => ConvenioEstado::EnValidacionAcademica, 'fecha_recepcion' => today(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1,
    ]);
    $emitido = $convenio->dictamenes()->create(['carrera_id' => $this->abog->id, 'dictamen' => Dictamen::Aprobatorio]);
    $convenio->dictamenes()->create(['carrera_id' => $this->hist->id, 'dictamen' => Dictamen::Pendiente]);

    expect(fn () => app(ConvenioWorkflow::class)->registrarDictamenCarrera($emitido, Dictamen::Negativo, 'cambio de opinión', $this->admin))
        ->toThrow(ValidationException::class, 'ya fue emitido');
});

test('un programa con postulaciones no cambia de carrera ni regresa a borrador', function () {
    $this->convenio->dictamenes()->create(['carrera_id' => $this->hist->id, 'dictamen' => Dictamen::Aprobatorio]);

    expect(fn () => Programa::find($this->programa->id)->update(['carrera_id' => $this->hist->id]))
        ->toThrow(ValidationException::class, 'postulaciones')
        ->and(fn () => Programa::find($this->programa->id)->update(['estado' => ProgramaEstado::Borrador]))
        ->toThrow(ValidationException::class, 'postulaciones');

    // Cerrarlo sí se puede
    Programa::find($this->programa->id)->update(['estado' => ProgramaEstado::Cerrado]);
    expect($this->programa->refresh()->estado)->toBe(ProgramaEstado::Cerrado);
});

test('un alumno ya acreditado no puede postularse a otro programa', function () {
    $this->practica->update(['estado' => PracticaEstado::EnCierre]);
    $this->practica->update(['estado' => PracticaEstado::Acreditada]);
    $otro = ($this->nuevoPrograma)('Otro programa');

    $this->actingAs($this->anaUser)->post(route('alumno.oferta-programas.postular', $otro), [
        'porcentaje_creditos_declarado' => 70, 'kardex' => trnPdf(),
    ])->assertSessionHasErrors('postulacion');

    expect(Postulacion::where('programa_id', $otro->id)->count())->toBe(0);
});

test('los programas de una empresa desactivada dejan de ofertarse', function () {
    $otro = ($this->nuevoPrograma)('Otro programa');

    expect(Programa::abiertos()->whereKey($otro->id)->exists())->toBeTrue();

    $this->empresaUser->update(['activo' => false]);

    expect(Programa::abiertos()->whereKey($otro->id)->exists())->toBeFalse();
});

test('acciones fuera de etapa: supervisor, borrador y documentos de un convenio cerrado', function () {
    $this->practica->update(['estado' => PracticaEstado::Cancelada]);
    $profesor = Profesor::create(['division_id' => $this->abog->division_id, 'nombre' => 'Dr. Prueba']);
    $admin = $this->actingAs($this->adminUser);

    $admin->post(route('admin.practicas.supervisor', $this->practica), ['profesor_id' => $profesor->id])
        ->assertSessionHasErrors('practica');

    $admin->post(route('admin.convenios.borrador', $this->convenio), ['borrador' => trnPdf()])
        ->assertSessionHasErrors('convenio');
    expect($this->convenio->getMedia('borrador_convenio'))->toHaveCount(0);

    $media = $this->empresa->addMedia(trnPdf())->toMediaCollection('acta_constitutiva');
    $admin->post(route('admin.convenios.documentos.resolver', [$this->convenio, $media]), ['estado' => 'validada'])
        ->assertSessionHasErrors('convenio');
});