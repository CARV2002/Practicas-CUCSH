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

        {{-- Postulaciones en trámite --}}
        <x-tabla.marco titulo="Postulaciones en trámite" descripcion="Cada fila te dice cuál es el siguiente paso." :total="$postulaciones->total()">
            <table class="tabla">
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Alumno</th>
                        <th class="tabla-th">Programa</th>
                        <th class="tabla-th">Créditos y kardex</th>
                        <th class="tabla-th">Estado y cartas</th>
                        <th class="tabla-th px-6">Siguiente paso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
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
                        @endphp
                        <tr class="align-top tabla-fila">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <x-ui.user-avatar :user="$po->alumno->user" size="mt-0.5 size-9 shrink-0 text-xs ring-2 ring-white" />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $po->alumno->user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $po->alumno->num_control }} · {{ $po->alumno->carrera->carrera_clave }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $po->alumno->grado ? $po->alumno->grado . '.º semestre' : 'Sin grado' }} · {{ $po->alumno->turno?->label() ?? 'sin turno' }}
                                        </p>
                                        <p class="text-xs {{ $imssVigente ? 'text-gray-500' : 'font-semibold text-rose-600' }}">
                                            IMSS {{ $po->alumno->vigencia_imss ? 'vigente hasta ' . $po->alumno->vigencia_imss->format('d/m/Y') : 'sin vigencia capturada' }}
                                        </p>
                                        <x-coordinacion.documento :media="$imss" etiqueta="Constancia IMSS"
                                            :descarga="$imss ? route('coordinacion.alumnos.archivos', [$po->alumno, $imss]) : null" :acciones="false" />
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-gray-900 md:min-w-44">{{ $po->programa->titulo }}</p>
                                <p class="text-xs text-gray-500">{{ $po->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</p>
                                <p class="text-xs text-gray-500">Ciclo {{ $po->programa->ciclo }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="flex items-baseline gap-1.5">
                                    <span @class(['font-display text-lg font-bold tabular-nums', 'text-emerald-700' => $cumple, 'text-rose-600' => !$cumple])>{{ \App\Support\Formato::porcentaje($po->porcentaje_creditos_declarado) }}%</span>
                                    <span class="text-xs text-gray-500">mínimo {{ \App\Support\Formato::porcentaje($min) }}%</span>
                                </p>
                                <x-coordinacion.documento :media="$kardex" etiqueta="Kardex"
                                    :descarga="$kardex ? route('coordinacion.alumnos.archivos', [$po->alumno, $kardex]) : null" :acciones="false" />
                            </td>
                            <td class="px-4 py-4">
                                <x-ui.estatus :estado="$po->estado" />
                                <ul class="mt-2 space-y-1 text-xs">
                                    @foreach ([['Carta de presentación', $presentacion], ['Carta de aceptación', $aceptacion]] as [$nombreCarta, $archivoCarta])
                                        <li>
                                            @if ($archivoCarta)
                                                <a href="{{ route('coordinacion.postulaciones.archivos', [$po, $archivoCarta]) }}" class="inline-flex items-center gap-1 font-semibold text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> {{ $nombreCarta }}</a>
                                            @else
                                                <span class="dato-vacio">{{ $nombreCarta }}: aún no</span>
                                            @endif
                                        </li>
                                    @endforeach
                                    @if ($interno)
                                        <li>
                                            @if ($oficioInterno)
                                                <a href="{{ route('coordinacion.postulaciones.archivos', [$po, $oficioInterno]) }}" class="inline-flex items-center gap-1 font-semibold text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Oficio de aceptación interno</a>
                                            @elseif (in_array($po->estado, [PE::CartaPresentacionEmitida, PE::CartaAceptacionRecibida], true))
                                                <span class="font-semibold text-rose-600">Falta oficio de aceptación interno</span>
                                            @endif
                                        </li>
                                    @endif
                                </ul>
                            </td>
                            <td class="acciones px-6 py-4">
                                <div class="flex min-w-56 flex-col items-stretch gap-2">
                                    @if ($po->estado === PE::Pendiente)
                                        {{-- El expediente (kardex + IMSS) ya se validó antes de postularse: aquí solo se emite la carta --}}
                                        @php $faltaCarta = \App\Support\Requisitos::emitirCarta($po); @endphp
                                        @if ($faltaCarta)
                                            <x-ui.accion-bloqueada :motivo="$faltaCarta" tamano="chico">Emitir carta de presentación</x-ui.accion-bloqueada>
                                        @else
                                            <p class="flex items-center gap-1.5 text-xs text-emerald-700"><x-dashboard.icono nombre="check" clase="size-3.5" /> Expediente validado</p>
                                            <form method="POST" action="{{ route('coordinacion.postulaciones.carta', $po) }}">
                                                @csrf
                                                <button class="boton boton-chico boton-primario w-full justify-center"><x-dashboard.icono nombre="documento" clase="size-3.5" /> Emitir carta de presentación</button>
                                            </form>
                                        @endif
                                    @elseif ($po->estado === PE::CartaPresentacionEmitida)
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('coordinacion.postulaciones.aceptacion', $po) }}" class="space-y-1.5">
                                            @csrf
                                            @if ($aceptacion)
                                                <p class="text-xs text-gray-600">El alumno ya subió su carta: revísala y confírmala, o sube otra si la recibiste tú.</p>
                                            @endif
                                            <x-ui.zona-archivo name="archivo" :etiqueta="$aceptacion ? 'Otra carta (opcional)' : 'Carta de aceptación'" :requerido="!$aceptacion" />
                                            <button class="boton boton-chico boton-primario w-full">{{ $aceptacion ? 'Confirmar carta del alumno' : 'Registrar aceptación' }}</button>
                                        </form>
                                    @elseif ($po->estado === PE::CartaAceptacionRecibida && ($faltaActivar = \App\Support\Requisitos::activarPractica($po)))
                                        <x-ui.accion-bloqueada :motivo="$faltaActivar" tamano="chico">Activar práctica</x-ui.accion-bloqueada>
                                    @elseif ($po->estado === PE::CartaAceptacionRecibida)
                                        <form method="POST" action="{{ route('coordinacion.postulaciones.activar', $po) }}" class="grid grid-cols-2 gap-1.5">
                                            @csrf
                                            <label class="text-xs font-medium text-gray-600">Inicio<input type="date" name="fecha_inicio" required class="campo mt-0.5 h-9 text-xs"></label>
                                            <label class="text-xs font-medium text-gray-600">Fin<input type="date" name="fecha_fin" required class="campo mt-0.5 h-9 text-xs"></label>
                                            <select name="profesor_id" class="campo col-span-2 h-9 text-xs" aria-label="Supervisor">
                                                <option value="">Supervisor (opcional)</option>
                                                @foreach ($profesores->where('division_id', $po->alumno->carrera->division_id) as $pf)
                                                    <option value="{{ $pf->id }}">{{ $pf->nombre }}</option>
                                                @endforeach
                                            </select>
                                            <button class="boton boton-chico boton-exito col-span-2"><x-dashboard.icono nombre="check" clase="size-3.5" /> Activar práctica</button>
                                        </form>
                                    @endif

                                    @if ($interno && !$oficioInterno && in_array($po->estado, [PE::CartaPresentacionEmitida, PE::CartaAceptacionRecibida], true))
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('coordinacion.postulaciones.oficio-interno', $po) }}" class="space-y-1.5 rounded-lg bg-oro-50/60 p-2">
                                            @csrf
                                            <x-ui.zona-archivo name="archivo" etiqueta="Oficio de aceptación interno" ayuda="Firmado por el jefe de departamento o director del área receptora." />
                                            <button class="boton boton-chico boton-secundario w-full"><x-dashboard.icono nombre="subir" clase="size-3.5" /> Subir oficio interno</button>
                                        </form>
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
                    @empty
                        <tr><td colspan="5" class="px-6 py-4"><x-dashboard.vacio icono="usuarios" titulo="No hay postulaciones en trámite" texto="Cuando un alumno se postule a un programa de tu carrera aparecerá aquí." /></td></tr>
                    @endforelse
                </tbody>
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

            <table class="tabla">
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
                                <div class="w-44">
                                    <div class="mb-1 flex items-baseline justify-between">
                                        <span class="text-sm font-bold text-gray-900">{{ $horas }}<span class="text-xs font-normal text-gray-400"> / {{ $pr->horas_requeridas }} h</span></span>
                                        <span class="text-xs font-semibold text-udg-900">{{ $pct }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $pct }}%"></div></div>
                                    @if ($pr->horas_reconocidas > 0)
                                        <p class="mt-1 text-[11px] text-gray-500">Incluye {{ $pr->horas_reconocidas }} h de su plaza anterior</p>
                                    @endif
                                    @if ($pr->motivo_baja)
                                        <p class="mt-1 text-[11px] font-medium text-rose-600">{{ $pr->motivoBajaTexto() }} · {{ $pr->conserva_horas ? 'conserva horas' : 'horas reiniciadas' }}</p>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm text-gray-800">{{ $pr->fecha_inicio->format('d/m/Y') }}</p>
                                <p class="text-xs text-gray-500">al {{ $pr->fecha_fin->format('d/m/Y') }}</p>
                            </td>
                            <td class="px-4 py-4">
                                @if ($activa)
                                    <form method="POST" action="{{ route('coordinacion.practicas.supervisor', $pr) }}" class="flex items-center gap-1.5">
                                        @csrf
                                        <select name="profesor_id" required class="campo h-9 w-44 text-xs" aria-label="Supervisor de {{ $al->user->name }}">
                                            <option value="">{{ $pr->supervisor?->nombre ?? 'Asignar supervisor' }}</option>
                                            @foreach ($profesores->where('division_id', $al->carrera->division_id) as $pf)
                                                <option value="{{ $pf->id }}">{{ $pf->nombre }}</option>
                                            @endforeach
                                        </select>
                                        <button class="boton boton-chico boton-secundario" title="Guardar supervisor">Guardar</button>
                                    </form>
                                @else
                                    {!! $pr->supervisor?->nombre ? '<span class="text-sm text-gray-800">' . e($pr->supervisor->nombre) . '</span>' : '<span class="dato-vacio">Sin supervisor asignado</span>' !!}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><x-ui.estatus :estado="$pr->estado" /></td>
                            <td class="acciones whitespace-nowrap px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($activa)
                                        <button type="button" x-data
                                            x-on:click="$dispatch('abrir-baja', { id: {{ $pr->id }}, nombre: @js($al->user->name), horas: {{ $horas }} })"
                                            class="group/btn relative inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 text-sm font-semibold text-rose-600 shadow-theme-xs transition hover:border-rose-300 hover:bg-rose-50 active:scale-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25">
                                            <x-dashboard.icono nombre="equis" clase="size-4" /> Baja o cambio
                                        </button>
                                    @else
                                        <span class="dato-vacio">Sin acciones pendientes</span>
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

        {{-- Baja o cambio de plaza: la coordinación decide qué pasa con las horas --}}
        <div x-data="{ abierto: false, p: { id: 0, nombre: '', horas: 0 }, conserva: '1', url: '{{ route('coordinacion.practicas.cancelar', 0) }}' }"
            x-on:abrir-baja.window="p = $event.detail; conserva = '1'; abierto = true">
        <template x-teleport="body">
        <div x-show="abierto" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="abierto = false"
            class="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="titulo-baja">
            <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="abierto = false"></div>
            <form method="POST" :action="url.replace(/0$/, p.id)" x-on:click.stop class="relative w-full max-w-lg animate-entrada overflow-hidden rounded-2xl bg-white shadow-theme-xl motion-reduce:animate-none">
                @csrf
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 id="titulo-baja" class="font-display text-lg font-bold text-gray-900">Baja o cambio de plaza</h3>
                    <p class="text-sm text-gray-500">Práctica de <span class="font-semibold text-gray-800" x-text="p.nombre"></span>. Se libera el cupo y se avisa al alumno y a la organización.</p>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="etiqueta" for="baja-motivo">Motivo</label>
                        <select id="baja-motivo" name="motivo_baja" required class="campo h-11">
                            @foreach (\App\Models\Practica::MOTIVOS_BAJA as $valor => $texto)
                                <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="etiqueta" for="baja-detalle">Qué pasó</label>
                        <textarea id="baja-detalle" name="detalle_baja" rows="2" required maxlength="1000" class="campo" placeholder="Ej. La organización cerró el área; el alumno continuará en otra plaza."></textarea>
                    </div>
                    <fieldset>
                        <legend class="etiqueta">¿Qué pasa con sus horas validadas?</legend>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer flex-col rounded-xl border border-gray-200 px-3 py-2.5 transition hover:border-udg-200 has-[:checked]:border-udg-800 has-[:checked]:bg-udg-50">
                                <span class="flex items-center gap-2 text-sm font-semibold text-gray-900"><input type="radio" name="conserva_horas" value="1" x-model="conserva"> Se conservan</span>
                                <span class="mt-0.5 text-xs text-gray-500">Las <span x-text="p.horas"></span> h validadas se suman a su siguiente plaza.</span>
                            </label>
                            <label class="flex cursor-pointer flex-col rounded-xl border border-gray-200 px-3 py-2.5 transition hover:border-rose-200 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50">
                                <span class="flex items-center gap-2 text-sm font-semibold text-gray-900"><input type="radio" name="conserva_horas" value="0" x-model="conserva"> Se reinician</span>
                                <span class="mt-0.5 text-xs text-gray-500">Empieza de cero en la nueva institución.</span>
                            </label>
                        </div>
                    </fieldset>
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
