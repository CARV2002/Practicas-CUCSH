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
    $tooltip = 'tooltip-boton';
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
