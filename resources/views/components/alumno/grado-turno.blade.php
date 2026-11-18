@props(['grado' => null, 'turno' => null])

@php
    $clase = 'campo h-11';
    $grado = old('grado', $grado);
    $turno = old('turno', $turno instanceof \App\Enums\Turno ? $turno->value : $turno);
@endphp

{{-- Grado y turno se imprimen en la Carta de Presentación --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="grado" class="etiqueta">
            Grado (semestre)<span class="text-error-500">*</span>
        </label>
        <select id="grado" name="grado" required class="{{ $clase }}">
            <option value="">Selecciona</option>
            @foreach (range(1, 12) as $g)
                <option value="{{ $g }}" @selected((string) $grado === (string) $g)>{{ $g }}.º semestre</option>
            @endforeach
        </select>
        @error('grado')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="turno" class="etiqueta">
            Turno<span class="text-error-500">*</span>
        </label>
        <select id="turno" name="turno" required class="{{ $clase }}">
            <option value="">Selecciona</option>
            @foreach (\App\Enums\Turno::cases() as $t)
                <option value="{{ $t->value }}" @selected($turno === $t->value)>{{ $t->label() }}</option>
            @endforeach
        </select>
        @error('turno')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
    </div>
</div>
