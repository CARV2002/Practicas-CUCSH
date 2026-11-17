@extends('layouts.fullscreen-layout')

@section('content')
    @php
        $codigo = $exception->getStatusCode();
        $mensajes = [
            403 => ['No tienes permiso', 'Tu cuenta no tiene acceso a esta sección.'],
            404 => ['Página no encontrada', 'La dirección no existe o el contenido ya no está disponible.'],
            419 => ['Tu sesión expiró', 'Recarga la página e inténtalo de nuevo.'],
            429 => ['Demasiados intentos', 'Espera unos minutos antes de volver a intentarlo.'],
            503 => ['Sistema en mantenimiento', 'Volveremos en unos minutos.'],
        ];
        [$titulo, $texto] = $mensajes[$codigo] ?? (
            $codigo >= 500
                ? ['Algo salió mal', 'Ocurrió un error en el sistema. Inténtalo más tarde; si continúa, contacta a la Coordinación de Prácticas.']
                : ['No se pudo completar la solicitud', 'Revisa la dirección o regresa al inicio.']
        );
    @endphp

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md tarjeta p-8 text-center shadow-sm">
            <p class="text-real-600 text-4xl font-bold">{{ $codigo }}</p>
            <h1 class="mt-3 text-xl font-bold text-gray-900">{{ $titulo }}</h1>
            <p class="mt-2 text-sm text-gray-500">{{ $texto }}</p>
            <div class="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
                @auth
                    <a href="{{ route('dashboard') }}" class="boton boton-primario">Ir a mi panel</a>
                @else
                    <a href="{{ route('inicio') }}" class="boton boton-primario">Ir al inicio</a>
                @endauth
            </div>
        </div>
    </div>
@endsection