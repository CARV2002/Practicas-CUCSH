@extends('layouts.app')

@section('content')
    @php
        $input = 'campo h-11';
        $label = 'etiqueta';
        $card = 'tarjeta p-6';
    @endphp

    <x-common.page-breadcrumb pageTitle="Mi perfil" />

    <div class="max-w-2xl space-y-6">
        @if (session('status') === 'profile-updated')
            <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">Datos actualizados.</div>
        @endif
        @if (session('status') === 'password-updated')
            <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">Contraseña actualizada.</div>
        @endif
        @if (session('status') === 'foto-updated')
            <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">Foto actualizada.</div>
        @endif
        @if (session('status') === 'datos-escolares-updated')
            <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">Datos escolares actualizados.</div>
        @endif
        @if (session('status') === 'foto-deleted')
            <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">Foto eliminada.</div>
        @endif

        <div class="{{ $card }} space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900">Foto de perfil</h3>
                <p class="text-xs text-gray-500">JPG, PNG o WebP, de hasta 2 MB. Es opcional.</p>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <x-ui.user-avatar :user="$user" size="h-20 w-20 text-xl" />
                <form method="POST" action="{{ route('profile.foto') }}" enctype="multipart/form-data"
                    class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required
                        class="focus:border-real-500 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <button
                        class="boton boton-primario">Subir
                        foto</button>
                </form>
            </div>
            @error('foto')
            <p class="text-error-500 text-sm">{{ $message }}</p>@enderror

            @if ($user->foto_url)
                <form method="POST" action="{{ route('profile.foto.eliminar') }}">
                    @csrf
                    @method('DELETE')
                    <button class="text-xs font-semibold text-red-600 underline">Quitar foto</button>
                </form>
            @endif
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="{{ $card }} space-y-5">
            @csrf
            @method('PATCH')
            <div>
                <h3 class="text-base font-bold text-gray-900">Datos de la cuenta</h3>
                <p class="text-xs text-gray-500">Si cambias tu correo tendrás que confirmarlo de nuevo.</p>
            </div>

            <div>
                <label for="name" class="{{ $label }}">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255"
                    autocomplete="name" class="{{ $input }}">
                @error('name')
                <p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="{{ $label }}">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                    maxlength="255" autocomplete="username" class="{{ $input }}">
                @error('email')
                <p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
                @unless ($user->hasVerifiedEmail())
                    <p class="mt-1 text-xs text-yellow-700">Tu correo aún no está verificado. <a
                            href="{{ route('verification.notice') }}" class="underline">Reenviar enlace</a></p>
                @endunless
            </div>

            <div class="flex justify-end">
                <button
                    class="bg-udg-800 hover:bg-udg-700 rounded-lg px-5 py-2.5 text-sm font-medium text-white">Guardar</button>
            </div>
        </form>

        @if ($user->alumno)
            <form method="POST" action="{{ route('alumno.datos-escolares') }}" class="{{ $card }} space-y-5">
                @csrf
                @method('PUT')
                <div>
                    <h3 class="text-base font-bold text-gray-900">Datos escolares</h3>
                    <p class="text-xs text-gray-500">
                        {{ $user->alumno->carrera->nombre }} · Código {{ $user->alumno->num_control }}.
                        Tu grado y turno aparecen en tu Carta de Presentación: mantenlos al día.
                    </p>
                </div>
                <x-alumno.grado-turno :grado="$user->alumno->grado" :turno="$user->alumno->turno" />
                <div class="flex justify-end">
                    <button class="bg-udg-800 hover:bg-udg-700 rounded-lg px-5 py-2.5 text-sm font-medium text-white">Guardar datos escolares</button>
                </div>
            </form>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="{{ $card }} space-y-5">
            @csrf
            @method('PUT')
            <div>
                <h3 class="text-base font-bold text-gray-900">Cambiar contraseña</h3>
                <p class="text-xs text-gray-500">Usa una contraseña larga que no uses en otros sitios.</p>
            </div>

            <div>
                <label for="current_password" class="{{ $label }}">Contraseña actual</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                    class="{{ $input }}">
                @if ($errors->updatePassword->first('current_password'))
                <p class="text-error-500 mt-1 text-sm">{{ $errors->updatePassword->first('current_password') }}</p>@endif
            </div>
            <div>
                <label for="password" class="{{ $label }}">Contraseña nueva</label>
                <input id="password" name="password" type="password" autocomplete="new-password" class="{{ $input }}">
                @if ($errors->updatePassword->first('password'))
                <p class="text-error-500 mt-1 text-sm">{{ $errors->updatePassword->first('password') }}</p>@endif
            </div>
            <div>
                <label for="password_confirmation" class="{{ $label }}">Confirmar contraseña nueva</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                    class="{{ $input }}">
            </div>

            <div class="flex justify-end">
                <button
                    class="bg-udg-800 hover:bg-udg-700 rounded-lg px-5 py-2.5 text-sm font-medium text-white">Actualizar
                    contraseña</button>
            </div>
        </form>

        <p class="text-xs text-gray-400">
            ¿Quieres corregir tus datos personales o cancelar tu cuenta? Escribe a
            {{ $user->alumno ? 'tu coordinación: ' . ($user->alumno->carrera->correoContacto() ?? config('practicas.contacto.correo')) : 'Vinculación: ' . config('practicas.contacto.correo') }}
            (consulta el <a href="{{ route('privacidad') }}"
                class="underline">aviso de privacidad</a>).
        </p>
    </div>
@endsection