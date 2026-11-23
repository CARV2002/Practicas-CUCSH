@extends('layouts.fullscreen-layout')

@section('content')
    @if ($vencido ?? false)
        {{-- Enlace vencido (72 h), ya usado o incompleto: pantalla amigable con salida, sin formulario inútil --}}
        <x-auth.split title="Este enlace ya no es válido"
            subtitle="Los enlaces para configurar tu contraseña vencen a las 72 horas y solo se pueden usar una vez.">
            <div class="space-y-5">
                <p class="text-[15px] text-gray-700">
                    Solicita un enlace nuevo con tu correo institucional: te llegará en unos minutos y tendrás otras 72 horas para usarlo.
                </p>
                <a href="{{ route('password.request') }}"
                    class="flex h-12 w-full items-center justify-center rounded-xl bg-udg-900 px-4 text-[15px] font-semibold text-white hover:bg-udg-800">
                    Solicitar un enlace nuevo
                </a>
                <p class="text-sm text-gray-500">
                    Si ya configuraste tu contraseña, <a href="{{ route('login') }}" class="font-semibold text-udg-900 underline">inicia sesión</a>.
                    Si el problema continúa, pide a la administración de Prácticas Profesionales que te reenvíe el enlace.
                </p>
            </div>
        </x-auth.split>
    @else
        <x-auth.split title="Configura tu contraseña"
            subtitle="Escribe la contraseña con la que entrarás a la plataforma. Solo tú la conoces; al guardarla, tu cuenta queda activa y tu correo verificado.">
            <form method="POST" action="{{ route('cuenta.activar.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="space-y-5">
                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-gray-800">Correo institucional</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" readonly
                            class="h-12 w-full rounded-xl border border-gray-200 bg-gray-100 px-4 text-[15px] text-gray-700">
                        @error('email')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                    </div>
                    <x-auth.input name="password" type="password" label="Nueva contraseña" autocomplete="new-password" />
                    <x-auth.input name="password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" />
                    <x-auth.submit>Guardar y entrar</x-auth.submit>
                </div>
            </form>
        </x-auth.split>
    @endif
@endsection
