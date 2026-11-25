@extends('layouts.app')

@section('content')
@php
    use App\Enums\ConvenioEstado as E;
    use App\Enums\EtapaConvenio as Et;
    use App\Enums\Dictamen;

    $estado = $convenio->estado;
    $etapa = $etapaAbierta?->etapa;
    $maxEtapa = $etapa?->diasHabilesDocumentados()[1] ?? null;
    $diasEtapa = $etapaAbierta?->diasHabiles();
    $estadosDoc = ['validada' => ['menta', 'Validado'], 'rechazada' => ['rosa', 'Rechazado'], 'pendiente' => ['ambar', 'Por revisar']];
    // El administrador supervisa en solo lectura: no ve los controles del trámite
    $opera = auth()->user()->can('gestionar', $convenio);
    $bitacora = ['pendiente' => 'bg-amber-400', 'aprobado' => 'bg-emerald-500', 'observado' => 'bg-sky-400', 'rechazado' => 'bg-rose-500'];
@endphp

<div class="space-y-6">
    <x-pagina.encabezado :titulo="$empresa->razon_social" :volver="route('admin.lista-convenios')" volver-texto="Bandeja de convenios"
        :descripcion="'RFC ' . $empresa->rfc . ' · ' . ($empresa->tipo_organismo?->label() ?? 'Tipo sin definir')">
        <x-slot:acciones>
            <x-ui.estatus :estado="$estado" tamano="normal" />
        </x-slot:acciones>
    </x-pagina.encabezado>

    @unless ($opera)
        <x-ui.aviso tipo="info" titulo="Supervisión de solo lectura">Consultas el trámite y su bitácora; la gestión y formalización del convenio corresponde exclusivamente a Vinculación.</x-ui.aviso>
    @endunless

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    {{-- Fase actual --}}
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs sm:p-6" aria-labelledby="titulo-fase">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="titulo-fase" class="font-display text-base font-bold text-gray-900">Avance del trámite</h2>
            <p class="text-xs text-gray-500">
                Recibido el {{ $convenio->fecha_recepcion->format('d/m/Y') }}
                @if ($convenio->folio) · Folio <span class="font-semibold text-gray-700">{{ $convenio->folio }}</span>@endif
            </p>
        </div>
        <x-convenio.pista :estado="$estado" grande class="mt-4" />

        @php $detalle = $convenio->estadoDetallado(); @endphp
        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-gray-100 px-4 py-3">
            <x-ui.estatus :familia="$detalle['familia']" :texto="$detalle['texto']" tamano="normal" />
            <p class="min-w-0 flex-1 text-sm text-gray-700">{{ $detalle['detalle'] ?? 'Sin detalle adicional' }}</p>
            <p class="text-xs text-gray-500">Lo tiene: <span class="font-semibold text-gray-800">{{ $detalle['responsable'] }}</span></p>
        </div>

        @if ($etapaAbierta)
            <div @class([
                'mt-4 flex flex-col gap-3 rounded-xl p-4 ring-1 ring-inset sm:flex-row sm:items-center sm:justify-between',
                'bg-rose-50 ring-rose-200' => $etapaAbierta->excedeEspera(),
                'bg-gray-50 ring-gray-100' => !$etapaAbierta->excedeEspera(),
            ])>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500">Etapa abierta</p>
                    <p class="font-semibold text-gray-900">{{ $etapa->label() }}</p>
                    <p class="text-sm text-gray-600">Responsable: {{ $etapa->responsable() }}</p>
                    @if ($etapaAbierta->comentario)<p class="mt-1 text-sm text-gray-600">{{ $etapaAbierta->comentario }}</p>@endif
                </div>
                <div class="shrink-0 sm:w-56 sm:text-right">
                    <p @class(['font-display text-2xl font-bold leading-none', 'text-rose-600' => $etapaAbierta->excedeEspera(), 'text-gray-900' => !$etapaAbierta->excedeEspera()])>
                        {{ $diasEtapa }} <span class="font-sans text-sm font-medium text-gray-500">{{ $diasEtapa === 1 ? 'día hábil' : 'días hábiles' }}</span>
                    </p>
                    @if ($maxEtapa)
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white ring-1 ring-inset ring-gray-200">
                            <div class="h-full rounded-full {{ $etapaAbierta->excedeEspera() ? 'bg-rose-500' : 'bg-udg-800' }}" style="width: {{ min(100, round($diasEtapa / max(1, $maxEtapa) * 100)) }}%"></div>
                        </div>
                        <p class="mt-1 text-xs {{ $etapaAbierta->excedeEspera() ? 'font-semibold text-rose-600' : 'text-gray-500' }}">
                            {{ $etapaAbierta->excedeEspera() ? 'Excede el máximo de ' . $maxEtapa . ' días' : 'Máximo documentado: ' . $maxEtapa . ' días' }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-gray-500">Etapa sin plazo fijo</p>
                    @endif
                </div>
            </div>
        @elseif ($estado->esFinal())
            <p class="mt-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-600 ring-1 ring-inset ring-gray-100">
                {{ $estado === E::Formalizado ? 'Trámite concluido: el convenio está formalizado.' : 'Trámite cerrado como rechazado.' }}
            </p>
        @endif
    </section>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Expediente --}}
            <x-dashboard.panel id="expediente" class="scroll-mt-24" titulo="Expediente de la empresa" icono="documento"
                :descripcion="$estado === E::Solicitado ? 'Valida cada documento en el orden que quieras, o marca varios y valídalos juntos. Si alguno está mal, devuélvelo con su motivo. Lo validado ya no se rechaza.' : 'Los documentos se validan durante la revisión de documentos.'">
                {{-- Lo que capturó la empresa: se compara contra sus documentos --}}
                <details class="group mb-4 rounded-xl bg-gray-50 ring-1 ring-inset ring-gray-100" @if ($estado === E::Solicitado) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Datos capturados por la empresa</span>
                            <span class="block text-xs text-gray-500">Compáralos con el acta, la constancia fiscal y la identificación.</span>
                        </span>
                        <svg class="size-4 shrink-0 text-gray-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    @php
                        $usaEscritura = \App\Support\DatosNotariales::usaEscritura($empresa->tipo_organismo);
                        $capturados = array_filter([
                            'Razón social' => $empresa->razon_social,
                            'RFC' => $empresa->rfc,
                            'Domicilio legal' => $empresa->domicilio_fiscal,
                            'Representante legal' => trim($empresa->representante_legal . ($empresa->representante_cargo ? ', ' . $empresa->representante_cargo : '')),
                        ] + ($usaEscritura ? [
                            'Escritura pública' => $empresa->escritura_numero ? 'Núm. ' . $empresa->escritura_numero . ' del ' . $empresa->escritura_fecha?->format('d/m/Y') : null,
                            'Notario' => $empresa->notario_nombre ? $empresa->notario_nombre . ', notaría ' . $empresa->notaria_numero . ' de ' . $empresa->notaria_ciudad : null,
                            'Folio mercantil' => $empresa->folio_mercantil ?: 'No indicado',
                        ] : ['Ley o decreto de creación' => $empresa->constitucion]), fn ($v) => $v !== null);
                    @endphp
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 border-t border-gray-100 px-4 py-3 sm:grid-cols-2">
                        @foreach ($capturados as $k => $val)
                            <div class="min-w-0"><dt class="text-xs text-gray-500">{{ $k }}</dt><dd class="break-words text-sm font-medium text-gray-900">{{ $val }}</dd></div>
                        @endforeach
                        @if ($usaEscritura && blank($empresa->escritura_numero))
                            <div class="sm:col-span-2"><p class="text-xs font-medium text-amber-700">La empresa aún no captura sus datos notariales por separado. Pídeselos o escríbelos al generar el borrador.</p></div>
                        @endif
                        <div class="min-w-0 sm:col-span-2"><dt class="text-xs text-gray-500">Carreras solicitadas</dt><dd class="text-sm font-medium text-gray-900">{{ $convenio->dictamenes->map(fn ($d) => $d->carrera->carrera_clave)->join(', ') }}</dd></div>
                        @if ($convenio->actividades_propuestas)
                            <div class="min-w-0 sm:col-span-2"><dt class="text-xs text-gray-500">Actividades propuestas</dt><dd class="whitespace-pre-line text-sm text-gray-800">{{ $convenio->actividades_propuestas }}</dd></div>
                        @endif
                    </dl>
                </details>
                @php
                    $pendientesLote = collect($colecciones)->map(fn ($c) => $empresa->documentoVigente($c))
                        ->filter(fn ($m) => $m && in_array($m->getCustomProperty('estado'), [null, 'pendiente'], true));
                    $revisable = $estado === E::Solicitado && $opera;
                @endphp
                <div x-data="{ elegidos: [], todos: @js($pendientesLote->pluck('id')->values()) }">
                    @if ($revisable && $pendientesLote->isNotEmpty())
                        {{-- Validar por lote: Lorena elige los que ya revisó, en el orden que quiera --}}
                        <form id="validar-lote" method="POST" action="{{ route('admin.convenios.documentos.validar', $convenio) }}"
                            class="mb-3 flex flex-col gap-2 rounded-xl bg-emerald-50/60 px-4 py-3 ring-1 ring-inset ring-emerald-100 sm:flex-row sm:items-center sm:justify-between">
                            @csrf
                            <label class="flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" class="rounded border-gray-300" :checked="elegidos.length === todos.length"
                                    x-on:change="elegidos = $event.target.checked ? [...todos] : []">
                                Seleccionar los {{ $pendientesLote->count() }} pendientes de revisar
                            </label>
                            <button class="boton boton-exito boton-chico" :disabled="elegidos.length === 0" :class="elegidos.length === 0 && 'opacity-50 cursor-not-allowed'">
                                <x-dashboard.icono nombre="check" clase="size-3.5" /> Validar seleccionados <span x-text="'(' + elegidos.length + ')'"></span>
                            </button>
                        </form>
                    @endif

                    <ul class="-my-1 divide-y divide-gray-100">
                        @foreach ($colecciones as $col)
                            @php
                                $media = $empresa->documentoVigente($col);
                                $st = $media?->getCustomProperty('estado') ?? ($media ? 'pendiente' : null);
                                $emision = $media?->getCustomProperty('fecha_emision') ? \Illuminate\Support\Carbon::parse($media->getCustomProperty('fecha_emision')) : null;
                                $obligatorio = in_array($col, $empresa->obligatorias(), true);
                            @endphp
                            <li class="flex flex-col gap-3 py-3.5 lg:flex-row lg:items-center lg:justify-between lg:gap-4" x-data="{ rechazar: false }">
                                <div class="flex min-w-0 flex-1 items-start gap-3">
                                    @if ($revisable && $st === 'pendiente')
                                        <input type="checkbox" form="validar-lote" name="documentos[]" value="{{ $media->id }}" x-model.number="elegidos"
                                            class="mt-2.5 size-4 shrink-0 rounded border-gray-300" aria-label="Seleccionar {{ \App\Models\Empresa::nombreDocumento($col) }}">
                                    @endif
                                    <span @class([
                                        'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset',
                                        'bg-emerald-50 text-emerald-600 ring-emerald-100' => $st === 'validada',
                                        'bg-rose-50 text-rose-600 ring-rose-100' => $st === 'rechazada',
                                        'bg-amber-50 text-amber-600 ring-amber-100' => $st === 'pendiente',
                                        'bg-gray-50 text-gray-400 ring-gray-200' => !$st,
                                    ])><x-dashboard.icono :nombre="$st === 'validada' ? 'check' : ($st === 'rechazada' ? 'equis' : 'documento')" clase="size-[18px]" /></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900">{{ \App\Models\Empresa::nombreDocumento($col) }}@unless ($obligatorio)<span class="ml-1 font-normal text-gray-400">(opcional)</span>@endunless</p>
                                        @if ($media)
                                            <div class="mt-0.5 flex flex-wrap items-center gap-x-1 gap-y-1">
                                                <a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $media]) }}" class="inline-flex min-w-0 max-w-full items-center gap-1 text-xs font-semibold text-real-600 hover:text-real-700">
                                                    <x-dashboard.icono nombre="documento" clase="size-3.5 shrink-0" /><span class="truncate" title="{{ $media->file_name }}">{{ $media->file_name }}</span>
                                                </a>
                                            </div>
                                            @if ($emision)
                                                <p @class(['text-xs', 'font-semibold text-rose-600' => $emision->lt(today()->subMonths(3)), 'text-gray-500' => !$emision->lt(today()->subMonths(3))])>
                                                    Emitido el {{ $emision->format('d/m/Y') }}{{ $emision->lt(today()->subMonths(3)) ? ' · tiene más de 3 meses' : '' }}
                                                </p>
                                            @endif
                                            @if ($st === 'rechazada' && $media->getCustomProperty('motivo'))<p class="text-xs text-rose-600">Motivo: {{ $media->getCustomProperty('motivo') }}</p>@endif
                                        @else
                                            <p class="dato-vacio">La empresa aún no sube este documento</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:flex-nowrap lg:justify-end">
                                    @if ($st)
                                        <x-ui.estatus :familia="$estadosDoc[$st][0]" :texto="$st === 'rechazada' ? 'Devuelto: espera corrección' : $estadosDoc[$st][1]" />
                                        @if ($revisable && $st === 'pendiente')
                                            {{-- Uno por uno: validar o devolver este documento --}}
                                            <div x-show="!rechazar" class="flex items-center gap-2">
                                                <form method="POST" action="{{ route('admin.convenios.documentos.resolver', [$convenio, $media]) }}">
                                                    @csrf
                                                    <input type="hidden" name="estado" value="validada">
                                                    <button class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95">
                                                        <x-dashboard.icono nombre="check" clase="size-4" /> Validar
                                                    </button>
                                                </form>
                                                <button type="button" x-on:click="rechazar = true; $nextTick(() => $refs.motivo.focus())"
                                                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 active:scale-95">
                                                    <x-dashboard.icono nombre="equis" clase="size-4" /> Devolver
                                                </button>
                                            </div>
                                            <form x-show="rechazar" x-cloak x-transition.opacity method="POST" action="{{ route('admin.convenios.documentos.resolver', [$convenio, $media]) }}"
                                                data-confirmar="¿Devolver este documento a la empresa? Tendrá que subirlo corregido para continuar el trámite." data-confirmar-titulo="Devolver documento" data-confirmar-boton="Sí, devolver"
                                                class="flex w-full flex-col gap-2 rounded-xl bg-rose-50/70 p-3 ring-1 ring-inset ring-rose-200 lg:w-80">
                                                @csrf
                                                <input type="hidden" name="estado" value="rechazada">
                                                <label for="motivo-{{ $col }}" class="text-xs font-semibold text-rose-800">¿Qué debe corregir la empresa? <span class="text-rose-500">*</span></label>
                                                <input id="motivo-{{ $col }}" x-ref="motivo" type="text" name="motivo" required maxlength="1000" pattern=".*\S.*"
                                                    data-msg-formato="Escribe el motivo para que la empresa sepa qué corregir." placeholder="Ej. Borroso, vencido, incompleto…" class="campo h-9 text-sm">
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" x-on:click="rechazar = false" class="boton boton-chico text-gray-500 hover:bg-white">Cancelar</button>
                                                    <button class="boton boton-chico boton-peligro">Devolver a la empresa</button>
                                                </div>
                                            </form>
                                        @endif
                                    @else
                                        <x-ui.estatus familia="gris" texto="Sin archivo" />
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($estado === E::Solicitado && $opera)
                    <div class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-relaxed text-gray-500 sm:max-w-md">Revisa que cada archivo sea legible, esté vigente y coincida con los datos capturados. Al aprobar el último documento, la propuesta se envía sola a las coordinaciones de las carreras solicitadas.</p>
                        @if ($faltaTurnar)
                            <x-ui.accion-bloqueada :motivo="$faltaTurnar">Enviar a dictamen académico</x-ui.accion-bloqueada>
                        @else
                            <form method="POST" action="{{ route('admin.convenios.validacion-academica', $convenio) }}" class="shrink-0">
                                @csrf
                                <button class="boton boton-primario"><x-dashboard.icono nombre="enviar" clase="size-4" /> Enviar a dictamen académico</button>
                            </form>
                        @endif
                    </div>
                @endif
            </x-dashboard.panel>

            {{-- Validación académica --}}
            <x-dashboard.panel titulo="Dictamen académico por carrera" icono="academico"
                descripcion="La propuesta llega solo a las coordinaciones de las carreras solicitadas. Cada una vota y sube su dictamen firmado; tú solo das seguimiento.">
                @if ($estado === E::EnValidacionAcademica || $convenio->fecha_turno_secretaria || $convenio->fecha_turno_division)
                    <details class="group mb-4 rounded-xl bg-gray-50 ring-1 ring-inset ring-gray-100">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">Paso del oficio por Secretaría Académica y la División</span>
                                <span class="block text-xs text-gray-500">Opcional: solo sirve para medir tiempos; no detiene el dictamen.
                                    @if ($convenio->fecha_turno_secretaria) Secretaría: {{ $convenio->fecha_turno_secretaria->format('d/m/Y') }}. @endif
                                    @if ($convenio->fecha_turno_division) División: {{ $convenio->fecha_turno_division->format('d/m/Y') }}. @endif
                                </span>
                            </span>
                            @if ($estado === E::EnValidacionAcademica && $opera)<span class="shrink-0 text-xs font-semibold text-real-600 group-open:hidden">Registrar fechas</span>@endif
                        </summary>
                        @if ($estado === E::EnValidacionAcademica && $opera)
                            <form method="POST" action="{{ route('admin.convenios.turnos', $convenio) }}" class="grid grid-cols-1 gap-3 border-t border-gray-100 p-4 md:grid-cols-3 md:items-end">
                                @csrf
                                <div><label for="f-sec" class="etiqueta">Secretaría Académica</label>
                                    <input id="f-sec" type="date" name="fecha_turno_secretaria" max="{{ today()->toDateString() }}" value="{{ old('fecha_turno_secretaria', $convenio->fecha_turno_secretaria?->toDateString()) }}" class="campo h-10"></div>
                                <div><label for="f-div" class="etiqueta">División</label>
                                    <input id="f-div" type="date" name="fecha_turno_division" max="{{ today()->toDateString() }}" value="{{ old('fecha_turno_division', $convenio->fecha_turno_division?->toDateString()) }}" class="campo h-10"></div>
                                <button class="boton boton-secundario h-10">Guardar fechas</button>
                            </form>
                        @endif
                    </details>
                @endif

                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @forelse ($convenio->dictamenes as $d)
                        <li class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $d->carrera->nombre }}</p>
                                    <p class="text-xs text-gray-500">{{ $d->carrera->carrera_clave }}</p>
                                </div>
                                <x-ui.estatus :estado="$d->dictamen" class="shrink-0" />
                            </div>
                            @if ($d->fecha_dictamen)
                                <p class="mt-2 text-xs text-gray-500">{{ $d->fecha_dictamen->format('d/m/Y') }}{{ $d->dictaminador?->user ? ' · ' . $d->dictaminador->user->name : '' }}</p>
                            @elseif ($estado === E::EnValidacionAcademica)
                                <p class="mt-2 text-xs text-amber-700">Esperando el dictamen de la coordinación.</p>
                            @else
                                <p class="dato-vacio mt-2">Sin dictamen</p>
                            @endif
                            @if ($d->observaciones)<p class="mt-1 text-xs leading-relaxed text-gray-600">{{ $d->observaciones }}</p>@endif
                            @if ($d->dictamen === \App\Enums\Dictamen::Negativo && $estado !== E::Rechazado)
                                <p class="mt-2 rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs text-rose-700">Esta carrera queda fuera del convenio: no aparece en el contrato y sus alumnos no verán a la organización.</p>
                            @endif
                            @if ($pdfDictamen = $d->getFirstMedia('dictamen_pertinencia'))
                                <a href="{{ route('admin.dictamenes.archivos.descargar', [$d, $pdfDictamen]) }}" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-real-600 hover:underline"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Dictamen firmado</a>
                            @endif
                        </li>
                    @empty
                        <li class="sm:col-span-2"><x-dashboard.vacio icono="academico" titulo="Aún no se turna a las carreras" texto="Al validar el expediente, turna el convenio para que cada carrera dictamine." /></li>
                    @endforelse
                </ul>
            </x-dashboard.panel>

            {{-- Borrador y Jurídica --}}
            @if (in_array($estado, [E::EnValidacionAcademica, E::EnRevisionJuridica], true) || $borradores->isNotEmpty())
                <x-dashboard.panel titulo="Borrador y Unidad Jurídica" icono="juridico" descripcion="Genera o adjunta el borrador del convenio y registra el dictamen jurídico.">
                    @php
                        // ¿Jurídica devolvió el borrador? Se muestra arriba, en rojo, con qué hacer y dónde
                        $observacionJuridica = $etapa === Et::RedaccionBorrador
                            ? $convenio->seguimientos->filter(fn ($s) => $s->etapa === Et::DictamenJuridico && $s->estado === \App\Enums\SeguimientoEstado::Observado)->sortByDesc('id')->first()
                            : null;
                        $versionDevuelta = $observacionJuridica ? ($convenio->versionesBorrador()->filter(fn ($v) => $v['archivo']->hasCustomProperty('observada_juridica'))->last()['version'] ?? null) : null;
                        $corregida = $observacionJuridica && $versionDevuelta && ($convenio->versionesBorrador()->last()['version'] ?? 0) > $versionDevuelta;
                    @endphp

                    @if ($observacionJuridica)
                        <x-ui.correccion class="mb-4" :titulo="'La Unidad Jurídica devolvió el borrador' . ($versionDevuelta ? ' v' . $versionDevuelta : '') . ' con observaciones'"
                            :fecha="$observacionJuridica->fecha_resolucion ?? $observacionJuridica->created_at" :motivo="$observacionJuridica->comentario"
                            :resuelta="$corregida" resuelta-texto="Ya subiste una versión corregida: revísala y vuelve a turnarla a Jurídica.">
                            Corrige aquí mismo, en la sección marcada en rojo: <strong>«Generar borrador desde el Formato de PP»</strong> (si el cambio es en los datos o cláusulas)
                            o <strong>«Adjuntar borrador propio»</strong> (si Jurídica te regresó el Word ya corregido). Después pulsa <strong>«Turnar a Jurídica»</strong>.
                        </x-ui.correccion>
                    @endif

                    @if ($borradores->isNotEmpty())
                        {{-- Solo la versión vigente se imprime; las anteriores quedan como historial (obsoletas) --}}
                        <ul class="mb-4 space-y-2">
                            @foreach ($convenio->versionesBorrador()->reverse() as $v)
                                @php $b = $v['archivo']; $esWord = $b->mime_type !== 'application/pdf'; $final = $b->getCustomProperty('final'); @endphp
                                <li @class(['grid grid-cols-1 items-center gap-2 rounded-xl px-3.5 py-2.5 ring-1 ring-inset sm:grid-cols-[1fr_auto]',
                                    'bg-udg-50/60 ring-udg-100' => $v['vigente'], 'bg-gray-50 ring-gray-100 opacity-80' => !$v['vigente']])>
                                    <span class="flex min-w-0 flex-wrap items-center gap-2 text-sm">
                                        <span @class(['rounded-md px-1.5 py-0.5 text-[11px] font-bold', 'bg-udg-900 text-white' => $v['vigente'], 'bg-gray-300 text-gray-700' => !$v['vigente']])>v{{ $v['version'] }}</span>
                                        <span class="truncate text-gray-800" title="{{ $b->file_name }}">{{ $b->file_name }}</span>
                                        <x-ui.estatus :familia="$v['vigente'] ? 'menta' : 'piedra'" :texto="$v['vigente'] ? 'Vigente' : 'Obsoleta'" />
                                        @if ($final)
                                            <x-ui.estatus familia="turquesa" :texto="'Juegos finales · firma ' . \Illuminate\Support\Carbon::parse($b->getCustomProperty('fecha_firma'))->format('d/m/Y')" />
                                        @elseif (!$b->getCustomProperty('fecha_firma') && $b->getCustomProperty('datos'))
                                            <span class="text-[11px] text-gray-500">Fecha de firma en blanco</span>
                                        @endif
                                        <span class="text-xs text-gray-400">{{ $b->created_at->format('d/m/Y H:i') }}</span>
                                    </span>
                                    <span class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                                        @if ($v['vigente'] && $v['pdf'])
                                            <x-ui.boton-icono :href="route('admin.convenios.archivos.descargar', [$convenio, $v['pdf']])" icono="documento" etiqueta="PDF para imprimir" tono="primario" con-texto />
                                        @elseif ($v['vigente'])
                                            <span class="text-xs text-amber-700" title="LibreOffice no está disponible para convertir el Word">Sin PDF: imprime desde el Word</span>
                                        @endif
                                        @if ($esWord)
                                            {{-- El Word no se previsualiza: solo se descarga para editarlo --}}
                                            <x-ui.boton-icono data-sin-boton-ver :href="route('admin.convenios.archivos.descargar', [$convenio, $b])" icono="descargar" :etiqueta="$v['vigente'] ? 'Descargar Word (editable)' : 'Word (historial)'" />
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="dato-vacio mb-4">Aún no hay borradores del convenio.</p>
                    @endif

                    @if ($etapa === Et::RedaccionBorrador && $opera)
                        @php
                            $e = $empresa;
                            $marcaCorregir = $observacionJuridica && !$corregida ? 'ring-2 ring-rose-300 border-rose-300' : 'border-gray-200';
                        @endphp
                        <details class="group mb-4 rounded-xl border {{ $marcaCorregir }}" @if ($errors->any()) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-udg-900 hover:bg-gray-50">
                                <span class="flex items-center gap-2"><x-dashboard.icono nombre="documento" clase="size-4" /> Generar borrador desde el Formato de PP</span>
                                <svg class="size-4 text-gray-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            @php
                                $faltanDecl = collect([$e->representante_legal, $e->representante_cargo, $e->constitucion, $e->objeto_social, $e->facultad_firmante, $e->domicilio_fiscal])->filter(fn ($v) => blank($v))->count();
                            @endphp
                            <form method="POST" action="{{ route('admin.convenios.borrador.generar', $convenio) }}" class="space-y-4 border-t border-gray-100 p-4">
                                @csrf
                                {{-- Declaraciones de la ENTIDAD: las capturó la empresa; aquí solo se revisan contra el acta --}}
                                <details class="group/decl rounded-xl bg-gray-50 ring-1 ring-inset ring-gray-100" @if ($faltanDecl > 0 || $errors->hasAny(['representante_nombre', 'representante_cargo', 'constitucion', 'atribuciones', 'facultad_firmante', 'domicilio_legal'])) open @endif>
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-900">Declaraciones de la entidad</span>
                                            <span class="block text-xs {{ $faltanDecl ? 'font-medium text-amber-700' : 'text-gray-500' }}">{{ $faltanDecl ? "La empresa no capturó {$faltanDecl} dato(s): complétalos aquí o pídeselos" : 'Capturadas por la empresa · revísalas contra el acta constitutiva' }}</span>
                                        </span>
                                        <span class="text-xs font-semibold text-real-600 group-open/decl:hidden">Revisar o corregir</span>
                                    </summary>
                                    <div class="grid grid-cols-1 gap-4 border-t border-gray-100 p-4 md:grid-cols-2">
                                        <div><label class="etiqueta" for="b-rep">Representante (nombre completo)</label><input id="b-rep" type="text" name="representante_nombre" required value="{{ old('representante_nombre', $e->representante_legal) }}" class="campo h-11"></div>
                                        <div><label class="etiqueta" for="b-cargo">Cargo</label><input id="b-cargo" type="text" name="representante_cargo" required value="{{ old('representante_cargo', $e->representante_cargo) }}" class="campo h-11"></div>
                                        <div class="md:col-span-2"><label class="etiqueta" for="b-const">I. Creación o constitución</label><textarea id="b-const" name="constitucion" rows="2" required class="campo">{{ old('constitucion', $e->constitucion) }}</textarea></div>
                                        <div class="md:col-span-2"><label class="etiqueta" for="b-atr">II. Atribuciones u objeto social</label><textarea id="b-atr" name="atribuciones" rows="2" required class="campo">{{ old('atribuciones', $e->objeto_social) }}</textarea></div>
                                        <div class="md:col-span-2"><label class="etiqueta" for="b-fac">III. Facultad de quien firma</label><textarea id="b-fac" name="facultad_firmante" rows="2" required class="campo">{{ old('facultad_firmante', $e->facultad_firmante) }}</textarea></div>
                                        <div class="md:col-span-2"><label class="etiqueta" for="b-dom">IV. Domicilio legal</label><input id="b-dom" type="text" name="domicilio_legal" required value="{{ old('domicilio_legal', $e->domicilio_fiscal) }}" class="campo h-11"><p class="ayuda">V. RFC: <span class="font-mono font-semibold text-gray-700">{{ $e->rfc }}</span></p></div>
                                        {{-- Cancelar: deshace lo que se cambió y cierra --}}
                                        <div class="flex justify-end md:col-span-2">
                                            <button type="button" class="boton boton-chico boton-secundario"
                                                x-on:click="const d = $el.closest('details'); d.querySelectorAll('input, textarea').forEach(i => i.value = i.defaultValue); d.open = false">Cancelar</button>
                                        </div>
                                    </div>
                                </details>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="etiqueta" for="b-anios">Vigencia pactada (cláusula UNDÉCIMA)</label>
                                        <select id="b-anios" name="vigencia_anios" required class="campo h-11">
                                            @foreach (\App\Models\Convenio::DURACIONES as $a)
                                                <option value="{{ $a }}" @selected((int) old('vigencia_anios', $convenio->duracionAnios()) === $a)>{{ $a }} {{ $a === 1 ? 'año' : 'años' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div><label class="etiqueta" for="b-lugar">Ciudad de firma</label><input id="b-lugar" type="text" name="lugar_firma" required data-solo="letras" value="{{ old('lugar_firma', 'Guadalajara') }}" class="campo h-11"></div>
                                    <div>
                                        <label class="etiqueta" for="b-fecha">Fecha de firma <span class="font-normal text-gray-400">(tentativa, opcional)</span></label>
                                        <input id="b-fecha" type="date" name="fecha_firma" value="{{ old('fecha_firma') }}" class="campo h-11">
                                        <p class="ayuda">Déjala vacía: el borrador sale con «a los ___ días del mes de ___» y Jurídica solo revisa cláusulas. La fecha real se fija al imprimir los juegos finales.</p>
                                    </div>
                                    <div class="md:col-span-3"><label class="etiqueta" for="b-apoyo">Ayuda o estímulo económico (opcional)</label><input id="b-apoyo" type="text" name="apoyo_economico" value="{{ old('apoyo_economico') }}" class="campo h-11"><p class="ayuda">Si lo dejas vacío se usa el texto estándar.</p></div>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="boton boton-secundario"
                                        x-on:click="const f = $el.closest('form'); f.reset(); f.closest('details').open = false">Cancelar</button>
                                    <button class="boton boton-primario"><x-dashboard.icono nombre="documento" clase="size-4" /> Generar borrador (Word + PDF)</button>
                                </div>
                            </form>
                        </details>

                        {{-- Adjuntar un Word o PDF propio --}}
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.borrador', $convenio) }}"
                            class="mb-4 grid grid-cols-1 gap-4 rounded-xl border border-dashed p-4 md:grid-cols-[1fr_13rem] {{ $observacionJuridica && !$corregida ? 'border-rose-300 bg-rose-50/30' : 'border-gray-300' }}">
                            @csrf
                            <div class="md:row-span-2">
                                <x-ui.zona-archivo name="borrador" etiqueta="Adjuntar borrador propio" accept=".pdf,.docx" formatos="PDF o Word (.docx)" ayuda="Úsalo si redactaste el convenio fuera del sistema o Jurídica te devolvió una versión corregida." />
                            </div>
                            <div>
                                <label class="etiqueta" for="ad-anios">Vigencia del borrador</label>
                                {{-- Precarga los años de la versión anterior para no cambiarlos por accidente --}}
                                <select id="ad-anios" name="vigencia_anios" required class="campo h-11">
                                    @foreach (\App\Models\Convenio::DURACIONES as $a)
                                        <option value="{{ $a }}" @selected((int) old('vigencia_anios', $convenio->duracionAnios()) === $a)>{{ $a }} {{ $a === 1 ? 'año' : 'años' }}</option>
                                    @endforeach
                                </select>
                                @if ($convenio->vigencia_anios)<p class="ayuda">La versión anterior dice {{ $convenio->vigencia_anios }} {{ $convenio->vigencia_anios === 1 ? 'año' : 'años' }}.</p>@endif
                            </div>
                            <button class="boton boton-secundario h-11 w-full self-end"><x-dashboard.icono nombre="subir" clase="size-4" /> Adjuntar borrador</button>
                        </form>

                        {{-- Turnar a Jurídica, con a quién le llega el correo a la vista --}}
                        <div class="space-y-3 rounded-xl border border-gray-200 p-4" x-data="{ editarCorreo: {{ $errors->has('correo_juridica') ? 'true' : 'false' }} }">
                            @if ($borradores->isNotEmpty())
                                @php $vigente = $convenio->versionesBorrador()->last()['version']; @endphp
                                @if ($correoJuridica)
                                    <div class="flex items-start gap-3 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-inset ring-sky-200">
                                        <span class="text-lg leading-none" aria-hidden="true">✉️</span>
                                        <p class="flex-1"><strong>Notificación activa:</strong> al dar clic en «Turnar v{{ $vigente }} a Jurídica» se enviará copia del expediente y del borrador a
                                            <strong class="break-all">{{ $correoJuridica }}</strong>.
                                            <button type="button" class="ml-1 font-semibold text-real-700 underline underline-offset-2 hover:text-real-800" x-on:click="editarCorreo = !editarCorreo">Editar correo</button></p>
                                    </div>
                                @else
                                    <div class="flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-inset ring-amber-200">
                                        <span class="text-lg leading-none" aria-hidden="true">⚠️</span>
                                        <p class="flex-1"><strong>Sin correo de Jurídica:</strong> al turnar solo quedará en la bitácora; tendrás que llevar el expediente en persona.
                                            <button type="button" class="ml-1 font-semibold text-real-700 underline underline-offset-2 hover:text-real-800" x-on:click="editarCorreo = true">Configurar correo</button></p>
                                    </div>
                                @endif
                            @endif

                            <form x-show="editarCorreo" x-cloak x-transition.opacity method="POST" action="{{ route('admin.configuracion.juridica') }}" class="grid grid-cols-1 gap-2 rounded-xl bg-gray-50 p-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="etiqueta" for="cfg-juridica">Correo institucional de la Unidad Jurídica</label>
                                    <input id="cfg-juridica" type="email" name="correo_juridica" value="{{ $correoJuridica }}" placeholder="juridico@csh.udg.mx" class="campo h-10">
                                </div>
                                <button type="button" class="boton boton-secundario h-10" x-on:click="editarCorreo = false; $el.form.reset()">Cancelar</button>
                                <button class="boton boton-primario h-10">Guardar correo</button>
                            </form>

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                @if ($borradores->isEmpty())
                                    <x-ui.accion-bloqueada motivo="Genera o adjunta el borrador del convenio antes de turnarlo a Jurídica.">Turnar a Jurídica</x-ui.accion-bloqueada>
                                @else
                                    <form method="POST" action="{{ route('admin.convenios.juridica.turnar', $convenio) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center"
                                        data-confirmar="¿Turnar la versión v{{ $vigente }} a la Unidad Jurídica?{{ $correoJuridica ? ' Se enviará copia a ' . $correoJuridica . '.' : '' }} El borrador ya no se podrá cambiar hasta registrar su dictamen." data-confirmar-titulo="Turnar a Jurídica" data-confirmar-boton="Sí, turnar" data-confirmar-tono="normal">
                                        @csrf
                                        @if ($correoJuridica)
                                            <input type="hidden" name="enviar_correo" value="0">
                                            <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="enviar_correo" value="1" checked class="rounded border-gray-300"> Enviar copia por correo</label>
                                        @endif
                                        <button class="boton boton-primario h-11"><x-dashboard.icono nombre="enviar" clase="size-4" /> Turnar v{{ $vigente }} a Jurídica</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($estado === E::EnRevisionJuridica && $etapa === Et::DictamenJuridico && $opera)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.juridica.dictamen', $convenio) }}"
                            class="grid grid-cols-1 gap-3 rounded-xl border border-dashed border-gray-300 p-4 md:grid-cols-3 md:items-end">
                            @csrf
                            <p class="text-sm font-semibold text-gray-900 md:col-span-3">¿Qué respondió la Unidad Jurídica?</p>
                            <div><label class="etiqueta" for="j-res">Resultado</label>
                                <select id="j-res" name="resultado" required class="campo h-11">
                                    <option value="aprobado">Viable: pasa a firmas</option>
                                    <option value="observado">Pide correcciones</option>
                                    <option value="rechazado">No viable: cerrar trámite</option>
                                </select></div>
                            <div><label class="etiqueta" for="j-fecha">Fecha del dictamen</label><input id="j-fecha" type="date" name="fecha_dictamen" max="{{ today()->toDateString() }}" value="{{ old('fecha_dictamen', today()->toDateString()) }}" class="campo h-11"></div>
                            <div><label class="etiqueta" for="j-com">Comentario</label><input id="j-com" type="text" name="comentario" placeholder="Obligatorio si pide correcciones o no es viable" class="campo h-11"></div>
                            <div class="md:col-span-2"><x-ui.zona-archivo name="documento" etiqueta="Dictamen escaneado" :requerido="false" ayuda="Opcional: el Dictamen de Viabilidad Jurídica firmado." /></div>
                            <button class="boton boton-primario h-11">Registrar dictamen</button>
                            <p class="ayuda md:col-span-3">Si pide correcciones, el trámite regresa a redacción: corrige los datos, genera el borrador otra vez y vuélvelo a turnar.</p>
                        </form>
                    @endif
                </x-dashboard.panel>
            @endif

            {{-- Firmas y formalización --}}
            @if ($estado === E::EnFirmas && $opera)
                <x-dashboard.panel titulo="Firmas y formalización" icono="firma" descripcion="Registra cada firma y, al final, formaliza con folio y vigencia.">
                    @if (in_array($etapa, [Et::FirmaEmpresa, Et::FirmaRectoria], true))
                        <p class="mb-4 rounded-xl bg-udg-50/60 px-4 py-3 text-sm leading-relaxed text-udg-900 ring-1 ring-inset ring-udg-100">{{ $etapa === Et::FirmaEmpresa
                            ? 'Imprime los 3 juegos del convenio definitivo y avisa a la empresa para que pase por ellos. Su representante rubrica cada hoja en el margen izquierdo, firma y sella la última hoja, y los regresa. Cuando los tengas de vuelta, escanea la última hoja de los 3 juegos y regístralo aquí.'
                            : 'Lleva los 3 juegos ya firmados por la empresa a Rectoría. Cuando regresen con la firma autógrafa y el sello oficial, escanea un juego completo en un solo PDF: ese archivo queda como el convenio firmado.' }}</p>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.firmas', $convenio) }}" class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto] md:items-end">
                            @csrf
                            <input type="hidden" name="etapa" value="{{ $etapa->value }}">
                            <div class="md:col-span-2">
                                <x-ui.zona-archivo name="evidencia" :max-mb="20"
                                    :etiqueta="$etapa === Et::FirmaEmpresa ? 'Evidencia: última hoja firmada y sellada por la empresa' : 'Evidencia: juego completo con las firmas y sellos de ambas partes'"
                                    :ayuda="$etapa === Et::FirmaEmpresa ? 'Un PDF con la última hoja de los 3 juegos (firma completa y sello de la empresa).' : 'Un solo PDF con todas las hojas: rúbricas al margen, firmas y sellos visibles.'" />
                            </div>
                            <div><label class="etiqueta" for="fir-com">Comentario (opcional)</label><input id="fir-com" type="text" name="comentario" maxlength="1000" class="campo h-11"></div>
                            <button class="boton boton-primario h-11 whitespace-nowrap"><x-dashboard.icono nombre="firma" clase="size-4" /> {{ $etapa === Et::FirmaEmpresa ? 'La empresa ya firmó los 3 juegos' : 'Rectoría ya firmó los 3 juegos' }}</button>
                        </form>
                    @elseif ($etapa === Et::ArchivoDistribucion)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.formalizar', $convenio) }}" class="grid grid-cols-1 gap-4 md:grid-cols-4"
                            data-confirmar="¿Formalizar el convenio con este folio y fecha de inicio? Quedará vigente y la empresa podrá publicar vacantes. Revisa bien los datos: ya no se pueden cambiar." data-confirmar-titulo="Formalizar convenio" data-confirmar-boton="Sí, formalizar" data-confirmar-tono="normal"
                            x-data="{ inicio: '', anios: {{ $convenio->duracionAnios() }},
                                get fin() { if (!this.inicio) return ''; const d = new Date(this.inicio + 'T00:00:00'); d.setFullYear(d.getFullYear() + Number(this.anios)); d.setDate(d.getDate() - 1); return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }); } }">
                            @csrf
                            <div><label class="etiqueta" for="fo-folio">Folio</label><input id="fo-folio" type="text" name="folio" required minlength="3" maxlength="30" data-solo="folio" placeholder="CV-2026-014" pattern="[A-Z0-9\/\-]{3,30}" data-msg-formato="El folio solo lleva letras mayúsculas, números, guiones o diagonales." class="campo h-11"></div>
                            <div><label class="etiqueta" for="fo-ini">Inicio de vigencia (fecha de firma)</label><input id="fo-ini" type="date" name="fecha_inicio_vigencia" required x-model="inicio" class="campo h-11"></div>
                            <div>
                                <span class="etiqueta">Duración pactada (cláusula UNDÉCIMA)</span>
                                @if ($convenio->vigencia_anios)
                                    <p class="flex h-11 items-center rounded-xl bg-gray-100 px-4 text-sm font-semibold text-gray-800 ring-1 ring-inset ring-gray-200">{{ $convenio->vigencia_anios }} {{ $convenio->vigencia_anios === 1 ? 'año' : 'años' }} · fijada en el borrador</p>
                                    <p class="ayuda" x-text="fin ? 'Vence el ' + fin + ' (avisos a 60 y 30 días)' : 'La fecha de fin se calcula sola con los años impresos en el convenio'"></p>
                                @else
                                    <p class="text-xs font-medium text-rose-600">El borrador no registró años de vigencia: adjunta de nuevo el borrador firmado indicando la vigencia.</p>
                                @endif
                            </div>
                            <div class="md:col-span-4">
                                @if ($firmado)
                                    <div class="flex flex-col gap-2 rounded-xl bg-emerald-50 px-4 py-3 ring-1 ring-inset ring-emerald-100 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="flex items-center gap-2 text-sm text-emerald-800"><x-dashboard.icono nombre="check" clase="size-4 shrink-0" /> El convenio firmado ya se subió al registrar la firma de Rectoría.</p>
                                        <a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $firmado]) }}" class="text-sm font-semibold text-real-600 hover:underline">Ver {{ $firmado->file_name }}</a>
                                    </div>
                                    <details class="mt-2"><summary class="cursor-pointer text-xs font-semibold text-gray-500 hover:text-gray-700">Reemplazar el escaneo</summary>
                                        <div class="mt-2"><x-ui.zona-archivo name="convenio_firmado" etiqueta="Nuevo escaneo del convenio firmado" :max-mb="20" :requerido="false" /></div>
                                    </details>
                                @else
                                    <x-ui.zona-archivo name="convenio_firmado" etiqueta="Escaneo de un juego firmado por ambas partes" :max-mb="20" ayuda="Un solo PDF con todas las hojas, firmas al calce y sellos visibles." />
                                @endif
                            </div>
                            {{-- Al formalizar, los 3 juegos concluyen solos; aquí se dejan en seguimiento los que aún no se entregan --}}
                            <fieldset class="md:col-span-4">
                                <legend class="etiqueta">¿Algún juego queda pendiente de entrega física? <span class="font-normal text-gray-400">(opcional)</span></legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ([1, 2, 3] as $j)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-inset ring-gray-200">
                                            <input type="checkbox" name="juegos_pendientes[]" value="{{ $j }}" class="size-4 rounded border-gray-300" @checked(in_array((string) $j, old('juegos_pendientes', []), true))>
                                            Juego {{ $j }} <span class="text-xs text-gray-500">· {{ \App\Enums\TipoDocumentoFisico::destinoJuego($j) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="ayuda">Los que marques siguen en Documentos físicos hasta registrar su entrega; los demás pasan a Concluidos.</p>
                            </fieldset>
                            <div class="md:col-span-3"><label class="etiqueta" for="fo-com">Comentario (opcional)</label><input id="fo-com" type="text" name="comentario" class="campo h-11"></div>
                            <div class="flex items-end"><button class="boton boton-exito h-11 w-full"><x-dashboard.icono nombre="vigente" clase="size-4" /> Formalizar convenio</button></div>
                            <p class="ayuda md:col-span-4">Al formalizar, la organización queda activa: puede publicar vacantes y los alumnos de las carreras aprobadas pueden postularse. De los 3 juegos, uno se entrega a la empresa, otro queda en tu carpeta y el tercero va al archivo de la Unidad Jurídica (síguelos en Documentos físicos).</p>
                        </form>
                    @else
                        <p class="dato-vacio">No hay una firma pendiente por registrar.</p>
                    @endif
                </x-dashboard.panel>
            @endif

            {{-- Rechazar --}}
            @unless ($estado->esFinal() || ! $opera)
                <details class="group rounded-2xl border border-rose-200 bg-white shadow-theme-xs">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-rose-700 hover:bg-rose-50/50 sm:px-6">
                        <span class="flex items-center gap-2"><x-dashboard.icono nombre="alerta" clase="size-4" /> Rechazar convenio</span>
                        <svg class="size-4 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <form method="POST" action="{{ route('admin.convenios.rechazar', $convenio) }}" class="flex flex-col gap-2 border-t border-rose-100 px-5 py-4 sm:flex-row sm:items-end sm:px-6"
                        data-confirmar="¿Cerrar el trámite como rechazado? La empresa recibirá el motivo y el trámite ya no podrá continuar." data-confirmar-titulo="Rechazar convenio" data-confirmar-boton="Sí, rechazar">
                        @csrf
                        <div class="flex-1"><label class="etiqueta" for="r-motivo">Motivo</label><input id="r-motivo" type="text" name="motivo" required maxlength="1000" class="campo h-11"></div>
                        <button class="boton boton-peligro h-11">Rechazar trámite</button>
                    </form>
                </details>
            @endunless
        </div>

        {{-- Columna lateral --}}
        <aside class="space-y-6" aria-label="Datos del convenio">
            <x-dashboard.panel titulo="Datos del convenio" icono="documento">
                <dl class="space-y-4 text-sm">
                    <div><dt class="text-xs font-medium text-gray-500">Objetivos</dt><dd class="mt-1 leading-relaxed text-gray-800">{{ $convenio->objetivos ?: 'Sin objetivos registrados' }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500">Actividades propuestas</dt><dd class="mt-1 leading-relaxed text-gray-800">{{ $convenio->actividades_propuestas ?: 'Sin actividades registradas' }}</dd></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100"><dt class="text-xs text-gray-500">Plazas estimadas</dt><dd class="mt-0.5 font-display text-xl font-bold tabular-nums text-gray-900">{{ $convenio->plazas_estimadas }}</dd></div>
                        <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100"><dt class="text-xs text-gray-500">Vigencia</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-gray-900">
                                @if ($convenio->fecha_fin_vigencia){{ $convenio->fecha_inicio_vigencia->format('d/m/Y') }} – {{ $convenio->fecha_fin_vigencia->format('d/m/Y') }}@else<span class="text-xs font-normal italic text-gray-400">Se define al formalizar</span>@endif
                            </dd></div>
                    </div>
                </dl>
                @if ($firmado)
                    <a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $firmado]) }}" class="boton boton-secundario mt-4 w-full"><x-dashboard.icono nombre="descargar" clase="size-4" /> Convenio firmado</a>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel titulo="Documentos físicos" icono="firma" :enlace="route('admin.documentos-fisicos', ['q' => $empresa->razon_social, 'vista' => 'activos'])" enlace-texto="Controlar">
                @forelse ($convenio->documentosFisicos as $d)
                    <div class="flex items-center justify-between gap-3 py-1.5">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-medium text-gray-900">{{ $d->nombre() }}</p>
                            <p @class(['text-xs', 'font-semibold text-rose-600' => $d->vencido(), 'text-gray-500' => !$d->vencido()])>
                                {{ $d->estado->fueraDeOficina() && $d->fecha_compromiso ? ($d->vencido() ? 'Debía regresar el ' : 'Regresa el ') . $d->fecha_compromiso->format('d/m/Y') : 'Lo tiene ' . $d->estado->custodio() }}
                            </p>
                        </div>
                        <x-ui.estatus :estado="$d->estado" class="shrink-0" />
                    </div>
                @empty
                    @if (in_array($estado, [E::EnValidacionAcademica, E::EnRevisionJuridica, E::EnFirmas, E::Formalizado], true))
                        <p class="text-sm text-gray-600">Este trámite aún no tiene control de sus papeles.</p>
                        @can('operar-convenios')<x-ui.boton-icono :accion="route('admin.convenios.fisicos.preparar', $convenio)" icono="mas" etiqueta="Abrir control físico" tono="primario" con-texto class="mt-3" />@endcan
                    @else
                        <p class="dato-vacio">El oficio de turno se registra al turnar a las carreras</p>
                    @endif
                @endforelse
            </x-dashboard.panel>

            <x-dashboard.panel titulo="Contacto de la empresa" icono="alumno" :enlace="route('admin.empresas.show', $empresa)" enlace-texto="Expediente">
                <p class="font-semibold text-gray-900">{{ $empresa->contacto_nombre ?: 'Contacto sin registrar' }}</p>
                <p class="text-sm text-gray-600">{{ $empresa->contacto_cargo ?: 'Cargo sin registrar' }}</p>
                <p class="mt-2 break-all text-sm text-gray-700">{{ $empresa->user?->email ?? 'Sin cuenta de acceso' }}</p>
                <p class="text-sm {{ $empresa->contacto_telefono ? 'text-gray-700' : 'italic text-gray-400' }}">{{ $empresa->contacto_telefono ?: 'Sin teléfono' }}</p>
                <p class="mt-2 text-xs text-gray-500">Representante legal: <span class="font-medium text-gray-700">{{ $empresa->representante_legal ?: 'sin registrar' }}</span></p>
            </x-dashboard.panel>

            <x-dashboard.panel titulo="Bitácora" icono="reloj" :enlace="auth()->user()->can('ver-auditoria') ? route('admin.auditoria', ['entidad_tipo' => 'Convenio', 'entidad_id' => $convenio->id]) : null" enlace-texto="Auditoría">
                @if ($convenio->seguimientos->isEmpty())
                    <p class="dato-vacio">Sin movimientos todavía.</p>
                @else
                    <ol class="relative space-y-4 before:absolute before:inset-y-1 before:left-[5px] before:w-px before:bg-gray-200">
                        @foreach ($convenio->seguimientos as $s)
                            <li class="relative pl-6">
                                <span class="absolute left-0 top-1.5 size-[11px] rounded-full ring-4 ring-white {{ $bitacora[$s->estado->value] ?? 'bg-gray-300' }}" aria-hidden="true"></span>
                                <p class="text-sm font-semibold text-gray-900">{{ $s->etapa->label() }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ ucfirst($s->estado->value) }} · {{ $s->fecha_registro->format('d/m/Y') }}{{ $s->fecha_resolucion ? ' → ' . $s->fecha_resolucion->format('d/m/Y') : '' }}
                                    · {{ $s->diasHabiles() }} {{ $s->diasHabiles() === 1 ? 'día hábil' : 'días hábiles' }}
                                </p>
                                @if ($s->estado === \App\Enums\SeguimientoEstado::Pendiente)
                                    <p class="text-xs text-gray-500">Lo tiene: <span class="font-medium text-gray-700">{{ $s->etapa->responsable() }}</span></p>
                                @else
                                    @foreach ($s->resolvio($convenio) as $quien)
                                        <p class="text-xs text-gray-700"><span class="text-gray-500">{{ $s->etapa === \App\Enums\EtapaConvenio::TurnoAcademico ? '' : ($s->etapa->responsable() === 'Vinculación' ? 'Atendió: ' : 'Resolvió: ') }}</span><span class="font-medium">{{ $quien }}</span></p>
                                    @endforeach
                                    @if ($s->capturadoPorTerceros() && $s->registrador?->user)
                                        <p class="text-[11px] text-gray-400">Registrado en el sistema por {{ $s->registrador->user->name }}</p>
                                    @endif
                                @endif
                                @foreach ($s->media as $evidencia)
                                    <a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $evidencia]) }}" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-real-600 hover:underline"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> {{ $s->etapa === \App\Enums\EtapaConvenio::DictamenJuridico ? 'Dictamen jurídico' : 'Evidencia' }}</a>
                                @endforeach
                                @if ($s->comentario)<p class="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs leading-relaxed text-gray-600">{{ $s->comentario }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-dashboard.panel>
        </aside>
    </div>
</div>
@endsection
