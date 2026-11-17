@props(['titulo' => 'Directorio de coordinaciones de carrera'])

{{--
    Directorio público: el alumno resuelve su trámite con la Coordinación de su carrera.
    Cada coordinación captura su correo y teléfono en «Reglas de mi carrera».
--}}
@php
    $divisiones = \App\Models\Division::with(['carreras' => fn ($q) => $q->orderBy('nombre')])
        ->whereHas('carreras')
        ->orderBy('nombre')
        ->get();
@endphp

<div {{ $attributes->merge(['class' => 'mx-auto w-full max-w-5xl']) }}
    x-data="{ copiado: null, copiar(t) { navigator.clipboard?.writeText(t).then(() => { this.copiado = t; setTimeout(() => this.copiado = null, 2000) }) } }">
    <h2 class="mb-1 text-2xl font-bold text-gray-900">{{ $titulo }}</h2>
    <p class="mb-6 text-sm text-gray-600">
        Postulación, carta de presentación, reportes, carta de término y liberación se atienden en la coordinación de tu carrera.
        Vinculación solo atiende convenios con organizaciones.
    </p>

    <div class="space-y-6">
        @foreach ($divisiones as $division)
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                <h3 class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-semibold text-gray-700">{{ $division->nombre }}</h3>
                <ul class="divide-y divide-gray-100">
                    @foreach ($division->carreras as $c)
                        <li class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $c->nombre }}</p>
                                @if ($c->coordinacion_telefono)
                                    <p class="text-xs text-gray-500">Tel. {{ $c->coordinacion_telefono }}</p>
                                @endif
                            </div>
                            @if ($c->coordinacion_correo)
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <a href="{{ \App\Support\Correo::gmail($c->coordinacion_correo, 'Prácticas profesionales · ' . $c->carrera_clave) }}"
                                        target="_blank" rel="noopener" class="font-semibold text-blue-700 underline">{{ $c->coordinacion_correo }}</a>
                                    <button type="button" @click="copiar(@js($c->coordinacion_correo))"
                                        class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        aria-label="Copiar correo de la coordinación de {{ $c->nombre }}">
                                        <span x-text="copiado === @js($c->coordinacion_correo) ? '¡Copiado!' : 'Copiar correo'">Copiar correo</span>
                                    </button>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">Correo por confirmar: acude a tu división</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
