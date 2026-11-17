@extends('layouts.app')

@section('content')
@php
    $filtro = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
@endphp

<x-common.page-breadcrumb pageTitle="Convenios vigentes" />

<div class="space-y-6">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5">
        <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Organizaciones con convenio vigente</h3>
                <p class="mt-0.5 text-sm text-gray-500">
                    Solo puedes hacer prácticas externas en organizaciones con convenio vigente para tu carrera.
                    Las vacantes abiertas están en <a href="{{ route('alumno.oferta-programas') }}" class="text-brand-600 font-semibold">Oferta de programas</a>.
                </p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar organización..." aria-label="Buscar organización" class="{{ $filtro }} w-52">
                <select name="carrera_id" aria-label="Carrera" class="{{ $filtro }} w-64">
                    <option value="0" @selected($carreraId === 0)>Todas las carreras</option>
                    @foreach ($carreras as $c)
                        <option value="{{ $c->id }}" @selected($carreraId === $c->id)>{{ $c->carrera_clave }} · {{ $c->nombre }}{{ $c->id === $alumno->carrera_id ? ' (mi carrera)' : '' }}</option>
                    @endforeach
                </select>
                <button class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Filtrar</button>
            </form>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Organización</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Carreras aprobadas</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Vigente hasta</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Vacantes abiertas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($convenios as $c)
                        <tr>
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $c->empresa->razon_social }}</span>
                                <span class="text-xs text-gray-500">{{ $c->empresa->tipo_organismo->label() }} · Folio {{ $c->folio }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($c->dictamenes as $d)
                                        <span class="rounded border px-2 py-0.5 font-mono text-xs font-bold {{ $d->carrera_id === $alumno->carrera_id ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-gray-200 bg-gray-50 text-gray-600' }}"
                                            title="{{ $d->carrera->nombre }}">{{ $d->carrera->carrera_clave }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">{{ $c->fecha_fin_vigencia->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                @php $n = (int) ($vacantes[$c->id] ?? 0); @endphp
                                @if ($n > 0)
                                    <a href="{{ route('alumno.oferta-programas', ['q' => $c->empresa->razon_social]) }}" class="text-brand-600 text-sm font-semibold">{{ $n }} abierta(s) →</a>
                                @else
                                    <span class="text-xs text-gray-400">Sin vacantes por ahora</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400">No hay convenios vigentes con ese filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $convenios->links() }}</div>
    </div>
</div>
@endsection
