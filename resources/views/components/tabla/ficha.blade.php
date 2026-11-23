@props([
    'href' => null,          // a dónde lleva (página de detalle); sin href es un botón (x-on:click)
    'titulo',
    'subtitulo' => null,
    'inicial' => null,       // letra del avatar (si no hay ícono)
    'icono' => null,         // ícono de x-dashboard.icono
    'avatar' => null,        // usuario para x-ui.user-avatar
    'ver' => 'Ver ficha',    // texto que aparece al pasar el cursor
])

{{--
    Celda principal de una fila que abre su detalle. Al pasar el cursor: el fondo se tiñe, el avatar se vuelve
    azul sólido, el título cambia de color y aparece «Ver ficha →». Mismo comportamiento en todas las tablas.
    Lo que va en el slot se muestra debajo (datos extra, avisos).
--}}
@php
    $tag = $href ? 'a' : 'button';
    $clases = 'group/ficha -mx-2 flex w-[calc(100%+1rem)] items-start gap-3 rounded-xl px-2 py-1.5 text-left transition duration-200 ease-out hover:bg-udg-50/80 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:transition-none';
    $marco = 'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-udg-50 font-display text-sm font-bold text-udg-900 ring-1 ring-inset ring-udg-100 transition duration-200 ease-out group-hover/ficha:scale-105 group-hover/ficha:bg-udg-800 group-hover/ficha:text-white group-hover/ficha:ring-udg-800 motion-reduce:transform-none';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @else type="button" @endif title="{{ $ver }}" {{ $attributes->merge(['class' => $clases]) }}>
    @if ($avatar)
        <span class="mt-0.5 shrink-0 rounded-full transition duration-200 ease-out group-hover/ficha:scale-105 group-hover/ficha:ring-2 group-hover/ficha:ring-udg-800 motion-reduce:transform-none">
            <x-ui.user-avatar :user="$avatar" size="size-9 text-xs ring-2 ring-white" />
        </span>
    @elseif ($icono || $inicial !== null)
        <span class="{{ $marco }}" aria-hidden="true">
            @if ($icono)<x-dashboard.icono :nombre="$icono" clase="size-[18px]" />@else{{ $inicial }}@endif
        </span>
    @endif
    <span class="min-w-0 flex-1">
        <span class="block break-words font-semibold leading-snug text-gray-900 transition-colors duration-200 group-hover/ficha:text-udg-800">{{ $titulo }}</span>
        <span class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
            @if ($subtitulo)<span class="truncate">{{ $subtitulo }}</span>@endif
            <span class="inline-flex shrink-0 -translate-x-1 items-center gap-0.5 font-semibold text-udg-800 opacity-0 transition duration-200 group-hover/ficha:translate-x-0 group-hover/ficha:opacity-100 group-focus-visible/ficha:translate-x-0 group-focus-visible/ficha:opacity-100 motion-reduce:transform-none">
                {{ $ver }} <x-dashboard.icono nombre="flecha" clase="size-3" />
            </span>
        </span>
        @if (trim($slot) !== '')
            <span class="mt-0.5 block text-xs text-gray-500">{{ $slot }}</span>
        @endif
    </span>
</{{ $tag }}>
