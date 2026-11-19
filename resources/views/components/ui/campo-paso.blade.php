@props(['paso' => null, 'etiqueta', 'name', 'type' => 'text', 'value' => null, 'ayuda' => null, 'requerido' => true])

{{-- Campo numerado que acompaña a un archivo en x-ui.carga-documento (p. ej. «1. Vigencia de tu seguro»). --}}
@php $id = 'campo-' . $name . '-' . substr(md5(uniqid('', true)), 0, 5); @endphp
<div>
    <label for="{{ $id }}" class="etiqueta">
        @if ($paso)<span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-udg-800 text-[11px] font-bold text-white">{{ $paso }}</span>@endif{{ $etiqueta }}
    </label>
    <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" @required($requerido)
        {{ $attributes->class(['campo h-11 text-base sm:text-sm', '!border-rose-400 !ring-rose-100' => $errors->has($name)]) }}
        @if ($ayuda) aria-describedby="{{ $id }}-ayuda" @endif>
    @if ($ayuda)<p id="{{ $id }}-ayuda" class="ayuda">{{ $ayuda }}</p>@endif
    @error($name)<p class="mt-1 text-sm font-medium text-rose-600" role="alert">{{ $message }}</p>@enderror
</div>
