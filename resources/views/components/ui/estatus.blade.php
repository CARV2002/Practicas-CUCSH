{{--
    Insignia de estatus (DESIGN_SYSTEM.md §1.4): pastel, con punto de color y siempre con texto.
    Uso: <x-ui.estatus :estado="$convenio->estado" />
         <x-ui.estatus :estado="$c->estado" :texto="$c->estado->etiquetaEmpresa()" />
         <x-ui.estatus familia="menta" texto="Vigente" tamano="normal" />
--}}
@props([
    'estado' => null,
    'familia' => null,
    'texto' => null,
    'tamano' => 'chico',   // chico | normal
])

@php
    $familia ??= \App\Support\Estatus::familiaDe($estado);
    $texto ??= \App\Support\Estatus::etiquetaDe($estado);
    $medida = $tamano === 'normal' ? 'px-3 py-1 text-xs sm:text-sm' : 'px-2.5 py-0.5 text-xs';
@endphp

<span {{ $attributes->class([
    'inline-flex max-w-full items-center gap-1.5 rounded-full text-left font-medium leading-tight ring-1 ring-inset',
    $medida,
    \App\Support\Estatus::chip($familia),
]) }}>
    <span class="size-1.5 shrink-0 rounded-full {{ \App\Support\Estatus::punto($familia) }}" aria-hidden="true"></span>
    {{-- En columnas estrechas pasa a otra línea en vez de esconder el estado --}}
    <span class="min-w-0 [text-wrap:balance]">{{ $texto }}</span>
</span>
