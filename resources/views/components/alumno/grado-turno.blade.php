@props(['grado' => null, 'turno' => null])

@php
    $clase = 'dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden';
    $grado = old('grado', $grado);
    $turno = old('turno', $turno instanceof \App\Enums\Turno ? $turno->value : $turno);
@endphp

{{-- Grado y turno se imprimen en la Carta de Presentación --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="grado" class="mb-1.5 block text-sm font-medium text-gray-700">
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
        <label for="turno" class="mb-1.5 block text-sm font-medium text-gray-700">
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
