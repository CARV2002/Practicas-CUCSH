@props([
    'href' => null,
    'etiqueta',
    'valor',
    'sufijo' => null,
    'detalle' => null,
    'icono' => 'documento',
    'tono' => 'udg',       // udg | cielo | lavanda | indigo | ambar | menta | rosa | turquesa | gris
    'alerta' => null,      // texto corto en rojo, p. ej. «2 exceden plazo»
    'orden' => 0,          // posición, para escalonar la animación de entrada
    'accion' => 'Ver',     // texto del enlace (lectores de pantalla y hover)
])

{{--
    Tarjeta KPI interactiva (DESIGN_SYSTEM.md §5): toda la tarjeta es un enlace, se eleva al pasar el cursor,
    el número cuenta hacia arriba al cargar (solo si el usuario no pidió reducir movimiento).
--}}
@php
    $tonos = [
        'udg' => ['icono' => 'bg-udg-50 text-udg-900 ring-udg-100', 'barra' => 'bg-udg-800', 'borde' => 'hover:border-udg-200'],
        'cielo' => ['icono' => 'bg-sky-50 text-sky-700 ring-sky-100', 'barra' => 'bg-sky-400', 'borde' => 'hover:border-sky-200'],
        'lavanda' => ['icono' => 'bg-violet-50 text-violet-700 ring-violet-100', 'barra' => 'bg-violet-400', 'borde' => 'hover:border-violet-200'],
        'indigo' => ['icono' => 'bg-indigo-50 text-indigo-700 ring-indigo-100', 'barra' => 'bg-indigo-400', 'borde' => 'hover:border-indigo-200'],
        'ambar' => ['icono' => 'bg-amber-50 text-amber-700 ring-amber-100', 'barra' => 'bg-amber-400', 'borde' => 'hover:border-amber-200'],
        'menta' => ['icono' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'barra' => 'bg-emerald-500', 'borde' => 'hover:border-emerald-200'],
        'rosa' => ['icono' => 'bg-rose-50 text-rose-700 ring-rose-100', 'barra' => 'bg-rose-500', 'borde' => 'hover:border-rose-200'],
        'turquesa' => ['icono' => 'bg-teal-50 text-teal-700 ring-teal-100', 'barra' => 'bg-teal-500', 'borde' => 'hover:border-teal-200'],
        'gris' => ['icono' => 'bg-gray-100 text-gray-600 ring-gray-200', 'barra' => 'bg-gray-400', 'borde' => 'hover:border-gray-300'],
    ];
    $t = $tonos[$tono] ?? $tonos['udg'];
    $numerico = is_int($valor) || (is_string($valor) && ctype_digit($valor));
    $etiquetaTag = $href ? 'a' : 'div';
@endphp

<{{ $etiquetaTag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs animate-entrada motion-reduce:animate-none',
        'transition duration-200 ease-out hover:-translate-y-1 hover:shadow-theme-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:transition-none motion-reduce:hover:translate-y-0' => $href,
        $t['borde'] => $href,
    ]) }}
    style="animation-delay: {{ $orden * 60 }}ms">
    <span class="absolute inset-x-0 top-0 h-1 origin-left scale-x-0 {{ $t['barra'] }} transition-transform duration-300 group-hover:scale-x-100 motion-reduce:transition-none" aria-hidden="true"></span>

    <div class="flex items-start justify-between gap-3">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset {{ $t['icono'] }} transition-transform duration-200 group-hover:scale-105 motion-reduce:transition-none">
            <x-dashboard.icono :nombre="$icono" />
        </span>
        @if ($alerta)
            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">
                <span class="relative flex size-1.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-rose-400 opacity-75 motion-reduce:hidden"></span><span class="relative inline-flex size-1.5 rounded-full bg-rose-500"></span></span>
                {{ $alerta }}
            </span>
        @elseif ($href)
            <x-dashboard.icono nombre="flecha" clase="size-4 -translate-x-1 text-gray-300 opacity-0 transition duration-200 group-hover:translate-x-0 group-hover:text-gray-500 group-hover:opacity-100 motion-reduce:transition-none" />
        @endif
    </div>

    <p class="mt-4 text-[13px] font-medium leading-snug text-gray-600">{{ $etiqueta }}</p>
    <p class="mt-1 flex items-baseline gap-1.5">
        <span class="font-display text-3xl font-bold tabular-nums tracking-tight text-gray-900"
            @if ($numerico)
                x-data="{ n: {{ (int) $valor }} }"
                x-init="if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && n > 0) { const fin = n, t0 = performance.now(); n = 0; const paso = (t) => { const p = Math.min(1, (t - t0) / 700); n = Math.round(fin * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(paso); }; requestAnimationFrame(paso); }"
                x-text="n"
            @endif>{{ $valor }}</span>
        @if ($sufijo)
            <span class="text-sm font-medium text-gray-500">{{ $sufijo }}</span>
        @endif
    </p>
    @if ($detalle)
        <p class="mt-1.5 text-xs leading-relaxed text-gray-500">{{ $detalle }}</p>
    @endif
    {{ $slot }}
    @if ($href)
        <span class="sr-only">{{ $accion }}: {{ $etiqueta }}</span>
    @endif
</{{ $etiquetaTag }}>
