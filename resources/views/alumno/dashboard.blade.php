@extends('layouts.app')

@section('content')
@php
    $pasos = [
        ['Postúlate', 'Elige un programa de tu carrera'],
        ['Carta de presentación', 'Tu coordinación valida y la emite'],
        ['Carta de aceptación', 'La organización te acepta'],
        ['Práctica en curso', 'Reporta tus horas'],
        ['Cierre', 'Carta de término y oficio'],
        ['Acreditada', 'Captura en SIIAU'],
    ];
    $pct = $kpis['pct'] ?? 0;
@endphp

<div class="space-y-6">

    <x-dashboard.encabezado :eyebrow="$alumno->carrera->nombre" :titulo="'Hola de nuevo, ' . $saludo"
        :subtitulo="'Código ' . $alumno->num_control . ($alumno->grado ? ' · ' . $alumno->grado . '.º semestre' : '')">
        {{-- Siguiente paso --}}
        <div class="mt-5 flex max-w-2xl items-start gap-3 rounded-xl bg-white/10 p-4 ring-1 ring-inset ring-white/15 backdrop-blur-sm">
            <span class="relative mt-1 flex size-2.5 shrink-0"><span class="absolute inline-flex size-full animate-ping rounded-full bg-oro-300 opacity-75 motion-reduce:hidden"></span><span class="relative inline-flex size-2.5 rounded-full bg-oro-400"></span></span>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-oro-300">Tu siguiente paso</p>
                <p class="mt-1 text-sm leading-relaxed text-white sm:text-[15px]">{{ $paso }}</p>
            </div>
        </div>
        <x-slot:acciones>
            @if ($practica)
                <a href="{{ route('alumno.mis-documentos') }}" class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50">
                    <x-dashboard.icono nombre="subir" clase="size-4" /> Subir reporte
                </a>
                <a href="{{ route('alumno.mis-practicas') }}" class="boton border border-white/25 text-white hover:bg-white/10">Mi práctica</a>
            @else
                <a href="{{ route('alumno.oferta-programas') }}" class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50">
                    <x-dashboard.icono nombre="buscar" clase="size-4" /> Explorar programas
                </a>
                <a href="{{ route('alumno.mis-documentos') }}" class="boton border border-white/25 text-white hover:bg-white/10">Mis documentos</a>
            @endif
        </x-slot:acciones>
    </x-dashboard.encabezado>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Progreso de horas --}}
            <section class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs sm:p-7" aria-labelledby="titulo-progreso">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 id="titulo-progreso" class="text-xs font-semibold uppercase tracking-[0.14em] text-oro-700">Progreso de tus horas</h2>
                        @if ($kpis)
                            <p class="mt-2 flex items-baseline gap-2">
                                <span class="font-display text-5xl font-extrabold tabular-nums tracking-tight text-udg-900">{{ $kpis['horas'] }}</span>
                                <span class="text-lg font-medium text-gray-500">/ {{ $kpis['requeridas'] }} h</span>
                            </p>
                            <p class="mt-1 text-sm text-gray-600">
                                @if ($kpis['horas'] >= $kpis['requeridas'])
                                    ¡Completaste las horas que pide tu carrera!
                                @else
                                    Te faltan <strong class="font-semibold text-gray-900">{{ $kpis['requeridas'] - $kpis['horas'] }} horas</strong> validadas.
                                @endif
                            </p>
                        @else
                            <p class="mt-2 font-display text-2xl font-bold text-udg-900">Aún no inicias tu práctica</p>
                            <p class="mt-1 text-sm text-gray-600">Tu carrera pide <strong class="font-semibold text-gray-900">{{ $alumno->carrera->horas_requeridas }} horas</strong>. El contador arranca cuando se active tu práctica.</p>
                        @endif
                    </div>
                    <div class="hidden size-20 shrink-0 items-center justify-center rounded-full bg-udg-50 ring-4 ring-oro-100 min-[420px]:flex" aria-hidden="true">
                        <span class="font-display text-xl font-bold tabular-nums text-udg-900">{{ $pct }}%</span>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="relative h-4 overflow-hidden rounded-full bg-gray-100 ring-1 ring-inset ring-gray-200"
                        role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Horas validadas">
                        <div class="h-full rounded-full bg-gradient-to-r from-udg-900 via-udg-700 to-oro-400 transition-[width] duration-1000 ease-out motion-reduce:transition-none"
                            x-data="{ ancho: 0 }" x-init="requestAnimationFrame(() => ancho = {{ $pct }})" :style="`width: ${ancho}%`" style="width: {{ $pct }}%"></div>
                        @foreach ([25, 50, 75] as $marca)
                            <span class="absolute inset-y-0 w-px bg-white/70" style="left: {{ $marca }}%" aria-hidden="true"></span>
                        @endforeach
                    </div>
                    <div class="mt-1.5 flex justify-between text-[11px] font-medium text-gray-400" aria-hidden="true">
                        <span>0</span><span>25%</span><span>50%</span><span>75%</span><span>Meta</span>
                    </div>
                </div>

                @if ($kpis)
                    <dl class="mt-6 grid grid-cols-3 gap-3 border-t border-gray-100 pt-5">
                        <div>
                            <dt class="text-xs text-gray-500">Reportes validados</dt>
                            <dd class="mt-1 font-display text-xl font-bold tabular-nums text-gray-900">{{ $kpis['validados'] }}<span class="text-sm font-medium text-gray-400"> / {{ $kpis['reportes_total'] }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Por corregir o entregar</dt>
                            <dd @class(['mt-1 font-display text-xl font-bold tabular-nums', 'text-rose-600' => $kpis['pendientes'], 'text-gray-900' => !$kpis['pendientes']])>{{ $kpis['pendientes'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Días restantes</dt>
                            <dd class="mt-1 font-display text-xl font-bold tabular-nums text-gray-900">{{ $kpis['dias_restantes'] }}</dd>
                        </div>
                    </dl>
                @endif
            </section>

            {{-- Pasos del trámite --}}
            <x-dashboard.panel titulo="Tu trámite paso a paso" icono="check" descripcion="Así avanza tu práctica profesional, del registro a la acreditación.">
                <ol class="grid grid-cols-1 gap-0 sm:grid-cols-3 sm:gap-y-6 lg:grid-cols-6">
                    @foreach ($pasos as $i => [$titulo, $ayuda])
                        @php $hecho = $i < $etapa; $actual = $i === $etapa; @endphp
                        <li class="relative flex gap-3 pb-6 last:pb-0 sm:flex-col sm:items-center sm:gap-2 sm:pb-0 sm:text-center">
                            {{-- Conector --}}
                            @if (!$loop->last)
                                {{-- vertical en celular, horizontal cuando los 6 pasos caben en una fila --}}
                                <span class="absolute left-[15px] top-8 h-[calc(100%-2rem)] w-0.5 sm:hidden {{ $hecho ? 'bg-udg-800' : 'bg-gray-200' }}" aria-hidden="true"></span>
                                <span class="absolute left-[calc(50%+1.25rem)] top-4 hidden h-0.5 w-[calc(100%-2.5rem)] lg:block {{ $hecho ? 'bg-udg-800' : 'bg-gray-200' }}" aria-hidden="true"></span>
                            @endif
                            <span @class([
                                'relative z-1 flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition',
                                'bg-udg-800 text-white' => $hecho,
                                'bg-oro-400 text-udg-950 ring-4 ring-oro-100' => $actual,
                                'bg-gray-100 text-gray-400 ring-1 ring-inset ring-gray-200' => !$hecho && !$actual,
                            ])>
                                @if ($hecho)<x-dashboard.icono nombre="check" clase="size-4" />@else{{ $i + 1 }}@endif
                            </span>
                            <div class="min-w-0 pt-1 sm:pt-0">
                                <p @class(['text-sm leading-snug', 'font-semibold text-gray-900' => $actual, 'font-medium text-gray-700' => $hecho, 'text-gray-400' => !$hecho && !$actual])>
                                    {{ $titulo }}@if ($actual)<span class="sr-only"> (paso actual)</span>@endif
                                </p>
                                <p class="mt-0.5 text-xs leading-snug text-gray-500">{{ $ayuda }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-dashboard.panel>

            {{-- Accesos rápidos --}}
            <section aria-labelledby="titulo-accesos">
                <h2 id="titulo-accesos" class="mb-3 font-display text-base font-bold text-gray-900">Accesos rápidos</h2>
                <div class="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 lg:grid-cols-3">
                    @php
                        $accesos = [
                            ['Convenios vigentes', 'Organizaciones donde puedes hacer prácticas externas', route('convenios-vigentes'), 'vigente', 'menta'],
                            $practica
                                ? ['Mi práctica', ($empresa?->razon_social ?? 'Proyecto interno') . ' · ' . $programa->titulo, route('alumno.mis-practicas'), 'empresa', 'cielo']
                                : ['Oferta de programas', 'Vacantes y proyectos para tu carrera', route('alumno.oferta-programas'), 'buscar', 'cielo'],
                            ['Mis documentos', $practica ? 'Reportes firmados y carta de término' : 'Constancia IMSS, kardex y cartas', route('alumno.mis-documentos'), 'documento', 'lavanda'],
                        ];
                    @endphp
                    @php
                        $tonosAcceso = [
                            'menta' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
                            'cielo' => 'bg-sky-50 text-sky-700 ring-sky-100',
                            'lavanda' => 'bg-violet-50 text-violet-700 ring-violet-100',
                        ];
                    @endphp
                    @foreach ($accesos as $i => [$titulo, $texto, $url, $icono, $tono])
                        <a href="{{ $url }}" style="animation-delay: {{ $i * 60 }}ms"
                            class="group flex animate-entrada items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs transition duration-200 hover:-translate-y-1 hover:border-udg-200 hover:shadow-theme-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:animate-none motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset {{ $tonosAcceso[$tono] }}">
                                <x-dashboard.icono :nombre="$icono" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2 font-semibold text-gray-900">
                                    {{ $titulo }}
                                    <x-dashboard.icono nombre="flecha" clase="size-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-udg-800" />
                                </span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-gray-500">{{ $texto }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Columna lateral --}}
        <aside class="space-y-6" aria-label="Contacto y avisos">
            <x-alumno.tu-coordinacion :carrera="$alumno->carrera" />

            @unless ($alumno->yaAcreditado())
                <x-ui.aviso tipo="advertencia" titulo="Agenda la materia en SIIAU">
                    Inscribe la asignatura de Prácticas Profesionales en SIIAU dentro del calendario escolar oficial. Sin ella, Control Escolar no puede asentar tus créditos al terminar.
                    Al postularte te pediremos confirmarlo.
                </x-ui.aviso>
            @endunless

            <x-dashboard.panel titulo="Avisos" icono="alerta">
                <div class="space-y-3">
                    @forelse ($alertas as $a)
                        <x-ui.aviso :tipo="$a['tipo'] === 'danger' ? 'error' : 'info'" :titulo="$a['titulo']">{{ $a['texto'] }}</x-ui.aviso>
                    @empty
                        <x-ui.aviso :tipo="$imssOk ? 'exito' : 'advertencia'" :titulo="$imssOk ? 'Seguro IMSS verificado' : 'Sube tu constancia IMSS'">
                            {{ $imssOk ? 'Cumples el requisito de seguro facultativo.' : 'Es requisito para postularte y activar tu práctica.' }}
                        </x-ui.aviso>
                    @endforelse
                </div>
            </x-dashboard.panel>

            <section class="relative overflow-hidden rounded-2xl border border-oro-100 bg-gradient-to-br from-oro-50 to-white p-5 shadow-theme-xs">
                <span class="text-3xl" aria-hidden="true">{{ $motivacion['icono'] }}</span>
                <h2 class="mt-2 font-display text-base font-bold text-udg-900">{{ $motivacion['titulo'] }}</h2>
                <p class="mt-1 text-sm leading-relaxed text-gray-600">{{ $motivacion['texto'] }}</p>
                @if ($kpis)
                    <p class="mt-3 inline-flex rounded-full bg-udg-900 px-3 py-1 text-xs font-semibold text-white">{{ $kpis['pct'] }}% completado</p>
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection
