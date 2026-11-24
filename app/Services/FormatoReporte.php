<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReporteEstado;
use App\Models\Reporte;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;

/**
 * Formato estándar del reporte de horas (PDF) con lo que el alumno redactó: encabezado del CUCSH, datos del
 * alumno y la organización, tabla de horas, actividades y tres firmas. Se imprime, se firma y se sube escaneado.
 */
class FormatoReporte
{
    public function generar(Reporte $reporte): DocumentoPdf
    {
        $practica = $reporte->practica->load(['postulacion.alumno.user', 'postulacion.alumno.carrera', 'postulacion.programa.convenio.empresa', 'supervisor']);
        $programa = $practica->postulacion->programa;

        // Horas que ya llevaba validadas antes de este reporte (incluye las reconocidas de una baja parcial)
        $validadasAntes = (int) $practica->reportes()->where('estado', ReporteEstado::Validado->value)->where('id', '!=', $reporte->id)->sum('horas_reportadas')
            + (int) ($practica->horas_reconocidas ?? 0);
        $logo = public_path('images/home/logo-udg.png');

        return Pdf::loadView('alumno.pdf.reporte', [
            'reporte' => $reporte,
            'practica' => $practica,
            'alumno' => $practica->postulacion->alumno,
            'programa' => $programa,
            'empresa' => $programa->convenio?->empresa,
            'validadasAntes' => $validadasAntes,
            'logo' => is_file($logo) ? $logo : null,
        ])->setPaper('letter');
    }

    /** Nombre del archivo: reporte-3-219876543.pdf */
    public function nombre(Reporte $reporte): string
    {
        return "reporte-{$reporte->numero}-{$reporte->practica->postulacion->alumno->num_control}.pdf";
    }
}
