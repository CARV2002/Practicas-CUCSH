<?php

/**
 * Restricciones por estado: pestañas con candado y motivo, documentos validados que ya no
 * se reemplazan y mensajes claros en las tarjetas de carga.
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
use App\Models\Postulacion;
use App\Models\Programa;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function rsPdf(string $n = 'doc.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($n, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesSeeder::class);
    $div = Division::create(['nombre' => 'D']);
    $this->carrera = Carrera::create(['division_id' => $div->id, 'carrera_clave' => 'RS', 'nombre' => 'Restricciones',
        'horas_requeridas' => 200, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8]);

    $this->alumnoUser = User::factory()->create();
    $this->alumnoUser->assignRole(Rol::Alumno->value);
    $this->alumno = Alumno::create(['user_id' => $this->alumnoUser->id, 'carrera_id' => $this->carrera->id, 'num_control' => '1',
        'grado' => 7, 'turno' => 'matutino']);

    $empresaUser = User::factory()->create();
    $empresaUser->assignRole(Rol::Empresa->value);
    $empresa = Empresa::create(['user_id' => $empresaUser->id, 'rfc' => 'RST010101AB1', 'razon_social' => 'Org', 'tipo_organismo' => TipoOrganismo::Privado, 'contacto_nombre' => 'C']);
    $convenio = Convenio::create(['empresa_id' => $empresa->id, 'estado' => ConvenioEstado::Formalizado, 'folio' => 'R-1',
        'fecha_recepcion' => now()->subYear(), 'fecha_inicio_vigencia' => now()->subYear(), 'fecha_fin_vigencia' => now()->addYears(2),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 2]);
    $convenio->dictamenes()->create(['carrera_id' => $this->carrera->id, 'dictamen' => Dictamen::Aprobatorio]);
    $this->programa = Programa::create(['convenio_id' => $convenio->id, 'carrera_id' => $this->carrera->id, 'modalidad' => 'externa',
        'ciclo' => '2027A', 'titulo' => 'Vacante R', 'descripcion' => 'x', 'actividades' => ['a'], 'perfil_requerido' => 'p',
        'cupos_totales' => 2, 'estado' => ProgramaEstado::Publicado]);
});

test('con la solicitud en revisión, la oferta queda con candado y explica por qué', function () {
    $this->actingAs($this->alumnoUser)->get(route('alumno.oferta-programas'))->assertOk()->assertSee('Vacante R');

    Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $this->programa->id, 'estado' => PostulacionEstado::Pendiente,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);

    $this->actingAs($this->alumnoUser->fresh())->get(route('alumno.oferta-programas'))->assertOk()
        ->assertSee('La oferta de programas no está disponible por ahora')
        ->assertSee('Tu coordinación está revisando tu kardex e IMSS')
        ->assertDontSee('Vacante R');

    // El menú muestra el candado con el mismo motivo
    $this->actingAs($this->alumnoUser->fresh())->get(route('alumno.dashboard'))->assertOk()
        ->assertSee('Tu coordinación está revisando tu kardex e IMSS', false);
});

test('Mis documentos explica cada estado: pendiente, en revisión, devuelto y validado', function () {
    $po = Postulacion::create(['alumno_id' => $this->alumno->id, 'programa_id' => $this->programa->id, 'estado' => PostulacionEstado::Pendiente,
        'porcentaje_creditos_declarado' => 70, 'fecha_postulacion' => today()]);
    $kardex = $po->addMedia(rsPdf('kardex.pdf'))->toMediaCollection('kardex');
    $imss = $this->alumno->addMedia(rsPdf('imss.pdf'))->toMediaCollection('constancia_imss');
    $this->alumno->update(['vigencia_imss' => now()->addYear()]);

    $this->actingAs($this->alumnoUser)->get(route('alumno.mis-documentos'))->assertOk()
        ->assertSee('Documentos para autorizar tu práctica')
        ->assertSee('¿Hasta qué fecha está vigente tu seguro?')
        ->assertSee('En revisión')
        ->assertSee('Tu coordinación está revisando tus documentos');

    $po->resolverDocumento($kardex, ValidacionEstado::Rechazada, 'El kardex es de un ciclo anterior.', $this->alumnoUser->id);
    $this->alumno->resolverDocumento($imss, ValidacionEstado::Validada, null, $this->alumnoUser->id);

    $this->actingAs($this->alumnoUser->fresh())->get(route('alumno.mis-documentos'))->assertOk()
        ->assertSee('Tu coordinación te pidió corregir un documento')
        ->assertSee('El kardex es de un ciclo anterior.')
        ->assertSee('Enviar kardex corregido')
        ->assertSee('Ya está validado.');   // el IMSS ya no muestra formulario
});
