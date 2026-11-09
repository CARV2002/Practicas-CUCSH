@extends('layouts.app')

@section('content')
@php
    use App\Enums\Dictamen;

    $input = 'focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-3 focus:outline-hidden';
    $btn = 'bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2 text-sm font-medium text-white';
    $card = 'rounded-2xl border border-gray-200 bg-white';
@endphp

<x-common.page-breadcrumb pageTitle="Dictámenes por carrera" />

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Resumen --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="{{ $card }} p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pendientes</p>
            <h3 class="mt-1 text-2xl font-bold text-yellow-600">{{ $resumen['pendientes'] }}</h3>
        </div>
        <div class="{{ $card }} p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Aprobatorios</p>
            <h3 class="mt-1 text-2xl font-bold text-green-600">{{ $resumen['aprobatorios'] }}</h3>
        </div>
        <div class="{{ $card }} p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Negativos</p>
            <h3 class="mt-1 text-2xl font-bold text-red-600">{{ $resumen['negativos'] }}</h3>
        </div>
    </div>

    {{-- Listado --}}
    <div class="{{ $card }} overflow-hidden pt-5">
        <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Dictámenes de pertinencia académica</h3>
                <p class="mt-0.5 text-sm text-gray-500">Las divisiones no usan el sistema: captura aquí el resultado cuando regrese el oficio firmado.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Empresa o RFC..." class="{{ $input }} !w-52">
                <select name="carrera_id" class="{{ $input }} !w-56">
                    <option value="">Todas las carreras</option>
                    @foreach ($carreras as $c)
                        <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                    @endforeach
                </select>
                <select name="dictamen" class="{{ $input }} !w-40">
                    <option value="pendiente" @selected($filtro === 'pendiente')>Pendientes</option>
                    <option value="aprobatorio" @selected($filtro === 'aprobatorio')>Aprobatorios</option>
                    <option value="negativo" @selected($filtro === 'negativo')>Negativos</option>
                    <option value="todos" @selected($filtro === 'todos')>Todos</option>
                </select>
                <button class="{{ $btn }}">Filtrar</button>
            </form>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Convenio / Empresa</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Carrera</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Dictamen</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Detalle</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($dictamenes as $d)
                        @php
                            $color = match ($d->dictamen) {
                                Dictamen::Aprobatorio => 'bg-green-50 text-green-700',
                                Dictamen::Negativo => 'bg-red-50 text-red-700',
                                default => 'bg-yellow-50 text-yellow-700',
                            };
                            $pdf = $d->getFirstMedia('dictamen_pertinencia');
                            $capturable = $d->dictamen === Dictamen::Pendiente
                                && $d->convenio->estado === \App\Enums\ConvenioEstado::EnValidacionAcademica;
                        @endphp
                        <tr class="align-top transition-colors hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $d->convenio->empresa->razon_social }}</span>
                                <a href="{{ route('admin.convenios.show', $d->convenio) }}" class="text-brand-600 text-xs">Ver convenio · {{ $d->convenio->estado->label() }}</a>
                            </td>
                            <td class="px-4 py-4">
                                <span class="block text-sm text-gray-800">{{ $d->carrera->nombre }}</span>
                                <span class="text-xs text-gray-500">{{ $d->carrera->division->nombre }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $color }}">{{ ucfirst($d->dictamen->value) }}</span>
                            </td>
                            <td class="px-4 py-4 text-xs text-gray-600">
                                @if ($d->fecha_dictamen)
                                    <span class="block">{{ $d->fecha_dictamen->format('d/m/Y') }}{{ $d->dictaminador?->user ? ' · '.$d->dictaminador->user->name : '' }}</span>
                                @endif
                                @if ($d->observaciones) <span class="block">{{ $d->observaciones }}</span> @endif
                                @if ($pdf)
                                    <a href="{{ route('admin.dictamenes.archivos.descargar', [$d, $pdf]) }}" class="text-brand-600 font-semibold">Descargar PDF</a>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if ($capturable)
                                    <button type="button" onclick="document.getElementById('captura-{{ $d->id }}').classList.toggle('hidden')"
                                        class="text-brand-600 hover:text-brand-700 text-xs font-semibold">Capturar</button>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @if ($capturable)
                            <tr id="captura-{{ $d->id }}" class="hidden bg-gray-50/60">
                                <td colspan="5" class="px-6 py-4">
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.dictamenes.registrar', $d) }}" class="grid grid-cols-1 gap-2 md:grid-cols-5">
                                        @csrf
                                        <select name="dictamen" required class="{{ $input }}">
                                            <option value="aprobatorio">Aprobatorio</option>
                                            <option value="negativo">Negativo</option>
                                        </select>
                                        <input type="text" name="observaciones" placeholder="Observaciones (obligatorio si es negativo)" class="{{ $input }} md:col-span-2">
                                        <input type="file" name="documento" accept="application/pdf" class="{{ $input }}">
                                        <button class="{{ $btn }}">Registrar</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">No hay dictámenes con ese filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $dictamenes->links() }}</div>
    </div>

    {{-- Todas negativas --}}
    <div class="{{ $card }} p-6">
        <h4 class="text-base font-bold text-gray-900">Convenios con todos los dictámenes negativos</h4>
        <p class="mb-4 text-xs text-gray-500">El trámite se cierra como rechazado cuando ninguna carrera aprueba.</p>
        <div class="divide-y divide-gray-100">
            @forelse ($todasNegativas as $c)
                <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ $c->empresa->razon_social }}</p>
                        <p class="text-xs text-gray-500">{{ $c->dictamenes->map(fn ($d) => $d->carrera->carrera_clave)->join(', ') }} · {{ $c->estado->label() }}</p>
                    </div>
                    <a href="{{ route('admin.convenios.show', $c) }}" class="text-brand-600 text-xs font-semibold">Ver →</a>
                </div>
            @empty
                <p class="py-3 text-center text-sm text-gray-400">Ninguno por ahora.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection