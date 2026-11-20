@extends('layouts.app')

@section('content')
@php
    use App\Enums\EstadoDocumentoFisico as EF;

    $vistas = [
        ['valor' => 'activos', 'texto' => 'En seguimiento', 'conteo' => $conteos['activos']],
        ['valor' => 'fuera', 'texto' => 'Fuera de la oficina', 'conteo' => $conteos['fuera'], 'familia' => 'ambar'],
        ['valor' => 'vencidos', 'texto' => 'Regreso vencido', 'conteo' => $conteos['vencidos'], 'familia' => 'rosa'],
        ['valor' => 'casa', 'texto' => 'En mi oficina', 'conteo' => $conteos['casa'], 'familia' => 'cielo'],
        ['valor' => 'concluidos', 'texto' => 'Concluidos', 'conteo' => $conteos['concluidos'], 'familia' => 'piedra'],
    ];
    $fuera = EF::fuera();
    $vacios = [
        'activos' => ['Todo en orden', 'No hay papeles en seguimiento. Aparecen solos cuando un convenio se turna a las carreras.'],
        'fuera' => ['Ningún papel fuera de la oficina', 'Todo lo que salió ya regresó a Vinculación.'],
        'vencidos' => ['Sin regresos vencidos', 'Ningún papel rebasó su fecha de regreso.'],
        'casa' => ['Nada pendiente en tu oficina', 'No hay papeles esperando a ser enviados o archivados.'],
        'concluidos' => ['Aún no hay papeles concluidos', 'Los juegos entregados y los oficios archivados aparecerán aquí.'],
    ];
@endphp

<div class="space-y-6">
    <x-pagina.encabezado titulo="Documentos físicos" descripcion="Dónde está cada papel del trámite: oficios de turno, dictámenes jurídicos y los 3 juegos originales del convenio, con su fecha de regreso." />

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="No se guardó el movimiento"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-dashboard.kpi :href="route('admin.documentos-fisicos', ['vista' => 'fuera'])" etiqueta="Fuera de la oficina" :valor="$conteos['fuera']" icono="enviar" tono="ambar" detalle="Con la empresa, Jurídica, Rectoría…" :orden="0" />
        <x-dashboard.kpi :href="route('admin.documentos-fisicos', ['vista' => 'vencidos'])" etiqueta="Regreso vencido" :valor="$conteos['vencidos']" icono="alerta" tono="rosa" :alerta="$conteos['vencidos'] > 0 ? 'Requiere seguimiento' : null" detalle="Pasó su fecha de regreso" :orden="1" />
        <x-dashboard.kpi :href="route('admin.documentos-fisicos', ['vista' => 'casa'])" etiqueta="En mi oficina" :valor="$conteos['casa']" icono="documento" tono="cielo" detalle="Por enviar, firmar o archivar" :orden="2" />
        <x-dashboard.kpi :href="route('admin.documentos-fisicos', ['vista' => 'concluidos'])" etiqueta="Concluidos" :valor="$conteos['concluidos']" icono="check" tono="menta" detalle="Entregados o archivados" :orden="3" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-tabla.marco titulo="Papeles por convenio" :total="$grupos->flatten()->count()" class="xl:col-span-2">
            <x-slot:herramientas>
                <x-tabla.filtros-estado :opciones="$vistas" parametro="vista" :actual="$vista" etiqueta="Filtrar documentos" />
                <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                    <input type="hidden" name="vista" value="{{ $vista }}">
                    <div class="relative flex-1 sm:max-w-sm">
                        <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <label for="q" class="sr-only">Buscar por organización o folio</label>
                        <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por organización o folio" class="campo-filtro pl-9 sm:w-full">
                    </div>
                    <button class="boton boton-secundario">Buscar</button>
                    @if (request('q'))<a href="{{ route('admin.documentos-fisicos', ['vista' => $vista]) }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>@endif
                </form>
            </x-slot:herramientas>

            <div class="divide-y divide-gray-100">
                @forelse ($grupos as $docs)
                    @php
                        $convenio = $docs->first()->convenio;
                        $detalle = $convenio->estadoDetallado();
                        $conEmpresa = $docs->where('estado', EF::ConEmpresa)->count();
                    @endphp
                    <section class="px-5 py-5 sm:px-6" aria-label="{{ $convenio->empresa->razon_social }}">
                        <header class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-udg-50 font-display text-sm font-bold text-udg-900 ring-1 ring-inset ring-udg-100" aria-hidden="true">{{ mb_strtoupper(mb_substr($convenio->empresa->razon_social, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.convenios.show', $convenio) }}" class="font-semibold text-gray-900 underline-offset-2 hover:text-udg-900 hover:underline">{{ $convenio->empresa->razon_social }}</a>
                                    <p class="text-xs text-gray-500">{{ $convenio->folio ? 'Folio ' . $convenio->folio : 'Sin folio aún' }} · {{ $detalle['texto'] }}@if ($detalle['detalle']) — {{ $detalle['detalle'] }}@endif</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($conEmpresa > 0)
                                    <x-ui.boton-icono :accion="route('admin.convenios.fisicos.recordar', $convenio)" icono="reloj" etiqueta="Recordar a la empresa" tono="neutro" con-texto
                                        confirmar="¿Enviar a la empresa un recordatorio por correo para devolver los juegos firmados?" />
                                @endif
                                <x-ui.boton-icono :href="route('admin.convenios.show', $convenio)" icono="flecha" etiqueta="Trámite" tono="primario" con-texto />
                            </div>
                        </header>

                        <ul class="mt-4 space-y-2">
                            @foreach ($docs as $d)
                                @php
                                    $camino = array_values(array_filter($d->tipo->estados(), fn ($e) => !in_array($e, [EF::ConObservaciones, EF::Extraviado], true)));
                                    $pos = array_search($d->estado, $camino, true);
                                    $vencido = $d->vencido();
                                    $porVencer = $d->porVencer();
                                    $opcionesJs = collect($d->tipo->estados())->mapWithKeys(fn ($e) => [$e->value => $e->plazoHabiles()]);
                                @endphp
                                <li x-data="{ editar: {{ $errors->any() && old('doc') == $d->id ? 'true' : 'false' }}, historial: false, estado: '{{ old('doc') == $d->id ? old('estado') : $d->estado->value }}', plazos: {{ $opcionesJs->toJson() }} }"
                                    @class([
                                        '@container overflow-hidden rounded-xl border transition-colors',
                                        'border-rose-300 bg-rose-50/50 shadow-[inset_3px_0_0_var(--color-rose-500)]' => $vencido,
                                        'border-amber-200 bg-white shadow-[inset_3px_0_0_var(--color-amber-400)]' => $porVencer,
                                        'bg-white' => !$vencido && !$porVencer,
                                        'border-gray-200 hover:border-gray-300' => !$vencido && !$porVencer,
                                    ])>
                                    <div class="flex flex-col gap-3 p-3.5 sm:p-4 @4xl:flex-row @4xl:items-center">
                                        <div class="flex min-w-0 flex-1 items-start gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ \App\Support\Estatus::chip($d->estado->familia()) }}">
                                                <x-dashboard.icono :nombre="$d->tipo->icono()" clase="size-[18px]" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900">{{ $d->nombre() }}</p>
                                                <p class="text-xs text-gray-500">{{ $d->juego ? 'Destino: ' . ($d->destino ?: 'sin definir') : $d->tipo->descripcion() }}</p>
                                                {{-- Recorrido del papel --}}
                                                <ol class="mt-2 flex items-center gap-1" aria-label="Recorrido: paso {{ $pos === false ? '—' : $pos + 1 }} de {{ count($camino) }}">
                                                    @foreach ($camino as $i => $paso)
                                                        <li title="{{ $paso->label() }}" @class([
                                                            'h-1.5 rounded-full transition-all',
                                                            'w-6 bg-udg-800' => $pos !== false && $i < $pos,
                                                            'w-8 ' . \App\Support\Estatus::punto($d->estado->familia()) => $pos !== false && $i === $pos,
                                                            'w-4 bg-gray-200' => $pos === false || $i > $pos,
                                                        ])></li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs @lg:grid-cols-[minmax(0,1.4fr)_1fr_1fr] @4xl:flex @4xl:shrink-0 @4xl:items-center @4xl:gap-5">
                                            <div class="col-span-2 min-w-0 @lg:col-span-1 @4xl:w-48">
                                                <x-ui.estatus :estado="$d->estado" />
                                                <p class="mt-1 text-gray-500">Lo tiene: <span class="font-medium text-gray-700">{{ $d->estado->custodio() }}</span></p>
                                            </div>
                                            <div class="@4xl:w-28">
                                                <p class="text-gray-400">Desde</p>
                                                <p class="font-medium tabular-nums text-gray-800">{{ $d->fecha_estado->format('d/m/Y') }}</p>
                                                <p class="text-gray-500">{{ $d->diasEnEstado() === 1 ? '1 día hábil' : $d->diasEnEstado() . ' días hábiles' }}</p>
                                            </div>
                                            <div class="@4xl:w-28">
                                                <p class="text-gray-400">Regresa</p>
                                                @if ($d->fecha_compromiso && $d->estado->fueraDeOficina())
                                                    <p @class(['font-semibold tabular-nums', 'text-rose-600' => $vencido, 'text-amber-700' => $porVencer, 'text-gray-800' => !$vencido && !$porVencer])>{{ $d->fecha_compromiso->format('d/m/Y') }}</p>
                                                    <p @class(['text-rose-600 font-medium' => $vencido, 'text-amber-700' => $porVencer, 'text-gray-500' => !$vencido && !$porVencer])>{{ $vencido ? 'Vencido' : ($porVencer ? 'Muy pronto' : 'A tiempo') }}</p>
                                                @else
                                                    <p class="dato-vacio">{{ $d->estado->concluido() ? 'Concluido' : 'Está en tu oficina' }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1.5 @4xl:shrink-0">
                                            <button type="button" class="boton boton-chico boton-secundario" x-on:click="editar = !editar" :aria-expanded="editar.toString()">
                                                <x-dashboard.icono nombre="editar" clase="size-3.5" /> Mover
                                            </button>
                                            <button type="button" class="boton boton-chico text-gray-500 hover:bg-gray-100 hover:text-gray-800" x-on:click="historial = !historial" :aria-expanded="historial.toString()">
                                                <x-dashboard.icono nombre="reloj" clase="size-3.5" /> <span class="sr-only sm:not-sr-only">Historial</span>
                                            </button>
                                        </div>
                                    </div>

                                    @if ($d->notas)<p class="px-4 pb-3 text-xs text-gray-600">{{ $d->notas }}</p>@endif

                                    {{-- Mover --}}
                                    <form x-show="editar" x-transition.opacity x-cloak method="POST" action="{{ route('admin.documentos-fisicos.mover', $d) }}"
                                        class="grid grid-cols-1 gap-3 border-t border-gray-100 bg-gray-50/70 px-4 py-4 sm:grid-cols-2 lg:grid-cols-4">
                                        @csrf
                                        <input type="hidden" name="doc" value="{{ $d->id }}">
                                        <div class="sm:col-span-2 lg:col-span-1">
                                            <label class="etiqueta" for="estado-{{ $d->id }}">Nuevo estado</label>
                                            <select id="estado-{{ $d->id }}" name="estado" x-model="estado" class="campo h-10">
                                                @foreach ($d->tipo->estados() as $e)
                                                    <option value="{{ $e->value }}">{{ $e->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="etiqueta" for="fecha-{{ $d->id }}">Fecha del movimiento</label>
                                            <input id="fecha-{{ $d->id }}" type="date" name="fecha" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="campo h-10">
                                        </div>
                                        <div x-show="plazos[estado]">
                                            <label class="etiqueta" for="comp-{{ $d->id }}">Debe regresar el</label>
                                            <input id="comp-{{ $d->id }}" type="date" name="fecha_compromiso" class="campo h-10" :disabled="!plazos[estado]">
                                            <p class="ayuda" x-text="'Vacío = ' + plazos[estado] + ' días hábiles'"></p>
                                        </div>
                                        @if ($d->juego)
                                            <div>
                                                <label class="etiqueta" for="dest-{{ $d->id }}">Destino del juego</label>
                                                <input id="dest-{{ $d->id }}" type="text" name="destino" value="{{ $d->destino }}" maxlength="255" class="campo h-10">
                                            </div>
                                        @endif
                                        <div class="sm:col-span-2 lg:col-span-4">
                                            <label class="etiqueta" for="nota-{{ $d->id }}">Nota <span class="font-normal text-gray-400">(obligatoria si regresa con observaciones o se extravía)</span></label>
                                            <input id="nota-{{ $d->id }}" type="text" name="nota" maxlength="1000" placeholder="Ej. Lo recogió el representante legal; acuse con sello de Secretaría" class="campo h-10">
                                        </div>
                                        <div class="flex justify-end gap-2 sm:col-span-2 lg:col-span-4">
                                            <button type="button" class="boton boton-chico text-gray-500 hover:bg-gray-100" x-on:click="editar = false">Cancelar</button>
                                            <button class="boton boton-chico boton-primario"><x-dashboard.icono nombre="check" clase="size-3.5" /> Guardar movimiento</button>
                                        </div>
                                    </form>

                                    {{-- Historial --}}
                                    <div x-show="historial" x-transition.opacity x-cloak class="border-t border-gray-100 px-4 py-3">
                                        <ol class="relative space-y-3 border-l border-gray-200 pl-4">
                                            @forelse ($d->movimientos as $m)
                                                <li class="relative">
                                                    <span class="absolute -left-[21px] top-1 size-2.5 rounded-full ring-2 ring-white {{ \App\Support\Estatus::punto($m->estado->familia()) }}" aria-hidden="true"></span>
                                                    <p class="text-xs font-semibold text-gray-800">{{ $m->estado->label() }} <span class="font-normal text-gray-400">· {{ $m->fecha->format('d/m/Y') }}{{ $m->registrador?->user ? ' · ' . $m->registrador->user->name : '' }}</span></p>
                                                    @if ($m->nota)<p class="text-xs text-gray-600">{{ $m->nota }}</p>@endif
                                                </li>
                                            @empty
                                                <li class="dato-vacio">Sin movimientos registrados</li>
                                            @endforelse
                                        </ol>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @empty
                    <div class="px-6 py-4">
                        <x-dashboard.vacio icono="documento" :titulo="request('q') ? 'Sin resultados para «' . request('q') . '»' : $vacios[$vista][0] ?? $vacios['activos'][0]" :texto="request('q') ? 'Prueba con otro nombre o folio.' : $vacios[$vista][1] ?? $vacios['activos'][1]" />
                    </div>
                @endforelse
            </div>
        </x-tabla.marco>

        <aside class="space-y-6">
            <x-dashboard.panel titulo="Próximos regresos" icono="calendario" descripcion="Papeles fuera de la oficina, del más urgente al más lejano.">
                @forelse ($proximos as $p)
                    <a href="{{ route('admin.convenios.show', $p->convenio) }}" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-gray-50">
                        <span @class([
                            'flex w-12 shrink-0 flex-col items-center rounded-lg py-1 ring-1 ring-inset',
                            'bg-rose-50 text-rose-700 ring-rose-100' => $p->vencido(),
                            'bg-amber-50 text-amber-800 ring-amber-100' => $p->porVencer(),
                            'bg-gray-50 text-gray-700 ring-gray-200' => !$p->vencido() && !$p->porVencer(),
                        ])>
                            <span class="font-display text-base font-bold leading-none tabular-nums">{{ $p->fecha_compromiso->format('d') }}</span>
                            <span class="text-[10px] font-medium uppercase">{{ $p->fecha_compromiso->translatedFormat('M') }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-gray-900">{{ $p->convenio->empresa->razon_social }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ $p->nombre() }} · {{ $p->estado->custodio() }}</span>
                        </span>
                    </a>
                @empty
                    <x-dashboard.vacio icono="check" titulo="Nada fuera de la oficina" texto="Cuando envíes un papel con fecha de regreso, aparecerá aquí." />
                @endforelse

                {{-- Calendario de regresos --}}
                <div x-data="calendarioRegresos(@js($calendario))" class="mt-3 border-t border-gray-100 pt-3">
                    <button type="button" x-on:click="abrir()" class="boton boton-secundario w-full">
                        <x-dashboard.icono nombre="calendario" clase="size-4" /> Ver calendario
                        <span class="rounded-full bg-udg-50 px-1.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100">{{ $calendario->count() }}</span>
                    </button>

                    {{-- Se monta en <body> para quedar por encima de la barra lateral --}}
                    <template x-teleport="body">
                    <div x-show="abierto" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="abierto = false"
                        class="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto p-3 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-calendario">
                        <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="abierto = false"></div>
                        <div class="relative w-full max-w-4xl animate-entrada overflow-hidden rounded-2xl bg-white shadow-theme-xl motion-reduce:animate-none" x-on:click.stop>
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                                <div>
                                    <h3 id="titulo-calendario" class="font-display text-lg font-bold text-gray-900">Calendario de regresos</h3>
                                    <p class="text-xs text-gray-500">Fecha en que cada papel debe volver a Vinculación.</p>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" class="boton boton-chico boton-secundario" x-on:click="mover(-1)" aria-label="Mes anterior">‹</button>
                                    <p class="w-36 text-center text-sm font-semibold capitalize text-gray-900" x-text="tituloMes"></p>
                                    <button type="button" class="boton boton-chico boton-secundario" x-on:click="mover(1)" aria-label="Mes siguiente">›</button>
                                    <button type="button" class="boton boton-chico text-gray-600 hover:bg-gray-100" x-on:click="hoy()">Hoy</button>
                                    <button type="button" x-on:click="abierto = false" class="ml-1 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Cerrar"><x-dashboard.icono nombre="equis" clase="size-4" /></button>
                                </div>
                            </div>

                            <div class="grid gap-0 lg:grid-cols-[1fr_17rem]">
                                <div class="p-3 sm:p-4">
                                    <div class="grid grid-cols-7 text-center text-[11px] font-semibold text-gray-500">
                                        <template x-for="d in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']"><div class="py-1" x-text="d"></div></template>
                                    </div>
                                    <div class="grid grid-cols-7 gap-1">
                                        <template x-for="celda in celdas" :key="celda.clave">
                                            <button type="button" x-on:click="celda.fecha && (seleccion = celda.fecha)"
                                                :disabled="!celda.fecha"
                                                class="flex min-h-14 flex-col items-start rounded-lg border p-1.5 text-left transition sm:min-h-20"
                                                :class="[
                                                    !celda.fecha ? 'border-transparent' : 'border-gray-100 hover:border-udg-200',
                                                    celda.fecha === seleccion ? '!border-udg-800 ring-2 ring-udg-800/15' : '',
                                                    celda.fecha === hoyIso ? 'bg-udg-50/60' : 'bg-white',
                                                ]">
                                                <span class="text-xs font-semibold tabular-nums" :class="celda.fecha === hoyIso ? 'text-udg-900' : 'text-gray-700'" x-text="celda.dia"></span>
                                                <span class="mt-auto flex flex-wrap gap-0.5">
                                                    <template x-for="ev in celda.eventos.slice(0, 4)">
                                                        <span class="size-2 rounded-full" :class="{ 'bg-rose-500': ev.tono === 'vencido', 'bg-amber-400': ev.tono === 'pronto', 'bg-udg-800': ev.tono === 'normal' }"></span>
                                                    </template>
                                                    <span x-show="celda.eventos.length > 4" class="text-[10px] font-semibold text-gray-500" x-text="'+' + (celda.eventos.length - 4)"></span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                    <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500">
                                        <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500"></span> Regreso vencido</span>
                                        <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-amber-400"></span> Regresa muy pronto</span>
                                        <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-udg-800"></span> A tiempo</span>
                                    </p>
                                </div>
                                <div class="border-t border-gray-100 bg-gray-50/60 p-4 lg:border-l lg:border-t-0">
                                    <p class="text-sm font-semibold text-gray-900" x-text="seleccion ? fechaLarga(seleccion) : 'Elige un día'"></p>
                                    <ul class="mt-2 space-y-2">
                                        <template x-for="ev in eventosDe(seleccion)">
                                            <li>
                                                <a :href="ev.url" class="block rounded-lg border border-gray-200 bg-white p-2.5 transition hover:border-udg-200">
                                                    <span class="block truncate text-sm font-medium text-gray-900" x-text="ev.empresa"></span>
                                                    <span class="block text-xs text-gray-600" x-text="ev.papel + ' · ' + ev.estado"></span>
                                                    <span class="block text-[11px] font-medium" :class="{ 'text-rose-600': ev.tono === 'vencido', 'text-amber-700': ev.tono === 'pronto', 'text-gray-500': ev.tono === 'normal' }"
                                                        x-text="'Lo tiene: ' + ev.custodio + (ev.tono === 'vencido' ? ' · vencido' : '')"></span>
                                                </a>
                                            </li>
                                        </template>
                                        <li x-show="seleccion && eventosDe(seleccion).length === 0" class="text-xs italic text-gray-400">Ningún papel regresa este día.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    </template>
                </div>
            </x-dashboard.panel>

            @if ($sinControl->isNotEmpty())
                <x-dashboard.panel titulo="Convenios sin control físico" icono="alerta" descripcion="Trámites anteriores a este control. Ábrelo para registrar sus papeles.">
                    <ul class="divide-y divide-gray-100">
                        @foreach ($sinControl as $c)
                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $c->empresa->razon_social }}</p>
                                    <x-ui.estatus :estado="$c->estado" class="mt-0.5" />
                                </div>
                                <x-ui.boton-icono :accion="route('admin.convenios.fisicos.preparar', $c)" icono="mas" etiqueta="Abrir control" tono="primario" con-texto />
                            </li>
                        @endforeach
                    </ul>
                </x-dashboard.panel>
            @endif

            <section class="rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-600">
                <p class="font-semibold text-gray-900">Cómo se llena solo</p>
                <ul class="mt-2 space-y-1.5">
                    <li class="flex gap-2"><x-dashboard.icono nombre="enviar" clase="mt-0.5 size-4 shrink-0 text-gray-400" /> Al turnar a las carreras se crea el oficio de turno.</li>
                    <li class="flex gap-2"><x-dashboard.icono nombre="juridico" clase="mt-0.5 size-4 shrink-0 text-gray-400" /> Al turnar a Jurídica, el dictamen queda «En Unidad Jurídica».</li>
                    <li class="flex gap-2"><x-dashboard.icono nombre="firma" clase="mt-0.5 size-4 shrink-0 text-gray-400" /> Con el dictamen aprobado nacen los 3 juegos; las firmas que registres en el trámite los actualizan.</li>
                    <li class="flex gap-2"><x-dashboard.icono nombre="reloj" clase="mt-0.5 size-4 shrink-0 text-gray-400" /> Cada mañana se avisa de los regresos vencidos, y a la empresa si tiene juegos por devolver.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
@endsection
