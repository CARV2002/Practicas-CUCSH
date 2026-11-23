@props([
    'icono',
    'etiqueta',            // tooltip y lector de pantalla
    'accion' => null,      // URL de un formulario POST; sin ella es un botón (x-on:click)
    'metodo' => 'POST',
    'confirmar' => null,
    'tono' => 'neutro',    // neutro | peligro
])

{{--
    Botón de ícono dentro de <x-ui.herramientas>: comparten un solo marco, así las acciones secundarias
    se leen como una barra y no como botones sueltos.
--}}
@php
    $color = $tono === 'peligro'
        ? 'text-gray-500 hover:bg-rose-50 hover:text-rose-600'
        : 'text-gray-500 hover:bg-udg-50 hover:text-udg-900';
    $clases = "group/btn relative flex size-9 items-center justify-center transition duration-150 ease-out active:scale-90 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40 motion-reduce:transition-none {$color}";
    $tooltip = 'pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 translate-y-1 whitespace-nowrap rounded-md bg-udg-950 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-theme-md transition duration-150 group-hover/btn:translate-y-0 group-hover/btn:opacity-100 group-focus-visible/btn:translate-y-0 group-focus-visible/btn:opacity-100';
@endphp

@if ($accion)
    <form method="POST" action="{{ $accion }}" class="flex" @if ($confirmar) data-confirmar="{{ $confirmar }}" data-confirmar-boton="Sí, {{ mb_strtolower($etiqueta) }}" @endif>
        @csrf
        @if (strtoupper($metodo) !== 'POST') @method($metodo) @endif
        {{ $slot }}
        <button type="submit" data-sin-bloqueo="1" aria-label="{{ $etiqueta }}" {{ $attributes->merge(['class' => $clases]) }}>
            <x-dashboard.icono :nombre="$icono" clase="size-[17px]" /><span class="{{ $tooltip }}" aria-hidden="true">{{ $etiqueta }}</span>
        </button>
    </form>
@else
    <button type="button" aria-label="{{ $etiqueta }}" {{ $attributes->merge(['class' => $clases]) }}>
        <x-dashboard.icono :nombre="$icono" clase="size-[17px]" /><span class="{{ $tooltip }}" aria-hidden="true">{{ $etiqueta }}</span>
    </button>
@endif
