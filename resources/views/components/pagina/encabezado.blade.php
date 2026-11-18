@props(['titulo', 'descripcion' => null, 'volver' => null, 'volverTexto' => 'Volver'])

{{--
    Encabezado de página de trabajo (bandejas y detalles): título claro, descripción corta y acciones.
    Más sobrio que el encabezado de los tableros: aquí el protagonista es el contenido.
--}}
<header {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($volver)
            <a href="{{ $volver }}" class="group mb-2 inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-gray-500 transition-colors hover:text-udg-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40">
                <svg class="size-4 transition-transform group-hover:-translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6 6-6-6 6-6"/></svg>
                {{ $volverTexto }}
            </a>
        @endif
        <h1 class="font-display text-2xl font-bold leading-tight tracking-[-0.02em] text-udg-900 sm:text-[1.75rem]">{{ $titulo }}</h1>
        @if ($descripcion)
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-gray-600">{{ $descripcion }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($acciones)
        <div class="flex shrink-0 flex-wrap gap-2">{{ $acciones }}</div>
    @endisset
</header>
