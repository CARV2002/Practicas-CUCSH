@props([
    'icono',
    'etiqueta',
    'accion' => null,      // URL de un formulario POST; sin ella es un botón (x-on:click)
    'metodo' => 'POST',
    'confirmar' => null,
    'tono' => 'contorno',  // primario | exito | contorno | suave
])

{{--
    Acción principal de una fila: un solo botón con texto, siempre del mismo ancho (ocupa su columna),
    para que la tabla se lea en una línea vertical. El color indica la jerarquía:
      primario = algo espera tu decisión · exito = publicar · contorno = acción normal · suave = solo consultar.
--}}
@php
    $tonos = [
        'primario' => 'border-transparent bg-udg-800 text-white shadow-theme-sm hover:bg-udg-700 hover:shadow-theme-md',
        'exito' => 'border-transparent bg-emerald-600 text-white shadow-theme-sm hover:bg-emerald-500 hover:shadow-theme-md',
        'contorno' => 'border-gray-200 bg-white text-gray-700 shadow-theme-xs hover:border-udg-200 hover:bg-udg-50 hover:text-udg-900',
        'suave' => 'border-transparent bg-transparent text-gray-500 hover:bg-gray-100 hover:text-gray-800',
    ];
    $clases = 'inline-flex h-9 w-full items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border px-3 text-[13px] font-semibold tracking-[-0.005em] '
        . 'transition duration-150 ease-out hover:-translate-y-px active:translate-y-0 active:scale-[.98] '
        . 'focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:transform-none motion-reduce:transition-none '
        . ($tonos[$tono] ?? $tonos['contorno']);
@endphp

@if ($accion)
    <form method="POST" action="{{ $accion }}" class="w-full" @if ($confirmar) data-confirmar="{{ $confirmar }}" data-confirmar-boton="Sí, {{ mb_strtolower($etiqueta) }}" @endif>
        @csrf
        @if (strtoupper($metodo) !== 'POST') @method($metodo) @endif
        {{ $slot }}
        <button type="submit" data-sin-bloqueo="1" {{ $attributes->merge(['class' => $clases]) }}>
            <x-dashboard.icono :nombre="$icono" clase="size-4 shrink-0" /><span>{{ $etiqueta }}</span>
        </button>
    </form>
@else
    <button type="button" {{ $attributes->merge(['class' => $clases]) }}>
        <x-dashboard.icono :nombre="$icono" clase="size-4 shrink-0" /><span>{{ $etiqueta }}</span>
    </button>
@endif
