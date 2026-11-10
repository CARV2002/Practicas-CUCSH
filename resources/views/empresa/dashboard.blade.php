@extends('layouts.app')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5';
    $panel = 'rounded-2xl border border-gray-200 bg-white p-6';
    $ocupacion = $kpis['cupos_totales'] > 0 ? round($kpis['cupos_ocupados'] / $kpis['cupos_totales'] * 100) : 0;
@endphp

<x-common.page-breadcrumb pageTitle="Panel de empresa" />

<div class="space-y-6">

    {{-- Avisos --}}
    @if (! $vigente && ! $enTramite)
        <div class="rounded-xl border border-yellow-500 bg-yellow-50 p-4 text-sm text-yellow-800">
            Aún no tienes un convenio con el CUCSH. <a href="{{ route('empresa.convenio') }}" class="font-semibold underline">Solicítalo aquí</a> para poder ofertar programas.
        </div>
    @elseif ($vigente && $diasRestantes <= 60)
        <div class="rounded-xl border border-yellow-500 bg-yellow-50 p-4 text-sm text-yellow-800">
            Tu convenio vence en {{ $diasRestantes }} días. <a href="{{ route('empresa.convenio') }}" class="font-semibold underline">Solicita la renovación</a>.
        </div>
    @endif

    @if (! $expedienteCompleto)
        <div class="rounded-xl border border-blue-500 bg-blue-50 p-4 text-sm text-blue-800">
            Tu expediente está incompleto o con documentos sin validar. <a href="{{ route('empresa.documentos') }}" class="font-semibold underline">Revisar documentos</a>.
        </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Practicantes activos</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['practicantes_activos'] }}</h3>
            <p class="mt-1 text-xs text-gray-500">{{ $kpis['acreditados'] }} acreditados</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Programas publicados</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $kpis['programas_publicados'] }}</h3>
            <p class="mt-1 text-xs text-gray-500">{{ $programasSinPublicar }} en borrador</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Ocupación de cupos</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $ocupacion }}%</h3>
            <div class="mt-2 h-1.5 w-full rounded-full bg-gray-200"><div class="bg-brand-500 h-1.5 rounded-full" style="width: {{ $ocupacion }}%"></div></div>
            <p class="mt-1 text-xs text-gray-500">{{ $kpis['cupos_libres'] }} cupos libres</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Convenio</p>
            @if ($vigente)
                <h3 class="mt-1 flex items-center gap-1.5 text-lg font-bold text-green-700"><span class="h-2 w-2 rounded-full bg-green-600"></span> Vigente</h3>
                <p class="mt-1 text-xs text-gray-500">Vence {{ $vigente->fecha_fin_vigencia->format('d/m/Y') }}</p>
            @elseif ($enTramite)
                <h3 class="mt-1 text-lg font-bold text-blue-700">En trámite</h3>
                <p class="mt-1 text-xs text-gray-500">{{ $enTramite->estado->label() }}</p>
            @else
                <h3 class="mt-1 text-lg font-bold text-gray-500">Sin convenio</h3>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Retroalimentación pendiente --}}
        <div class="{{ $panel }} lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h4 class="text-base font-bold text-gray-900">Reportes sin tu retroalimentación</h4>
                    <p class="text-xs text-gray-500">Reportes de horas de tus practicantes que aún no has comentado.</p>
                </div>
                <a href="{{ route('empresa.lista-practicantes') }}" class="text-brand-600 text-xs font-semibold hover:underline">Ver practicantes</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($reportesPendientes as $r)
                    @php $al = $r->practica->postulacion->alumno; @endphp
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $al->user->name }}</p>
                            <p class="text-xs text-gray-500">{{ ucfirst($r->tipo->value) }} · {{ $r->horas_reportadas }} h · {{ $r->periodo_inicio->format('d/m') }} – {{ $r->periodo_fin->format('d/m/Y') }}</p>
                        </div>
                        <a href="{{ route('empresa.practicantes.show', $r->practica) }}" class="bg-brand-500 hover:bg-brand-600 rounded-lg px-3 py-1.5 text-xs font-semibold text-white">Comentar</a>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-gray-400">No tienes reportes pendientes de comentar.</p>
                @endforelse
            </div>
        </div>

        {{-- Accesos rápidos --}}
        <div class="{{ $panel }}">
            <h4 class="mb-3 text-base font-bold text-gray-900">Accesos rápidos</h4>
            <div class="space-y-2">
                <a href="{{ route('empresa.lista-programas') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-brand-300 hover:bg-gray-50">Mis programas <span>→</span></a>
                <a href="{{ route('empresa.convenio') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-brand-300 hover:bg-gray-50">Convenio CUCSH <span>→</span></a>
                <a href="{{ route('empresa.documentos') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-brand-300 hover:bg-gray-50">Documentos <span>→</span></a>
            </div>
        </div>
    </div>

    {{-- Candidatos con carta de presentación --}}
    <div class="{{ $panel }}">
        <h4 class="text-base font-bold text-gray-900">Alumnos con carta de presentación</h4>
        <p class="mb-4 text-xs text-gray-500">Alumnos validados por la Coordinación que se presentarán en tus programas. Al aceptarlos, entrega la carta de aceptación sellada al alumno.</p>
        <div class="divide-y divide-gray-100">
            @forelse ($candidatos as $po)
                <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $po->alumno->user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $po->alumno->carrera->nombre }} · {{ $po->programa->titulo }}</p>
                    </div>
                    <a href="{{ route('empresa.programas.postulantes', $po->programa) }}" class="text-brand-600 text-xs font-semibold">Ver programa →</a>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">Sin alumnos pendientes de presentarse.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection