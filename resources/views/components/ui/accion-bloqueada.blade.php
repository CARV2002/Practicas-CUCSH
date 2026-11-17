@props(['motivo', 'tamano' => 'normal'])

{{--
    Botón deshabilitado con su explicación: el usuario ve qué acción existe y qué le falta para usarla.
    $motivo puede ser un texto o una lista de textos.
--}}
@php
    $motivos = array_values(array_filter((array) $motivo));
    $boton = $tamano === 'chico' ? 'px-3 py-1.5 text-xs' : 'px-4 py-2.5 text-sm';
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex flex-col items-end gap-1']) }}>
    <button type="button" disabled aria-disabled="true" title="{{ implode(' ', $motivos) }}"
        class="{{ $boton }} inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-100 font-medium text-gray-400">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        {{ $slot }}
    </button>
    @foreach ($motivos as $m)
        <span class="max-w-xs text-right text-xs leading-snug text-warning-700">{{ $m }}</span>
    @endforeach
</div>
