@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Registro de alumno" subtitle="Crea tu cuenta para consultar ofertas y gestionar tu práctica.">
        @if ($carreras->isEmpty())
            <div class="mb-4 rounded-lg bg-yellow-50 p-3 text-sm text-yellow-800">
                Aún no hay carreras registradas. Contacta a la Coordinación de Prácticas Profesionales.
            </div>
        @endif

        <form method="POST" action="{{ route('registro.alumno') }}">
            @csrf
            <div class="space-y-5">
                <x-auth.input name="name" label="Nombre completo" autocomplete="name" />
                <x-auth.input name="num_control" label="Código de alumno" />

                <div>
                    <label for="carrera_id" class="mb-1.5 block text-sm font-medium text-gray-700">
                        Carrera<span class="text-error-500">*</span>
                    </label>
                    <select id="carrera_id" name="carrera_id" required class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                        <option value="">Selecciona tu carrera</option>
                        @foreach ($carreras as $carrera)
                            <option value="{{ $carrera->id }}" @selected(old('carrera_id') == $carrera->id)>{{ $carrera->nombre }}</option>
                        @endforeach
                    </select>
                    @error('carrera_id')
                        <p class="text-error-500 mt-1 text-sm">{{ $message }}</p>
                    @enderror
                </div>

                <x-auth.input name="email" type="email" label="Correo electrónico" autocomplete="username" />
                <x-auth.input name="password" type="password" label="Contraseña" autocomplete="new-password" />
                <x-auth.input name="password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" />

                <div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="aviso_privacidad" value="1" class="mt-0.5 rounded border-gray-300"
                            @checked(old('aviso_privacidad')) required>
                        <span>Acepto el aviso de privacidad y el tratamiento de mis datos personales.</span>
                    </label>
                    @error('aviso_privacidad')
                        <p class="text-error-500 mt-1 text-sm">{{ $message }}</p>
                    @enderror
                </div>

                <x-auth.submit>Crear cuenta</x-auth.submit>

                <p class="text-center text-sm text-gray-500">
                    ¿Ya tienes cuenta?
                    <a href="{{ route('login') }}" class="text-brand-500 hover:text-brand-600">Inicia sesión</a>
                </p>
            </div>
        </form>
    </x-auth.split>
@endsection
