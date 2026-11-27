<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CatalogoPostal;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/cp/{cp}: estado, municipio y colonias de un código postal, desde el catálogo local de SEPOMEX.
 * Nunca responde 500: un CP mal escrito es 422, uno que no existe es 404 y sin catálogo es 503,
 * siempre con un mensaje para mostrar tal cual bajo el campo.
 */
class CodigoPostalController extends Controller
{
    public function __invoke(string $cp): JsonResponse
    {
        if (preg_match('/^\d{5}$/', $cp) !== 1) {
            return response()->json(['mensaje' => 'El código postal son 5 números.'], 422);
        }

        try {
            if (! CatalogoPostal::cargado()) {
                return response()->json(['mensaje' => 'Por ahora escribe tu colonia, municipio y estado.'], 503);
            }

            $datos = CatalogoPostal::buscar($cp);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['mensaje' => 'No pudimos buscar el código postal. Escribe tu colonia, municipio y estado.'], 503);
        }

        if ($datos === null) {
            return response()->json(['mensaje' => 'No encontramos ese código postal. Revisa los 5 números o escribe tu colonia.'], 404);
        }

        return response()->json($datos);
    }
}
