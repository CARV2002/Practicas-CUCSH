<?php

return [
    // Titular de Rectoría del CUCSH que firma el convenio: cámbialo aquí cuando cambie
    'rectora' => env('PRACTICAS_RECTORA', 'LIC. DULCE MARÍA ZÚÑIGA CHÁVEZ'),
    'rectora_cargo' => 'RECTORA DEL CENTRO UNIVERSITARIO DE CIENCIAS SOCIALES Y HUMANIDADES',
    'domicilio_cucsh' => 'Los Belenes, Av. José Parres Arias no. 150, San José del Bajío, C.P. 45132; Zapopan, Jalisco',

    // Datos de contacto de la Coordinación que muestran las páginas públicas.
    'contacto' => [
        'correo' => env('PRACTICAS_CORREO', 'practicas.profesionales.cucsh@csh.udg.mx'),
        'telefono' => '(33) 3819-3300',
        'telefono_enlace' => '+523338193300',
        'extensiones' => ['23549', '23404'],
    ],

    'google' => [
        'dominios_alumno' => array_filter(array_map('trim', explode(',', env('GOOGLE_DOMINIOS_ALUMNO', 'alumnos.udg.mx')))),
        'dominios_personal' => array_filter(array_map('trim', explode(',', env('GOOGLE_DOMINIOS_PERSONAL', 'academicos.udg.mx,csh.udg.mx,udg.mx')))),
    ],

    'auditoria' => (bool) env('AUDITORIA_ACTIVA', true),

    // Exportación del convenio (Formato de PP) a PDF con LibreOffice sin interfaz
    'pdf' => [
        'habilitado' => (bool) env('PRACTICAS_CONVERTIR_PDF', true),
        'soffice' => env('PRACTICAS_SOFFICE'),
        'timeout' => (int) env('PRACTICAS_PDF_TIMEOUT', 90),
    ],
];
