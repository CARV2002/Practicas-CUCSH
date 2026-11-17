@extends('layouts.app')

@section('content')
@php
    $card = 'tarjeta p-5';
    $panel = 'tarjeta p-6';
    $totalConvenios = max(1, $convenios_por_estado->sum());
    $pend = [
        ['Solicitudes nuevas', $pendientes['solicitudes'], route('admin.lista-convenios', ['estado' => 'solicitado'])],
        ['Dictámenes por carrera', $pendientes['dictamenes'], route('admin.lista-dictamenes')],
        ['En Unidad Jurídica', $pendientes['juridica'], route('admin.lista-convenios', ['estado' => 'en_revision_juridica'])],
        ['Firmas pendientes', $pendientes['firmas'], route('admin.lista-convenios', ['estado' => 'en_firmas'])],
    ];
@endphp

<x-common.page-breadcrumb pageTitle="Panel de Vinculación" />

<div class="space-y-6">

    {{-- KPIs de convenios --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Convenios vigentes</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['vigentes'] }}</h3>
            <p class="mt-1 text-xs text-gray-500">formalizados y dentro de su vigencia</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Trámites abiertos</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['en_tramite'] }}</h3>
            <p class="mt-1 text-xs text-gray-500">{{ $pendientes['solicitudes'] }} solicitud(es) nueva(s)</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Empresas con convenio vigente</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['empresas_con_convenio'] }}<span class="text-sm font-normal text-gray-400"> / {{ $kpis['empresas'] }}</span></h3>
            <p class="mt-1 text-xs text-gray-500">empresas registradas</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Convenios por vencer</p>
            <h3 class="mt-1 text-2xl font-bold {{ $kpis['por_vencer_60'] ? 'text-red-600' : 'text-gray-900' }}">{{ $kpis['por_vencer_60'] }}</h3>
            <p class="mt-1 text-xs text-gray-500">en 60 días · {{ $kpis['por_vencer_30'] }} en 30 días</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Pendientes --}}
        <div class="{{ $panel }}">
            <h4 class="text-base font-bold text-gray-900">Pendientes de convenios</h4>
            <p class="mb-3 text-xs text-gray-500">Lo que requiere acción de Vinculación.</p>
            <ul class="divide-y divide-gray-100">
                @foreach ($pend as [$texto, $n, $url])
                    <li>
                        <a href="{{ $url }}" class="flex items-center justify-between py-2.5 text-sm hover:text-real-700">
                            <span class="text-gray-700">{{ $texto }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $n ? 'bg-yellow-50 text-yellow-700' : 'bg-gray-100 text-gray-400' }}">{{ $n }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        {{-- Cuellos de botella --}}
        <div class="{{ $panel }} lg:col-span-2">
            <h4 class="text-base font-bold text-gray-900">Cuellos de botella</h4>
            <p class="mb-3 text-xs text-gray-500">Etapas abiertas que rebasan el máximo documentado de días hábiles.</p>
            <div class="divide-y divide-gray-100">
                @forelse ($cuellos as $s)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $s->convenio->empresa->razon_social }}</p>
                            <p class="text-xs text-gray-500">{{ $s->etapa->label() }} · {{ $s->etapa->responsable() }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded bg-red-50 px-2 py-0.5 text-xs font-bold text-red-700">{{ $s->diasHabiles() }} días hábiles</span>
                            <a href="{{ route('admin.convenios.show', $s->convenio) }}" class="text-real-600 text-xs font-semibold">Gestionar →</a>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">Ningún trámite excede su tiempo de espera.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Convenios por vencer --}}
        <div class="{{ $panel }}">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-base font-bold text-gray-900">Convenios por vencer</h4>
                <a href="{{ route('admin.lista-convenios') }}" class="text-real-600 text-xs font-semibold hover:underline">Ver todos</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($convenios_por_vencer as $c)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $c->empresa->razon_social }}</p>
                            <p class="text-xs text-gray-500">{{ $c->folio }}</p>
                        </div>
                        <div class="text-right">
                            <span class="block text-xs font-semibold text-red-600">{{ $c->fecha_fin_vigencia->format('d/m/Y') }}</span>
                            <span class="text-xs text-gray-400">en {{ (int) now()->diffInDays($c->fecha_fin_vigencia, false) }} días</span>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">Sin vencimientos próximos.</p>
                @endforelse
            </div>
        </div>

        {{-- Convenios por estado --}}
        <div class="{{ $panel }}">
            <h4 class="mb-3 text-base font-bold text-gray-900">Convenios por estado</h4>
            <div class="space-y-3">
                @foreach ($convenios_por_estado as $estado => $n)
                    <div>
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="text-gray-700">{{ $estado }}</span>
                            <span class="font-semibold text-gray-900">{{ $n }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-gray-100"><div class="bg-udg-800 h-1.5 rounded-full" style="width: {{ round($n / $totalConvenios * 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6">
        {{-- Convenios recientes --}}
        <div class="{{ $panel }}">
            <h4 class="mb-3 text-base font-bold text-gray-900">Convenios recientes</h4>
            <div class="divide-y divide-gray-100">
                @forelse ($convenios_recientes as $c)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $c->empresa->razon_social }}</p>
                            <p class="text-xs text-gray-500">Recibido {{ $c->fecha_recepcion->format('d/m/Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-ui.estatus :estado="$c->estado" />
                            <a href="{{ route('admin.convenios.show', $c) }}" class="text-real-600 text-xs font-semibold">Ver</a>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">Aún no hay convenios.</p>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection
