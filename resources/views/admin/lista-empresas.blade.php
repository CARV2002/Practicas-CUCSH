{{-- resources/views/admin/lista-empresas.blade.php --}}
@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Empresas" />

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5">
        <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Empresas e instituciones</h3>
                <p class="mt-0.5 text-sm text-gray-500">Cuentas registradas, su tipo de organismo y el estado de su convenio.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Razón social, RFC o contacto..."
                    class="focus:border-brand-300 focus:ring-brand-500/10 h-10 w-64 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden">
                <select name="tipo" class="h-10 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="">Todos los tipos</option>
                    @foreach ($tipos as $t)
                        <option value="{{ $t->value }}" @selected(request('tipo') === $t->value)>{{ $t->label() }}</option>
                    @endforeach
                </select>
                <select name="convenio" class="h-10 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="">Cualquier convenio</option>
                    <option value="vigente" @selected(request('convenio') === 'vigente')>Con convenio vigente</option>
                    <option value="sin" @selected(request('convenio') === 'sin')>Sin convenio vigente</option>
                </select>
                <button class="bg-brand-500 hover:bg-brand-600 h-10 rounded-lg px-4 text-sm font-medium text-white">Filtrar</button>
            </form>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Empresa</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Tipo</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Contacto</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Convenio</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Cuenta</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($empresas as $empresa)
                        @php
                            $vigente = $empresa->convenios->first(fn ($c) => $c->estaVigente());
                            $ultimo = $empresa->convenios->sortByDesc('id')->first();
                            $activo = $empresa->user?->activo ?? false;
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $empresa->razon_social }}</span>
                                <span class="font-mono text-xs text-gray-500">{{ $empresa->rfc }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-600">{{ $empresa->tipo_organismo->label() }}</td>
                            <td class="px-4 py-4">
                                <span class="block text-sm text-gray-700">{{ $empresa->contacto_nombre }}</span>
                                <span class="text-xs text-gray-500">{{ $empresa->user?->email }}{{ $empresa->contacto_telefono ? ' · '.$empresa->contacto_telefono : '' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                @if ($vigente)
                                    <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700">Vigente</span>
                                    <span class="mt-1 block text-xs text-gray-500">hasta {{ $vigente->fecha_fin_vigencia->format('d/m/Y') }}</span>
                                @elseif ($ultimo)
                                    <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">{{ $ultimo->estado->label() }}</span>
                                @else
                                    <span class="text-xs text-gray-400">Sin solicitud</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $activo ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.empresas.show', $empresa) }}" class="text-brand-600 hover:text-brand-700 text-xs font-semibold">Ver</a>
                                    @if ($empresa->user)
                                        <form method="POST" action="{{ route('admin.empresas.activo', $empresa) }}"
                                            onsubmit="return confirm('{{ $activo ? '¿Desactivar' : '¿Activar' }} la cuenta de esta empresa?')">
                                            @csrf
                                            <button class="text-xs font-semibold {{ $activo ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700' }}">
                                                {{ $activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400">No hay empresas que coincidan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $empresas->links() }}</div>
    </div>
</div>
@endsection