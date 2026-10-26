@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Crear cuenta" subtitle="¿Cómo vas a usar la plataforma?">
        <div class="space-y-4">
            <a href="{{ route('registro.alumno') }}"
                class="hover:border-brand-300 block rounded-xl border border-gray-200 p-5 transition hover:bg-gray-50">
                <h2 class="font-semibold text-gray-800">Soy alumno</h2>
                <p class="mt-1 text-sm text-gray-500">Consulta ofertas de prácticas profesionales y da seguimiento a tu práctica.</p>
            </a>

            <a href="{{ route('registro.empresa') }}"
                class="hover:border-brand-300 block rounded-xl border border-gray-200 p-5 transition hover:bg-gray-50">
                <h2 class="font-semibold text-gray-800">Represento a una empresa o institución</h2>
                <p class="mt-1 text-sm text-gray-500">Tramita tu convenio con el CUCSH y publica tus ofertas para practicantes.</p>
            </a>

            <p class="text-center text-sm text-gray-500">
                ¿Ya tienes cuenta?
                <a href="{{ route('login') }}" class="text-brand-500 hover:text-brand-600">Inicia sesión</a>
            </p>
        </div>
    </x-auth.split>
@endsection
