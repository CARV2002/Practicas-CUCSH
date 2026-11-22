@extends('layouts.fullscreen-layout')

@section('content')
    @php
        $selectCls = 'h-12 w-full rounded-xl border border-gray-300 bg-gray-50/60 px-4 text-[15px] text-gray-900 shadow-theme-xs focus:border-real-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-real-500/15';
    @endphp
    <x-auth.split title="Registro de empresa"
        subtitle="Paso 1 de 2: crea la cuenta de tu organización. Después, desde tu panel, llenas la solicitud de convenio por secciones y puedes guardar tu avance.">
        <form method="POST" action="{{ route('registro.empresa') }}" x-data="{ tipo: @js(old('tipo_organismo', '')) }">
            @csrf
            <div class="space-y-8">
                <fieldset class="space-y-5">
                    <legend class="mb-3 block font-display text-base font-bold text-gray-900">Tu organización</legend>
                    <x-auth.input name="razon_social" label="Razón social" minlength="3" maxlength="{{ \App\Support\Formatos::MAX_RAZON_SOCIAL }}" data-contador="caracteres"
                        placeholder="Como aparece en tu constancia de situación fiscal" />
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-auth.input name="rfc" label="RFC" placeholder="Ej. BJP150420ABC" autocomplete="off" data-solo="rfc" minlength="12" maxlength="13"
                            :pattern="\App\Support\Formatos::RFC_HTML"
                            data-msg-formato="El RFC lleva 12 caracteres si es persona moral (BJP150420ABC) o 13 si es persona física (PEGS850812XYZ)."
                            ayuda="12 caracteres (persona moral) o 13 (persona física). Se escribe en mayúsculas solo." />
                        <div>
                            <label for="tipo_organismo" class="mb-2 block text-sm font-semibold text-gray-800">Tipo de organismo<span class="ml-0.5 text-error-500" aria-hidden="true">*</span></label>
                            <select id="tipo_organismo" name="tipo_organismo" required x-model="tipo" class="{{ $selectCls }}">
                                <option value="">Selecciona una opción</option>
                                @foreach ($tiposOrganismo as $tipo)
                                    <option value="{{ $tipo->value }}" @selected(old('tipo_organismo') === $tipo->value)>{{ $tipo->label() }}</option>
                                @endforeach
                            </select>
                            @error('tipo_organismo')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div x-show="tipo === 'otro'" x-cloak>
                        <label for="tipo_organismo_otro" class="mb-2 block text-sm font-semibold text-gray-800">¿Qué tipo de organismo es?<span class="ml-0.5 text-error-500" aria-hidden="true">*</span></label>
                        <input id="tipo_organismo_otro" name="tipo_organismo_otro" value="{{ old('tipo_organismo_otro') }}" minlength="3" maxlength="80" :required="tipo === 'otro'" :disabled="tipo !== 'otro'"
                            placeholder="Ej. Organismo internacional, fideicomiso, cooperativa"
                            class="h-12 w-full rounded-xl border border-gray-300 bg-gray-50/60 px-4 text-[15px] text-gray-900 shadow-theme-xs focus:border-real-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-real-500/15">
                        @error('tipo_organismo_otro')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                    </div>
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-3 block font-display text-base font-bold text-gray-900">Persona de contacto</legend>
                    <p class="!mt-1 text-sm leading-relaxed text-gray-500">Quien dará seguimiento al trámite con Vinculación (por ejemplo, el enlace de Recursos Humanos).</p>
                    <x-auth.input name="name" label="Nombre completo" autocomplete="name" placeholder="Nombre(s) y apellidos" data-solo="letras" minlength="5"
                        maxlength="{{ \App\Support\Formatos::MAX_NOMBRE_PERSONA }}" data-contador="caracteres" :pattern="\App\Support\Formatos::NOMBRE_PERSONA_HTML"
                        data-msg-formato="Escribe el nombre completo del contacto (nombre y apellido), solo con letras." />
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-auth.input name="contacto_cargo" label="Cargo" data-solo="letras" minlength="3" maxlength="120" placeholder="Ej. Coordinadora de RH" />
                        <x-auth.input name="contacto_telefono" label="Teléfono" autocomplete="tel" type="tel" inputmode="numeric" data-solo="digitos" data-contador="dígitos"
                            minlength="10" maxlength="10" pattern="\d{10}" data-msg-formato="El teléfono son 10 números, sin espacios ni guiones (por ejemplo 3312345678)." placeholder="Ej. 3312345678" />
                    </div>
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-3 block font-display text-base font-bold text-gray-900">Acceso</legend>
                    <x-auth.input name="email" type="email" label="Correo electrónico" autocomplete="username" ayuda="De preferencia el institucional de tu organización: aquí te llegarán los avisos del trámite." />
                    <x-auth.input name="password" type="password" label="Contraseña" autocomplete="new-password" />
                    <x-auth.input name="password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" />
                </fieldset>

                <div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="aviso_privacidad" value="1" class="mt-0.5 rounded border-gray-300" @checked(old('aviso_privacidad')) required>
                        <span>Acepto el <a href="{{ route('privacidad') }}" target="_blank" rel="noopener" class="text-real-600 underline">aviso de privacidad</a>
                            y los <a href="{{ route('terminos') }}" target="_blank" rel="noopener" class="text-real-600 underline">términos y condiciones</a>,
                            y el tratamiento de mis datos personales.</span>
                    </label>
                    @error('aviso_privacidad')<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
                </div>

                <x-auth.submit>Crear cuenta</x-auth.submit>

                <p class="text-center text-sm text-gray-500">
                    ¿Ya tienes cuenta?
                    <a href="{{ route('login') }}" class="text-real-600 hover:text-real-700">Inicia sesión</a>
                </p>
            </div>
        </form>
    </x-auth.split>
@endsection
