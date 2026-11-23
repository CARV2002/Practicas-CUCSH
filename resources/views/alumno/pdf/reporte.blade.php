<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte {{ $reporte->numero }} de prácticas profesionales</title>
    <style>
        @page { margin: 28mm 18mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1f2937; line-height: 1.35; }
        header { position: fixed; top: -22mm; left: 0; right: 0; border-bottom: 2px solid #1b2a4a; padding-bottom: 5px; }
        header table { width: 100%; border-collapse: collapse; }
        header img { height: 15mm; }
        header .u { font-size: 11pt; font-weight: bold; color: #1b2a4a; }
        header .c { font-size: 8.5pt; color: #374151; }
        header .f { font-size: 8pt; color: #6b7280; text-align: right; }
        footer { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 7pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 3px; }
        h1 { font-size: 12.5pt; text-align: center; color: #1b2a4a; margin: 0 0 2px; }
        .sub { text-align: center; font-size: 9pt; color: #4b5563; margin-bottom: 12px; }
        h2 { font-size: 9.5pt; text-transform: uppercase; letter-spacing: .4px; color: #1b2a4a; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; margin: 10px 0 5px; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos td { border: 1px solid #d1d5db; padding: 4px 7px; vertical-align: top; }
        table.datos .l { width: 24%; background: #f3f4f6; font-size: 8pt; color: #4b5563; }
        table.horas { width: 100%; border-collapse: collapse; text-align: center; }
        table.horas th { background: #1b2a4a; color: #fff; font-size: 8pt; padding: 4px; }
        table.horas td { border: 1px solid #d1d5db; padding: 5px; font-size: 10pt; font-weight: bold; }
        .texto { border: 1px solid #d1d5db; padding: 8px 10px; min-height: 14mm; white-space: pre-line; }
        table.firmas { width: 100%; border-collapse: collapse; margin-top: 16mm; page-break-inside: avoid; }
        table.firmas td { width: 33.3%; text-align: center; vertical-align: top; padding: 0 6px; font-size: 8pt; }
        .linea { border-top: 1px solid #111827; margin: 0 6px 3px; padding-top: 3px; font-weight: bold; font-size: 8.5pt; }
        .sello { color: #9ca3af; font-size: 7pt; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                <td style="width: 62mm">@if ($logo)<img src="{{ $logo }}" alt="Universidad de Guadalajara">@endif</td>
                <td>
                    <div class="u">Centro Universitario de Ciencias Sociales y Humanidades</div>
                    <div class="c">Coordinación de Prácticas Profesionales</div>
                </td>
                <td class="f">Formato estándar del sistema<br>Generado el {{ now()->format('d/m/Y') }}</td>
            </tr>
        </table>
    </header>
    <footer>Reporte {{ $reporte->numero }} · {{ $alumno->user->name }} ({{ $alumno->num_control }}) · Folio interno R-{{ str_pad((string) $reporte->id, 6, '0', STR_PAD_LEFT) }}</footer>

    <h1>Reporte {{ $reporte->numero }} de prácticas profesionales</h1>
    <div class="sub">{{ $reporte->tipo->label() }} · del {{ $reporte->periodo_inicio->format('d/m/Y') }} al {{ $reporte->periodo_fin->format('d/m/Y') }}</div>

    <h2>Datos del alumno</h2>
    <table class="datos">
        <tr><td class="l">Nombre</td><td>{{ $alumno->user->name }}</td><td class="l">Código UdeG</td><td>{{ $alumno->num_control }}</td></tr>
        <tr><td class="l">Carrera</td><td colspan="3">{{ $alumno->carrera->nombre }}</td></tr>
        <tr><td class="l">Grado y turno</td><td>{{ $alumno->grado ? $alumno->grado . '.º' : '—' }} · {{ $alumno->turno?->label() ?? '—' }}</td><td class="l">Profesor supervisor</td><td>{{ $practica->supervisor?->nombre ?? '—' }}</td></tr>
    </table>

    <h2>Datos de la organización</h2>
    <table class="datos">
        <tr><td class="l">Organización</td><td colspan="3">{{ $empresa?->razon_social ?? 'Proyecto interno del CUCSH' }}</td></tr>
        <tr><td class="l">Programa o plaza</td><td colspan="3">{{ $programa->titulo }}</td></tr>
        <tr><td class="l">Periodo de la práctica</td><td>{{ $practica->fecha_inicio->format('d/m/Y') }} al {{ $practica->fecha_fin->format('d/m/Y') }}</td><td class="l">Horario</td><td>{{ $programa->horario ?: '—' }}</td></tr>
    </table>

    <h2>Horas</h2>
    <table class="horas">
        <tr><th>Horas de este reporte</th><th>Horas validadas antes</th><th>Total con este reporte</th><th>Horas requeridas</th></tr>
        <tr><td>{{ $reporte->horas_reportadas }}</td><td>{{ $validadasAntes }}</td><td>{{ $validadasAntes + $reporte->horas_reportadas }}</td><td>{{ $practica->horas_requeridas }}</td></tr>
    </table>

    <h2>Actividades realizadas</h2>
    <div class="texto">{{ $reporte->actividades }}</div>

    @if (filled($reporte->logros))
        <h2>Logros y aprendizajes</h2>
        <div class="texto" style="min-height: 10mm">{{ $reporte->logros }}</div>
    @endif

    <table class="firmas">
        <tr>
            <td><div class="linea">{{ $alumno->user->name }}</div>Firma del alumno</td>
            <td><div class="linea">Jefe directo en la organización</div>Nombre, firma y sello<br><span class="sello">(sello de la organización)</span></td>
            <td><div class="linea">{{ $practica->supervisor?->nombre ?? 'Coordinación de Carrera' }}</div>Vo. Bo. coordinación / profesor supervisor</td>
        </tr>
    </table>
</body>
</html>
