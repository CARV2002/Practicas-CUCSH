@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Iniciar sesión" subtitle="Ingresa tus credenciales para entrar.">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="space-y-5">
                <x-auth.input name="email" type="email" label="Correo electrónico" placeholder="correo@ejemplo.com"
                    autocomplete="username" />

                <x-auth.input name="password" type="password" label="Contraseña" placeholder="Ingresa tu contraseña"
                    autocomplete="current-password" />

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="remember" class="rounded border-gray-300"> Recordarme
                    </label>
                    <a href="{{ route('password.request') }}" class="text-brand-500 hover:text-brand-600 text-sm">
                        Recuperar contraseña
                    </a>
                </div>

                <x-auth.submit>Iniciar sesión</x-auth.submit>

                <p class="text-center text-sm text-gray-500">
                    ¿No tienes cuenta?
                    <a href="{{ route('register') }}" class="text-brand-500 hover:text-brand-600">Regístrate</a>
                </p>
            </div>
        </form>
    </x-auth.split>
@endsection
