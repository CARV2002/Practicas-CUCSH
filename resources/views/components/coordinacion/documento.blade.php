@props(['media', 'etiqueta', 'descarga'])

{{-- Documento del expediente del alumno: descarga, estado de validación y acciones de la coordinación --}}
@php
    use App\Enums\ValidacionEstado as V;

    $estado = $media ? (V::tryFrom((string) $media->getCustomProperty('estado')) ?? V::Pendiente) : null;
@endphp

<div {{ $attributes->merge(['class' => 'mt-2 rounded-lg border border-gray-200 bg-white p-2.5 text-xs']) }} x-data="{ rechazar: false }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        @if ($media)
            <a href="{{ $descarga }}" class="inline-flex min-w-0 items-center gap-1 font-semibold text-real-600 hover:text-real-700">
                <x-dashboard.icono nombre="descargar" clase="size-3.5 shrink-0" /><span class="truncate">{{ $etiqueta }}</span>
            </a>
            <x-ui.estatus :estado="$estado" :texto="$estado === V::Pendiente ? 'Por revisar' : null" />
        @else
            <span class="font-medium text-gray-600">{{ $etiqueta }}</span>
            <x-ui.estatus familia="gris" texto="Sin archivo" />
        @endif
    </div>

    @if ($media && $estado === V::Rechazada && $media->getCustomProperty('motivo'))
        <p class="mt-1.5 text-rose-600">Motivo: {{ $media->getCustomProperty('motivo') }}</p>
    @endif

    @if ($media && $estado !== V::Validada)
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <form method="POST" action="{{ route('coordinacion.documentos.resolver', $media) }}">
                @csrf
                <input type="hidden" name="estado" value="validada">
                <button data-sin-bloqueo="1" class="inline-flex h-7 items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95">
                    <x-dashboard.icono nombre="check" clase="size-3" /> Validar
                </button>
            </form>
            <button type="button" @click="rechazar = !rechazar" :aria-expanded="rechazar.toString()"
                class="inline-flex h-7 items-center gap-1 rounded-md border border-rose-200 bg-white px-2 font-semibold text-rose-600 transition hover:bg-rose-50 active:scale-95">
                <x-dashboard.icono nombre="equis" clase="size-3" /> Rechazar
            </button>
        </div>
        <form x-show="rechazar" x-cloak x-transition.opacity.duration.150ms method="POST" action="{{ route('coordinacion.documentos.resolver', $media) }}" class="mt-1.5 flex items-center gap-1.5">
            @csrf
            <input type="hidden" name="estado" value="rechazada">
            <label class="sr-only" for="motivo-doc-{{ $media->id }}">Motivo del rechazo de {{ $etiqueta }}</label>
            <input id="motivo-doc-{{ $media->id }}" type="text" name="motivo" required maxlength="500" placeholder="Motivo que verá el alumno" class="campo h-8 flex-1 text-xs">
            <button class="boton boton-chico boton-peligro">Enviar</button>
        </form>
    @endif
</div>
