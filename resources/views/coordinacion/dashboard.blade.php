@extends('layouts.app')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5';
    $panel = 'rounded-2xl border border-gray-200 bg-white p-6';
    $totalPracticas = max(1, $practicas_por_estado->sum());
    $pend = [
        ['Postulaciones por validar', $pendientes['postulaciones'], route('coordinacion.lista-practicantes')],
        ['Reportes por validar', $pendientes['reportes'], route('coordinacion.lista-reportes')],
        ['Cierres por atender', $pendientes['cierres'], route('coordinacion.lista-cierres')],
        ['Vacantes por aprobar', $pendientes['vacantes_por_aprobar'], route('coordinacion.lista-programas', ['estado' => 'pendiente_aprobacion'])],
    ];
@endphp

<x-common.page-breadcrumb pageTitle="Panel de Coordinación" />

<div class="space-y-6">

    <p class="text-sm text-gray-500">
        Alcance:
        @foreach ($carreras as $c)
            <span class="mr-1 inline-block rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700" title="{{ $c->nombre }}">{{ $c->carrera_clave }}</span>
        @endforeach
    </p>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Alumnos registrados</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($kpis['alumnos']) }}</h3>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Prácticas activas</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['practicas_activas'] }}</h3>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Programas publicados</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['programas_publicados'] }}</h3>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Ocupación de plazas</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['ocupacion'] }}%</h3>
            <div class="mt-2 h-1.5 w-full rounded-full bg-gray-200"><div class="bg-brand-500 h-1.5 rounded-full" style="width: {{ $kpis['ocupacion'] }}%"></div></div>
            <p class="mt-1 text-xs text-gray-500">{{ $kpis['cupos_ocupados'] }} de {{ $kpis['cupos_totales'] }} cupos publicados</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Pendientes --}}
        <div class="{{ $panel }}">
            <h4 class="text-base font-bold text-gray-900">Pendientes de atención</h4>
            <p class="mb-3 text-xs text-gray-500">Lo que requiere acción de tu coordinación.</p>
            <ul class="divide-y divide-gray-100">
                @foreach ($pend as [$texto, $n, $url])
                    <li>
                        <a href="{{ $url }}" class="flex items-center justify-between py-2.5 text-sm hover:text-brand-600">
                            <span class="text-gray-700">{{ $texto }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $n ? 'bg-yellow-50 text-yellow-700' : 'bg-gray-100 text-gray-400' }}">{{ $n }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Prácticas por estado --}}
        <div class="{{ $panel }} lg:col-span-2">
            <h4 class="mb-3 text-base font-bold text-gray-900">Prácticas por estado</h4>
            <div class="space-y-3">
                @foreach ($practicas_por_estado as $estado => $n)
                    <div>
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="text-gray-700">{{ $estado }}</span>
                            <span class="font-semibold text-gray-900">{{ $n }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-gray-100"><div class="bg-brand-500 h-1.5 rounded-full" style="width: {{ round($n / $totalPracticas * 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Programas recientes --}}
    <div class="{{ $panel }}">
        <div class="mb-3 flex items-center justify-between">
            <h4 class="text-base font-bold text-gray-900">Programas recientes</h4>
            <a href="{{ route('coordinacion.lista-programas') }}" class="text-brand-600 text-xs font-semibold hover:underline">Ver todos</a>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($programas_recientes as $p)
                <div class="flex items-center justify-between py-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ $p->titulo }}</p>
                        <p class="text-xs text-gray-500">{{ $p->carrera->carrera_clave }} · {{ $p->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</p>
                    </div>
                    <div class="text-right">
                        <span class="block text-sm font-bold text-gray-800">{{ (int) $p->cupos_ocupados }}/{{ $p->cupos_totales }}</span>
                        <span class="text-xs text-gray-400">{{ ucfirst($p->estado->value) }}</span>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">Aún no hay programas.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
