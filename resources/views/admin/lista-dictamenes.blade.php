@extends('layouts.app')

@section('content')
@php
    use App\Enums\Dictamen;

    $esCoordinacion = ($modo ?? 'vinculacion') === 'coordinacion';
    $rutaArchivo = $esCoordinacion ? 'coordinacion.dictamenes.archivos' : 'admin.dictamenes.archivos.descargar';
    $hayFiltros = request()->hasAny(['q', 'carrera_id']) || $filtro !== 'pendiente';
@endphp

<div class="space-y-6">
    <x-pagina.encabezado :titulo="$esCoordinacion ? 'Dictámenes de mi carrera' : 'Dictámenes por carrera'"
        :descripcion="$esCoordinacion
            ? 'Revisa si las actividades que propone la organización corresponden al perfil de egreso de tu carrera y emite tu dictamen.'
            : 'Cada coordinación de carrera emite su dictamen de pertinencia desde su panel; aquí das seguimiento.'" />

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="No se registró el dictamen"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    <x-tabla.marco titulo="Dictámenes de pertinencia académica" :total="$dictamenes->total()">
        <x-slot:herramientas>
            <x-tabla.filtros-estado parametro="dictamen" :actual="$filtro" etiqueta="Filtrar por dictamen" :opciones="[
                ['valor' => 'pendiente', 'texto' => 'Pendientes', 'conteo' => $resumen['pendientes'], 'familia' => 'ambar'],
                ['valor' => 'aprobatorio', 'texto' => 'Aprobatorios', 'conteo' => $resumen['aprobatorios'], 'familia' => 'menta'],
                ['valor' => 'negativo', 'texto' => 'Negativos', 'conteo' => $resumen['negativos'], 'familia' => 'rosa'],
                ['valor' => 'todos', 'texto' => 'Todos', 'conteo' => $resumen['pendientes'] + $resumen['aprobatorios'] + $resumen['negativos']],
            ]" />
            <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                <input type="hidden" name="dictamen" value="{{ $filtro }}">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar por empresa o RFC</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Empresa o RFC" class="campo-filtro pl-9 sm:w-full">
                </div>
                @if ($carreras->count() > 1)
                    <label for="carrera_id" class="sr-only">Carrera</label>
                    <select id="carrera_id" name="carrera_id" class="campo-filtro sm:max-w-xs" onchange="this.form.requestSubmit()">
                        <option value="">Todas las carreras</option>
                        @foreach ($carreras as $c)
                            <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="boton boton-secundario">Buscar</button>
                @if ($hayFiltros)
                    <a href="{{ url()->current() }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                @endif
            </form>
        </x-slot:herramientas>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th px-6">Organización</th>
                    <th class="tabla-th">Carrera</th>
                    <th class="tabla-th">Dictamen</th>
                    <th class="tabla-th">Observaciones y documento</th>
                    <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($dictamenes as $d)
                    @php
                        $pdf = $d->getFirstMedia('dictamen_pertinencia');
                        $capturable = $esCoordinacion
                            && $d->dictamen === Dictamen::Pendiente
                            && $d->convenio->estado === \App\Enums\ConvenioEstado::EnValidacionAcademica;
                    @endphp
                    <tr class="tabla-fila">
                        <td class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-udg-50 font-display text-sm font-bold text-udg-900 ring-1 ring-inset ring-udg-100" aria-hidden="true">{{ mb_strtoupper(mb_substr($d->convenio->empresa->razon_social, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $d->convenio->empresa->razon_social }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $d->convenio->plazas_estimadas }} {{ (int) $d->convenio->plazas_estimadas === 1 ? 'plaza estimada' : 'plazas estimadas' }}</p>
                                    @if ($esCoordinacion)
                                        <details class="group mt-1.5 text-xs text-gray-600">
                                            <summary class="cursor-pointer list-none font-semibold text-real-600 hover:text-real-700">
                                                <span class="inline-flex items-center gap-1">Objetivos y actividades
                                                    <svg class="size-3.5 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                                </span>
                                            </summary>
                                            <div class="mt-2 space-y-2 rounded-lg bg-gray-50 p-3 leading-relaxed">
                                                <p><span class="font-semibold text-gray-800">Objetivos:</span> {{ $d->convenio->objetivos }}</p>
                                                <p><span class="font-semibold text-gray-800">Actividades:</span> {{ $d->convenio->actividades_propuestas }}</p>
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-start gap-2">
                                <span class="shrink-0 rounded-md bg-udg-50 px-1.5 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100">{{ $d->carrera->carrera_clave }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-800">{{ $d->carrera->nombre }}</p>
                                    <p class="text-xs text-gray-500">{{ $d->carrera->division->nombre }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <x-ui.estatus :estado="$d->dictamen" />
                            @if ($d->fecha_dictamen)
                                <p class="mt-1 text-xs text-gray-500">{{ $d->fecha_dictamen->format('d/m/Y') }}</p>
                                @if ($d->dictaminador?->user)<p class="text-xs text-gray-500">{{ $d->dictaminador->user->name }}</p>@endif
                            @else
                                <p class="dato-vacio mt-1">{{ $d->convenio->estado === \App\Enums\ConvenioEstado::EnValidacionAcademica ? 'Esperando a la coordinación' : 'No se emitió' }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            @if ($d->observaciones)
                                <p class="text-sm leading-relaxed text-gray-700">{{ $d->observaciones }}</p>
                            @else
                                <p class="dato-vacio">Sin observaciones</p>
                            @endif
                            @if ($pdf)
                                <a href="{{ route($rutaArchivo, [$d, $pdf]) }}" class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-real-600 hover:text-real-700">
                                    <x-dashboard.icono nombre="descargar" clase="size-3.5" /> Dictamen en PDF
                                </a>
                            @else
                                <p class="dato-vacio mt-1">Sin PDF adjunto</p>
                            @endif
                        </td>
                        <td class="acciones whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if ($capturable)
                                    <x-ui.boton-icono icono="editar" etiqueta="Emitir dictamen" tono="primario" con-texto
                                        onclick="document.getElementById('captura-{{ $d->id }}').classList.toggle('hidden')"
                                        aria-controls="captura-{{ $d->id }}" />
                                @elseif (!$esCoordinacion)
                                    <x-ui.boton-icono :href="route('admin.convenios.show', $d->convenio)" icono="ver" etiqueta="Ver convenio" />
                                @else
                                    <span class="dato-vacio">Sin acciones</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($capturable)
                        <tr id="captura-{{ $d->id }}" class="hidden bg-oro-50/40">
                            <td colspan="5" class="px-6 py-5">
                                <form method="POST" enctype="multipart/form-data" action="{{ route('coordinacion.dictamenes.registrar', $d) }}"
                                    class="grid grid-cols-1 gap-4 md:grid-cols-[12rem_1fr_16rem_auto] md:items-end">
                                    @csrf
                                    <div>
                                        <label for="dictamen-{{ $d->id }}" class="etiqueta">Dictamen</label>
                                        <select id="dictamen-{{ $d->id }}" name="dictamen" required class="campo h-11">
                                            <option value="aprobatorio">Aprobatorio</option>
                                            <option value="negativo">Negativo</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="obs-{{ $d->id }}" class="etiqueta">Observaciones</label>
                                        <input id="obs-{{ $d->id }}" type="text" name="observaciones" placeholder="Obligatorias si el dictamen es negativo" class="campo h-11">
                                    </div>
                                    <x-ui.zona-archivo name="documento" etiqueta="Dictamen firmado" :requerido="false" ayuda="Opcional: el dictamen de pertinencia firmado y sellado." />
                                    <button class="boton boton-primario h-11">Registrar dictamen</button>
                                </form>
                                <p class="ayuda">Un dictamen registrado ya no se puede cambiar.</p>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4">
                            <x-dashboard.vacio icono="academico"
                                titulo="{{ $filtro === 'pendiente' && !request()->hasAny(['q', 'carrera_id']) ? 'No hay dictámenes pendientes' : 'No hay dictámenes con ese filtro' }}"
                                texto="{{ $filtro === 'pendiente' ? 'Cuando Vinculación turne un convenio a las carreras aparecerá aquí.' : 'Prueba con otro estado o quita la búsqueda.' }}" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:pie>{{ $dictamenes->links() }}</x-slot:pie>
    </x-tabla.marco>

    {{-- Convenios en los que ninguna carrera aprobó --}}
    @unless ($esCoordinacion)
        <x-dashboard.panel titulo="Convenios con todos los dictámenes negativos" icono="alerta"
            descripcion="Si ninguna carrera aprueba, el trámite se cierra como rechazado.">
            @forelse ($todasNegativas as $c)
                <div class="-mx-2 flex flex-wrap items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $c->empresa->razon_social }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            @foreach ($c->dictamenes as $dn)
                                <span class="rounded-md bg-rose-50 px-1.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-200" title="{{ $dn->carrera->nombre }}">{{ $dn->carrera->carrera_clave }}</span>
                            @endforeach
                            <x-ui.estatus :estado="$c->estado" />
                        </div>
                    </div>
                    <x-ui.boton-icono :href="route('admin.convenios.show', $c)" icono="ver" etiqueta="Ver convenio" />
                </div>
            @empty
                <x-dashboard.vacio titulo="Ninguno por ahora" texto="Todos los convenios dictaminados tienen al menos una carrera que los aprueba." />
            @endforelse
        </x-dashboard.panel>
    @endunless
</div>
@endsection
