@extends('layouts.app')

@section('content')
@php
    $filtro = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
    $rutas = ['Convenio' => 'admin.convenios.show', 'Empresa' => 'admin.empresas.show'];
    $color = fn ($a) => match (true) {
        in_array($a, ['acceso.fallido', 'acceso.bloqueado', 'acceso.cuenta_inactiva'], true) => 'bg-red-50 text-red-700',
        str_starts_with($a, 'acceso.') => 'bg-gray-100 text-gray-600',
        str_starts_with($a, 'documento.') => 'bg-purple-50 text-purple-700',
        str_ends_with($a, '.eliminado') => 'bg-red-50 text-red-700',
        str_ends_with($a, '.creado') => 'bg-green-50 text-green-700',
        default => 'bg-blue-50 text-blue-700',
    };
@endphp

<x-common.page-breadcrumb pageTitle="Auditoría" />

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5">
    <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h3 class="text-lg font-bold text-gray-900">Bitácora de acciones</h3>
            <p class="mt-0.5 text-sm text-gray-500">Quién hizo qué y cuándo. Solo lectura: los registros no se pueden editar ni borrar.</p>
            @if (request('entidad_tipo'))
                <p class="mt-1 text-xs text-brand-600">Historial de {{ request('entidad_tipo') }} #{{ request('entidad_id') }}
                    · <a href="{{ route('admin.auditoria') }}" class="underline">quitar filtro</a></p>
            @endif
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            @if (request('entidad_tipo'))
                <input type="hidden" name="entidad_tipo" value="{{ request('entidad_tipo') }}">
                <input type="hidden" name="entidad_id" value="{{ request('entidad_id') }}">
            @endif
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Persona o texto..." class="{{ $filtro }} w-44">
            <select name="accion" class="{{ $filtro }}">
                <option value="">Todas las acciones</option>
                @foreach ($acciones as $a)
                    <option value="{{ $a }}" @selected(request('accion') === $a)>{{ $a }}</option>
                @endforeach
            </select>
            <input type="date" name="desde" value="{{ request('desde') }}" class="{{ $filtro }}" title="Desde">
            <input type="date" name="hasta" value="{{ request('hasta') }}" class="{{ $filtro }}" title="Hasta">
            <button class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Filtrar</button>
        </form>
    </div>

    <div class="max-w-full overflow-x-auto">
        <table class="w-full border-collapse text-left">
            <thead class="border-y border-gray-100 bg-gray-50/70">
                <tr>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Fecha</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Quién</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Acción</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Detalle</th>
                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Origen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    <tr class="align-top transition-colors hover:bg-gray-50/40">
                        <td class="whitespace-nowrap px-6 py-3 text-xs text-gray-600">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3">
                            <span class="block text-sm font-medium text-gray-800">{{ $log->actor_nombre ?? 'Sistema / visitante' }}</span>
                            <span class="text-xs text-gray-400">{{ $log->actor_rol ?? '—' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $color($log->accion) }}">{{ $log->accion }}</span>
                        </td>
                        <td class="max-w-[420px] px-4 py-3 text-sm text-gray-700">
                            {{ $log->descripcion }}
                            <span class="mt-1 block text-xs">
                                @if ($log->entidad_tipo && $log->entidad_id)
                                    @isset($rutas[$log->entidad_tipo])
                                        <a href="{{ route($rutas[$log->entidad_tipo], $log->entidad_id) }}" class="text-brand-600 font-semibold">Abrir {{ $log->entidad_tipo }}</a> ·
                                    @endisset
                                    <a href="{{ route('admin.auditoria', ['entidad_tipo' => $log->entidad_tipo, 'entidad_id' => $log->entidad_id]) }}" class="text-gray-500 underline">Ver historial</a>
                                @endif
                            </span>
                            @if ($log->cambios)
                                <details class="mt-1">
                                    <summary class="cursor-pointer text-xs text-gray-500">Ver cambios</summary>
                                    <pre class="mt-1 overflow-x-auto rounded bg-gray-50 p-2 text-[11px] text-gray-700">{{ json_encode($log->cambios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-3 text-xs text-gray-400">{{ $log->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">No hay registros con ese filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-gray-100 px-6 py-4">{{ $logs->links() }}</div>
</div>
@endsection