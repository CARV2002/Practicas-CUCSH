@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Verifica tu correo"
        subtitle="Te enviamos un enlace de confirmación a {{ auth()->user()->email }}. Ábrelo para usar la plataforma.">

        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700">Te enviamos un nuevo enlace de verificación.</div>
        @endif

        <div class="space-y-4">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-auth.submit>Reenviar correo de verificación</x-auth.submit>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button class="text-sm text-gray-500 hover:text-gray-700">Cerrar sesión</button>
            </form>

            <p class="text-center text-xs text-gray-400">
                ¿No llega? Revisa spam o escribe a practicas.profesionales.cucsh@csh.udg.mx
            </p>
        </div>
    </x-auth.split>
@endsection