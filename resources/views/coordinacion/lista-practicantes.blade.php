{{-- resources/views/coordinacion/lista-practicantes.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        use App\Enums\PostulacionEstado as PE;
        use App\Enums\PracticaEstado as PrE;

        $input = 'campo';
        $filtro = 'campo-filtro';
        $btn = 'boton boton-primario';
        $card = 'tarjeta overflow-hidden pt-5';
        $th = 'tabla-th';
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Practicantes" descripcion="Del expediente a la práctica activa: valida documentos, emite la carta de presentación, registra la aceptación y da seguimiento a las horas." />

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
        @endif

        {{-- Expedientes por revisar: kardex + IMSS juntos, ANTES de que el alumno pueda postularse --}}
        <x-tabla.marco titulo="Expedientes por revisar" descripcion="Kardex y constancia IMSS que los alumnos te entregan juntos. Al validarlos, el alumno ya puede postularse a una vacante." :total="$expedientesPorRevisar->count()">
            @if ($expedientesPorRevisar->isEmpty())
                <div class="px-6 py-4"><x-dashboard.vacio icono="check" titulo="No hay expedientes pendientes" texto="Cuando un alumno envíe su kardex y su constancia IMSS, aparecerá aquí." /></div>
            @else
                <ul class="grid grid-cols-1 gap-4 p-4 sm:p-6 lg:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($expedientesPorRevisar as $al)
                        @php
                            $k = $al->documentoVigente('kardex');
                            $i = $al->documentoVigente('constancia_imss');
                            $kSt = $k ? ($k->getCustomProperty('estado') ?? 'pendiente') : null;
                            $iSt = $i ? ($i->getCustomProperty('estado') ?? 'pendiente') : null;
                            $min = (float) $al->carrera->porcentaje_creditos_minimo;
                            $cumple = (float) $al->porcentaje_creditos >= $min;
                            $imssVigente = $al->tieneImssVigenteHasta(now());
                            $abierto = old('alumno') == $al->id && $errors->hasAny(['motivo', 'documentos']);
                            $motivoPrevio = $abierto ? old('motivo', '') : '';
                        @endphp
                        <li class="flex flex-col rounded-2xl border border-gray-200 bg-white p-4" x-data="{ devolver: {{ $abierto ? 'true' : 'false' }}, motivo: @js($motivoPrevio) }">
                            <div class="flex items-start gap-3">
                                <x-ui.user-avatar :user="$al->user" size="size-10 shrink-0 text-xs ring-2 ring-white" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-gray-900">{{ $al->user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }} · {{ $al->grado ? $al->grado . '.º semestre' : 'sin grado' }}</p>
                                </div>
                                <span @class(['shrink-0 text-right font-display text-lg font-bold tabular-nums', 'text-emerald-700' => $cumple, 'text-rose-600' => !$cumple])>
                                    {{ $al->porcentaje_creditos !== null ? \App\Support\Formato::porcentaje($al->porcentaje_creditos) . '%' : 'Sin dato' }}
                                    <span class="block font-sans text-[10px] font-medium text-gray-500">créditos · mín. {{ \App\Support\Formato::porcentaje($min) }}%</span>
                                </span>
                            </div>

                            @php
                                $filas = [
                                    ['Kardex', $k, $kSt, null],
                                    ['Constancia IMSS', $i, $iSt, $al->vigencia_imss ? ($imssVigente ? 'Vigente al ' : 'Venció el ') . $al->vigencia_imss->format('d/m/Y') : null],
                                ];
                            @endphp
                            <ul class="mt-3 space-y-2 text-sm">
                                @foreach ($filas as [$nom, $doc, $st, $nota])
                                    <li class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2">
                                        <span class="min-w-0">
                                            @if ($doc)
                                                <a href="{{ route('coordinacion.alumnos.archivos', [$al, $doc]) }}" class="font-medium text-real-600 hover:underline">{{ $nom }}</a>
                                            @else
                                                <span class="font-medium text-gray-600">{{ $nom }}</span>
                                            @endif
                                            @if ($nota)<span @class(['block text-[11px]', 'text-gray-500' => $imssVigente, 'font-semibold text-rose-600' => !$imssVigente])>{{ $nota }}</span>@endif
                                        </span>
                                        <x-ui.estatus :familia="match ($st) { 'validada' => 'menta', 'rechazada' => 'rosa', 'pendiente' => 'ambar', default => 'gris' }"
                                            :texto="match ($st) { 'validada' => 'Correcto', 'rechazada' => 'Devuelto', 'pendiente' => 'Por revisar', default => 'Sin archivo' }" />
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-auto grid grid-cols-1 gap-1.5 pt-3">
                                @php
                                    $impide = array_values(array_filter([
                                        !$imssVigente ? 'La vigencia del IMSS ya pasó: devuélvelo para que suba una constancia actual.' : null,
                                        !$cumple ? 'Los créditos están por debajo del mínimo de su carrera: devuélvelo si no coinciden con su kardex.' : null,
                                    ]));
                                @endphp
                                @if ($k && $i && $kSt !== 'rechazada' && $iSt !== 'rechazada' && $impide)
                                    <x-ui.accion-bloqueada :motivo="$impide" tamano="chico" class="!items-stretch">Autorizar al alumno</x-ui.accion-bloqueada>
                                @elseif ($k && $i && $kSt !== 'rechazada' && $iSt !== 'rechazada')
                                    <form method="POST" action="{{ route('coordinacion.alumnos.expediente', $al) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="autorizar">
                                        <button class="boton boton-chico boton-exito w-full justify-center"><x-dashboard.icono nombre="check" clase="size-3.5" /> Ambos correctos: autorizar al alumno</button>
                                    </form>
                                @elseif (!$k || !$i)
                                    <p class="text-xs text-gray-500">Falta un documento del alumno.</p>
                                @endif
                                <button type="button" class="boton boton-chico w-full justify-center border border-rose-200 bg-white text-rose-600 hover:bg-rose-50" x-on:click="devolver = !devolver" :aria-expanded="devolver.toString()">
                                    <x-dashboard.icono nombre="deshacer" clase="size-3.5" /> Devolver con observaciones
                                </button>
                            </div>

                            <form x-show="devolver" x-cloak x-transition.opacity method="POST" action="{{ route('coordinacion.alumnos.expediente', $al) }}" class="mt-2 space-y-2 rounded-lg bg-rose-50/60 p-2.5">
                                @csrf
                                <input type="hidden" name="decision" value="devolver">
                                <input type="hidden" name="alumno" value="{{ $al->id }}">
                                <p class="text-xs font-semibold text-gray-800">¿Qué debe corregir?</p>
                                <div class="flex flex-wrap gap-1.5 text-xs">
                                    @foreach (['kardex' => ['Kardex', $kSt], 'imss' => ['IMSS', $iSt]] as $valor => [$texto, $st])
                                        @continue($st === 'validada')
                                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-white px-2.5 py-1 ring-1 ring-inset ring-gray-200 has-[:checked]:bg-rose-100 has-[:checked]:ring-rose-300">
                                            <input type="checkbox" name="documentos[]" value="{{ $valor }}" class="size-3.5 rounded border-gray-300 text-rose-600"> {{ $texto }}
                                        </label>
                                    @endforeach
                                </div>
                                <label class="sr-only" for="motivo-al-{{ $al->id }}">Motivo</label>
                                <textarea id="motivo-al-{{ $al->id }}" name="motivo" x-model="motivo" rows="2" required maxlength="500" class="campo text-xs" placeholder="Qué está mal y qué debe subir"></textarea>
                                <div class="flex flex-wrap gap-1">
                                    @foreach (['El kardex es de un ciclo anterior; sube el del ciclo actual.', 'El IMSS aparece dado de baja; descarga una constancia vigente.', 'El porcentaje de créditos no coincide con tu kardex.', 'El archivo no se puede leer; súbelo escaneado completo.'] as $sug)
                                        <button type="button" class="rounded-full bg-white px-2 py-0.5 text-[11px] text-gray-600 ring-1 ring-inset ring-gray-200 hover:ring-udg-300" x-on:click="motivo = @js($sug)">{{ \Illuminate\Support\Str::before($sug, ';') }}</button>
                                    @endforeach
                                </div>
                                @if ($abierto)
                                    @error('documentos')<p class="text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                                    @error('motivo')<p class="text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                                @endif
                                <button class="boton boton-chico boton-peligro w-full justify-center">Devolver al alumno</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-tabla.marco>

        {{-- Alumnos bloqueados: baja por sanción o límite de bajas. Solo la coordinación los libera, con justificación. --}}
        @if ($bloqueados->isNotEmpty())
            <x-tabla.marco titulo="Alumnos bloqueados" descripcion="No pueden postularse por su cuenta: tuvieron una baja por sanción o llegaron al límite de {{ \App\Models\Alumno::LIMITE_BAJAS }} bajas. Si el alumno acude con una causa de fuerza mayor, puedes hacer un desbloqueo excepcional." :total="$bloqueados->count()">
                <ul class="divide-y divide-gray-100">
                    @foreach ($bloqueados as $al)
                        @php $bajas = $al->bajasVigentes(); $motivoBloqueo = $al->motivoBloqueo(); @endphp
                        <li class="grid gap-4 px-6 py-5 lg:grid-cols-[1fr_minmax(0,24rem)]" x-data="{ abierto: false }">
                            <div class="flex items-start gap-3">
                                <x-ui.user-avatar :user="$al->user" size="size-10 shrink-0 text-xs ring-2 ring-white" />
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $al->user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }}</p>
                                    <p class="mt-2 inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">
                                        {{ $motivoBloqueo === 'sancion' ? 'Baja por sanción' : 'Límite de bajas alcanzado' }}
                                    </p>
                                    <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                        @foreach ($bajas as $b)
                                            <li>{{ $b->fecha_baja?->format('d/m/Y') ?? 'Sin fecha' }} · {{ $b->motivoBajaTexto() ?? 'Sin motivo' }} · <span class="font-medium">{{ $b->tipoBajaTexto() ?? 'Sin clasificar' }}</span></li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <div>
                                <button type="button" x-show="!abierto" x-on:click="abierto = true" class="boton boton-secundario w-full">Desbloqueo excepcional</button>
                                <form x-show="abierto" x-cloak method="POST" action="{{ route('coordinacion.alumnos.desbloquear', $al) }}" class="space-y-2">
                                    @csrf
                                    <label class="etiqueta" for="just-{{ $al->id }}">Justificación (causa de fuerza mayor)</label>
                                    <textarea id="just-{{ $al->id }}" name="justificacion" rows="3" required minlength="10" maxlength="1000" class="campo"
                                        placeholder="Ej. Las dos organizaciones cerraron de forma definitiva; el alumno presentó los comprobantes."></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" x-on:click="abierto = false" class="boton boton-secundario boton-chico">Cancelar</button>
                                        <button type="submit" class="boton boton-primario boton-chico">Desbloquear alumno</button>
                                    </div>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-tabla.marco>
        @endif

        {{-- Postulaciones en trámite --}}
        <x-tabla.marco titulo="Postulaciones en trámite" descripcion="Cada fila te dice cuál es el siguiente paso; el formulario se abre debajo de la fila." :total="$postulaciones->total()">
            <table class="tabla table-fixed">
                <colgroup><col class="w-[25%]"><col class="w-[21%]"><col class="w-28"><col><col class="w-48"></colgroup>
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Alumno</th>
                        <th class="tabla-th">Programa</th>
                        <th class="tabla-th">Créditos</th>
                        <th class="tabla-th">Situación</th>
                        <th class="tabla-th px-6 text-right">Siguiente paso</th>
                    </tr>
                </thead>
                @forelse ($postulaciones as $po)
                    @php
                        $kardex = $po->alumno->documentoVigente('kardex');   // expediente del alumno, validado antes de postularse
                        $aceptacion = $po->getFirstMedia('carta_aceptacion');
                        $presentacion = $po->documentoVigente('carta_presentacion');
                        $oficioInterno = $po->documentoVigente('oficio_aceptacion_interno');
                        $imss = $po->alumno->documentoVigente('constancia_imss');
                        $interno = $po->programa->esInterno();
                        $min = $po->alumno->carrera->porcentaje_creditos_minimo;
                        $cumple = (float) $po->porcentaje_creditos_declarado >= (float) $min;
                        $imssVigente = $po->alumno->tieneImssVigenteHasta(now());
                        $faltaOficio = $interno && !$oficioInterno && in_array($po->estado, [PE::CartaPresentacionEmitida, PE::CartaAceptacionRecibida], true);
                        $faltaActivar = $po->estado === PE::CartaAceptacionRecibida ? \App\Support\Requisitos::activarPractica($po) : null;
                    @endphp
                    <tbody x-data="{ abierto: {{ $errors->any() && old('postulacion') == $po->id ? 'true' : 'false' }} }">
                        <tr class="tabla-fila align-top border-t border-gray-100">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <x-ui.user-avatar :user="$po->alumno->user" size="mt-0.5 size-9 shrink-0 text-xs ring-2 ring-white" />
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-gray-900">{{ $po->alumno->user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $po->alumno->num_control }} · {{ $po->alumno->carrera->carrera_clave }} · {{ $po->alumno->grado ? $po->alumno->grado . '.º sem.' : 'sin grado' }}</p>
                                        <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs">
                                            <span class="{{ $imssVigente ? 'text-gray-500' : 'font-semibold text-rose-600' }}">IMSS {{ $po->alumno->vigencia_imss ? 'al ' . $po->alumno->vigencia_imss->format('d/m/Y') : 'sin vigencia' }}</span>
                                            @if ($imss)<a href="{{ route('coordinacion.alumnos.archivos', [$po->alumno, $imss]) }}" class="font-semibold text-real-600 hover:underline">Ver</a>@endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-gray-900">{{ $po->programa->titulo }}</p>
                                <p class="text-xs text-gray-500">{{ $po->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }} · {{ $po->programa->ciclo }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p @class(['font-display text-lg font-bold leading-none tabular-nums', 'text-emerald-700' => $cumple, 'text-rose-600' => !$cumple])>{{ \App\Support\Formato::porcentaje($po->porcentaje_creditos_declarado) }}%</p>
                                <p class="mt-1 text-xs text-gray-500">mínimo {{ \App\Support\Formato::porcentaje($min) }}%</p>
                                @if ($kardex)<a href="{{ route('coordinacion.alumnos.archivos', [$po->alumno, $kardex]) }}" class="text-xs font-semibold text-real-600 hover:underline">Kardex</a>@endif
                            </td>
                            <td class="px-4 py-4">
                                <x-ui.estatus :estado="$po->estado" />
                                <p class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                    @if ($presentacion)<a href="{{ route('coordinacion.postulaciones.archivos', [$po, $presentacion]) }}" class="inline-flex items-center gap-1 font-semibold text-real-600 hover:underline"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Presentación</a>@endif
                                    @if ($aceptacion)<a href="{{ route('coordinacion.postulaciones.archivos', [$po, $aceptacion]) }}" class="inline-flex items-center gap-1 font-semibold text-real-600 hover:underline"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Aceptación</a>@endif
                                    @if ($oficioInterno)<a href="{{ route('coordinacion.postulaciones.archivos', [$po, $oficioInterno]) }}" class="inline-flex items-center gap-1 font-semibold text-real-600 hover:underline"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Oficio interno</a>@endif
                                </p>
                                @if ($faltaOficio)<p class="mt-1 text-xs font-semibold text-rose-600">Falta el oficio de aceptación interno</p>@endif
                            </td>
                            <td class="acciones px-6 py-4 text-right">
                                <div class="flex flex-col items-end gap-1.5">
                                    @if ($po->estado === PE::Pendiente)
                                        {{-- El expediente (kardex + IMSS) ya se validó antes de postularse: aquí solo se emite la carta --}}
                                        @if ($faltaCarta = \App\Support\Requisitos::emitirCarta($po))
                                            <x-ui.accion-bloqueada :motivo="$faltaCarta" tamano="chico">Emitir carta</x-ui.accion-bloqueada>
                                        @else
                                            <form method="POST" action="{{ route('coordinacion.postulaciones.carta', $po) }}">
                                                @csrf
                                                <button class="boton boton-chico boton-primario"><x-dashboard.icono nombre="documento" clase="size-3.5" /> Emitir carta de presentación</button>
                                            </form>
                                        @endif
                                    @elseif ($po->estado === PE::CartaPresentacionEmitida)
                                        <button type="button" class="boton boton-chico boton-primario" x-on:click="abierto = !abierto" :aria-expanded="abierto.toString()">
                                            <x-dashboard.icono nombre="subir" clase="size-3.5" /> {{ $aceptacion ? 'Revisar carta de aceptación' : 'Registrar aceptación' }}
                                        </button>
                                    @elseif ($faltaActivar)
                                        <x-ui.accion-bloqueada :motivo="$faltaActivar" tamano="chico">Activar práctica</x-ui.accion-bloqueada>
                                    @elseif ($po->estado === PE::CartaAceptacionRecibida)
                                        <button type="button" class="boton boton-chico boton-exito" x-on:click="abierto = !abierto" :aria-expanded="abierto.toString()">
                                            <x-dashboard.icono nombre="check" clase="size-3.5" /> Activar práctica
                                        </button>
                                    @endif

                                    @if ($faltaOficio && !($po->estado === PE::CartaAceptacionRecibida && !$faltaActivar))
                                        <button type="button" class="boton boton-chico boton-secundario" x-on:click="abierto = !abierto"><x-dashboard.icono nombre="subir" clase="size-3.5" /> Subir oficio interno</button>
                                    @endif

                                    @if (in_array($po->estado, [PE::Pendiente, PE::CartaPresentacionEmitida], true))
                                        <form method="POST" action="{{ route('coordinacion.postulaciones.rechazar', $po) }}" onsubmit="return confirm('¿Rechazar esta postulación? El alumno recibirá un aviso.')">
                                            @csrf
                                            <button class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-700"><x-dashboard.icono nombre="equis" clase="size-3.5" /> Rechazar postulación</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Formulario del siguiente paso, a lo ancho de la fila --}}
                        @if ($po->estado === PE::CartaPresentacionEmitida || ($po->estado === PE::CartaAceptacionRecibida && !$faltaActivar) || $faltaOficio)
                            <tr x-show="abierto" x-cloak>
                                <td colspan="5" class="border-t border-gray-100 bg-gray-50/70 px-6 py-4">
                                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                        @if ($po->estado === PE::CartaPresentacionEmitida)
                                            <form method="POST" enctype="multipart/form-data" action="{{ route('coordinacion.postulaciones.aceptacion', $po) }}" class="space-y-2 rounded-xl bg-white p-4 ring-1 ring-inset ring-gray-200">
                                                @csrf
                                                <input type="hidden" name="postulacion" value="{{ $po->id }}">
                                                <p class="text-sm font-semibold text-gray-900">Carta de aceptación de la organización</p>
                                                @if ($aceptacion)
                                                    <p class="text-xs text-gray-600">El alumno ya la subió: <a href="{{ route('coordinacion.postulaciones.archivos', [$po, $aceptacion]) }}" class="font-semibold text-real-600 hover:underline">revísala</a> y confírmala, o sube otra si la recibiste tú.</p>
                                                @endif
                                                <x-ui.zona-archivo name="archivo" :etiqueta="$aceptacion ? 'Otra carta (opcional)' : 'Carta de aceptación'" :requerido="!$aceptacion" />
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" class="boton boton-chico text-gray-500 hover:bg-gray-100" x-on:click="abierto = false">Cancelar</button>
                                                    <button class="boton boton-chico boton-primario">{{ $aceptacion ? 'Confirmar carta del alumno' : 'Registrar aceptación' }}</button>
                                                </div>
                                            </form>
                                        @elseif ($po->estado === PE::CartaAceptacionRecibida && !$faltaActivar)
                                            <form method="POST" action="{{ route('coordinacion.postulaciones.activar', $po) }}" class="grid grid-cols-2 gap-3 rounded-xl bg-white p-4 ring-1 ring-inset ring-gray-200">
                                                @csrf
                                                <input type="hidden" name="postulacion" value="{{ $po->id }}">
                                                <p class="col-span-2 text-sm font-semibold text-gray-900">Periodo de la práctica</p>
                                                <div><label class="etiqueta" for="ini-{{ $po->id }}">Inicio</label><input id="ini-{{ $po->id }}" type="date" name="fecha_inicio" required class="campo h-10"></div>
                                                <div><label class="etiqueta" for="fin-{{ $po->id }}">Fin</label><input id="fin-{{ $po->id }}" type="date" name="fecha_fin" required class="campo h-10"></div>
                                                <div class="col-span-2">
                                                    <label class="etiqueta" for="sup-{{ $po->id }}">Profesor supervisor <span class="font-normal text-gray-400">(puedes asignarlo después)</span></label>
                                                    <select id="sup-{{ $po->id }}" name="profesor_id" class="campo h-10">
                                                        <option value="">Sin asignar por ahora</option>
                                                        @foreach ($profesores->where('division_id', $po->alumno->carrera->division_id) as $pf)
                                                            <option value="{{ $pf->id }}">{{ $pf->nombre }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-span-2 flex justify-end gap-2">
                                                    <button type="button" class="boton boton-chico text-gray-500 hover:bg-gray-100" x-on:click="abierto = false">Cancelar</button>
                                                    <button class="boton boton-chico boton-exito"><x-dashboard.icono nombre="check" clase="size-3.5" /> Activar práctica</button>
                                                </div>
                                            </form>
                                        @endif

                                        @if ($faltaOficio)
                                            <form method="POST" enctype="multipart/form-data" action="{{ route('coordinacion.postulaciones.oficio-interno', $po) }}" class="space-y-2 rounded-xl bg-oro-50/60 p-4 ring-1 ring-inset ring-oro-100">
                                                @csrf
                                                <p class="text-sm font-semibold text-gray-900">Oficio de aceptación interno</p>
                                                <x-ui.zona-archivo name="archivo" etiqueta="Oficio firmado" ayuda="Firmado por el jefe de departamento o director del área receptora." />
                                                <div class="flex justify-end"><button class="boton boton-chico boton-secundario"><x-dashboard.icono nombre="subir" clase="size-3.5" /> Subir oficio interno</button></div>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody><tr><td colspan="5" class="px-6 py-4"><x-dashboard.vacio icono="usuarios" titulo="No hay postulaciones en trámite" texto="Cuando un alumno se postule a un programa de tu carrera aparecerá aquí." /></td></tr></tbody>
                @endforelse
            </table>
            <x-slot:pie>{{ $postulaciones->links() }}</x-slot:pie>
        </x-tabla.marco>

        {{-- Prácticas --}}
        <x-tabla.marco titulo="Seguimiento de prácticas" descripcion="Horas validadas, periodo y supervisor de cada practicante." :total="$practicas->total()">
            <x-slot:herramientas>
                <x-tabla.filtros-estado :actual="request('estado', '')" :opciones="collect($estados)->map(fn ($e) => ['valor' => $e->value, 'texto' => $e->label(), 'familia' => $e->familia()])->prepend(['valor' => '', 'texto' => 'Todas'])->all()" />
                <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                    @if (request('estado'))<input type="hidden" name="estado" value="{{ request('estado') }}">@endif
                    <div class="relative flex-1 sm:max-w-xs">
                        <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <label for="q" class="sr-only">Buscar por nombre o código</label>
                        <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o código" class="campo-filtro pl-9 sm:w-full">
                    </div>
                    @if ($carreras->count() > 1)
                        <select name="carrera_id" class="campo-filtro" aria-label="Carrera" onchange="this.form.requestSubmit()">
                            <option value="">Todas las carreras</option>
                            @foreach ($carreras as $c)
                                <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button class="boton boton-secundario">Buscar</button>
                    @if (request()->hasAny(['q', 'carrera_id', 'estado']))
                        <a href="{{ route('coordinacion.lista-practicantes') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                    @endif
                </form>
            </x-slot:herramientas>

            <table class="tabla table-fixed">
                <colgroup><col class="w-[27%]"><col class="w-48"><col class="w-28"><col><col class="w-28"><col class="w-40"></colgroup>
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Practicante</th>
                        <th class="tabla-th">Progreso de horas</th>
                        <th class="tabla-th">Periodo</th>
                        <th class="tabla-th">Supervisor</th>
                        <th class="tabla-th">Estado</th>
                        <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($practicas as $pr)
                        @php
                            $al = $pr->postulacion->alumno;
                            $horas = (int) $pr->horas_acumuladas;
                            $pct = $pr->horas_requeridas > 0 ? min(100, round($horas / $pr->horas_requeridas * 100)) : 0;
                            $activa = in_array($pr->estado, PrE::activas(), true);
                        @endphp
                        <tr class="align-top tabla-fila">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <x-ui.user-avatar :user="$al->user" size="mt-0.5 size-9 shrink-0 text-xs ring-2 ring-white" />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $al->user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }}</p>
                                        <p class="text-xs text-gray-500">{{ $pr->postulacion->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="w-full max-w-44">
                                    <div class="mb-1 flex items-baseline justify-between">
                                        <span class="text-sm font-bold text-gray-900">{{ $horas }}<span class="text-xs font-normal text-gray-400"> / {{ $pr->horas_requeridas }} h</span></span>
                                        <span class="text-xs font-semibold text-udg-900">{{ $pct }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $pct }}%"></div></div>
                                    @if ($pr->horas_reconocidas > 0)
                                        <p class="mt-1 text-[11px] text-gray-500">Incluye {{ $pr->horas_reconocidas }} h de su plaza anterior</p>
                                    @endif
                                    @if ($pr->motivo_baja)
                                        <p class="mt-1 text-[11px] font-medium text-rose-600">{{ $pr->motivoBajaTexto() }} · {{ $pr->tipoBajaTexto() ?? ($pr->conserva_horas ? 'conserva horas' : 'horas reiniciadas') }}</p>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm text-gray-800">{{ $pr->fecha_inicio->format('d/m/Y') }}</p>
                                <p class="text-xs text-gray-500">al {{ $pr->fecha_fin->format('d/m/Y') }}</p>
                            </td>
                            <td class="px-4 py-4">
                                @if ($activa)
                                    <div x-data="{ cambiar: false }">
                                        <div x-show="!cambiar" class="flex flex-wrap items-center gap-x-2">
                                            @if ($pr->supervisor)
                                                <span class="text-sm text-gray-800">{{ $pr->supervisor->nombre }}</span>
                                            @else
                                                <span class="text-sm font-medium text-amber-700">Sin asignar</span>
                                            @endif
                                            <button type="button" class="text-xs font-semibold text-real-600 hover:underline" x-on:click="cambiar = true">{{ $pr->supervisor ? 'Cambiar' : 'Asignar' }}</button>
                                        </div>
                                        <form x-show="cambiar" x-cloak method="POST" action="{{ route('coordinacion.practicas.supervisor', $pr) }}" class="flex flex-col gap-1.5">
                                            @csrf
                                            <select name="profesor_id" required class="campo h-9 text-xs" aria-label="Supervisor de {{ $al->user->name }}">
                                                <option value="">Elige un profesor</option>
                                                @foreach ($profesores->where('division_id', $al->carrera->division_id) as $pf)
                                                    <option value="{{ $pf->id }}" @selected($pr->profesor_supervisor_id === $pf->id)>{{ $pf->nombre }}</option>
                                                @endforeach
                                            </select>
                                            <div class="flex gap-1.5">
                                                <button class="boton boton-chico boton-primario">Guardar</button>
                                                <button type="button" class="boton boton-chico text-gray-500 hover:bg-gray-100" x-on:click="cambiar = false">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                @else
                                    {!! $pr->supervisor?->nombre ? '<span class="text-sm text-gray-800">' . e($pr->supervisor->nombre) . '</span>' : '<span class="dato-vacio">Sin supervisor asignado</span>' !!}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><x-ui.estatus :estado="$pr->estado" /></td>
                            <td class="acciones whitespace-nowrap px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($activa)
                                        <button type="button" x-data
                                            x-on:click="$dispatch('abrir-baja', { id: {{ $pr->id }}, nombre: @js($al->user->name), horas: {{ $horas }}, bajas: {{ $al->bajasVigentes()->count() }} })"
                                            class="group/btn relative inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 text-sm font-semibold text-rose-600 shadow-theme-xs transition hover:border-rose-300 hover:bg-rose-50 active:scale-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25">
                                            <x-dashboard.icono nombre="equis" clase="size-4" /> Baja o cambio
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-4"><x-dashboard.vacio icono="reloj" titulo="{{ request()->hasAny(['q', 'carrera_id', 'estado']) ? 'No hay prácticas con ese filtro' : 'Aún no hay prácticas' }}" texto="Al activar una práctica podrás dar seguimiento a sus horas aquí." /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:pie>{{ $practicas->links() }}</x-slot:pie>
        </x-tabla.marco>

        {{-- Baja de práctica: la resolución sale del motivo y de los reportes validados (criterios de la Coordinación de Carrera) --}}
        @php
            $sanciones = \App\Models\Practica::MOTIVOS_SANCION;
            $motivosJustificados = collect(\App\Models\Practica::MOTIVOS_BAJA)->except($sanciones);
            $motivosSancion = collect(\App\Models\Practica::MOTIVOS_BAJA)->only($sanciones);
        @endphp
        <div x-data="{ abierto: false, p: { id: 0, nombre: '', horas: 0, bajas: 0 }, motivo: 'cierre_empresa', sanciones: @js($sanciones), limite: {{ \App\Models\Alumno::LIMITE_BAJAS }}, url: '{{ route('coordinacion.practicas.cancelar', 0) }}',
                get tipo() { return this.sanciones.includes(this.motivo) ? 'sancion' : (this.p.horas > 0 ? 'parcial' : 'sin_reportes'); },
                get bloquea() { return this.tipo === 'sancion' || this.p.bajas + 1 >= this.limite; },
                get explicacion() {
                    return { parcial: 'Conserva sus ' + this.p.horas + ' h validadas: en su siguiente plaza solo cubrirá las horas restantes.',
                        sin_reportes: 'No tiene reportes validados: sus horas no se conservan y deberá iniciar un trámite nuevo.',
                        sancion: 'Se invalidan todas sus horas (' + this.p.horas + ' h), aunque tenga reportes firmados.' }[this.tipo];
                },
                get consecuencia() {
                    if (this.tipo === 'sancion') return 'El alumno quedará bloqueado hasta que levantes la restricción.';
                    return 'Será su baja número ' + (this.p.bajas + 1) + ' de ' + this.limite + (this.bloquea ? ': alcanza el límite y quedará bloqueado para postularse.' : '. Podrá postularse de nuevo.');
                } }"
            x-on:abrir-baja.window="p = $event.detail; motivo = 'cierre_empresa'; abierto = true">
        <template x-teleport="body">
        <div x-show="abierto" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="abierto = false"
            class="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="titulo-baja">
            <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="abierto = false"></div>
            <form method="POST" :action="url.replace(/0$/, p.id)" x-on:click.stop class="relative w-full max-w-lg animate-entrada overflow-hidden rounded-2xl bg-white shadow-theme-xl motion-reduce:animate-none">
                @csrf
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 id="titulo-baja" class="font-display text-lg font-bold text-gray-900">Baja de práctica</h3>
                    <p class="text-sm text-gray-500">Práctica de <span class="font-semibold text-gray-800" x-text="p.nombre"></span>. Se libera el cupo y se avisa al alumno y a la organización.</p>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="etiqueta" for="baja-motivo">Motivo</label>
                        <select id="baja-motivo" name="motivo_baja" required class="campo h-11" x-model="motivo">
                            <optgroup label="Baja justificada">
                                @foreach ($motivosJustificados as $valor => $texto)
                                    <option value="{{ $valor }}">{{ $texto }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Sanción (invalida horas y bloquea)">
                                @foreach ($motivosSancion as $valor => $texto)
                                    <option value="{{ $valor }}">{{ $texto }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <label class="etiqueta" for="baja-detalle">Qué pasó</label>
                        <textarea id="baja-detalle" name="detalle_baja" rows="2" required maxlength="1000" class="campo" placeholder="Ej. La organización cerró el área; el alumno continuará en otra plaza."></textarea>
                    </div>

                    {{-- Resultado: se calcula, la coordinación solo elige el motivo --}}
                    <div class="rounded-xl p-4 ring-1 ring-inset" aria-live="polite"
                        :class="tipo === 'sancion' ? 'bg-rose-50 ring-rose-200' : (tipo === 'parcial' ? 'bg-emerald-50 ring-emerald-200' : 'bg-amber-50 ring-amber-200')">
                        <p class="text-sm font-semibold"
                            :class="tipo === 'sancion' ? 'text-rose-800' : (tipo === 'parcial' ? 'text-emerald-800' : 'text-amber-800')"
                            x-text="tipo === 'sancion' ? 'Baja por sanción' : (tipo === 'parcial' ? 'Baja parcial acreditada' : 'Baja sin reportes validados')"></p>
                        <p class="mt-1 text-xs leading-relaxed text-gray-700" x-text="explicacion"></p>
                        <p class="mt-2 text-xs" :class="bloquea ? 'font-semibold text-rose-700' : 'text-gray-600'" x-text="consecuencia"></p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                    <button type="button" x-on:click="abierto = false" class="boton boton-secundario">Cancelar</button>
                    <button type="submit" class="boton boton-peligro">Registrar baja</button>
                </div>
            </form>
        </div>
        </template>
        </div>
    </div>
@endsection
