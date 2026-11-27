@props(['name', 'etiqueta', 'paso' => null, 'ayuda' => null, 'accept' => '.pdf', 'formatos' => 'PDF', 'maxMb' => 10, 'requerido' => true])

{{--
    Zona para elegir o arrastrar UN archivo dentro de un formulario con varios campos
    (p. ej. la postulación: kardex + IMSS). Valida tipo y peso al momento.
--}}
@php $id = 'zona-' . $name . '-' . substr(md5(uniqid('', true)), 0, 5); @endphp
<div x-data="cargaDocumento({ maxMb: {{ (float) $maxMb }}, accept: @js($accept), formatos: @js($formatos) })" data-zona-archivo
    x-init="$el.closest('form')?.addEventListener('reset', () => limpiar())">
    <p class="etiqueta">
        @if ($paso)<span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-udg-800 text-[11px] font-bold text-white">{{ $paso }}</span>@endif{{ $etiqueta }}@if ($requerido)<span class="ml-0.5 text-rose-500" aria-hidden="true">*</span>@endif
    </p>
    @if ($ayuda)<p class="-mt-1 mb-2 text-xs leading-relaxed text-gray-500">{{ $ayuda }}</p>@endif
    <label for="{{ $id }}"
        x-on:dragover.prevent="arrastrando = true" x-on:dragleave.prevent="arrastrando = false" x-on:drop.prevent="soltar($event)"
        class="group flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed px-4 py-3.5 transition"
        :class="error ? 'border-rose-300 bg-rose-50/50' : (arrastrando ? 'border-udg-800 bg-udg-50' : (archivo ? 'border-emerald-300 bg-emerald-50/40' : 'border-gray-300 bg-gray-50/50 hover:border-udg-300 hover:bg-udg-50/50'))">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full transition"
            :class="archivo ? 'bg-emerald-100 text-emerald-700' : 'bg-white text-udg-900 shadow-theme-xs ring-1 ring-gray-200 group-hover:scale-105'">
            <span x-show="!archivo"><x-dashboard.icono nombre="subir" clase="size-5" /></span>
            <span x-show="archivo" x-cloak><x-dashboard.icono nombre="check" clase="size-5" /></span>
        </span>
        <span class="min-w-0 text-left">
            <span x-show="!archivo" class="block text-sm font-semibold text-gray-800"><span class="text-real-600 underline underline-offset-2">Elige un archivo</span> o arrástralo aquí</span>
            <span x-show="!archivo" class="block text-xs text-gray-500">{{ $formatos }} · máximo {{ $maxMb }} MB</span>
            <span x-show="archivo" x-cloak class="block truncate text-sm font-semibold text-gray-900" x-text="archivo?.nombre"></span>
            <span x-show="archivo" x-cloak class="block text-xs text-gray-500"><span x-text="archivo?.peso"></span> · <span class="font-medium text-real-600 underline">Cambiar</span></span>
        </span>
        <input id="{{ $id }}" x-ref="entrada" type="file" name="{{ $name }}" accept="{{ $accept }}" data-max-mb="{{ $maxMb }}" class="sr-only"
            @required($requerido) x-on:change="elegir($event.target.files[0])">
    </label>
    {{-- Renglón reservado para el error del archivo: nada salta al elegir otro --}}
    <p x-text="error" :aria-hidden="(!error).toString()" class="mt-1.5 min-h-5 text-sm font-medium text-rose-600" role="alert"></p>
    @error($name)<p class="mt-1.5 text-sm font-medium text-rose-600" role="alert">{{ $message }}</p>@enderror
</div>
