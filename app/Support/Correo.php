<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Enlaces para escribir un correo desde Gmail con destinatario, asunto y cuerpo ya llenos.
 */
class Correo
{
    /**
     * Enlace que abre la redacción de un correo en Gmail (en vez del cliente
     * de correo predeterminado, que en muchas computadoras es Outlook).
     */
    public static function gmail(string $para, string $asunto = '', string $cuerpo = ''): string
    {
        return 'https://mail.google.com/mail/?'.http_build_query(array_filter([
            'view' => 'cm',
            'fs' => '1',
            'to' => $para,
            'su' => $asunto,
            'body' => $cuerpo,
        ], fn ($v) => $v !== ''), '', '&', PHP_QUERY_RFC3986);
    }
}
