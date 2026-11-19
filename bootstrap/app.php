<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un archivo que excede post_max_size vacía el formulario completo: en vez de la página 413,
        // regresamos al formulario con un mensaje claro (los formularios permiten hasta 10 MB; el escaneo firmado, 20 MB).
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            $limite = ini_get('post_max_size');
            $mensaje = "El archivo es demasiado grande: el servidor acepta hasta {$limite} por envío. Comprime el PDF o divídelo e inténtalo de nuevo.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje], 413);
            }

            return redirect()->back()->withErrors(['archivo' => $mensaje]);
        });
    })->create();
