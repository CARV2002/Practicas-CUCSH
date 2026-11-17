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
                    <label for="carrera_id" class="etiqueta">
                        Carrera<span class="text-error-500">*</span>
                    </label>
                    <select id="carrera_id" name="carrera_id" required
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-real-500 focus:ring-real-500/20 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                        <option value="">Selecciona tu carrera</option>
                        @foreach ($carreras as $carrera)
                            <option value="{{ $carrera->id }}" @selected(old('carrera_id') == $carrera->id)>{{ $carrera->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('carrera_id')
                        <p class="text-error-500 mt-1 text-sm">{{ $message }}</p>
                    @enderror
                </div>

                <x-alumno.grado-turno />

                <x-auth.input name="email" type="email" label="Correo electrónico" autocomplete="username" />
                <x-auth.input name="password" type="password" label="Contraseña" autocomplete="new-password" />
                <x-auth.input name="password_confirmation" type="password" label="Confirmar contraseña"
                    autocomplete="new-password" />

                <div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="aviso_privacidad" value="1" class="mt-0.5 rounded border-gray-300"
                            @checked(old('aviso_privacidad')) required>
                        <span>Acepto el <a href="{{ route('privacidad') }}" target="_blank" rel="noopener"
                                class="text-real-600 underline">aviso de privacidad</a>
                            y los <a href="{{ route('terminos') }}" target="_blank" rel="noopener"
                                class="text-real-600 underline">términos y condiciones</a>,
                            y el tratamiento de mis datos personales.</span>
                    </label>
                    @error('aviso_privacidad')
                        <p class="text-error-500 mt-1 text-sm">{{ $message }}</p>
                    @enderror
                </div>

                <x-auth.submit>Crear cuenta</x-auth.submit>

                <p class="text-center text-sm text-gray-500">
                    ¿Ya tienes cuenta?
                    <a href="{{ route('login') }}" class="text-real-600 hover:text-real-700">Inicia sesión</a>
                </p>
            </div>
        </form>
        <div class="my-5 flex items-center gap-3 text-xs text-gray-400">
            <span class="h-px flex-1 bg-gray-200"></span>o<span class="h-px flex-1 bg-gray-200"></span>
        </div>

        <a href="{{ route('google.redirect') }}"
            class="flex h-11 w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#EA4335"
                    d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
                <path fill="#4285F4"
                    d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
                <path fill="#FBBC05"
                    d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
                <path fill="#34A853"
                    d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
            </svg>
            Continuar con correo institucional UdG
        </a>
        <p class="mt-2 text-center text-xs text-gray-500">
            Para alumnos y personal con correo institucional. Las empresas inician con su correo y contraseña.
        </p>
    </x-auth.split>
@endsection