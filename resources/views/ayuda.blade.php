@extends('layouts.app')

@section('content')
    @php
        $usuario = auth()->user();
        $correo = config('practicas.contacto.correo');
    @endphp

    <x-common.page-breadcrumb pageTitle="Ayuda" />

    <div class="max-w-3xl space-y-6">
        @if ($usuario->alumno)
            {{-- El alumno resuelve todo su proceso con la coordinación de su carrera --}}
            <x-alumno.tu-coordinacion :carrera="$usuario->alumno->carrera" />
        @endif

        <div class="space-y-3 tarjeta p-6"
            x-data="{ copiado: false, copiar(t) { navigator.clipboard?.writeText(t).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000) }) } }">
            <h3 class="text-lg font-bold text-gray-900">Vinculación · convenios con organizaciones</h3>
            <p class="text-sm text-gray-600">
                @if ($usuario->alumno)
                    Vinculación solo atiende convenios con organizaciones. Para tu postulación, cartas, reportes o liberación, escribe a tu coordinación.
                @else
                    Registro de organizaciones, solicitud y seguimiento de convenios, y cuentas de la plataforma.
                @endif
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ \App\Support\Correo::gmail($correo) }}" target="_blank" rel="noopener"
                    class="bg-udg-800 hover:bg-udg-700 rounded-lg px-3 py-2 text-sm font-medium text-white">Escribir por Gmail</a>
                <button type="button" @click="copiar(@js($correo))"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <span x-show="!copiado">Copiar correo</span>
                    <span x-show="copiado" style="display: none">¡Copiado!</span>
                </button>
                <span class="text-sm text-gray-700">{{ $correo }}</span>
            </div>
            <p class="text-sm text-gray-700">Teléfono: {{ config('practicas.contacto.telefono') }}, ext.
                {{ implode(' y ', config('practicas.contacto.extensiones')) }}</p>
        </div>

        @unless ($usuario->alumno)
            <p class="text-sm text-gray-500">
                ¿Buscas a una coordinación de carrera? Consulta el <a href="{{ route('contacto') }}#directorio" class="text-real-600 font-semibold">directorio en Contacto</a>.
            </p>
        @endunless
    </div>
@endsection
