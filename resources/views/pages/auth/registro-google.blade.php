@extends('layouts.fullscreen-layout')

@section('content')
    @php
        $input = 'focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden';
        $label = 'mb-1.5 block text-sm font-medium text-gray-700';
    @endphp

    <x-auth.split title="Completa tu registro" subtitle="Tu correo institucional ya fue verificado con Google. Solo faltan unos datos.">
        <form method="POST" action="{{ route('registro.google') }}">
            @csrf
            <div class="space-y-5">
                <div>
                    <span class="{{ $label }}">Correo institucional</span>
                    <p class="h-11 rounded-lg bg-gray-50 px-4 text-sm leading-[2.75rem] text-gray-600">{{ $datos['email'] }}</p>
                </div>

                <div>
                    <label for="name" class="{{ $label }}">Nombre completo<span class="text-error-500">*</span></label>
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $datos['name']) }}" class="{{ $input }}">
                    @error('name')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="num_control" class="{{ $label }}">Código de alumno<span class="text-error-500">*</span></label>
                    <input id="num_control" name="num_control" type="text" required maxlength="20" value="{{ old('num_control') }}" class="{{ $input }}">
                    @error('num_control')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="carrera_id" class="{{ $label }}">Carrera<span class="text-error-500">*</span></label>
                    <select id="carrera_id" name="carrera_id" required class="{{ $input }}">
                        <option value="">Selecciona tu carrera</option>
                        @foreach ($carreras as $carrera)
                            <option value="{{ $carrera->id }}" @selected(old('carrera_id') == $carrera->id)>{{ $carrera->nombre }}</option>
                        @endforeach
                    </select>
                    @error('carrera_id')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="aviso_privacidad" value="1" class="mt-0.5 rounded border-gray-300" @checked(old('aviso_privacidad')) required>
                        <span>Acepto el <a href="{{ route('privacidad') }}" target="_blank" rel="noopener" class="text-brand-500 underline">aviso de privacidad</a>
                            y los <a href="{{ route('terminos') }}" target="_blank" rel="noopener" class="text-brand-500 underline">términos y condiciones</a>,
                            y el tratamiento de mis datos personales.</span>
                    </label>
                    @error('aviso_privacidad')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
                </div>

                <x-auth.submit>Crear cuenta</x-auth.submit>

                <p class="text-center text-sm text-gray-500">
                    <a href="{{ route('login') }}" class="text-brand-500 hover:text-brand-600">Cancelar</a>
                </p>
            </div>
        </form>
    </x-auth.split>
@endsection