@props(['titulo', 'descripcion' => null, 'enlace' => null, 'enlaceTexto' => 'Ver todo', 'icono' => null])

{{-- Panel de tablero: tarjeta con encabezado (título, descripción y enlace opcional). --}}
<section {{ $attributes->merge(['class' => 'flex flex-col rounded-2xl border border-gray-200 bg-white shadow-theme-xs']) }}>
    <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
        <div class="flex min-w-0 items-start gap-3">
            @if ($icono)
                <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-udg-50 text-udg-900 ring-1 ring-inset ring-udg-100">
                    <x-dashboard.icono :nombre="$icono" clase="size-[18px]" />
                </span>
            @endif
            <div class="min-w-0">
                <h2 class="font-display text-base font-bold text-gray-900">{{ $titulo }}</h2>
                @if ($descripcion)
                    <p class="mt-0.5 text-xs leading-relaxed text-gray-500">{{ $descripcion }}</p>
                @endif
            </div>
        </div>
        @if ($enlace)
            <a href="{{ $enlace }}" class="group inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-real-600 transition-colors hover:bg-real-50 hover:text-real-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40">
                {{ $enlaceTexto }}
                <x-dashboard.icono nombre="flecha" clase="size-3.5 transition-transform group-hover:translate-x-0.5" />
            </a>
        @endif
    </div>
    <div class="flex-1 px-5 py-4 sm:px-6">
        {{ $slot }}
    </div>
</section>
