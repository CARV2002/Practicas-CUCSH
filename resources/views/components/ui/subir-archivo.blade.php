@props(['name' => 'archivo', 'accept' => 'application/pdf', 'boton' => 'Subir', 'requerido' => true, 'ayuda' => null])

{{--
    Selector de archivo + botón de envío (va dentro de un <form> con @csrf).
    Muestra el nombre del archivo elegido y no deja enviar hasta elegir uno.
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }} x-data="{ nombre: '' }">
    <label class="inline-flex h-9 min-w-0 max-w-[15rem] flex-1 cursor-pointer items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-3 text-xs font-medium text-gray-600 transition hover:border-udg-300 hover:bg-udg-50 hover:text-udg-900 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-real-500/25 sm:flex-none"
        :class="nombre && '!border-solid !border-udg-200 !bg-udg-50 !text-udg-900'">
        <x-dashboard.icono nombre="subir" clase="size-3.5 shrink-0" />
        <span class="truncate" x-text="nombre || 'Elegir archivo'">Elegir archivo</span>
        <input type="file" name="{{ $name }}" accept="{{ $accept }}" @required($requerido) class="sr-only"
            @change="nombre = $event.target.files[0]?.name || ''">
    </label>
    <button class="boton boton-chico boton-primario" :disabled="!nombre">{{ $boton }}</button>
    @if ($ayuda)<p class="w-full text-[11px] text-gray-400">{{ $ayuda }}</p>@endif
</div>
