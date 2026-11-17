@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Te damos la bienvenida"
        subtitle="Accede para dar seguimiento a tus convenios, postulaciones y prácticas desde un solo lugar.">
        @if (session('status'))
            <div class="mb-5 flex items-start gap-2 rounded-xl border border-success-200 bg-success-50 p-3.5 text-sm text-success-800" role="status">
                <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg>
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('google'))
            <div class="mb-5 rounded-xl border border-error-200 bg-error-50 p-3.5 text-sm text-error-700" role="alert">{{ $errors->first('google') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="space-y-5">
                <x-auth.input name="email" type="email" label="Correo electrónico" placeholder="nombre@correo.com"
                    autocomplete="username" />

                <x-auth.input name="password" type="password" label="Contraseña" placeholder="Tu contraseña"
                    autocomplete="current-password" />

                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                    <label class="group flex cursor-pointer items-center gap-2.5 whitespace-nowrap text-sm text-gray-700 select-none">
                        <input type="checkbox" name="remember"
                            class="size-4 cursor-pointer rounded border-gray-300 text-udg-800 transition focus:ring-2 focus:ring-real-500/30 focus:ring-offset-0">
                        <span class="group-hover:text-gray-900">Mantener la sesión</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="enlace whitespace-nowrap text-sm">¿Olvidaste tu contraseña?</a>
                </div>

                <x-auth.submit>Entrar a mi cuenta</x-auth.submit>
            </div>
        </form>

        <div class="my-6 flex items-center gap-3 text-xs font-medium uppercase tracking-wider text-gray-400">
            <span class="h-px flex-1 bg-gray-200"></span>o<span class="h-px flex-1 bg-gray-200"></span>
        </div>

        <a href="{{ route('google.redirect') }}"
            class="group flex h-12 w-full items-center justify-center gap-3 rounded-xl border border-gray-300 bg-white px-4 text-[15px] font-semibold text-gray-800 shadow-theme-xs transition duration-200 ease-out hover:-translate-y-px hover:border-gray-400 hover:shadow-theme-md focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/20 active:translate-y-0 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
            <svg class="size-5 transition-transform duration-200 group-hover:scale-110 motion-reduce:transition-none" viewBox="0 0 48 48" aria-hidden="true">
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
        <p class="mt-2.5 text-center text-xs leading-relaxed text-gray-500">
            Para alumnos y personal con correo institucional. Las organizaciones entran con su correo y contraseña.
        </p>

        <p class="mt-8 rounded-xl bg-gray-50 px-4 py-3.5 text-center text-sm text-gray-600 ring-1 ring-inset ring-gray-200">
            ¿Aún no tienes cuenta?
            <a href="{{ route('register') }}" class="enlace">Crea tu cuenta</a>
        </p>
    </x-auth.split>
@endsection
