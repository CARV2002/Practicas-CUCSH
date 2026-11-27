{{--
    Datos que Vinculación devolvió para corregir: solo estos campos se pueden editar mientras la solicitud está
    en revisión (todo lo demás sigue en solo lectura). Se envían juntos a Empresa\ConvenioController::corregir.
--}}
@php
    use App\Support\SolicitudEmpresa as S;

    $pendientes = $convenio->correccionesPendientes();
    $nombres = S::atributos();
    $largos = ['descripcion', 'proyectos_principales', 'objeto_social', 'facultad_firmante', 'constitucion', 'objetivos', 'actividades_propuestas', 'experiencia_previa'];
    $fechas = ['escritura_fecha'];
    $valor = fn (string $c) => old($c, in_array($c, S::CORREGIBLES['perfiles'], true)
        ? $convenio->{$c}
        : ($empresa->{$c} instanceof \Carbon\CarbonInterface ? $empresa->{$c}->toDateString() : $empresa->{$c}));
@endphp

<section class="rounded-2xl border border-rose-200 bg-white shadow-theme-xs" aria-labelledby="titulo-correcciones">
    <div class="flex items-start gap-3 border-b border-rose-100 bg-rose-50/60 px-5 py-4 sm:px-6">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700"><x-dashboard.icono nombre="editar" clase="size-4" /></span>
        <div>
            <h2 id="titulo-correcciones" class="font-display text-base font-bold text-rose-900">Vinculación te pidió corregir {{ count($pendientes) }} {{ count($pendientes) === 1 ? 'dato' : 'datos' }}</h2>
            <p class="mt-0.5 text-sm text-rose-800">Solo estos campos están abiertos. Corrígelos y envíalos; lo demás de tu solicitud no cambia.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('empresa.convenio.corregir') }}" class="space-y-4 p-5 sm:p-6"
        data-confirmar="Antes de enviar, revisa que cada dato esté completo y bien escrito. ¿Todo está bien?" data-confirmar-titulo="Verifica tus correcciones" data-confirmar-boton="Sí, enviar corrección" data-confirmar-tono="normal">
        @csrf
        @method('PUT')
        @foreach ($pendientes as $campo => $c)
            <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-inset ring-gray-100">
                <label for="corr-{{ $campo }}" class="etiqueta">{{ ucfirst($nombres[$campo] ?? $campo) }} <span class="font-normal text-gray-500">· {{ S::SECCIONES[$c['seccion']] ?? '' }}</span></label>
                <p class="-mt-1 mb-2 text-xs text-rose-700">Motivo: «{{ $c['motivo'] }}»</p>
                @if (in_array($campo, $largos, true))
                    <textarea id="corr-{{ $campo }}" name="{{ $campo }}" rows="3" maxlength="3000" required class="campo !border-rose-300">{{ $valor($campo) }}</textarea>
                @elseif ($campo === 'entidad')
                    <select id="corr-{{ $campo }}" name="entidad" required class="campo h-11 !border-rose-300">
                        <option value="">Elige el estado</option>
                        @foreach (S::ENTIDADES as $e)<option value="{{ $e }}" @selected($valor('entidad') === $e)>{{ $e }}</option>@endforeach
                    </select>
                @elseif (in_array($campo, $fechas, true))
                    <input id="corr-{{ $campo }}" type="date" name="{{ $campo }}" value="{{ $valor($campo) }}" max="{{ today()->toDateString() }}" required class="campo h-11 !border-rose-300">
                @elseif ($campo === 'plazas_estimadas')
                    <input id="corr-{{ $campo }}" type="number" name="{{ $campo }}" value="{{ $valor($campo) }}" min="1" max="500" required class="campo h-11 !border-rose-300">
                @elseif ($campo === 'contacto_telefono')
                    <input id="corr-{{ $campo }}" type="tel" inputmode="numeric" data-solo="digitos" maxlength="10" pattern="{{ \App\Support\Formatos::TELEFONO_HTML }}"
                        data-msg-formato="Escribe un teléfono real de 10 números (ej. 3312345678)." name="{{ $campo }}" value="{{ $valor($campo) }}" required class="campo h-11 !border-rose-300">
                @elseif ($campo === 'codigo_postal')
                    <input id="corr-{{ $campo }}" inputmode="numeric" data-solo="digitos" maxlength="5" pattern="\d{5}" data-msg-formato="El código postal son 5 números." name="{{ $campo }}" value="{{ $valor($campo) }}" required class="campo h-11 !border-rose-300">
                @elseif (in_array($campo, ['numero_exterior', 'numero_interior'], true))
                    <input id="corr-{{ $campo }}" data-solo="domicilio" maxlength="15" pattern="{{ $campo === 'numero_exterior' ? \App\Support\Formatos::NUMERO_EXTERIOR_HTML : \App\Support\Formatos::NUMERO_INTERIOR_HTML }}"
                        name="{{ $campo }}" value="{{ $valor($campo) }}" @required($campo === 'numero_exterior') class="campo h-11 !border-rose-300">
                @else
                    <input id="corr-{{ $campo }}" name="{{ $campo }}" value="{{ $valor($campo) }}" maxlength="255" required class="campo h-11 !border-rose-300">
                @endif
                @error($campo)<p class="mt-1.5 text-sm font-medium text-error-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div class="flex justify-end">
            <button class="boton boton-primario"><x-dashboard.icono nombre="enviar" clase="size-4" /> Enviar corrección</button>
        </div>
    </form>
</section>
