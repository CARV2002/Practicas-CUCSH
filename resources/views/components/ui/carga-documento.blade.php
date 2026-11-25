@props([
    'action' => null,            // URL del formulario (POST). Sin acción: solo muestra el estado.
    'titulo',                    // «Constancia de vigencia del IMSS»
    'instrucciones' => null,     // qué subir y de dónde sacarlo
    'name' => 'archivo',
    'accept' => '.pdf',
    'formatos' => 'PDF',         // texto legible de los formatos aceptados
    'maxMb' => 10,
    'estado' => null,            // null | pendiente | validada | rechazada
    'motivo' => null,            // motivo del rechazo
    'archivo' => null,           // nombre del archivo actual
    'urlArchivo' => null,        // enlace de descarga del archivo actual (abre la vista previa)
    'fecha' => null,             // fecha en que se subió
    'boton' => null,             // texto del botón; por defecto «Enviar» o «Enviar corrección»
    'bloqueo' => null,           // motivo por el que no se puede subir (muestra candado)
    'reemplazable' => false,     // permite reemplazar aunque esté validado
    'compacto' => false,         // versión reducida para tablas y paneles laterales
    'pasoArchivo' => null,       // número del paso del archivo cuando hay campos previos (slot «campos»)
    'etiquetaEstado' => null,    // texto propio de la insignia (p. ej. «Generada» para una carta del sistema)
    'tituloMotivo' => null,      // título del recuadro rojo cuando no lo devolvió la coordinación (p. ej. una regla del sistema)
    'mensajeListo' => null,      // texto propio cuando ya está listo (sustituye a «Ya está validado…»)
])

{{--
    Tarjeta de carga de un documento (DESIGN_SYSTEM.md §6): estado visible, instrucciones claras,
    zona para arrastrar o elegir el archivo, validación de tipo y peso antes de enviar, errores
    del servidor en su lugar y botón que muestra «Enviando…». Slots opcionales:
      - campos: datos que acompañan al archivo (p. ej. la fecha de vigencia del IMSS), numerados como paso 1
      - ocultos: inputs hidden (p. ej. «coleccion»)
--}}
@php
    $validado = $estado === 'validada';
    $rechazado = $estado === 'rechazada';
    $enRevision = $estado === 'pendiente';
    $puedeSubir = $action && !$bloqueo && (!$validado || $reemplazable);
    $chip = match (true) {
        $validado => ['menta', $etiquetaEstado ?? 'Validado'],
        $rechazado => ['rosa', $etiquetaEstado ?? 'Devuelto para corregir'],
        $enRevision => ['ambar', 'En revisión'],
        default => ['gris', 'Pendiente de subir'],
    };
    $boton ??= $rechazado ? 'Enviar corrección' : ($archivo ? 'Reemplazar archivo' : 'Enviar documento');
    $idBase = 'carga-' . \Illuminate\Support\Str::slug($titulo) . '-' . substr(md5($action . $name), 0, 6);
    // Varios formularios usan «archivo»: el error se muestra solo en la tarjeta que se envió
    $propio = old('_carga') === $idBase;
    $tieneError = $propio && $errors->has($name);
@endphp

<div {{ $attributes->class([
    'rounded-2xl border bg-white transition-colors',
    'border-emerald-200' => $validado,
    'border-rose-300 ring-2 ring-rose-100' => $rechazado || $tieneError,
    'border-gray-200' => !$validado && !$rechazado && !$tieneError,
    'p-4' => $compacto,
    'p-5 sm:p-6' => !$compacto,
]) }}>
    {{-- Encabezado: qué es y en qué estado está --}}
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <p @class(['font-semibold text-gray-900', 'text-sm' => $compacto, 'font-display text-base' => !$compacto])>{{ $titulo }}</p>
            @if ($instrucciones && !$validado)
                <p class="mt-0.5 text-xs leading-relaxed text-gray-600 sm:text-sm">{{ $instrucciones }}</p>
            @endif
        </div>
        <x-ui.estatus :familia="$chip[0]" :texto="$chip[1]" :tamano="$compacto ? 'chico' : 'normal'" class="shrink-0" />
    </div>

    {{-- Archivo actual --}}
    @if ($archivo)
        <div @class(['mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl px-3 py-2 text-sm', 'bg-emerald-50/60' => $validado, 'bg-gray-50' => !$validado])>
            <x-dashboard.icono nombre="documento" clase="size-4 shrink-0 text-gray-500" />
            @if ($urlArchivo)
                <a href="{{ $urlArchivo }}" class="min-w-0 flex-1 truncate font-medium text-real-600 hover:text-real-700 hover:underline">{{ $archivo }}</a>
            @else
                <span class="min-w-0 flex-1 truncate font-medium text-gray-800">{{ $archivo }}</span>
            @endif
            @if ($fecha)<span class="text-xs text-gray-500">Subido el {{ $fecha }}</span>@endif
        </div>
    @endif

    {{-- Mensajes según el estado --}}
    @if ($validado && !$reemplazable)
        <p class="mt-3 flex items-start gap-2 rounded-xl bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800">
            <x-dashboard.icono nombre="check" clase="mt-0.5 size-4 shrink-0" />
            <span>@if ($mensajeListo){{ $mensajeListo }}@else<span class="font-semibold">Ya está validado.</span> No necesitas volver a subirlo.@endif</span>
        </p>
    @elseif ($rechazado)
        <div class="mt-3 rounded-xl bg-rose-50 px-3 py-2.5 text-sm text-rose-800" role="alert">
            <p class="flex items-center gap-2 font-semibold"><x-dashboard.icono nombre="alerta" clase="size-4 shrink-0" /> {{ $tituloMotivo ?? 'Tu coordinación te pidió corregirlo' }}</p>
            @if ($motivo)<p class="mt-1 pl-6">«{{ $motivo }}»</p>@endif
            @if ($puedeSubir)<p class="mt-1 pl-6 text-rose-700">Sube el archivo corregido aquí abajo; volverá a revisión automáticamente.</p>@endif
        </div>
    @elseif ($enRevision && $puedeSubir)
        <p class="mt-3 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
            <x-dashboard.icono nombre="reloj" clase="mt-0.5 size-4 shrink-0" />
            <span>Tu coordinación lo está revisando. Solo reemplázalo si te equivocaste de archivo.</span>
        </p>
    @endif

    @if ($bloqueo && !$validado)
        <p class="mt-3 flex items-start gap-2 rounded-xl bg-gray-50 px-3 py-2.5 text-sm text-gray-600 ring-1 ring-inset ring-gray-200">
            <x-dashboard.icono nombre="candado" clase="mt-0.5 size-4 shrink-0 text-gray-400" />
            <span>{{ $bloqueo }}</span>
        </p>
    @endif

    {{-- Formulario --}}
    @if ($puedeSubir)
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mt-4 space-y-4" novalidate
            x-data="cargaDocumento({ maxMb: {{ (float) $maxMb }}, accept: @js($accept), formatos: @js($formatos) })"
            x-on:submit="enviar($event)">
            @csrf
            <input type="hidden" name="_carga" value="{{ $idBase }}">
            {{ $ocultos ?? '' }}

            @isset($campos)
                <div class="space-y-3">{{ $campos }}</div>
            @endisset

            <div>
                @if ($pasoArchivo)
                    <p class="etiqueta"><span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-udg-800 text-[11px] font-bold text-white">{{ $pasoArchivo }}</span>Sube el archivo</p>
                @endif
                <label for="{{ $idBase }}"
                    x-on:dragover.prevent="arrastrando = true" x-on:dragleave.prevent="arrastrando = false" x-on:drop.prevent="soltar($event)"
                    @class([
                        'group flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed text-center transition',
                        'px-3 py-4' => $compacto,
                        'px-4 py-6 sm:py-7' => !$compacto,
                    ])
                    :class="error ? 'border-rose-300 bg-rose-50/50' : (arrastrando ? 'border-udg-800 bg-udg-50' : (archivo ? 'border-emerald-300 bg-emerald-50/40' : 'border-gray-300 bg-gray-50/50 hover:border-udg-300 hover:bg-udg-50/50'))">
                    <template x-if="!archivo">
                        <span class="flex flex-col items-center gap-1">
                            <span class="flex size-10 items-center justify-center rounded-full bg-white text-udg-900 shadow-theme-xs ring-1 ring-gray-200 transition group-hover:scale-105">
                                <x-dashboard.icono nombre="subir" clase="size-5" />
                            </span>
                            <span class="text-sm font-semibold text-gray-800"><span class="text-real-600 underline underline-offset-2">Elige un archivo</span> o arrástralo aquí</span>
                            <span class="text-xs text-gray-500">{{ $formatos }} · máximo {{ $maxMb }} MB</span>
                        </span>
                    </template>
                    <template x-if="archivo">
                        <span class="flex max-w-full items-center gap-3 text-left">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700"><x-dashboard.icono nombre="check" clase="size-5" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-gray-900" x-text="archivo.nombre"></span>
                                <span class="block text-xs text-gray-500"><span x-text="archivo.peso"></span> · <span class="font-medium text-real-600 underline">Cambiar archivo</span></span>
                            </span>
                        </span>
                    </template>
                    <input id="{{ $idBase }}" x-ref="entrada" type="file" name="{{ $name }}" accept="{{ $accept }}" data-max-mb="{{ $maxMb }}" class="sr-only"
                        x-on:change="elegir($event.target.files[0])" aria-describedby="{{ $idBase }}-ayuda">
                </label>
                <p id="{{ $idBase }}-ayuda" class="sr-only">Formatos aceptados: {{ $formatos }}. Tamaño máximo: {{ $maxMb }} MB.</p>
                <p x-show="error" x-cloak x-text="error" class="mt-1.5 flex items-center gap-1 text-sm font-medium text-rose-600" role="alert"></p>
                @if ($tieneError)<p class="mt-1.5 text-sm font-medium text-rose-600" role="alert">{{ $errors->first($name) }}</p>@endif
            </div>

            <button type="submit" class="boton boton-primario w-full justify-center" :disabled="enviando" :class="enviando && 'opacity-70'">
                <span x-show="!enviando" class="inline-flex items-center gap-2"><x-dashboard.icono nombre="enviar" clase="size-4" /> {{ $boton }}</span>
                <span x-show="enviando" x-cloak class="inline-flex items-center gap-2">
                    <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    Enviando…
                </span>
            </button>
        </form>
    @endif

    {{ $slot }}
</div>
