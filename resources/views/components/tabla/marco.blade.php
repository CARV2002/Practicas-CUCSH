@props(['titulo' => null, 'descripcion' => null, 'total' => null])

{{--
    Marco único para todas las tablas: tarjeta, barra de herramientas (filtros), la tabla y el pie (paginación).
    Uso:
      <x-tabla.marco titulo="…" :total="$lista->total()">
          <x-slot:herramientas>…filtros…</x-slot:herramientas>
          <table class="tabla">…</table>
          <x-slot:pie>{{ $lista->links() }}</x-slot:pie>
      </x-tabla.marco>
--}}
<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs']) }}>
    @if ($titulo || isset($herramientas))
        <div class="space-y-4 border-b border-gray-100 px-4 py-4 sm:px-6">
            @if ($titulo)
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <div class="min-w-0">
                        <h2 class="font-display text-base font-bold text-gray-900">{{ $titulo }}</h2>
                        @if ($descripcion)<p class="mt-0.5 text-xs leading-relaxed text-gray-500">{{ $descripcion }}</p>@endif
                    </div>
                    @if ($total !== null)
                        <p class="text-xs text-gray-500"><span class="font-semibold tabular-nums text-gray-800">{{ number_format($total) }}</span> {{ $total === 1 ? 'registro' : 'registros' }}</p>
                    @endif
                </div>
            @endif
            {{ $herramientas ?? '' }}
        </div>
    @endif

    <div class="tabla-scroll">
        {{ $slot }}
    </div>

    @isset($pie)
        @if (trim($pie) !== '')
            <div class="border-t border-gray-100 px-4 py-3 sm:px-6">{{ $pie }}</div>
        @endif
    @endisset
</section>
