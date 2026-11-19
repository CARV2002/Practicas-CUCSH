@props(['titulo', 'motivo', 'enlace' => null, 'enlaceTexto' => 'Ir a mi panel'])

{{-- Sección no disponible por el estado del trámite: candado, motivo claro y a dónde ir. --}}
<div class="flex min-h-[60vh] items-center justify-center px-4">
    <div class="w-full max-w-md animate-entrada text-center motion-reduce:animate-none">
        <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-gray-100 text-gray-500 ring-8 ring-gray-50">
            <x-dashboard.icono nombre="candado" clase="size-7" />
        </span>
        <h1 class="mt-5 font-display text-xl font-bold text-gray-900">{{ $titulo }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $motivo }}</p>
        @if ($enlace)
            <a href="{{ $enlace }}" class="boton boton-primario mt-6 inline-flex"><x-dashboard.icono nombre="flecha" clase="size-4" /> {{ $enlaceTexto }}</a>
        @endif
        {{ $slot }}
    </div>
</div>
