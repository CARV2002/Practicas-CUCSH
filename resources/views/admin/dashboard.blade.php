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

    <x-dashboard.encabezado eyebrow="Vinculación · Convenios"
        :titulo="'Hola, ' . $nombre"
        :subtitulo="ucfirst(now()->translatedFormat('l j \d\e F')) . ' · ' . $kpis['en_tramite'] . ' ' . ($kpis['en_tramite'] === 1 ? 'convenio en trámite' : 'convenios en trámite') . ($kpis['excedidas'] ? ', ' . $kpis['excedidas'] . ' ' . ($kpis['excedidas'] === 1 ? 'etapa excede' : 'etapas exceden') . ' su plazo.' : '.')">
        <x-slot:acciones>
            <a href="{{ route('admin.lista-convenios') }}" class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50">
                <x-dashboard.icono nombre="documento" clase="size-4" /> Bandeja de convenios
            </a>
            <a href="{{ route('admin.lista-dictamenes') }}" class="boton border border-white/25 text-white hover:bg-white/10">Dictámenes</a>
        </x-slot:acciones>
    </x-dashboard.encabezado>

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
                                        <p class="truncate text-sm font-semibold text-gray-900 group-hover:text-udg-900">{{ $s->convenio->empresa->razon_social }}</p>
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
        <x-dashboard.panel titulo="Convenios por vencer" icono="calendario" descripcion="Vigencias que terminan en los próximos 60 días."
            :enlace="route('admin.lista-convenios')" enlace-texto="Ver todos">
            @forelse ($convenios_por_vencer as $c)
                @php $dias = (int) now()->startOfDay()->diffInDays($c->fecha_fin_vigencia, false); @endphp
                <a href="{{ route('admin.convenios.show', $c) }}" class="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $c->empresa->razon_social }}</p>
                        <p class="text-xs text-gray-500">{{ $c->folio ?? 'Sin folio' }} · vence {{ $c->fecha_fin_vigencia->format('d/m/Y') }}</p>
                    </div>
                    <span @class([
                        'flex shrink-0 flex-col items-center rounded-xl px-2.5 py-1 text-center ring-1 ring-inset',
                        'bg-rose-50 text-rose-700 ring-rose-200' => $dias <= 30,
                        'bg-amber-50 text-amber-800 ring-amber-200' => $dias > 30,
                    ])>
                        <span class="font-display text-lg font-bold leading-none tabular-nums">{{ $dias }}</span>
                        <span class="text-[10px] font-semibold uppercase">días</span>
                    </span>
                </a>
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
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $c->empresa->razon_social }}</p>
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
