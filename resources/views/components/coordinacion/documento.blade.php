@props(['media', 'etiqueta', 'descarga'])

{{-- Documento del expediente del alumno: descarga, estado de validación y acciones de la coordinación --}}
@php
    $estado = $media ? \App\Enums\ValidacionEstado::tryFrom((string) $media->getCustomProperty('estado')) : null;
    $color = match ($estado) {
        \App\Enums\ValidacionEstado::Validada => 'text-green-700',
        \App\Enums\ValidacionEstado::Rechazada => 'text-red-600',
        default => 'text-yellow-700',
    };
@endphp

<div class="text-xs" x-data="{ rechazar: false }">
    @if ($media)
        <a href="{{ $descarga }}" class="text-real-600 font-semibold">{{ $etiqueta }}</a>
        <span class="{{ $color }} font-semibold">· {{ $estado ? ucfirst($estado->value) : 'Por revisar' }}</span>
        @if ($estado === \App\Enums\ValidacionEstado::Rechazada && $media->getCustomProperty('motivo'))
            <span class="block text-red-600">{{ $media->getCustomProperty('motivo') }}</span>
        @endif
        @if ($estado !== \App\Enums\ValidacionEstado::Validada)
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('coordinacion.documentos.resolver', $media) }}">
                    @csrf
                    <input type="hidden" name="estado" value="validada">
                    <button class="font-semibold text-green-600 hover:text-green-700">Validar</button>
                </form>
                <button type="button" @click="rechazar = !rechazar" class="font-semibold text-red-600 hover:text-red-700">Rechazar</button>
            </div>
            <form x-show="rechazar" style="display: none" method="POST" action="{{ route('coordinacion.documentos.resolver', $media) }}" class="mt-1 flex items-center gap-1">
                @csrf
                <input type="hidden" name="estado" value="rechazada">
                <input type="text" name="motivo" required maxlength="500" placeholder="Motivo" aria-label="Motivo del rechazo de {{ $etiqueta }}"
                    class="h-7 w-36 rounded border border-gray-300 px-2 text-xs">
                <button class="font-semibold text-red-600">Enviar</button>
            </form>
        @endif
    @else
        <span class="text-gray-400">{{ $etiqueta }}: sin archivo</span>
    @endif
</div>
