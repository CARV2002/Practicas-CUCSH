@extends('layouts.app')

@section('content')
@php
    $rutas = ['Convenio' => 'admin.convenios.show', 'Empresa' => 'admin.empresas.show'];
    $fallidos = \App\Http\Controllers\Admin\AuditoriaController::FALLIDOS;
    $familia = fn (string $a) => match (true) {
        in_array($a, $fallidos, true) => 'rosa',
        str_starts_with($a, 'acceso.') => 'gris',
        str_starts_with($a, 'documento.') => 'lavanda',
        str_ends_with($a, '.eliminado') => 'rosa',
        str_ends_with($a, '.creado') => 'menta',
        default => 'cielo',
    };
    $icono = fn (string $a) => match (true) {
        str_starts_with($a, 'acceso.') => in_array($a, $fallidos, true) ? 'candado' : 'usuarios',
        str_starts_with($a, 'documento.') => 'documento',
        str_starts_with($a, 'convenio') => 'firma',
        str_starts_with($a, 'empresa') => 'empresa',
        str_ends_with($a, '.eliminado') => 'basura',
        str_ends_with($a, '.creado') => 'mas',
        default => 'editar',
    };
    $nombresAcceso = [
        'inicio_sesion' => 'Inicio de sesión', 'cierre_sesion' => 'Cierre de sesión', 'fallido' => 'Intento de acceso fallido',
        'bloqueado' => 'Acceso bloqueado', 'cuenta_inactiva' => 'Intento con cuenta inactiva',
    ];
    $legible = function (string $a) use ($nombresAcceso) {
        [$entidad, $evento] = array_pad(explode('.', $a, 2), 2, '');
        if ($entidad === 'acceso') {
            return $nombresAcceso[$evento] ?? \Illuminate\Support\Str::ucfirst(str_replace('_', ' ', $evento));
        }

        return \Illuminate\Support\Str::ucfirst(str_replace('_', ' ', $entidad)) . ' ' . str_replace('_', ' ', $evento);
    };
    $opciones = $grupos->map(fn ($g, $clave) => ['valor' => $clave, 'texto' => $g['texto'], 'conteo' => $g['conteo']])
        ->prepend(['valor' => '', 'texto' => 'Todo', 'conteo' => $total])->values()->all();
    $filtrando = request()->hasAny(['q', 'accion', 'desde', 'hasta', 'grupo', 'entidad_tipo']);
    $dia = null;
@endphp

<div class="space-y-6">
    <x-pagina.encabezado titulo="Auditoría" descripcion="Quién hizo qué y cuándo. Solo lectura: los registros no se pueden editar ni borrar." />

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-dashboard.kpi etiqueta="Acciones hoy" :valor="$resumen['hoy']" icono="reloj" tono="udg" :detalle="$resumen['personas_hoy'] . ' ' . ($resumen['personas_hoy'] === 1 ? 'persona activa' : 'personas activas')" :orden="0" />
        <x-dashboard.kpi etiqueta="Últimos 7 días" :valor="$resumen['semana']" icono="calendario" tono="cielo" detalle="Movimientos registrados" :orden="1" />
        <x-dashboard.kpi :href="route('admin.auditoria', ['grupo' => 'acceso'])" etiqueta="Accesos fallidos" :valor="$resumen['fallidos']" icono="candado" tono="rosa" detalle="En los últimos 7 días" :alerta="$resumen['fallidos'] >= 10 ? 'Revisa posibles intentos' : null" :orden="2" />
        <x-dashboard.kpi etiqueta="Registros totales" :valor="$total" icono="tabla" tono="gris" detalle="Desde que inició la bitácora" :orden="3" />
    </div>

    @if (request('entidad_tipo'))
        <x-ui.aviso tipo="info" titulo="Historial de {{ request('entidad_tipo') }} #{{ request('entidad_id') }}">
            Solo ves los movimientos de este registro. <a href="{{ route('admin.auditoria') }}" class="enlace">Ver toda la bitácora</a>
        </x-ui.aviso>
    @endif

    <x-tabla.marco titulo="Bitácora de acciones" :total="$logs->total()">
        <x-slot:herramientas>
            <x-tabla.filtros-estado :opciones="$opciones" parametro="grupo" :actual="request('grupo', '')" etiqueta="Filtrar por tipo de acción" />
            <form method="GET" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center" role="search">
                @foreach (['grupo', 'entidad_tipo', 'entidad_id'] as $h)
                    @if (request($h))<input type="hidden" name="{{ $h }}" value="{{ request($h) }}">@endif
                @endforeach
                <div class="relative sm:col-span-2 lg:w-64">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar por persona o texto</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Persona o texto" class="campo-filtro w-full pl-9">
                </div>
                <label class="sr-only" for="accion">Acción</label>
                <select id="accion" name="accion" class="campo-filtro" onchange="this.form.requestSubmit()">
                    <option value="">Todas las acciones</option>
                    @foreach ($acciones as $a)
                        <option value="{{ $a }}" @selected(request('accion') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2">
                    <label for="desde" class="text-xs text-gray-500">Del</label>
                    <input id="desde" type="date" name="desde" value="{{ request('desde') }}" class="campo-filtro flex-1">
                </div>
                <div class="flex items-center gap-2">
                    <label for="hasta" class="text-xs text-gray-500">al</label>
                    <input id="hasta" type="date" name="hasta" value="{{ request('hasta') }}" class="campo-filtro flex-1">
                </div>
                <button class="boton boton-secundario">Filtrar</button>
                @if ($filtrando)
                    <a href="{{ route('admin.auditoria') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                @endif
            </form>
        </x-slot:herramientas>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th px-6">Acción</th>
                    <th class="tabla-th">Quién</th>
                    <th class="tabla-th">Detalle</th>
                    <th class="tabla-th px-6 text-right">Cuándo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    @php $fecha = $log->created_at->toDateString(); @endphp
                    @if ($fecha !== $dia)
                        @php $dia = $fecha; @endphp
                        <tr class="bg-gray-50/80">
                            <td colspan="4" class="px-6 py-2 text-xs font-semibold text-gray-600">
                                {{ $log->created_at->isToday() ? 'Hoy' : ($log->created_at->isYesterday() ? 'Ayer' : \Illuminate\Support\Str::ucfirst($log->created_at->translatedFormat('l j \d\e F \d\e Y'))) }}
                            </td>
                        </tr>
                    @endif
                    <tr class="tabla-fila align-top">
                        <td class="px-6 py-3.5">
                            <div class="flex items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ \App\Support\Estatus::chip($familia($log->accion)) }}">
                                    <x-dashboard.icono :nombre="$icono($log->accion)" clase="size-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $legible($log->accion) }}</p>
                                    <p class="font-mono text-[11px] text-gray-400">{{ $log->accion }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="md:min-w-40">
                                <p class="text-sm font-medium text-gray-800">{{ $log->actor_nombre ?? 'Sistema o visitante' }}</p>
                                <p class="text-xs text-gray-500">{{ $log->actor_rol ? (\App\Enums\Rol::tryFrom($log->actor_rol)?->label() ?? $log->actor_rol) : 'Sin sesión' }}</p>
                                <p class="text-[11px] text-gray-400">{{ $log->ip ?: 'IP no registrada' }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-sm text-gray-700" x-data="{ cambios: false }">
                            <div class="md:max-w-xl">
                                <p>{{ $log->descripcion ?: 'Sin descripción' }}</p>
                                @if ($log->entidad_tipo && $log->entidad_id)
                                    <p class="mt-1 flex flex-wrap gap-x-3 text-xs">
                                        @isset($rutas[$log->entidad_tipo])
                                            <a href="{{ route($rutas[$log->entidad_tipo], $log->entidad_id) }}" class="enlace">Abrir {{ mb_strtolower($log->entidad_tipo) }}</a>
                                        @endisset
                                        <a href="{{ route('admin.auditoria', ['entidad_tipo' => $log->entidad_tipo, 'entidad_id' => $log->entidad_id]) }}" class="text-gray-500 underline-offset-2 hover:text-gray-800 hover:underline">Historial de {{ $log->entidad_tipo }} #{{ $log->entidad_id }}</a>
                                    </p>
                                @endif
                                @if ($log->cambios)
                                    <button type="button" class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-gray-800" x-on:click="cambios = !cambios" :aria-expanded="cambios.toString()">
                                        <svg class="size-3.5 transition-transform" :class="cambios && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
                                        {{ count($log->cambios) }} {{ count($log->cambios) === 1 ? 'cambio' : 'cambios' }}
                                    </button>
                                    <div x-show="cambios" x-transition.opacity x-cloak class="mt-2 overflow-hidden rounded-lg ring-1 ring-gray-200">
                                        <dl class="divide-y divide-gray-100 text-xs">
                                            @foreach ($log->cambios as $campo => $c)
                                                <div class="grid grid-cols-1 gap-1 px-3 py-2 sm:grid-cols-[9rem_1fr]">
                                                    <dt class="font-medium text-gray-600">{{ str_replace('_', ' ', $campo) }}</dt>
                                                    <dd class="min-w-0 break-words">
                                                        @if (is_array($c) && array_key_exists('antes', $c))
                                                            <span class="rounded bg-rose-50 px-1 text-rose-700 line-through decoration-rose-300">{{ is_scalar($c['antes']) && $c['antes'] !== '' ? $c['antes'] : 'vacío' }}</span>
                                                            <span class="text-gray-400" aria-hidden="true">→</span>
                                                            <span class="rounded bg-emerald-50 px-1 text-emerald-800">{{ is_scalar($c['despues']) && $c['despues'] !== '' ? $c['despues'] : 'vacío' }}</span>
                                                        @else
                                                            <pre class="whitespace-pre-wrap font-mono text-[11px] text-gray-700">{{ is_scalar($c) ? $c : json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        @endif
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-3.5 text-right">
                            <p class="text-sm font-semibold tabular-nums text-gray-800">{{ $log->created_at->format('H:i') }}</p>
                            <p class="text-xs text-gray-500" title="{{ $log->created_at->format('d/m/Y H:i:s') }}">{{ $log->created_at->diffForHumans() }}</p>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4">
                            <x-dashboard.vacio icono="buscar" :titulo="$filtrando ? 'No hay registros con ese filtro' : 'La bitácora está vacía'"
                                :texto="$filtrando ? 'Amplía el rango de fechas o quita algún filtro.' : 'Cada inicio de sesión y cada cambio quedará registrado aquí.'" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:pie>{{ $logs->links() }}</x-slot:pie>
    </x-tabla.marco>
</div>
@endsection
