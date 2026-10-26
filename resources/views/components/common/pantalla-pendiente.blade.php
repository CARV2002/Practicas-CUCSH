@props(['titulo', 'descripcion', 'datos' => [], 'fuente' => null])

{{-- Marcador honesto de una pantalla aún no construida: dice qué mostrará y de dónde saldrán los datos. --}}
<x-common.page-breadcrumb :pageTitle="$titulo" />

<div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $descripcion }}</p>

    @if (count($datos))
        <h4 class="mt-5 text-sm font-semibold text-gray-800 dark:text-white/90">Esta pantalla mostrará</h4>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
            @foreach ($datos as $dato)
                <li>{{ $dato }}</li>
            @endforeach
        </ul>
    @endif

    @if ($fuente)
        <p class="mt-5 text-xs text-gray-400">Pendiente de implementar · {{ $fuente }}</p>
    @endif
</div>
