<?php

use App\Http\Middleware\EncabezadosSeguridad;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
        $middleware->web(append: [
            EnsureUserIsActive::class,
            EncabezadosSeguridad::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un archivo que excede post_max_size vacía el formulario completo: en vez de la página 413,
        // regresamos al formulario con un mensaje claro (los formularios permiten hasta 10 MB; el escaneo firmado, 20 MB).
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $limite = ini_get('post_max_size');
            $mensaje = "El archivo es demasiado grande: el servidor acepta hasta {$limite} por envío. Comprime el PDF o divídelo e inténtalo de nuevo.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje], 413);
            }

            return redirect()->back()->withErrors(['archivo' => $mensaje]);
        });
    })->create();
