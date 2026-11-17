@props(['name', 'label', 'type' => 'text', 'required' => true, 'placeholder' => '', 'autocomplete' => null])

{{--
    Campo de las pantallas de acceso. Las contraseñas tienen botón para mostrar/ocultar.
    El mensaje de validación (resources/js/validacion.js) se inserta justo después del <input>; el botón va anclado
    arriba para no moverse cuando aparece el mensaje.
--}}
<div @if ($type === 'password') x-data="{ ver: false }" @endif>
    <label for="{{ $name }}" class="mb-2 block text-sm font-semibold text-gray-800">
        {{ $label }}@if ($required)<span class="ml-0.5 text-error-500" aria-hidden="true">*</span>@endif
    </label>
    <div class="relative">
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type === 'password') :type="ver ? 'text' : 'password'" @endif
            value="{{ $type === 'password' ? '' : old($name) }}" placeholder="{{ $placeholder }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @required($required)
            @class([
                'h-12 w-full rounded-xl border border-gray-300 bg-gray-50/60 px-4 text-[15px] text-gray-900 shadow-theme-xs transition duration-200 ease-out placeholder:text-gray-400',
                'hover:border-gray-400 focus:border-real-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-real-500/15',
                'motion-reduce:transition-none',
                'pr-12' => $type === 'password',
            ]) />
        @if ($type === 'password')
            <button type="button" @click="ver = !ver"
                class="absolute right-1 top-1 flex size-10 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40"
                :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'" :aria-pressed="ver.toString()" aria-label="Mostrar contraseña">
                <svg x-show="!ver" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="ver" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.6 9.6 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
        @endif
    </div>
    @error($name)
        <p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>
    @enderror
</div>
