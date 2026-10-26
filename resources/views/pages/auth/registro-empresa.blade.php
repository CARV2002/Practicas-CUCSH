@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Registro de empresa" subtitle="Crea la cuenta de tu empresa o institución para tramitar el convenio con el CUCSH.">
        <form method="POST" action="{{ route('registro.empresa') }}">
            @csrf
            <div class="space-y-5">
                <x-auth.input name="razon_social" label="Razón social" />
                <x-auth.input name="rfc" label="RFC" placeholder="12 o 13 caracteres" />

                <div>
                    <label for="tipo_organismo" class="mb-1.5 block text-sm font-medium text-gray-700">
                        Tipo de organismo<span class="text-error-500">*</span>
                    </label>
                    <select id="tipo_organismo" name="tipo_organismo" required class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                        <option value="">Selecciona una opción</option>
                        @foreach ($tiposOrganismo as $tipo)
                            <option value="{{ $tipo->value }}" @selected(old('tipo_organismo') === $tipo->value)>{{ $tipo->label() }}</option>
                        @endforeach
                    </select>
                    @error('tipo_organismo')
                        <p class="text-error-500 mt-1 text-sm">{{ $message }}</p>
                    @enderror
                </div>

                <x-auth.input name="name" label="Nombre de la persona de contacto" autocomplete="name" />
                <x-auth.input name="contacto_cargo" label="Cargo" :required="false" />
                <x-auth.input name="contacto_telefono" label="Teléfono" :required="false" autocomplete="tel" />

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
