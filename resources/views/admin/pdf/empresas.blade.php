<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Empresas e instituciones</title>
    <style>
        @page { margin: 22mm 16mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1f2937; }
        header { position: fixed; top: -16mm; left: 0; right: 0; border-bottom: 2px solid #1b2a4a; padding-bottom: 4px; }
        header .t { font-size: 12pt; font-weight: bold; color: #1b2a4a; }
        header .s { font-size: 8pt; color: #6b7280; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #9ca3af; text-align: right; }
        footer .pagina:after { content: counter(page) " de " counter(pages); }
        .resumen { width: 100%; border-collapse: collapse; margin: 0 0 12px; }
        .resumen td { border: 1px solid #e5e7eb; padding: 5px 8px; text-align: center; }
        .resumen .n { font-size: 13pt; font-weight: bold; color: #1b2a4a; }
        .resumen .l { font-size: 7.5pt; color: #6b7280; }
        h2 { font-size: 10.5pt; margin: 14px 0 4px; color: #1b2a4a; }
        h2 span { font-weight: normal; color: #6b7280; font-size: 9pt; }
        table.lista { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        table.lista th { background: #1b2a4a; color: #fff; text-align: left; font-size: 8pt; padding: 5px 6px; }
        table.lista td { border-bottom: 1px solid #e5e7eb; padding: 5px 6px; vertical-align: top; }
        table.lista tr:nth-child(even) td { background: #f8fafc; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; }
        .gris { color: #6b7280; font-size: 8pt; }
        .vacio { color: #9ca3af; font-style: italic; }
    </style>
</head>
<body>
    <header>
        <div class="t">Empresas e instituciones · Prácticas Profesionales CUCSH</div>
        <div class="s">
            Generado el {{ now()->format('d/m/Y H:i') }}
            @if ($filtro) · Situación: {{ $filtro }} @endif
            @if ($busqueda) · Búsqueda: «{{ $busqueda }}» @endif
        </div>
    </header>
    <footer>Unidad de Vinculación · Página <span class="pagina"></span></footer>

    @php $total = $grupos->sum(fn ($g) => $g->count()); @endphp
    <table class="resumen">
        <tr>
            <td><div class="n">{{ $total }}</div><div class="l">Total</div></td>
            @foreach (\App\Models\Empresa::SITUACIONES as $clave => [$texto])
                @if (!$filtro || isset($grupos[$clave]))
                    <td><div class="n">{{ isset($grupos[$clave]) ? $grupos[$clave]->count() : 0 }}</div><div class="l">{{ $texto }}</div></td>
                @endif
            @endforeach
        </tr>
    </table>

    @forelse ($grupos as $clave => $grupo)
        <h2>{{ \App\Models\Empresa::SITUACIONES[$clave][0] }} <span>({{ $grupo->count() }})</span></h2>
        <table class="lista">
            <thead>
                <tr>
                    <th style="width: 30%">Organización</th>
                    <th style="width: 12%">RFC</th>
                    <th style="width: 13%">Tipo</th>
                    <th style="width: 20%">Contacto</th>
                    <th style="width: 25%">Detalle</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($grupo as [$e, $s])
                    <tr>
                        <td><strong>{{ $e->razon_social }}</strong><br><span class="gris">{{ $e->representante_legal ?: 'Representante sin registrar' }}</span></td>
                        <td class="mono">{{ $e->rfc }}</td>
                        <td>{{ $e->tipo_organismo?->label() ?? 'Sin definir' }}</td>
                        <td>{{ $e->contacto_nombre ?: 'Sin contacto' }}<br><span class="gris">{{ $e->user?->email ?? 'Sin cuenta' }}{{ $e->contacto_telefono ? ' · ' . $e->contacto_telefono : '' }}</span></td>
                        <td>{!! $s['detalle'] ? e($s['detalle']) : '<span class="vacio">Sin detalle</span>' !!}<br><span class="gris">{{ $e->convenios_count }} {{ $e->convenios_count === 1 ? 'solicitud' : 'solicitudes' }} registradas</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="vacio">No hay empresas con esos filtros.</p>
    @endforelse
</body>
</html>
