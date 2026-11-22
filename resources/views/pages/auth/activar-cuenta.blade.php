@extends('layouts.fullscreen-layout')

@section('content')
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
@endsection
