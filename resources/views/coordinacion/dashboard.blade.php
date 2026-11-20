@extends('layouts.app')

@section('content')
@php
    $alcance = $carreras->count() === 1 ? 'Coordinación de ' . $carreras->first()->nombre : 'Coordinación · ' . $carreras->count() . ' carreras';
    $familiaPractica = fn (string $etiqueta) => collect(\App\Enums\PracticaEstado::cases())->first(fn ($e) => $e->label() === $etiqueta)?->familia() ?? 'gris';
@endphp

<div class="space-y-6">

    <x-dashboard.encabezado :eyebrow="$alcance" :titulo="$nombre ? 'Hola, ' . $nombre : 'Te damos la bienvenida'"
        :subtitulo="$totalPendientes
            ? 'Tienes ' . $totalPendientes . ' ' . ($totalPendientes === 1 ? 'pendiente' : 'pendientes') . ' por atender. Empieza por las tarjetas con número.'
            : 'Todo al día: no hay pendientes por atender en tu carrera.'">
        @if ($carreras->count() > 1)
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($carreras as $c)
                    <span class="rounded-md bg-white/10 px-2 py-0.5 text-xs font-semibold text-white ring-1 ring-inset ring-white/15" title="{{ $c->nombre }}">{{ $c->carrera_clave }}</span>
                @endforeach
            </div>
        @endif
        <x-slot:acciones>
            <a href="{{ route('coordinacion.lista-practicantes') }}" class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50">
                <x-dashboard.icono nombre="usuarios" clase="size-4" /> Practicantes
            </a>
            <a href="{{ route('coordinacion.lista-programas') }}" class="boton border border-white/25 text-white hover:bg-white/10">Programas</a>
        </x-slot:acciones>
    </x-dashboard.encabezado>

    {{-- Pendientes de la carrera: acceso directo en un clic --}}
    <section aria-label="Pendientes de tu coordinación">
        <div class="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 lg:grid-cols-3">
            <x-dashboard.kpi :href="route('coordinacion.lista-practicantes')"
                etiqueta="Expedientes por revisar" :valor="$pendientes['expedientes']" icono="imss" tono="lavanda" :orden="0"
                :detalle="$pendientes['imss'] . ' IMSS · ' . $pendientes['kardex'] . ' kardex por validar'" accion="Revisar" />
            <x-dashboard.kpi :href="route('coordinacion.lista-practicantes')"
                etiqueta="Postulaciones por atender" :valor="$pendientes['postulaciones'] + $pendientes['cartas_aceptacion']" icono="usuarios" tono="cielo" :orden="1"
                :detalle="$pendientes['postulaciones'] . ' por carta de presentación · ' . $pendientes['cartas_aceptacion'] . ' por activar'" accion="Atender" />
            <x-dashboard.kpi :href="route('coordinacion.lista-programas', ['estado' => 'pendiente_aprobacion'])"
                etiqueta="Vacantes por aprobar" :valor="$pendientes['vacantes']" icono="vacante" tono="ambar" :orden="2"
                detalle="Propuestas de empresas para tu carrera" accion="Revisar" />
            <x-dashboard.kpi :href="route('coordinacion.lista-reportes')"
                etiqueta="Reportes por validar" :valor="$pendientes['reportes']" icono="reporte" tono="indigo" :orden="3"
                detalle="Entregados firmados: las horas cuentan al validarlos" accion="Validar" />
            <x-dashboard.kpi :href="route('coordinacion.lista-cierres')"
                etiqueta="Cierres por atender" :valor="$pendientes['cartas_termino'] + $pendientes['en_cierre']" icono="cierre" tono="menta" :orden="4"
                :detalle="$pendientes['cartas_termino'] . ' cartas de término · ' . $pendientes['en_cierre'] . ' oficios y SIIAU'" accion="Atender" />
            <x-dashboard.kpi :href="route('coordinacion.lista-practicantes')"
                etiqueta="Alumnos bloqueados" :valor="$pendientes['bloqueados']" icono="alerta" tono="rosa" :orden="5"
                detalle="Por bajas: solo tú haces el desbloqueo excepcional" accion="Revisar" />
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Tu carrera en números --}}
        <x-dashboard.panel titulo="Tu carrera en números" icono="informe" descripcion="Panorama general de tu alcance.">
            <dl class="grid grid-cols-2 gap-3">
                @foreach ([
                    ['Alumnos registrados', number_format($kpis['alumnos'])],
                    ['Prácticas activas', $kpis['practicas_activas']],
                    ['Programas publicados', $kpis['programas_publicados']],
                    ['Cupos publicados', $kpis['cupos_totales']],
                ] as [$texto, $n])
                    <div class="rounded-xl bg-gray-50 p-3.5 ring-1 ring-inset ring-gray-100">
                        <dt class="text-xs text-gray-500">{{ $texto }}</dt>
                        <dd class="mt-1 font-display text-2xl font-bold tabular-nums text-gray-900">{{ $n }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-5">
                <div class="flex items-baseline justify-between text-sm">
                    <span class="font-medium text-gray-700">Ocupación de plazas</span>
                    <span class="font-display text-lg font-bold tabular-nums text-udg-900">{{ $kpis['ocupacion'] }}%</span>
                </div>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ $kpis['ocupacion'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Ocupación de plazas">
                    <div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $kpis['ocupacion'] }}%"></div>
                </div>
                <p class="mt-1.5 text-xs text-gray-500">{{ $kpis['cupos_ocupados'] }} de {{ $kpis['cupos_totales'] }} cupos ocupados</p>
            </div>
        </x-dashboard.panel>

        {{-- Programas recientes --}}
        <x-dashboard.panel class="xl:col-span-2" titulo="Programas recientes" icono="vacante"
            descripcion="Últimas vacantes externas y proyectos internos de tu carrera."
            :enlace="route('coordinacion.lista-programas')" enlace-texto="Ver todos">
            @forelse ($programas_recientes as $p)
                @php $ocup = $p->cupos_totales > 0 ? min(100, (int) round($p->cupos_ocupados / $p->cupos_totales * 100)) : 0; @endphp
                <div class="-mx-2 flex flex-col gap-2 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50 sm:flex-row sm:items-center sm:gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $p->titulo }}</p>
                        <p class="mt-0.5 truncate text-xs text-gray-500">{{ $p->carrera->carrera_clave }} · {{ $p->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</p>
                    </div>
                    <div class="flex items-center gap-3 sm:w-72 sm:shrink-0">
                        <div class="flex-1">
                            <div class="flex justify-between text-xs"><span class="text-gray-500">Cupos</span><span class="font-semibold tabular-nums text-gray-900">{{ (int) $p->cupos_ocupados }}/{{ $p->cupos_totales }}</span></div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-udg-800" style="width: {{ $ocup }}%"></div></div>
                        </div>
                        <x-ui.estatus :estado="$p->estado" class="shrink-0" />
                    </div>
                </div>
            @empty
                <x-dashboard.vacio icono="vacante" titulo="Aún no hay programas" texto="Las vacantes que propongan las empresas y tus proyectos internos aparecerán aquí." />
            @endforelse
        </x-dashboard.panel>
    </div>

    {{-- Prácticas por estado --}}
    <x-dashboard.panel titulo="Prácticas por estado" icono="reloj" descripcion="Seguimiento de las prácticas de tu alcance."
        :enlace="route('coordinacion.lista-practicantes')" enlace-texto="Practicantes">
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ($practicas_por_estado as $estado => $n)
                <li class="rounded-xl p-4 ring-1 ring-inset {{ \App\Support\Estatus::chip($familiaPractica($estado)) }}">
                    <p class="text-xs font-semibold">{{ $estado }}</p>
                    <p class="mt-1 font-display text-2xl font-bold tabular-nums">{{ $n }}</p>
                </li>
            @endforeach
        </ul>
    </x-dashboard.panel>
</div>
@endsection
