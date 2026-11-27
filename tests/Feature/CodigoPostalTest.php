<?php

/*
 * Catálogo local de códigos postales (SEPOMEX): importación del archivo oficial, endpoint /api/cp/{cp}
 * sin errores 500 y sin depender de servicios externos; y tarjetas del convenio según su estado.
 */

use App\Enums\ConvenioEstado;
use App\Enums\Dictamen;
use App\Enums\Rol;
use App\Enums\TipoOrganismo;
use App\Models\Carrera;
use App\Models\CodigoPostal;
use App\Models\Convenio;
use App\Models\Division;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\User;
use App\Support\CatalogoPostal;
use Database\Seeders\CodigoPostalSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Http;

/** Archivo con el formato oficial de SEPOMEX (nota inicial, encabezados, «|» y codificación ISO-8859-1). */
function cpArchivo(): string
{
    $lineas = [
        'El Catálogo Nacional de Códigos Postales, es elaborado por Correos de México.',
        'd_codigo|d_asenta|d_tipo_asenta|D_mnpio|d_estado|d_ciudad|d_CP|c_estado|c_oficina|c_CP|c_tipo_asenta|c_mnpio|id_asenta_cpcons|d_zona|c_cve_ciudad',
        '00001|Colonia Uno|Colonia|Municipio Prueba|Jalisco|Ciudad Prueba|00001|14|00001||09|039|0001|Urbano|01',
        '00001|Barrio Dos|Barrio|Municipio Prueba|Jalisco|Ciudad Prueba|00001|14|00001||02|039|0002|Urbano|01',
        '00002|Fraccionamiento Tres|Fraccionamiento|Municipio Norte|Veracruz de Ignacio de la Llave||00002|30|00002||21|087|0003|Urbano|',
        '00003|Pueblo Cuatro|Pueblo|Otro Municipio|México||00003|15|00003||28|001|0004|Rural|',
        '00004|Colonia Cinco|Colonia|Ahualulco del Sonido 13|Jalisco||00004|14|00004||09|003|0005|Rural|',
        '',
    ];
    $ruta = tempnam(sys_get_temp_dir(), 'cp').'.txt';
    file_put_contents($ruta, mb_convert_encoding(implode("\r\n", $lineas), 'ISO-8859-1', 'UTF-8'));

    return $ruta;
}

function cpUsuario(Rol $rol): User
{
    $u = User::factory()->create();
    $u->assignRole($rol->value);

    return $u;
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Http::preventStrayRequests();   // ninguna consulta sale a internet
    $this->usuario = cpUsuario(Rol::Empresa);
    // Una cuenta sin perfil se cierra sola (EnsureUserIsActive): la organización va desde el inicio
    $this->empresa = Empresa::create(['user_id' => $this->usuario->id, 'rfc' => 'ABC010101XYZ', 'razon_social' => 'Organización Ejemplo S.C.',
        'tipo_organismo' => TipoOrganismo::SociedadCivil, 'contacto_nombre' => 'Nombre Apellido']);
});

test('importa el archivo oficial de SEPOMEX y normaliza los estados', function () {
    expect(CatalogoPostal::importar(cpArchivo()))->toBe(5)
        ->and(CodigoPostal::where('cp', '00001')->count())->toBe(2)
        ->and(CodigoPostal::where('cp', '00002')->value('estado'))->toBe('Veracruz')
        ->and(CodigoPostal::where('cp', '00003')->value('estado'))->toBe('Estado de México');

    // Reimportar reemplaza (no duplica)
    CatalogoPostal::importar(cpArchivo());
    expect(CodigoPostal::count())->toBe(5);
});

test('el endpoint responde estado, municipio y colonias del código postal', function () {
    CatalogoPostal::importar(cpArchivo());

    $this->actingAs($this->usuario)->getJson(route('api.cp', '00001'))
        ->assertOk()
        ->assertExactJson(['cp' => '00001', 'estado' => 'Jalisco', 'municipio' => 'Municipio Prueba', 'colonias' => ['Barrio Dos', 'Colonia Uno']]);
});

test('sin errores 500: formato inválido 422, inexistente 404 y sin catálogo 503, con mensaje', function () {
    $this->actingAs($this->usuario)->getJson(route('api.cp', '0001'))->assertStatus(422)->assertJsonPath('mensaje', 'El código postal son 5 números.');
    $this->actingAs($this->usuario)->getJson(route('api.cp', '0001a'))->assertStatus(422);
    $this->actingAs($this->usuario)->getJson(route('api.cp', '00001'))->assertStatus(503)->assertJsonStructure(['mensaje']);

    CatalogoPostal::importar(cpArchivo());
    $this->actingAs($this->usuario)->getJson(route('api.cp', '99999'))
        ->assertNotFound()
        ->assertJsonPath('mensaje', 'No encontramos ese código postal. Revisa los 5 números o escribe tu colonia.');
});

test('el endpoint exige sesión', function () {
    $this->getJson(route('api.cp', '00001'))->assertUnauthorized();
});

test('el seeder no repite la importación si el catálogo ya está cargado', function () {
    CodigoPostal::create(['cp' => '00001', 'colonia' => 'Colonia Uno', 'municipio' => 'Municipio Prueba', 'estado' => 'Jalisco']);

    $this->seed(CodigoPostalSeeder::class);

    expect(CodigoPostal::count())->toBe(1);
});

test('el detalle del convenio muestra Borrador y Documentos físicos solo cuando corresponden', function () {
    $division = Division::create(['nombre' => 'División de Prueba']);
    $carrera = Carrera::create(['division_id' => $division->id, 'carrera_clave' => 'ABOG', 'nombre' => 'Abogado', 'horas_requeridas' => 300,
        'porcentaje_creditos_minimo' => 60, 'creditos_otorgados' => 8]);
    $lorena = cpUsuario(Rol::Vinculacion);
    Personal::create(['user_id' => $lorena->id, 'puesto' => 'Vinculación']);
    $empresa = $this->empresa;
    $convenio = Convenio::create(['empresa_id' => $empresa->id, 'estado' => ConvenioEstado::EnValidacionAcademica, 'fecha_recepcion' => now(),
        'objetivos' => 'x', 'actividades_propuestas' => 'y', 'plazas_estimadas' => 1]);
    $convenio->dictamenes()->create(['carrera_id' => $carrera->id, 'dictamen' => Dictamen::Pendiente]);

    // Dictámenes pendientes: ni borrador ni papeles
    $this->actingAs($lorena)->get(route('admin.convenios.show', $convenio))->assertOk()
        ->assertDontSee('Borrador y Unidad Jurídica')->assertDontSee('Abrir control físico');

    // Rechazado (todas las carreras dijeron no): tampoco
    $convenio->update(['estado' => ConvenioEstado::Rechazado]);
    $this->actingAs($lorena)->get(route('admin.convenios.show', $convenio))->assertOk()
        ->assertDontSee('Borrador y Unidad Jurídica')->assertDontSee('Abrir control físico');

    // En Jurídica: ambas tarjetas
    $convenio->update(['estado' => ConvenioEstado::EnRevisionJuridica]);
    $this->actingAs($lorena)->get(route('admin.convenios.show', $convenio))->assertOk()
        ->assertSee('Borrador y Unidad Jurídica')->assertSee('Documentos físicos');
});

test('con el CP en el catálogo, estado y municipio deben corresponderle (y se acepta el municipio oficial con números)', function () {
    CatalogoPostal::importar(cpArchivo());
    $base = ['razon_social' => 'Organización Ejemplo S.C.', 'contacto_nombre' => 'Nombre Apellido', 'codigo_postal' => '00001', 'colonia' => 'Colonia Uno'];

    // Estado de otro CP: se rechaza sin guardar
    $this->actingAs($this->usuario)->put(route('empresa.convenio.seccion', 'generales'), $base + ['municipio' => 'Municipio Prueba', 'entidad' => 'Veracruz'])
        ->assertSessionHasErrors(['entidad' => 'El estado no corresponde al código postal 00001 (Jalisco).']);
    $this->actingAs($this->usuario)->put(route('empresa.convenio.seccion', 'generales'), $base + ['municipio' => 'Otro Municipio', 'entidad' => 'Jalisco'])
        ->assertSessionHasErrors('municipio');
    expect($this->empresa->fresh()->codigo_postal)->toBeNull();

    // Los datos del catálogo pasan, aunque el municipio lleve números
    $this->actingAs($this->usuario)->put(route('empresa.convenio.seccion', 'generales'),
        ['codigo_postal' => '00004', 'colonia' => 'Colonia Cinco', 'municipio' => 'Ahualulco del Sonido 13', 'entidad' => 'Jalisco'] + $base)
        ->assertSessionHasNoErrors();
    expect($this->empresa->fresh()->municipio)->toBe('Ahualulco del Sonido 13');

    // Un CP que no está en el catálogo se captura a mano con la regla de siempre (solo letras)
    $this->actingAs($this->usuario)->put(route('empresa.convenio.seccion', 'generales'), ['codigo_postal' => '99999', 'municipio' => 'Municipio 99', 'entidad' => 'Jalisco'] + $base)
        ->assertSessionHasErrors('municipio');
});
