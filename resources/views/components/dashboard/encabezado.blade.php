@props(['eyebrow' => null, 'titulo', 'subtitulo' => null])

{{--
    Encabezado de tablero: banda azul marino con filete dorado.
    Slot "acciones" para botones (se apilan a lo ancho en celular). Slot por defecto: contenido extra bajo el subtítulo.
--}}
<header {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl bg-gradient-to-br from-udg-950 via-udg-900 to-udg-800 px-5 py-6 text-white shadow-theme-md sm:px-8 sm:py-8']) }}>
    <div class="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full bg-oro-400/15 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-28 left-1/3 size-72 rounded-full bg-real-500/20 blur-3xl" aria-hidden="true"></div>
    <div class="absolute inset-x-0 bottom-0 h-1 bg-oro-400" aria-hidden="true"></div>

    <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0 animate-entrada motion-reduce:animate-none">
            @if ($eyebrow)
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-oro-300">{{ $eyebrow }}</p>
            @endif
            <h1 class="mt-1.5 font-display text-2xl font-bold leading-tight tracking-[-0.02em] sm:text-3xl">{{ $titulo }}</h1>
            @if ($subtitulo)
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/75 sm:text-[15px]">{{ $subtitulo }}</p>
            @endif
            {{ $slot }}
        </div>
        @isset($acciones)
            <div class="flex shrink-0 animate-entrada flex-col gap-2 [animation-delay:100ms] motion-reduce:animate-none sm:flex-row">
                {{ $acciones }}
            </div>
        @endisset
    </div>
</header>
