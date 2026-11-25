@extends('layouts.app')

@section('content')
@php
    use App\Enums\EstadoDocumentoFisico as EF;

    $vistas = [
        ['valor' => 'activos', 'texto' => 'En seguimiento', 'conteo' => $conteos['activos']],
        ['valor' => 'vencidos', 'texto' => 'Regreso vencido', 'conteo' => $conteos['vencidos'], 'familia' => 'rosa'],
        ['valor' => 'fuera', 'texto' => 'Fuera de la oficina', 'conteo' => $conteos['fuera'], 'familia' => 'ambar'],
        ['valor' => 'casa', 'texto' => 'En mi oficina', 'conteo' => $conteos['casa'], 'familia' => 'cielo'],
        ['valor' => 'concluidos', 'texto' => 'Concluidos', 'conteo' => $conteos['concluidos'], 'familia' => 'piedra'],
    ];
    $vacios = [
        'activos' => ['Todo en orden', 'No hay papeles en seguimiento. Aparecen solos cuando un convenio se envía a las carreras.'],
        'fuera' => ['Ningún papel fuera de la oficina', 'Todo lo que salió ya regresó a Vinculación.'],
        'vencidos' => ['Sin regresos vencidos', 'Ningún papel rebasó su fecha de regreso.'],
        'casa' => ['Nada pendiente en tu oficina', 'No hay papeles esperando a ser enviados o archivados.'],
        'concluidos' => ['Aún no hay papeles concluidos', 'Los juegos entregados y los expedientes archivados aparecerán aquí.'],
    ];
    $totalPapeles = $grupos->flatten()->count();
    // Cada convenio se muestra plegado; se abre solo el que tiene un error de «Mover» o todos al buscar
    $abiertosIniciales = $grupos->map(fn ($docs) => $docs->first()->convenio_id)
        ->filter(fn ($id) => request('q') || $grupos->count() === 1
            || ($errors->any() && $grupos->first(fn ($g) => $g->first()->convenio_id === $id)?->contains('id', (int) old('doc'))))
        ->values();
@endphp

<div class="space-y-6">
    <x-pagina.encabezado titulo="Documentos físicos" descripcion="Dónde está cada papel del trámite (el expediente que va a la Unidad Jurídica y los 3 juegos del convenio que firman la empresa y Rectoría), quién lo tiene y cuándo debe regresar.">
        <x-slot:acciones>
                {{-- Calendario de regresos --}}
                <div x-data="calendarioRegresos(@js($calendario))">
                    <button type="button" x-on:click="abrir()" class="boton boton-secundario">
                        <x-dashboard.icono nombre="calendario" clase="size-4" /> Calendario de regresos
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
                                                    <span class="block break-words text-sm font-medium text-gray-900" x-text="ev.empresa"></span>
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
            <a href="{{ route('admin.informes.exportar', ['tipo' => 'documentos-fisicos', 'vista' => $vista, 'q' => request('q')]) }}" data-sin-vista class="boton boton-primario">
                <x-dashboard.icono nombre="descargar" clase="size-4" /> Descargar informe
            </a>
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="No se guardó el movimiento"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-ui.aviso>
    @endif
    @if ($conteos['vencidos'] > 0 && $vista !== 'vencidos')
        <x-ui.aviso tipo="error" titulo="{{ $conteos['vencidos'] }} {{ $conteos['vencidos'] === 1 ? 'papel rebasó' : 'papeles rebasaron' }} su fecha de regreso">
            Da seguimiento con quien lo tiene. <a href="{{ route('admin.documentos-fisicos', ['vista' => 'vencidos']) }}" class="font-semibold underline underline-offset-2">Ver solo los vencidos</a>
        </x-ui.aviso>
    @endif

    {{-- Próximos regresos: lo urgente a la vista --}}
    @if ($proximos->isNotEmpty())
        <section aria-label="Próximos regresos" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs sm:p-5">
            <h2 class="text-sm font-semibold text-gray-900">Próximos regresos <span class="font-normal text-gray-500">· del más urgente al más lejano</span></h2>
            <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($proximos as $p)
                    <li>
                        <a href="{{ route('admin.convenios.show', $p->convenio) }}" class="flex items-center gap-3 rounded-xl border border-gray-100 px-3 py-2 transition hover:border-udg-200 hover:bg-gray-50">
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
                                <span class="block break-words text-sm font-medium text-gray-900">{{ $p->convenio->empresa->razon_social }}</span>
                                <span class="block break-words text-xs text-gray-500">{{ $p->nombre() }} · lo tiene {{ $p->estado->custodio() }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-tabla.marco titulo="Papeles por convenio" :total="$totalPapeles">
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

        <table class="tabla table-fixed" x-data="{ abiertos: @js($abiertosIniciales), alternar(id) { this.abiertos = this.abiertos.includes(id) ? this.abiertos.filter(a => a !== id) : [...this.abiertos, id] } }">
            <colgroup>
                <col class="w-[30%]"><col><col class="w-28"><col class="w-32"><col class="w-40">
            </colgroup>
            <thead>
                <tr>
                    <th class="tabla-th px-6">Papel</th>
                    <th class="tabla-th">Dónde está</th>
                    <th class="tabla-th">Desde</th>
                    <th class="tabla-th">Debe regresar</th>
                    <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            @forelse ($grupos as $docs)
                @php
                    $convenio = $docs->first()->convenio;
                    $detalle = $convenio->estadoDetallado();
                    $conEmpresa = $docs->where('estado', EF::ConEmpresa)->count();
                    $vencidosGrupo = $docs->filter(fn ($d) => $d->vencido())->count();
                    $porEstado = $docs->groupBy(fn ($d) => $d->estado->value)->map(fn ($g) => [$g->first()->estado, $g->count()]);
                    $cid = $convenio->id;
                @endphp
                {{-- Encabezado del convenio --}}
                <tbody>
                    <tr class="border-t-4 border-white bg-gray-50">
                        <td colspan="5" class="px-6 py-3" x-on:click.self="alternar({{ $cid }})">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white font-display text-sm font-bold text-udg-900 ring-1 ring-inset ring-udg-100" aria-hidden="true">{{ mb_strtoupper(mb_substr($convenio->empresa->razon_social, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.convenios.show', $convenio) }}" class="font-semibold text-gray-900 underline-offset-2 hover:text-udg-900 hover:underline">{{ $convenio->empresa->razon_social }}</a>
                                        <p class="text-xs text-gray-500">{{ $convenio->folio ? 'Folio ' . $convenio->folio : 'Sin folio aún' }} · {{ $detalle['texto'] }}
                                            · {{ $docs->count() }} {{ $docs->count() === 1 ? 'papel' : 'papeles' }}@if ($vencidosGrupo)<span class="font-semibold text-rose-600"> · {{ $vencidosGrupo }} {{ $vencidosGrupo === 1 ? 'vencido' : 'vencidos' }}</span>@endif</p>
                                        {{-- Dónde están sus papeles, de un vistazo, sin abrir el detalle --}}
                                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                                            @foreach ($porEstado as [$est, $n])
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ \App\Support\Estatus::chip($est->familia()) }}">{{ $n }} · {{ $est->label() }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($conEmpresa > 0 && auth()->user()->can('operar-convenios'))
                                        <x-ui.boton-icono :accion="route('admin.convenios.fisicos.recordar', $convenio)" icono="reloj" etiqueta="Recordar a la empresa" tono="neutro" con-texto
                                            confirmar="¿Enviar a la empresa un recordatorio por correo para devolver los juegos firmados?" />
                                    @endif
                                    <a href="{{ route('admin.convenios.show', $convenio) }}" class="boton boton-chico boton-secundario">Ver trámite <x-dashboard.icono nombre="flecha" clase="size-3.5" /></a>
                                    {{-- Los papeles del convenio se despliegan debajo: la tabla no carga todos los renglones a la vez --}}
                                    <button type="button" x-on:click="alternar({{ $cid }})" :aria-expanded="abiertos.includes({{ $cid }}).toString()"
                                        class="boton boton-chico" :class="abiertos.includes({{ $cid }}) ? 'boton-primario' : 'boton-secundario'">
                                        <x-dashboard.icono nombre="documento" clase="size-3.5" />
                                        <span x-text="abiertos.includes({{ $cid }}) ? 'Ocultar papeles' : 'Ver papeles'">Ver papeles</span>
                                        <svg class="size-3 transition-transform" :class="abiertos.includes({{ $cid }}) && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>

                @foreach ($docs as $d)
                    @php
                        $vencido = $d->vencido();
                        $porVencer = $d->porVencer();
                        $opcionesJs = collect($d->tipo->estados())->mapWithKeys(fn ($e) => [$e->value => $e->plazoHabiles()]);
                    @endphp
                    <tbody x-show="abiertos.includes({{ $cid }})" x-cloak x-data="{ editar: {{ $errors->any() && old('doc') == $d->id ? 'true' : 'false' }}, historial: false, estado: '{{ old('doc') == $d->id ? old('estado') : $d->estado->value }}', plazos: {{ $opcionesJs->toJson() }} }">
                        <tr @class(['tabla-fila align-top border-t border-gray-100', 'bg-rose-50/40 shadow-[inset_3px_0_0_var(--color-rose-500)]' => $vencido, 'shadow-[inset_3px_0_0_var(--color-amber-400)]' => $porVencer])>
                            <td class="px-6 py-3.5">
                                <div class="flex items-start gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ \App\Support\Estatus::chip($d->estado->familia()) }}">
                                        <x-dashboard.icono :nombre="$d->tipo->icono()" clase="size-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900">{{ $d->nombre() }}</p>
                                        <p class="text-xs text-gray-500">{{ $d->juego ? 'Destino: ' . ($d->destino ?: 'sin definir') : $d->tipo->descripcion() }}</p>
                                        @if ($d->notas)<p class="mt-1 text-xs italic text-gray-600">«{{ $d->notas }}»</p>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <x-ui.estatus :estado="$d->estado" />
                                <p class="mt-1 text-xs text-gray-500">Lo tiene: <span class="font-medium text-gray-700">{{ $d->estado->custodio() }}</span></p>
                            </td>
                            <td class="px-4 py-3.5 text-sm">
                                <p class="font-medium tabular-nums text-gray-800">{{ $d->fecha_estado->format('d/m/Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $d->diasEnEstado() === 1 ? '1 día hábil' : $d->diasEnEstado() . ' días hábiles' }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-sm">
                                @if ($d->fecha_compromiso && $d->estado->fueraDeOficina())
                                    <p @class(['font-semibold tabular-nums', 'text-rose-600' => $vencido, 'text-amber-700' => $porVencer, 'text-gray-800' => !$vencido && !$porVencer])>{{ $d->fecha_compromiso->format('d/m/Y') }}</p>
                                    <p @class(['text-xs', 'font-medium text-rose-600' => $vencido, 'text-amber-700' => $porVencer, 'text-gray-500' => !$vencido && !$porVencer])>{{ $vencido ? 'Vencido' : ($porVencer ? 'Muy pronto' : 'A tiempo') }}</p>
                                @else
                                    <p class="text-xs text-gray-500">{{ $d->estado->concluido() ? 'Concluido' : 'Está en tu oficina' }}</p>
                                @endif
                            </td>
                            <td class="acciones px-6 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @can('operar-convenios')<button type="button" class="boton boton-chico boton-secundario" x-on:click="editar = !editar; historial = false" :aria-expanded="editar.toString()">
                                        <x-dashboard.icono nombre="editar" clase="size-3.5" /> Mover
                                    </button>@endcan
                                    <button type="button" class="boton boton-chico text-gray-500 hover:bg-gray-100 hover:text-gray-800" x-on:click="historial = !historial; editar = false" :aria-expanded="historial.toString()" title="Historial de movimientos">
                                        <x-dashboard.icono nombre="reloj" clase="size-3.5" /><span class="sr-only">Historial</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- Mover --}}
                        <tr x-show="editar" x-cloak>
                            <td colspan="5" class="border-t border-gray-100 bg-gray-50/70 px-6 py-4">
                                @can('operar-convenios')
                                <form method="POST" action="{{ route('admin.documentos-fisicos.mover', $d) }}" class="grid grid-cols-1 items-start gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @csrf
                                    <input type="hidden" name="doc" value="{{ $d->id }}">
                                    <div>
                                        <label class="etiqueta" for="estado-{{ $d->id }}">¿Dónde está ahora?</label>
                                        <select id="estado-{{ $d->id }}" name="estado" x-model="estado" class="campo h-10">
                                            {{-- Solo los pasos válidos: sin dictamen favorable, el expediente solo regresa a Jurídica y los juegos no salen a firma --}}
                                            @foreach ($d->estadosDisponibles() as $e)
                                                <option value="{{ $e->value }}">{{ $e === \App\Enums\EstadoDocumentoFisico::EnJuridica && $d->estado === \App\Enums\EstadoDocumentoFisico::ConObservaciones ? 'En Unidad Jurídica · reenviar corregido (reinicia el conteo)' : $e->label() }}</option>
                                            @endforeach
                                        </select>
                                        @if ($d->tipo === \App\Enums\TipoDocumentoFisico::DictamenJuridico && $d->estado === \App\Enums\EstadoDocumentoFisico::ConObservaciones)
                                            <p class="ayuda text-rose-700">Jurídica lo devolvió: corrígelo y regístralo de nuevo en la Unidad Jurídica. Los juegos no saldrán a firma hasta el dictamen favorable.</p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="etiqueta" for="fecha-{{ $d->id }}">Fecha del movimiento</label>
                                        <input id="fecha-{{ $d->id }}" type="date" name="fecha" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="campo h-10">
                                    </div>
                                    <div x-show="plazos[estado]">
                                        <label class="etiqueta" for="comp-{{ $d->id }}">Debe regresar el</label>
                                        <input id="comp-{{ $d->id }}" type="date" name="fecha_compromiso" class="campo h-10" :disabled="!plazos[estado]">
                                        <p class="ayuda" x-text="'Si lo dejas vacío: ' + plazos[estado] + ' días hábiles'"></p>
                                    </div>
                                    @if ($d->juego)
                                        <div>
                                            <label class="etiqueta" for="dest-{{ $d->id }}">Destino del juego</label>
                                            <input id="dest-{{ $d->id }}" type="text" name="destino" value="{{ $d->destino }}" maxlength="255" class="campo h-10">
                                        </div>
                                    @endif
                                    {{-- Los juegos viajan en la misma carpeta: se pueden mover juntos los que están en el mismo lugar --}}
                                    @php $hermanos = $d->juego ? $docs->filter(fn ($o) => $o->juego && $o->id !== $d->id && $o->estado === $d->estado)->sortBy('juego') : collect(); @endphp
                                    @if ($hermanos->isNotEmpty())
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl bg-white px-3.5 py-2.5 text-sm ring-1 ring-inset ring-udg-100 sm:col-span-full">
                                            <input type="checkbox" name="juntos" value="1" checked class="mt-0.5 size-4 shrink-0 rounded border-gray-300">
                                            <span><span class="font-semibold text-gray-900">Mover también {{ $hermanos->count() === 1 ? 'el juego' : 'los juegos' }} {{ $hermanos->pluck('juego')->join(', ', ' y ') }}</span>
                                                <span class="text-gray-500">· están en «{{ $d->estado->label() }}» y viajan en la misma carpeta</span></span>
                                        </label>
                                    @endif
                                    <div class="flex flex-col gap-3 border-t border-gray-200/70 pt-3 sm:col-span-full sm:flex-row sm:items-end">
                                        <div class="min-w-0 flex-1">
                                            <label class="etiqueta" for="nota-{{ $d->id }}">Nota <span class="font-normal text-gray-400">(obligatoria si regresa con observaciones o se extravía)</span></label>
                                            <input id="nota-{{ $d->id }}" type="text" name="nota" maxlength="1000" placeholder="Ej. Lo recogió el representante legal; acuse con sello de Secretaría" class="campo h-10">
                                        </div>
                                        <div class="flex shrink-0 justify-end gap-2">
                                            <button type="button" class="boton boton-chico h-10 text-gray-500 hover:bg-gray-100" x-on:click="editar = false">Cancelar</button>
                                            <button class="boton boton-chico boton-primario h-10"><x-dashboard.icono nombre="check" clase="size-3.5" /> Guardar</button>
                                        </div>
                                    </div>
                                </form>
                                @endcan
                            </td>
                        </tr>

                        {{-- Historial --}}
                        <tr x-show="historial" x-cloak>
                            <td colspan="5" class="border-t border-gray-100 px-6 py-3">
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
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            @empty
                <tbody>
                    <tr>
                        <td colspan="5" class="px-6 py-4">
                            <x-dashboard.vacio icono="documento" :titulo="request('q') ? 'Sin resultados para «' . request('q') . '»' : $vacios[$vista][0] ?? $vacios['activos'][0]" :texto="request('q') ? 'Prueba con otro nombre o folio.' : $vacios[$vista][1] ?? $vacios['activos'][1]" />
                        </td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </x-tabla.marco>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @if ($sinControl->isNotEmpty())
            <x-dashboard.panel titulo="Convenios sin control físico" icono="alerta" descripcion="Trámites anteriores a este control. Ábrelo para registrar sus papeles.">
                <ul class="divide-y divide-gray-100">
                    @foreach ($sinControl as $c)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-medium text-gray-900">{{ $c->empresa->razon_social }}</p>
                                <x-ui.estatus :estado="$c->estado" class="mt-0.5" />
                            </div>
                            @can('operar-convenios')<x-ui.boton-icono :accion="route('admin.convenios.fisicos.preparar', $c)" icono="mas" etiqueta="Abrir control" tono="primario" con-texto />@endcan
                        </li>
                    @endforeach
                </ul>
            </x-dashboard.panel>
        @endif

        <details @class(['group h-fit rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-600', 'lg:col-span-2' => $sinControl->isEmpty()])>
            <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-gray-900">
                ¿Cómo se llena esta pantalla?
                <svg class="size-4 text-gray-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
            </summary>
            <ul class="mt-3 space-y-1.5">
                <li class="flex gap-2"><x-dashboard.icono nombre="enviar" clase="mt-0.5 size-4 shrink-0 text-gray-400" /> El turno a las carreras es digital: aquí solo se rastrean los papeles con firma autógrafa y sello.</li>
                <li class="flex gap-2"><x-dashboard.icono nombre="juridico" clase="mt-0.5 size-4 shrink-0 text-gray-400" /> Al turnar a Jurídica, el dictamen queda «En Unidad Jurídica».</li>
                <li class="flex gap-2"><x-dashboard.icono nombre="firma" clase="mt-0.5 size-4 shrink-0 text-gray-400" /> Con el dictamen aprobado nacen los 3 juegos; las firmas que registres en el trámite los actualizan.</li>
                <li class="flex gap-2"><x-dashboard.icono nombre="reloj" clase="mt-0.5 size-4 shrink-0 text-gray-400" /> Cada mañana se avisa de los regresos vencidos, y a la empresa si tiene juegos por devolver.</li>
            </ul>
        </details>
    </div>
</div>
@endsection
