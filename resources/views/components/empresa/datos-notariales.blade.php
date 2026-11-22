@props([
    'empresa' => null,
    'bloqueado' => false,
    'campo' => 'campo h-11',          // clase de los campos de una línea
    'area' => 'campo',                // clase de los textarea
    'etiqueta' => 'etiqueta',
])

{{--
    Datos de creación de la organización (paso 1 del trámite de Vinculación).
    Sociedades y asociaciones: escritura pública, fecha, notario, notaría y folio mercantil por separado;
    el sistema redacta la declaración del convenio. Dependencias públicas u otro: ley o decreto en texto libre.
    Requiere un ancestro con x-data que exponga `tipo` (valor de TipoOrganismo). Los campos ocultos se deshabilitan
    para que el navegador no los exija ni los envíe.
--}}
@php
    $v = fn (string $c) => old($c, $c === 'escritura_fecha' ? $empresa?->escritura_fecha?->toDateString() : $empresa?->{$c});
    $err = fn (string $c) => $errors->first($c);
    $escritura = "['privado', 'sociedad_civil'].includes(tipo)";
    $fijo = $bloqueado ? 'true' : 'false';   // bloqueado: ningún campo se edita
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <div x-show="{{ $escritura }}" x-cloak class="space-y-4">
        <div class="rounded-xl bg-udg-50/60 px-4 py-3 text-xs leading-relaxed text-udg-900 ring-1 ring-inset ring-udg-100">
            Copia estos datos tal como aparecen en tu acta constitutiva. Con ellos se redacta automáticamente tu declaración en el convenio.
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="dn-escritura" class="{{ $etiqueta }}">Número de escritura pública <span class="text-error-500" aria-hidden="true">*</span></label>
                <input id="dn-escritura" type="text" name="escritura_numero" maxlength="10" inputmode="numeric" data-solo="digitos" value="{{ $v('escritura_numero') }}" placeholder="Ej. 12345"
                    x-bind:disabled="{{ $fijo }} || !({{ $escritura }})" x-bind:required="{{ $escritura }}" class="{{ $campo }}">
                @if ($m = $err('escritura_numero'))<p class="mt-1 text-sm text-error-500">{{ $m }}</p>@endif
            </div>
            <div>
                <label for="dn-fecha" class="{{ $etiqueta }}">Fecha de la escritura <span class="text-error-500" aria-hidden="true">*</span></label>
                <input id="dn-fecha" type="date" name="escritura_fecha" max="{{ today()->toDateString() }}" value="{{ $v('escritura_fecha') }}"
                    x-bind:disabled="{{ $fijo }} || !({{ $escritura }})" x-bind:required="{{ $escritura }}" class="{{ $campo }}">
                @if ($m = $err('escritura_fecha'))<p class="mt-1 text-sm text-error-500">{{ $m }}</p>@endif
            </div>
            <div class="sm:col-span-2">
                <label for="dn-notario" class="{{ $etiqueta }}">Notario que dio fe <span class="text-error-500" aria-hidden="true">*</span></label>
                <input id="dn-notario" type="text" name="notario_nombre" minlength="5" maxlength="80" data-solo="letras" pattern="{{ \App\Support\Formatos::NOMBRE_PERSONA_HTML }}" data-msg-formato="Escribe el nombre completo del notario (nombre y apellido), solo con letras." value="{{ $v('notario_nombre') }}" placeholder="Nombre completo del notario"
                    x-bind:disabled="{{ $fijo }} || !({{ $escritura }})" x-bind:required="{{ $escritura }}" class="{{ $campo }}">
                @if ($m = $err('notario_nombre'))<p class="mt-1 text-sm text-error-500">{{ $m }}</p>@endif
            </div>
            <div>
                <label for="dn-notaria" class="{{ $etiqueta }}">Número de notaría <span class="text-error-500" aria-hidden="true">*</span></label>
                <input id="dn-notaria" type="text" name="notaria_numero" maxlength="4" inputmode="numeric" data-solo="digitos" value="{{ $v('notaria_numero') }}" placeholder="Ej. 45"
                    x-bind:disabled="{{ $fijo }} || !({{ $escritura }})" x-bind:required="{{ $escritura }}" class="{{ $campo }}">
                @if ($m = $err('notaria_numero'))<p class="mt-1 text-sm text-error-500">{{ $m }}</p>@endif
            </div>
            <div>
                <label for="dn-ciudad" class="{{ $etiqueta }}">Ciudad de la notaría <span class="text-error-500" aria-hidden="true">*</span></label>
                <input id="dn-ciudad" type="text" name="notaria_ciudad" minlength="3" maxlength="120" data-solo="letras" value="{{ $v('notaria_ciudad') }}" placeholder="Ej. Guadalajara, Jalisco"
                    x-bind:disabled="{{ $fijo }} || !({{ $escritura }})" x-bind:required="{{ $escritura }}" class="{{ $campo }}">
                @if ($m = $err('notaria_ciudad'))<p class="mt-1 text-sm text-error-500">{{ $m }}</p>@endif
            </div>
            <div class="sm:col-span-2">
                <label for="dn-folio" class="{{ $etiqueta }}">Folio mercantil <span class="font-normal text-gray-400">(si está inscrita)</span></label>
                <input id="dn-folio" type="text" name="folio_mercantil" minlength="3" maxlength="60" data-solo="clave" value="{{ $v('folio_mercantil') }}" placeholder="Folio del Registro Público de Comercio"
                    x-bind:disabled="{{ $fijo }} || !({{ $escritura }})" class="{{ $campo }}">
                @if ($m = $err('folio_mercantil'))<p class="mt-1 text-sm text-error-500">{{ $m }}</p>@endif
            </div>
        </div>
    </div>

    <div x-show="tipo && !({{ $escritura }})" x-cloak>
        <label for="dn-decreto" class="{{ $etiqueta }}">Ley o decreto de creación <span class="text-error-500" aria-hidden="true">*</span></label>
        <textarea id="dn-decreto" name="constitucion" rows="3" minlength="15" maxlength="1500" x-bind:disabled="{{ $fijo }} || {{ $escritura }}" x-bind:required="!({{ $escritura }})"
            class="{{ $area }}">{{ old('constitucion', \App\Support\DatosNotariales::usaEscritura($empresa?->tipo_organismo) ? '' : $empresa?->constitucion) }}</textarea>
        <p class="mt-1 text-xs leading-relaxed text-gray-500">Ej. «el decreto publicado en el Periódico Oficial del Estado de Jalisco el 10 de enero de 2010».</p>
        @if ($m = $err('constitucion'))<p class="mt-1 text-sm text-error-500">{{ $m }}</p>@endif
    </div>
</div>
