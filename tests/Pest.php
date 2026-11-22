<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Deja el expediente del alumno (kardex + IMSS) entregado y validado por su coordinación,
 * que es el requisito para postularse. $estado: validada | pendiente | rechazada
 */
function expedienteDe(\App\Models\Alumno $alumno, float $creditos = 70, string $estado = 'validada', ?\Carbon\CarbonInterface $vigencia = null): \App\Models\Alumno
{
    $pdf = fn (string $n) => \Illuminate\Http\UploadedFile::fake()->createWithContent($n, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

    $alumno->update(['porcentaje_creditos' => $creditos, 'vigencia_imss' => ($vigencia ?? now()->addYear())->toDateString()]);

    foreach (['kardex' => 'kardex.pdf', 'constancia_imss' => 'imss.pdf'] as $coleccion => $nombre) {
        $media = $alumno->documentoVigente($coleccion) ?? $alumno->addMedia($pdf($nombre))->toMediaCollection($coleccion);
        if ($estado !== 'pendiente') {
            $alumno->resolverDocumento($media, \App\Enums\ValidacionEstado::from($estado), $estado === 'rechazada' ? 'Motivo de prueba' : null, $alumno->user_id);
        }
    }

    return $alumno->fresh();
}

/** Da al usuario el perfil que exige su rol (alumno o empresa): sin él, la cuenta no puede entrar. */
function conPerfil(\App\Models\User $user): \App\Models\User
{
    $rol = $user->rolPrincipal();

    if ($rol === \App\Enums\Rol::Alumno) {
        $carrera = \App\Models\Carrera::first() ?? \App\Models\Carrera::create([
            'division_id' => \App\Models\Division::create(['nombre' => 'División de perfil'])->id,
            'carrera_clave' => 'PERF', 'nombre' => 'Carrera de perfil',
            'horas_requeridas' => 250, 'porcentaje_creditos_minimo' => 50, 'creditos_otorgados' => 8,
        ]);
        \App\Models\Alumno::create(['user_id' => $user->id, 'num_control' => \Illuminate\Support\Str::random(9), 'carrera_id' => $carrera->id]);
    } elseif ($rol === \App\Enums\Rol::Empresa) {
        \App\Models\Empresa::create(['user_id' => $user->id, 'rfc' => 'T' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(11)),
            'razon_social' => 'Empresa de perfil', 'tipo_organismo' => \App\Enums\TipoOrganismo::Privado, 'contacto_nombre' => 'Contacto']);
    }

    return $user->unsetRelation('roles');
}

/** Datos notariales válidos de una sociedad o asociación (escritura pública). */
function datosNotarialesPrueba(): array
{
    return ['escritura_numero' => '1234', 'escritura_fecha' => '2010-01-10', 'notario_nombre' => 'Lic. Juan Notario',
        'notaria_numero' => '5', 'notaria_ciudad' => 'Guadalajara, Jalisco', 'folio_mercantil' => 'N-2010001'];
}

/**
 * Deja completas las secciones de la solicitud de convenio de una empresa (generales, legales y perfiles),
 * como si la hubiera llenado en «Mi convenio». Los documentos se suben aparte.
 */
function solicitudLista(\App\Models\Empresa $empresa, array $carreraIds, array $perfiles = []): \App\Models\Empresa
{
    $escritura = \App\Support\DatosNotariales::usaEscritura($empresa->tipo_organismo);
    $empresa->update(array_merge([
        'giro' => 'Servicios profesionales', 'descripcion' => 'Organización dedicada a la asesoría y atención al público desde hace diez años.',
        'proyectos_principales' => 'Asesoría a dependencias públicas', 'calle' => 'Av. Juárez', 'numero_exterior' => '976',
        'colonia' => 'Centro', 'codigo_postal' => '44100', 'municipio' => 'Guadalajara', 'entidad' => 'Jalisco',
        'domicilio_fiscal' => 'Av. Juárez 976, col. Centro, C.P. 44100, Guadalajara, Jalisco',
        'contacto_nombre' => $empresa->contacto_nombre && str_contains($empresa->contacto_nombre, ' ') ? $empresa->contacto_nombre : 'Ana Contacto',
        'contacto_cargo' => 'Recursos Humanos', 'contacto_telefono' => '3312345678',
        'representante_legal' => $empresa->representante_legal ?: 'Lic. Rosa Firma', 'representante_cargo' => $empresa->representante_cargo ?: 'Directora General',
        'objeto_social' => 'la prestación de servicios profesionales a la comunidad',
    ], $escritura
        ? [...datosNotarialesPrueba(), 'constitucion' => \App\Support\DatosNotariales::redactar(datosNotarialesPrueba()), 'facultad_origen' => 'acta',
            'facultad_firmante' => 'las facultades que le otorga la escritura constitutiva citada en la declaración I']
        : ['constitucion' => 'el decreto publicado en el Periódico Oficial el 1 de enero de 2000', 'facultad_origen' => 'nombramiento',
            'facultad_firmante' => 'nombramiento de fecha 1 de enero de 2025 expedido por el titular del Ejecutivo'],
    ));
    $empresa->update(['solicitud_borrador' => array_merge([
        'carreras' => $carreraIds, 'objetivos' => 'Formar practicantes en proyectos reales de la organización.',
        'actividades_propuestas' => 'Apoyo en atención al público, archivo y elaboración de documentos.', 'plazas_estimadas' => 3,
        'medio_conocimiento' => 'Por la página del CUCSH', 'experiencia_previa' => 'Ninguno',
    ], $perfiles)]);

    return $empresa->refresh();
}
