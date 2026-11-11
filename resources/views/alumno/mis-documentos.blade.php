@extends('layouts.app')

@section('content')
@php
    use App\Enums\PostulacionEstado as PE;
    use App\Enums\PracticaEstado as PrE;
    use App\Enums\ReporteEstado as RE;

    $input = 'focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-3 focus:outline-hidden';
    $btn = 'bg-brand-500 hover:bg-brand-600 rounded-lg px-3 py-2 text-xs font-medium text-white';
    $card = 'overflow-hidden rounded-2xl border border-gray-200 bg-white';
    $head = 'border-b border-gray-100 px-6 py-4';

    $estadoDoc = fn ($m) => $m ? ($m->getCustomProperty('estado') ?? 'pendiente') : null;
    $colorDoc = fn ($s) => match ($s) {
        'validada' => 'bg-green-50 text-green-700',
        'rechazada' => 'bg-red-50 text-red-700',
        'pendiente' => 'bg-yellow-50 text-yellow-700',
        default => 'bg-gray-100 text-gray-500',
    };
    $imssSt = $estadoDoc($imss);
@endphp

<x-common.page-breadcrumb pageTitle="Mis documentos" />

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- IMSS --}}
    <div class="{{ $card }}">
        <div class="{{ $head }}">
            <h3 class="text-lg font-bold text-gray-900">Seguro facultativo (IMSS)</h3>
            <p class="text-sm text-gray-500">Requisito para activar tu práctica. Debe estar vigente durante todo el periodo.</p>
        </div>
        <div class="flex flex-col gap-4 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                @if ($imss)
                    <a href="{{ route('alumno.documentos.descargar', $imss) }}" class="text-brand-600 text-sm font-semibold hover:underline">{{ $imss->file_name }}</a>
                    <p class="text-xs text-gray-500">Vigencia declarada: {{ $alumno->vigencia_imss?->format('d/m/Y') ?? '—' }}
                        @if ($imssSt === 'rechazada' && $imss->getCustomProperty('motivo')) · <span class="text-red-600">{{ $imss->getCustomProperty('motivo') }}</span> @endif
                    </p>
                @else
                    <p class="text-sm text-gray-400">Sin constancia</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colorDoc($imssSt) }}">{{ $imssSt ? ucfirst($imssSt) : 'Pendiente de subir' }}</span>
                @if ($imssSt !== 'validada')
                    <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.imss') }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required class="{{ $input }} !w-52">
                        <input type="date" name="vigencia_imss" required min="{{ now()->addDay()->toDateString() }}" value="{{ old('vigencia_imss') }}" class="{{ $input }} !w-40" title="Vigencia">
                        <button class="{{ $btn }}">{{ $imss ? 'Reemplazar' : 'Subir' }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Postulaciones --}}
    <div class="{{ $card }}">
        <div class="{{ $head }}">
            <h3 class="text-lg font-bold text-gray-900">Documentos de mis postulaciones</h3>
            <p class="text-sm text-gray-500">Kardex, carta de presentación y carta de aceptación de cada programa.</p>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($postulaciones as $po)
                @php
                    $kardex = $po->getFirstMedia('kardex');
                    $presentacion = $po->getFirstMedia('carta_presentacion');
                    $aceptacion = $po->getFirstMedia('carta_aceptacion');
                    $abierta = in_array($po->estado, [PE::Pendiente, PE::CartaPresentacionEmitida], true);
                @endphp
                <div class="space-y-3 px-6 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $po->programa->titulo }}</p>
                            <p class="text-xs text-gray-500">{{ $po->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }} · {{ $po->programa->ciclo }}</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">{{ $po->estado->label() }}</span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <div class="rounded-lg border border-gray-100 p-3">
                            <p class="text-xs font-bold uppercase text-gray-400">Kardex</p>
                            @if ($kardex)<a href="{{ route('alumno.documentos.descargar', $kardex) }}" class="text-brand-600 text-xs font-semibold">{{ $kardex->file_name }}</a>@else<p class="text-xs text-gray-400">Sin archivo</p>@endif
                            @if ($abierta)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.kardex', $po) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required class="{{ $input }} text-xs">
                                    <button class="{{ $btn }}">{{ $kardex ? 'Reemplazar' : 'Subir' }}</button>
                                </form>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-100 p-3">
                            <p class="text-xs font-bold uppercase text-gray-400">Carta de presentación</p>
                            @if ($presentacion)<a href="{{ route('alumno.documentos.descargar', $presentacion) }}" class="text-brand-600 text-xs font-semibold">Descargar PDF</a>
                            @elseif ($po->estado === PE::Pendiente)<p class="text-xs text-gray-400">Pendiente de validación por la Coordinación</p>
                            @else<p class="text-xs text-gray-400">Sin archivo</p>@endif
                        </div>

                        <div class="rounded-lg border border-gray-100 p-3">
                            <p class="text-xs font-bold uppercase text-gray-400">Carta de aceptación</p>
                            @if ($aceptacion)<a href="{{ route('alumno.documentos.descargar', $aceptacion) }}" class="text-brand-600 text-xs font-semibold">{{ $aceptacion->file_name }}</a>@else<p class="text-xs text-gray-400">Sin archivo</p>@endif
                            @if ($po->estado === PE::CartaPresentacionEmitida)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.aceptacion', $po) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input type="file" name="archivo" accept="application/pdf" required class="{{ $input }} text-xs">
                                    <button class="{{ $btn }}">{{ $aceptacion ? 'Reemplazar' : 'Subir' }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-6 py-10 text-center text-sm text-gray-400">Aún no te has postulado a ningún programa.</p>
            @endforelse
        </div>
    </div>

    {{-- Prácticas --}}
    @foreach ($practicas as $pr)
        @php
            $activa = in_array($pr->estado, PrE::activas(), true);
            $carta = $pr->cartaTermino?->getFirstMedia('carta_termino');
            $cartaSt = $pr->cartaTermino ? $pr->cartaTermino->estado_validacion->value : null;
        @endphp
        <div class="{{ $card }}">
            <div class="{{ $head }} flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $pr->postulacion->programa->titulo }}</h3>
                    <p class="text-sm text-gray-500">{{ $pr->fecha_inicio->format('d/m/Y') }} – {{ $pr->fecha_fin->format('d/m/Y') }} · reportes y carta de término</p>
                </div>
                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">{{ $pr->estado->label() }}</span>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($pr->reportes->sortByDesc('periodo_fin') as $r)
                    @php
                        $pdf = $r->media->firstWhere('collection_name', 'reporte_firmado');
                        $c = match ($r->estado) { RE::Validado => 'bg-green-50 text-green-700', RE::Rechazado => 'bg-red-50 text-red-700', default => 'bg-yellow-50 text-yellow-700' };
                    @endphp
                    <div class="flex flex-col gap-3 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Reporte {{ $r->tipo->value }} · {{ $r->horas_reportadas }} h</p>
                            <p class="text-xs text-gray-500">{{ $r->periodo_inicio->format('d/m/Y') }} – {{ $r->periodo_fin->format('d/m/Y') }}
                                @if ($pdf) · <a href="{{ route('alumno.documentos.descargar', $pdf) }}" class="text-brand-600 font-semibold">{{ $pdf->file_name }}</a> @endif</p>
                            @if ($r->evaluacion_profesor)<p class="text-xs text-gray-500">Evaluación: {{ $r->evaluacion_profesor }}</p>@endif
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $c }}">{{ ucfirst($r->estado->value) }}</span>
                            @if ($activa && $r->estado === RE::Pendiente)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.reporte', $r) }}" class="flex gap-2">
                                    @csrf
                                    <input type="file" name="archivo" accept="application/pdf" required class="{{ $input }} !w-52 text-xs">
                                    <button class="{{ $btn }}">{{ $pdf ? 'Reemplazar' : 'Subir firmado' }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-6 py-6 text-center text-sm text-gray-400">Aún no hay reportes registrados.</p>
                @endforelse

                {{-- Carta de término --}}
                <div class="flex flex-col gap-3 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Carta de término</p>
                        @if ($carta)<a href="{{ route('alumno.documentos.descargar', $carta) }}" class="text-brand-600 text-xs font-semibold">{{ $carta->file_name }}</a>
                        @else<p class="text-xs text-gray-400">Se sube al completar tus horas.</p>@endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($cartaSt)<span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colorDoc($cartaSt) }}">{{ ucfirst($cartaSt) }}</span>@endif
                        @if ($activa && $cartaSt !== 'validada')
                            <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.termino', $pr) }}" class="flex gap-2">
                                @csrf
                                <input type="file" name="archivo" accept="application/pdf" required class="{{ $input }} !w-52 text-xs">
                                <button class="{{ $btn }}">{{ $carta ? 'Reemplazar' : 'Subir' }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection