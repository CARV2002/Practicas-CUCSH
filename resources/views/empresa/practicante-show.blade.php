@extends('layouts.app')

@section('content')
@php
    use App\Enums\ReporteEstado as RE;

    $horas = (int) $practica->horas_acumuladas;
    $pct = $practica->horas_requeridas > 0 ? min(100, round($horas / $practica->horas_requeridas * 100)) : 0;
    $input = 'focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-3 focus:outline-hidden';
    $editable = in_array($practica->estado, \App\Enums\PracticaEstado::activas(), true);
@endphp

<x-common.page-breadcrumb pageTitle="Practicante · {{ $alumno->user->name }}" />

<div class="space-y-6">
    <a href="{{ route('empresa.lista-practicantes') }}" class="text-sm text-gray-500 hover:text-gray-700">← Volver a practicantes</a>

    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900">{{ $alumno->user->name }}</h3>
                <p class="text-sm text-gray-500">{{ $alumno->num_control }} · {{ $alumno->carrera->nombre }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $practica->postulacion->programa->titulo }} · {{ $practica->fecha_inicio->format('d/m/Y') }} – {{ $practica->fecha_fin->format('d/m/Y') }}</p>
                <p class="text-sm text-gray-500">Supervisor UdeG: {{ $practica->supervisor?->nombre ?? 'Por asignar' }}</p>
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $practica->estado->label() }}</span>
        </div>
        <div class="mt-5 max-w-md">
            <div class="mb-1 flex items-baseline justify-between">
                <span class="text-sm font-bold text-gray-900">{{ $horas }}<span class="text-xs font-normal text-gray-400"> / {{ $practica->horas_requeridas }} h validadas</span></span>
                <span class="text-brand-600 text-xs font-semibold">{{ $pct }}%</span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-100"><div class="bg-brand-500 h-2 rounded-full" style="width: {{ $pct }}%"></div></div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="border-b border-gray-100 px-6 py-4">
            <h4 class="text-base font-bold text-gray-900">Reportes de horas</h4>
            <p class="text-xs text-gray-500">La Coordinación valida las horas; tú aportas tu retroalimentación sobre el desempeño.</p>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($reportes as $r)
                @php
                    $pdf = $r->media->firstWhere('collection_name', 'reporte_firmado');
                    $color = match ($r->estado) {
                        RE::Validado => 'bg-green-50 text-green-700',
                        RE::Rechazado => 'bg-red-50 text-red-700',
                        default => 'bg-yellow-50 text-yellow-700',
                    };
                @endphp
                <div class="px-6 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ ucfirst($r->tipo->value) }} · {{ $r->horas_reportadas }} h</p>
                            <p class="text-xs text-gray-500">{{ $r->periodo_inicio->format('d/m/Y') }} – {{ $r->periodo_fin->format('d/m/Y') }}
                                @if ($pdf) · <a href="{{ route('empresa.reportes.archivos', [$r, $pdf]) }}" class="text-brand-600 font-semibold">Descargar PDF</a> @endif
                            </p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $color }}">{{ ucfirst($r->estado->value) }}</span>
                    </div>

                    @if ($editable)
                        <form method="POST" action="{{ route('empresa.reportes.retroalimentar', $r) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <textarea name="retroalimentacion_empresa" rows="2" required maxlength="2000" placeholder="Retroalimentación sobre el desempeño del practicante..." class="{{ $input }}">{{ old('retroalimentacion_empresa', $r->retroalimentacion_empresa) }}</textarea>
                            <button class="bg-brand-500 hover:bg-brand-600 self-start rounded-lg px-4 py-2 text-sm font-medium text-white">Guardar</button>
                        </form>
                    @elseif ($r->retroalimentacion_empresa)
                        <p class="mt-2 text-xs text-gray-600">Tu retroalimentación: {{ $r->retroalimentacion_empresa }}</p>
                    @endif
                </div>
            @empty
                <p class="px-6 py-10 text-center text-sm text-gray-400">Este practicante aún no tiene reportes.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection