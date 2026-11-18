@props(['icono' => 'check', 'titulo', 'texto' => null])

{{-- Estado vacío de un panel: nunca dejar una lista en blanco sin explicación. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-4 py-8 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-100">
        <x-dashboard.icono :nombre="$icono" />
    </span>
    <p class="mt-3 text-sm font-semibold text-gray-900">{{ $titulo }}</p>
    @if ($texto)
        <p class="mt-1 max-w-xs text-xs leading-relaxed text-gray-500">{{ $texto }}</p>
    @endif
</div>
