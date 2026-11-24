<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encabezados de seguridad en todas las respuestas web.
 *
 * Siempre:
 *  - X-Frame-Options / frame-ancestors: el sistema no se puede incrustar en sitios ajenos (clickjacking);
 *    sí en sí mismo, porque la vista previa de documentos abre el PDF en un iframe propio.
 *  - X-Content-Type-Options: el navegador no «adivina» el tipo de un archivo descargado.
 *  - Referrer-Policy: a sitios externos solo se manda el dominio, nunca la ruta (que puede llevar IDs).
 *  - Permissions-Policy: la plataforma no usa cámara, micrófono ni ubicación.
 *
 * Solo en producción (en local romperían el servidor de Vite):
 *  - Content-Security-Policy: scripts, estilos, fuentes e imágenes solo del propio sitio, más Font Awesome
 *    (cdnjs) y las fuentes de bunny.net que usan las páginas públicas. Alpine necesita 'unsafe-eval'.
 *  - Strict-Transport-Security: si el sitio ya va por HTTPS, el navegador no vuelve a intentar HTTP.
 */
class EncabezadosSeguridad
{
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.bunny.net",
        "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.bunny.net",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "frame-src 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy', implode('; ', self::CSP));

            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }
}
