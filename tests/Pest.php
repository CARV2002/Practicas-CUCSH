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
