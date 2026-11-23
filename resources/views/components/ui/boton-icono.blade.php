@props([
    'icono',               // nombre del ícono (componente dashboard.icono)
    'etiqueta',            // texto de la tarea: tooltip y lector de pantalla
    'href' => null,        // enlace
    'accion' => null,      // URL de un formulario POST
    'metodo' => 'POST',
    'confirmar' => null,   // texto de confirmación antes de enviar
    'tono' => 'neutro',    // neutro | primario | exito | peligro
    'conTexto' => false,   // muestra la etiqueta junto al ícono (acción principal de la fila)
])

{{--
    Botón de ícono con tooltip. Toda acción de tabla usa este componente para verse y comportarse igual.
--}}
@php
    $tonos = [
        'neutro' => 'border-gray-200 bg-white text-gray-600 hover:border-udg-200 hover:bg-udg-50 hover:text-udg-900',
        'primario' => 'border-udg-800 bg-udg-800 text-white hover:border-udg-700 hover:bg-udg-700',
        'exito' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100',
        'peligro' => 'border-rose-200 bg-white text-rose-600 hover:border-rose-300 hover:bg-rose-50',
    ];
    $clases = 'group/btn relative inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border text-sm font-semibold shadow-theme-xs transition duration-150 ease-out active:scale-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:transition-none '
        . ($conTexto ? 'px-3 ' : 'w-9 ') . ($tonos[$tono] ?? $tonos['neutro']);
    $tooltip = 'pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 translate-y-1 whitespace-nowrap rounded-md bg-udg-950 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-theme-md transition duration-150 group-hover/btn:translate-y-0 group-hover/btn:opacity-100 group-focus-visible/btn:translate-y-0 group-focus-visible/btn:opacity-100';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases]) }} @unless ($conTexto) aria-label="{{ $etiqueta }}" @endunless>
        <x-dashboard.icono :nombre="$icono" clase="size-4" />
        @if ($conTexto)<span>{{ $etiqueta }}</span>@else<span class="{{ $tooltip }}" aria-hidden="true">{{ $etiqueta }}</span>@endif
    </a>
@elseif ($accion)
    <form method="POST" action="{{ $accion }}" class="inline-flex" @if ($confirmar) data-confirmar="{{ $confirmar }}" data-confirmar-boton="Sí, {{ mb_strtolower($etiqueta) }}" @endif>
        @csrf
        @if (strtoupper($metodo) !== 'POST') @method($metodo) @endif
        {{ $slot }}
        <button type="submit" data-sin-bloqueo="1" {{ $attributes->merge(['class' => $clases]) }} @unless ($conTexto) aria-label="{{ $etiqueta }}" @endunless>
            <x-dashboard.icono :nombre="$icono" clase="size-4" />
            @if ($conTexto)<span>{{ $etiqueta }}</span>@else<span class="{{ $tooltip }}" aria-hidden="true">{{ $etiqueta }}</span>@endif
        </button>
    </form>
@else
    <button type="button" {{ $attributes->merge(['class' => $clases]) }} @unless ($conTexto) aria-label="{{ $etiqueta }}" @endunless>
        <x-dashboard.icono :nombre="$icono" clase="size-4" />
        @if ($conTexto)<span>{{ $etiqueta }}</span>@else<span class="{{ $tooltip }}" aria-hidden="true">{{ $etiqueta }}</span>@endif
    </button>
@endif
