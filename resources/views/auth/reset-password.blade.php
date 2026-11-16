@extends('layouts.fullscreen-layout')

@section('content')
    <x-auth.split title="Nueva contraseña" subtitle="Elige una contraseña nueva para tu cuenta.">
        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="space-y-5">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">Correo electrónico<span class="text-error-500">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="username"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                    @error('email')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
                </div>

                <x-auth.input name="password" type="password" label="Contraseña nueva" autocomplete="new-password" />
                <x-auth.input name="password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" />

                <x-auth.submit>Restablecer contraseña</x-auth.submit>
            </div>
        </form>
    </x-auth.split>
@endsection