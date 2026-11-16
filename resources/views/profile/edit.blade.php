@extends('layouts.app')

@section('content')
@php
    $input = 'focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700';
    $card = 'rounded-2xl border border-gray-200 bg-white p-6';
@endphp

<x-common.page-breadcrumb pageTitle="Mi perfil" />

<div class="max-w-2xl space-y-6">
    @if (session('status') === 'profile-updated')
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">Datos actualizados.</div>
    @endif
    @if (session('status') === 'password-updated')
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">Contraseña actualizada.</div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" class="{{ $card }} space-y-5">
        @csrf
        @method('PATCH')
        <div>
            <h3 class="text-base font-bold text-gray-900">Datos de la cuenta</h3>
            <p class="text-xs text-gray-500">Si cambias tu correo tendrás que confirmarlo de nuevo.</p>
        </div>

        <div>
            <label for="name" class="{{ $label }}">Nombre</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" class="{{ $input }}">
            @error('name')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="{{ $label }}">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="username" class="{{ $input }}">
            @error('email')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
            @unless ($user->hasVerifiedEmail())
                <p class="mt-1 text-xs text-yellow-700">Tu correo aún no está verificado. <a href="{{ route('verification.notice') }}" class="underline">Reenviar enlace</a></p>
            @endunless
        </div>

        <div class="flex justify-end">
            <button class="bg-brand-500 hover:bg-brand-600 rounded-lg px-5 py-2.5 text-sm font-medium text-white">Guardar</button>
        </div>
    </form>

    <form method="POST" action="{{ route('password.update') }}" class="{{ $card }} space-y-5">
        @csrf
        @method('PUT')
        <div>
            <h3 class="text-base font-bold text-gray-900">Cambiar contraseña</h3>
            <p class="text-xs text-gray-500">Usa una contraseña larga que no uses en otros sitios.</p>
        </div>

        <div>
            <label for="current_password" class="{{ $label }}">Contraseña actual</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="{{ $input }}">
            @if ($errors->updatePassword->first('current_password'))<p class="text-error-500 mt-1 text-sm">{{ $errors->updatePassword->first('current_password') }}</p>@endif
        </div>
        <div>
            <label for="password" class="{{ $label }}">Contraseña nueva</label>
            <input id="password" name="password" type="password" autocomplete="new-password" class="{{ $input }}">
            @if ($errors->updatePassword->first('password'))<p class="text-error-500 mt-1 text-sm">{{ $errors->updatePassword->first('password') }}</p>@endif
        </div>
        <div>
            <label for="password_confirmation" class="{{ $label }}">Confirmar contraseña nueva</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $input }}">
        </div>

        <div class="flex justify-end">
            <button class="bg-brand-500 hover:bg-brand-600 rounded-lg px-5 py-2.5 text-sm font-medium text-white">Actualizar contraseña</button>
        </div>
    </form>

    <p class="text-xs text-gray-400">
        ¿Quieres corregir tus datos personales o cancelar tu cuenta? Escribe a la Coordinación:
        {{ config('practicas.contacto.correo') }} (consulta el <a href="{{ route('privacidad') }}" class="underline">aviso de privacidad</a>).
    </p>
</div>
@endsection