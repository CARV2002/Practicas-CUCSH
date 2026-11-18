@extends('layouts.fullscreen-layout')

@section('content')
    @php
        $area = 'w-full rounded-xl border border-gray-300 bg-gray-50/60 px-4 py-3 text-[15px] text-gray-900 shadow-theme-xs transition duration-200 ease-out placeholder:text-gray-400 hover:border-gray-400 focus:border-real-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-real-500/15 motion-reduce:transition-none';
        $legales = [
            ['constitucion', 'Acta o decreto de creación', 'Ej. Escritura pública 1234 del 10 de enero de 2010, ante el notario 5 de Guadalajara; o la ley o decreto publicado en el DOF o el Periódico Oficial, con su fecha.'],
            ['objeto_social', 'Objeto social o atribuciones relacionadas con las prácticas', 'Lo que dicen tus estatutos (asociaciones y sociedades) o tus atribuciones (dependencias de gobierno).'],
            ['facultad_firmante', 'Facultad de quien firma el convenio', 'Ej. Poder notarial 567 del 2 de febrero de 2020; o «manifiesta bajo protesta de decir verdad que cuenta con autorización».'],
        ];
    @endphp
    <x-auth.split title="Registro de empresa"
        subtitle="Crea la cuenta de tu empresa o institución para tramitar el convenio con el CUCSH. Los datos legales se escriben una sola vez y van directo a tu convenio.">
        <form method="POST" action="{{ route('registro.empresa') }}">
            @csrf
            <div class="space-y-8">
                <fieldset class="space-y-5">
                    <legend class="mb-3 block font-display text-base font-bold text-gray-900">Tu organización</legend>
                    <x-auth.input name="razon_social" label="Razón social" />
                    <x-auth.input name="rfc" label="RFC" placeholder="12 o 13 caracteres" />

                    <div>
                        <label for="tipo_organismo" class="mb-2 block text-sm font-semibold text-gray-800">
                            Tipo de organismo<span class="ml-0.5 text-error-500" aria-hidden="true">*</span>
                        </label>
                        <select id="tipo_organismo" name="tipo_organismo" required
                            class="h-12 w-full rounded-xl border border-gray-300 bg-gray-50/60 px-4 text-[15px] text-gray-900 shadow-theme-xs focus:border-real-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-real-500/15">
                            <option value="">Selecciona una opción</option>
                            @foreach ($tiposOrganismo as $tipo)
                                <option value="{{ $tipo->value }}" @selected(old('tipo_organismo') === $tipo->value)>{{ $tipo->label() }}</option>
                            @endforeach
                        </select>
                        @error('tipo_organismo')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
                    </div>

                    <x-auth.input name="domicilio_fiscal" label="Domicilio legal" placeholder="Calle, número, colonia, C.P., municipio y estado" />
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-3 block font-display text-base font-bold text-gray-900">Datos legales para el convenio</legend>
                    <p class="!mt-1 text-sm leading-relaxed text-gray-500">Son las declaraciones de tu organización en el convenio de prácticas. Vinculación las compara con tu acta constitutiva antes de generar el documento.</p>

                    <x-auth.input name="representante_legal" label="Nombre del representante legal" placeholder="Quien firmará el convenio" />
                    <x-auth.input name="representante_cargo" label="Cargo del representante" placeholder="Ej. Director General, Apoderado legal" />

                    @foreach ($legales as [$campo, $etiqueta, $ayuda])
                        <div>
                            <label for="{{ $campo }}" class="mb-2 block text-sm font-semibold text-gray-800">{{ $etiqueta }}<span class="ml-0.5 text-error-500" aria-hidden="true">*</span></label>
                            <textarea id="{{ $campo }}" name="{{ $campo }}" rows="3" required maxlength="1500" class="{{ $area }}">{{ old($campo) }}</textarea>
                            <p class="mt-1 text-xs leading-relaxed text-gray-500">{{ $ayuda }}</p>
                            @error($campo)<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-3 block font-display text-base font-bold text-gray-900">Persona de contacto y acceso</legend>
                    <x-auth.input name="name" label="Nombre de la persona de contacto" autocomplete="name" />
                    <x-auth.input name="contacto_cargo" label="Cargo" :required="false" />
                    <x-auth.input name="contacto_telefono" label="Teléfono" :required="false" autocomplete="tel" />

                    <x-auth.input name="email" type="email" label="Correo electrónico" autocomplete="username" />
                    <x-auth.input name="password" type="password" label="Contraseña" autocomplete="new-password" />
                    <x-auth.input name="password_confirmation" type="password" label="Confirmar contraseña" autocomplete="new-password" />
                </fieldset>

                <div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="aviso_privacidad" value="1" class="mt-0.5 rounded border-gray-300"
                            @checked(old('aviso_privacidad')) required>
                        <span>Acepto el <a href="{{ route('privacidad') }}" target="_blank" rel="noopener"
                                class="text-real-600 underline">aviso de privacidad</a>
                            y los <a href="{{ route('terminos') }}" target="_blank" rel="noopener"
                                class="text-real-600 underline">términos y condiciones</a>,
                            y el tratamiento de mis datos personales.</span>
                    </label>
                    @error('aviso_privacidad')<p class="text-error-500 mt-1 text-sm">{{ $message }}</p>@enderror
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
