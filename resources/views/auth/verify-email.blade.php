@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Verifica tu correo"
        subtitle="Te enviamos un enlace de confirmación a {{ auth()->user()->email }}. Ábrelo para usar la plataforma.">

        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700">Te enviamos un nuevo enlace de verificación.</div>
        @elseif (session('status') == 'verification-failed')
            <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">No pudimos enviar el correo de confirmación en este momento. Inténtalo de nuevo con «Reenviar correo de verificación»; si sigue fallando, avisa a la Coordinación de Prácticas.</div>
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
                ¿No llega? Revisa spam.
                @if (auth()->user()?->alumno?->carrera?->correoContacto())
                    Si persiste, escribe a tu coordinación: {{ auth()->user()->alumno->carrera->correoContacto() }}
                @else
                    Si persiste, escribe a {{ config('practicas.contacto.correo') }}
                @endif
            </p>
        </div>
    </x-auth.split>
@endsection