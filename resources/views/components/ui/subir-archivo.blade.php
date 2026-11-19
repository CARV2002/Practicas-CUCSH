@props(['name' => 'archivo', 'accept' => 'application/pdf', 'boton' => 'Subir', 'requerido' => true, 'ayuda' => null, 'maxMb' => 10])

{{--
    Selector de archivo + botón de envío (va dentro de un <form> con @csrf).
    Muestra el nombre del archivo elegido, revisa el peso antes de enviar (el servidor
    rechaza envíos mayores a post_max_size) y no deja enviar hasta elegir uno válido.
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}
    x-data="{ nombre: '', error: '',
        elegir(e) {
            const f = e.target.files[0];
            this.error = ''; this.nombre = '';
            if (!f) return;
            if (f.size > {{ (float) $maxMb }} * 1048576) {
                this.error = 'Pesa ' + (f.size / 1048576).toFixed(1) + ' MB; el máximo es {{ $maxMb }} MB.';
                e.target.value = '';
                return;
            }
            this.nombre = f.name;
        } }">
    <label class="inline-flex h-9 min-w-0 max-w-[15rem] flex-1 cursor-pointer items-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-3 text-xs font-medium text-gray-600 transition hover:border-udg-300 hover:bg-udg-50 hover:text-udg-900 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-real-500/25 sm:flex-none"
        :class="nombre && '!border-solid !border-udg-200 !bg-udg-50 !text-udg-900'">
        <x-dashboard.icono nombre="subir" clase="size-3.5 shrink-0" />
        <span class="truncate" x-text="nombre || 'Elegir archivo'">Elegir archivo</span>
        <input type="file" name="{{ $name }}" accept="{{ $accept }}" data-max-mb="{{ $maxMb }}" @required($requerido) class="sr-only"
            x-on:change="elegir($event)">
    </label>
    <button class="boton boton-chico boton-primario" :disabled="!nombre">{{ $boton }}</button>
    <p x-show="error" x-cloak class="w-full text-[11px] font-medium text-rose-600" x-text="error" role="alert"></p>
    <p class="w-full text-[11px] text-gray-400">{{ $ayuda ? $ayuda . ' · ' : '' }}Máximo {{ $maxMb }} MB.</p>
</div>
