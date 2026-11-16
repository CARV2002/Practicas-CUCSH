@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Recuperar contraseña" subtitle="Escribe tu correo y te enviaremos un enlace para elegir una contraseña nueva.">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="space-y-5">
                <x-auth.input name="email" type="email" label="Correo electrónico" autocomplete="username" />
                <x-auth.submit>Enviar enlace</x-auth.submit>
                <p class="text-center text-sm text-gray-500">
                    <a href="{{ route('login') }}" class="text-brand-500 hover:text-brand-600">Volver a iniciar sesión</a>
                </p>
            </div>
        </form>
    </x-auth.split>
@endsection