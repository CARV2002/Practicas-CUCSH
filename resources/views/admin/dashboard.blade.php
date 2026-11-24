@extends('layouts.app')

@section('content')
@php
    $totalConvenios = max(1, $convenios_por_estado->sum('total'));
    $barraFamilia = [
        'cielo' => 'bg-sky-400', 'lavanda' => 'bg-violet-400', 'indigo' => 'bg-indigo-400', 'ambar' => 'bg-amber-400',
        'menta' => 'bg-emerald-500', 'rosa' => 'bg-rose-400', 'gris' => 'bg-slate-300', 'piedra' => 'bg-stone-300', 'turquesa' => 'bg-teal-500',
    ];
@endphp

<div class="space-y-6">

    <x-dashboard.encabezado :eyebrow="$esAdministrador ? 'Coordinación de Servicios Académicos · Supervisión' : 'Vinculación · Convenios'"
        :titulo="'Hola, ' . $nombre"
        :subtitulo="ucfirst(now()->translatedFormat('l j \d\e F')) . ' · '
            . ($totalPendientes ? 'Tienes ' . $totalPendientes . ' ' . ($totalPendientes === 1 ? 'pendiente' : 'pendientes') . ' por atender (detalle en la campana).' : 'Todo al día: no tienes pendientes por atender.')
            . ' ' . $kpis['en_tramite'] . ' ' . ($kpis['en_tramite'] === 1 ? 'convenio en trámite' : 'convenios en trámite') . '.'">
        <x-slot:acciones>
            <a href="{{ route('admin.lista-convenios') }}" class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50">
                <x-dashboard.icono nombre="documento" clase="size-4" /> Bandeja de convenios
            </a>
            <a href="{{ route('admin.lista-dictamenes') }}" class="boton border border-white/25 text-white hover:bg-white/10">Dictámenes</a>
        </x-slot:acciones>
    </x-dashboard.encabezado>

    {{-- Panorama del CUCSH: solo para el administrador (supervisión, solo lectura) --}}
    @if ($panorama)
        {{-- La barra mide los alumnos de cada división frente a la más grande; el tramo dorado, cuántos están en práctica --}}
        @php $maxAlumnos = max(1, $panorama['divisiones']->max('alumnos')); @endphp
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3" aria-label="Panorama del CUCSH">
            <x-dashboard.panel titulo="Prácticas en todo el CUCSH" icono="informe" descripcion="Solo lectura: lo académico lo opera cada coordinación de carrera." class="xl:col-span-2">
                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    @foreach ([
                        ['Alumnos registrados', $panorama['alumnos'], 'text-gray-900'],
                        ['En curso', $panorama['activas'], 'text-udg-900'],
                        ['En cierre', $panorama['en_cierre'], 'text-teal-700'],
                        ['Acreditadas', $panorama['acreditadas'], 'text-emerald-700'],
                        ['Bajas', $panorama['bajas'], 'text-rose-700'],
                    ] as [$texto, $valor, $color])
                        <div class="rounded-xl bg-gray-50 p-3.5 ring-1 ring-inset ring-gray-100">
                            <dt class="text-xs text-gray-500">{{ $texto }}</dt>
                            <dd class="mt-1 font-display text-2xl font-bold tabular-nums {{ $color }}">{{ number_format($valor) }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-6 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h3 class="text-sm font-semibold text-gray-900">Alumnos en práctica por división</h3>
                    <p class="flex items-center gap-3 text-xs text-gray-500" aria-hidden="true">
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-oro-400"></span> En práctica</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-udg-200"></span> Registrados</span>
                    </p>
                </div>
                <ul class="mt-3 space-y-3.5">
                    @forelse ($panorama['divisiones'] as $d)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0 break-words font-medium text-gray-800">{{ $d->nombre }}</span>
                                <span class="shrink-0 text-xs text-gray-500"><span class="font-display text-base font-bold tabular-nums text-gray-900">{{ $d->activas }}</span> de {{ $d->alumnos }} {{ $d->alumnos === 1 ? 'alumno' : 'alumnos' }} · {{ $d->carreras }} {{ $d->carreras === 1 ? 'carrera' : 'carreras' }}</span>
                            </div>
                            <div class="mt-1.5 h-2.5 rounded-full bg-gray-50 ring-1 ring-inset ring-gray-100" role="img" aria-label="{{ $d->activas }} de {{ $d->alumnos }} alumnos en práctica">
                                <div class="flex h-full overflow-hidden rounded-full bg-udg-200" style="width: {{ $d->alumnos ? max(3, round($d->alumnos / $maxAlumnos * 100)) : 0 }}%">
                                    <div class="h-full bg-oro-400" style="width: {{ $d->alumnos ? round($d->activas / $d->alumnos * 100) : 0 }}%"></div>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li><p class="dato-vacio">Aún no hay divisiones en el catálogo.</p></li>
                    @endforelse
                </ul>
            </x-dashboard.panel>

            <x-dashboard.panel titulo="Coordinaciones en la plataforma" icono="usuarios" descripcion="Cada carrera necesita su cuenta de coordinación para operar.">
                @if ($panorama['sin_coordinacion']->isEmpty())
                    <p class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3.5 py-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-100"><x-dashboard.icono nombre="check" clase="size-4 shrink-0" /> Todas las carreras tienen coordinación.</p>
                @else
                    <p class="text-sm text-gray-700"><span class="font-semibold text-rose-700">{{ $panorama['sin_coordinacion']->count() }} {{ $panorama['sin_coordinacion']->count() === 1 ? 'carrera no tiene' : 'carreras no tienen' }}</span> cuenta de coordinación: sus avisos te llegan a ti.</p>
                    <ul class="mt-3 max-h-56 space-y-1.5 overflow-y-auto pr-1">
                        @foreach ($panorama['sin_coordinacion'] as $c)
                            <li class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm ring-1 ring-inset ring-gray-100">
                                <span class="min-w-0 break-words text-gray-800">{{ $c->nombre }}</span>
                                <span class="shrink-0 text-xs text-gray-500">{{ $c->carrera_clave }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.lista-usuarios') }}" class="boton boton-primario mt-4 w-full"><x-dashboard.icono nombre="usuarios" clase="size-4" /> Crear cuentas de coordinación</a>
                @endif
            </x-dashboard.panel>
        </section>

        <h2 class="font-display text-lg font-bold text-gray-900">Convenios de Vinculación</h2>
    @endif

    {{-- KPIs del flujo de convenios: revisión → dictamen → jurídica → firmas → vigentes --}}
    <section aria-label="Convenios por etapa">
        <div class="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            @foreach ($etapas as $i => $e)
                @php
                    $n = $conteo[$e['estado']->value];
                    $tarde = $excedidasPorEstado[$e['estado']->value] ?? 0;
                    $detalle = match ($e['estado']) {
                        \App\Enums\ConvenioEstado::EnValidacionAcademica => $pendientes['dictamenes'] . ' ' . ($pendientes['dictamenes'] === 1 ? 'dictamen pendiente' : 'dictámenes pendientes') . ' de las carreras',
                        \App\Enums\ConvenioEstado::EnRevisionJuridica => $pendientes['juridica'] . ' en espera de dictamen jurídico',
                        \App\Enums\ConvenioEstado::EnFirmas => 'Empresa y Rectoría',
                        default => 'Valida el expediente de la empresa',
                    };
                @endphp
                <x-dashboard.kpi :href="route('admin.lista-convenios', ['estado' => $e['estado']->value])"
                    :etiqueta="$e['etiqueta']" :valor="$n" :icono="$e['icono']" :tono="$e['tono']" :orden="$i"
                    :detalle="$detalle" :alerta="$tarde ? $tarde . ' fuera de plazo' : null" accion="Ver convenios" />
            @endforeach

            <x-dashboard.kpi :href="route('admin.lista-convenios', ['estado' => 'formalizado'])"
                etiqueta="Convenios vigentes" :valor="$kpis['vigentes']" icono="vigente" tono="menta" :orden="4"
                :detalle="$kpis['empresas_con_convenio'] . ' de ' . $kpis['empresas'] . ' empresas con convenio'"
                :alerta="$kpis['por_vencer_30'] ? $kpis['por_vencer_30'] . ' vencen en 30 días' : null" accion="Ver convenios" />
        </div>
    </section>

    {{-- Papeles del trámite híbrido --}}
    @if ($fisicos['fuera'] > 0 || $fisicos['por_imprimir'] > 0)
        <a href="{{ route('admin.documentos-fisicos', ['vista' => $fisicos['vencidos'] > 0 ? 'vencidos' : 'fuera']) }}"
            @class([
                'group flex flex-col gap-3 rounded-2xl border p-4 transition hover:shadow-theme-sm sm:flex-row sm:items-center sm:justify-between sm:px-6',
                'border-rose-200 bg-rose-50/60' => $fisicos['vencidos'] > 0,
                'border-amber-200 bg-amber-50/50' => $fisicos['vencidos'] === 0,
            ])>
            <div class="flex items-center gap-3">
                <span @class(['flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset', 'bg-rose-100 text-rose-700 ring-rose-200' => $fisicos['vencidos'] > 0, 'bg-amber-100 text-amber-800 ring-amber-200' => $fisicos['vencidos'] === 0])>
                    <x-dashboard.icono nombre="firma" clase="size-5" />
                </span>
                <div>
                    <p class="font-semibold text-gray-900">
                        {{ $fisicos['fuera'] }} {{ $fisicos['fuera'] === 1 ? 'papel fuera' : 'papeles fuera' }} de la oficina
                        @if ($fisicos['vencidos'] > 0)<span class="text-rose-700">· {{ $fisicos['vencidos'] }} con regreso vencido</span>@endif
                    </p>
                    <p class="text-sm text-gray-600">{{ $fisicos['por_imprimir'] > 0 ? $fisicos['por_imprimir'] . ' juego(s) u oficio(s) por imprimir. ' : '' }}Revisa dónde está cada oficio, dictamen y juego firmado.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 text-sm font-semibold text-udg-900">Documentos físicos <x-dashboard.icono nombre="flecha" clase="size-4 transition-transform group-hover:translate-x-0.5" /></span>
        </a>
    @endif

    <div class="grid grid-cols-1 gap-6 2xl:grid-cols-3">

        {{-- Atención prioritaria --}}
        <x-dashboard.panel class="2xl:col-span-2" titulo="Atención prioritaria" icono="alerta"
            descripcion="Etapas abiertas ordenadas por urgencia: días hábiles transcurridos contra el máximo documentado."
            :enlace="route('admin.lista-convenios')" enlace-texto="Bandeja">
            @if ($prioritarios->isEmpty())
                <x-dashboard.vacio titulo="No hay etapas abiertas" texto="Cuando una empresa envíe su solicitud aparecerá aquí." />
            @else
                <ul class="-my-1 divide-y divide-gray-100">
                    @foreach ($prioritarios as $p)
                        @php
                            $s = $p->seguimiento;
                            [$tono, $barra, $texto] = match (true) {
                                $p->excede => ['bg-rose-50 text-rose-700 ring-rose-200', 'bg-rose-500', 'Fuera de plazo'],
                                $p->por_vencer => ['bg-amber-50 text-amber-800 ring-amber-200', 'bg-amber-400', 'Cerca del límite'],
                                default => ['bg-slate-50 text-slate-600 ring-slate-200', 'bg-udg-800', 'En tiempo'],
                            };
                        @endphp
                        <li>
                            <a href="{{ route('admin.convenios.show', $s->convenio) }}"
                                class="group -mx-2 flex flex-col gap-3 rounded-xl px-2 py-3.5 transition-colors hover:bg-gray-50 sm:flex-row sm:items-center">
                                <div class="flex min-w-0 flex-1 items-start gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-udg-50 font-display text-sm font-bold text-udg-900 ring-1 ring-inset ring-udg-100" aria-hidden="true">
                                        {{ mb_strtoupper(mb_substr($s->convenio->empresa->razon_social, 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-semibold text-gray-900 group-hover:text-udg-900">{{ $s->convenio->empresa->razon_social }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $s->etapa->label() }} · {{ $s->etapa->responsable() }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 sm:w-64 sm:shrink-0">
                                    <div class="flex-1">
                                        <div class="flex items-baseline justify-between text-xs">
                                            <span class="font-semibold tabular-nums text-gray-900">{{ $p->dias }} {{ $p->dias === 1 ? 'día hábil' : 'días hábiles' }}</span>
                                            @if ($p->max)<span class="text-gray-400">máx. {{ $p->max }}</span>@endif
                                        </div>
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-full rounded-full {{ $barra }}" style="width: {{ $p->avance ?? 15 }}%"></div>
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $tono }}">{{ $texto }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-dashboard.panel>

        {{-- Por vencer --}}
        <x-dashboard.panel titulo="Convenios por vencer" icono="calendario" descripcion="Vigencias que terminan en los próximos 60 días. Entra directo a la empresa para dar seguimiento a su renovación."
            :enlace="route('admin.lista-convenios', ['estado' => 'por_vencer'])" enlace-texto="Ver todos">
            @forelse ($convenios_por_vencer as $c)
                @php $dias = (int) now()->startOfDay()->diffInDays($c->fecha_fin_vigencia, false); @endphp
                <div class="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                    <div class="min-w-0">
                        <a href="{{ route('admin.empresas.show', $c->empresa) }}" class="block truncate text-sm font-semibold text-gray-900 hover:text-real-600 hover:underline" title="Ir al expediente de la empresa">{{ $c->empresa->razon_social }}</a>
                        <p class="text-xs text-gray-500">{{ $c->folio ?? 'Sin folio' }} · vence {{ $c->fecha_fin_vigencia->format('d/m/Y') }} · <a href="{{ route('admin.convenios.show', $c) }}" class="font-semibold text-real-600 hover:underline">Ver trámite</a></p>
                    </div>
                    <a href="{{ route('admin.empresas.show', $c->empresa) }}" aria-label="Ir a {{ $c->empresa->razon_social }}" @class([
                        'flex shrink-0 flex-col items-center rounded-xl px-2.5 py-1 text-center ring-1 ring-inset',
                        'bg-rose-50 text-rose-700 ring-rose-200' => $dias <= 30,
                        'bg-amber-50 text-amber-800 ring-amber-200' => $dias > 30,
                    ])>
                        <span class="font-display text-lg font-bold leading-none tabular-nums">{{ $dias }}</span>
                        <span class="text-[10px] font-semibold uppercase">días</span>
                    </a>
                </div>
            @empty
                <x-dashboard.vacio titulo="Sin vencimientos próximos" texto="Ningún convenio vence en los próximos 60 días." />
            @endforelse
        </x-dashboard.panel>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Distribución por estado --}}
        <x-dashboard.panel titulo="Convenios por estado" icono="informe" descripcion="Todos los convenios registrados en el sistema."
            :enlace="route('admin.informes')" enlace-texto="Informes">
            <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100" role="img"
                aria-label="Distribución: {{ $convenios_por_estado->map(fn ($r) => $r['estado']->label() . ' ' . $r['total'])->join(', ') }}">
                @foreach ($convenios_por_estado as $r)
                    @if ($r['total'] > 0)
                        <span class="h-full {{ $barraFamilia[$r['estado']->familia()] ?? 'bg-gray-300' }} first:rounded-l-full last:rounded-r-full" style="width: {{ $r['total'] / $totalConvenios * 100 }}%"></span>
                    @endif
                @endforeach
            </div>
            <ul class="mt-5 grid grid-cols-1 gap-x-6 gap-y-2.5 sm:grid-cols-2">
                @foreach ($convenios_por_estado as $r)
                    <li>
                        <a href="{{ route('admin.lista-convenios', ['estado' => $r['estado']->value]) }}" class="-mx-2 flex items-center justify-between gap-2 rounded-lg px-2 py-1 text-sm transition-colors hover:bg-gray-50">
                            <span class="flex items-center gap-2 text-gray-700">
                                <span class="size-2.5 rounded-full {{ $barraFamilia[$r['estado']->familia()] ?? 'bg-gray-300' }}" aria-hidden="true"></span>
                                {{ $r['estado']->label() }}
                            </span>
                            <span class="font-semibold tabular-nums text-gray-900">{{ $r['total'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-dashboard.panel>

        {{-- Recientes --}}
        <x-dashboard.panel titulo="Solicitudes recientes" icono="empresa" descripcion="Últimos convenios registrados."
            :enlace="route('admin.lista-convenios')" enlace-texto="Ver todos">
            @forelse ($convenios_recientes as $c)
                <a href="{{ route('admin.convenios.show', $c) }}" class="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-semibold text-gray-900">{{ $c->empresa->razon_social }}</p>
                        <p class="text-xs text-gray-500">Recibido {{ $c->fecha_recepcion->format('d/m/Y') }}</p>
                    </div>
                    <x-ui.estatus :estado="$c->estado" class="shrink-0" />
                </a>
            @empty
                <x-dashboard.vacio titulo="Aún no hay convenios" />
            @endforelse
        </x-dashboard.panel>
    </div>
</div>
@endsection
