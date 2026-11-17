@props(['carrera'])

{{--
    «Tu coordinación»: el alumno resuelve su proceso (postulación, cartas, reportes, liberación)
    con la Coordinación de su carrera. Vinculación solo atiende convenios con empresas.
--}}
@php
    $coordinador = $carrera->coordinadores()->first();
    $correo = $carrera->coordinacion_correo ?: $coordinador?->email;
    $telefono = $carrera->coordinacion_telefono;
@endphp

<section {{ $attributes->merge(['class' => 'tarjeta p-5']) }} aria-labelledby="tu-coordinacion-titulo"
    x-data="{ copiado: false, copiar(t) { navigator.clipboard?.writeText(t).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000) }) } }">
    <h2 id="tu-coordinacion-titulo" class="text-xs font-semibold uppercase tracking-wider text-gray-500">Tu coordinación</h2>
    <p class="mt-1 text-base font-bold text-gray-900">Coordinación de {{ $carrera->nombre }}</p>
    @if ($coordinador)
        <p class="text-sm text-gray-600">{{ $coordinador->name }}</p>
    @endif
    <p class="mt-1 text-xs text-gray-500">Para tu postulación, cartas, reportes y liberación acude a tu coordinación. Vinculación solo atiende convenios con empresas.</p>

    @if ($correo)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <a href="{{ \App\Support\Correo::gmail($correo, 'Prácticas profesionales · ' . $carrera->carrera_clave) }}" target="_blank" rel="noopener"
                class="bg-udg-800 hover:bg-udg-700 rounded-lg px-3 py-2 text-sm font-medium text-white">Escribir por Gmail</a>
            <button type="button" @click="copiar(@js($correo))"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <span x-show="!copiado">Copiar correo</span>
                <span x-show="copiado" style="display: none">¡Copiado!</span>
            </button>
            <span class="text-sm text-gray-700">{{ $correo }}</span>
        </div>
    @else
        <p class="mt-3 text-sm text-gray-500">Tu coordinación aún no registra su correo de contacto.</p>
    @endif

    @if ($telefono)
        <p class="mt-2 text-sm text-gray-700">Teléfono: {{ $telefono }}</p>
    @endif
</section>
