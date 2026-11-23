@extends('layouts.app')

@section('content')
@php
    use App\Enums\Dictamen;

    $esCoordinacion = ($modo ?? 'vinculacion') === 'coordinacion';
    $rutaArchivo = $esCoordinacion ? 'coordinacion.dictamenes.archivos' : 'admin.dictamenes.archivos.descargar';
    $hayFiltros = request()->hasAny(['q', 'carrera_id']) || $filtro !== 'pendiente';
@endphp

<div class="space-y-6">
    <x-pagina.encabezado :titulo="$esCoordinacion ? 'Dictámenes de mi carrera' : 'Dictámenes por carrera'"
        :descripcion="$esCoordinacion
            ? 'Revisa si las actividades que propone la organización corresponden al perfil de egreso de tu carrera y emite tu dictamen.'
            : 'Cada coordinación de carrera emite su dictamen de pertinencia desde su panel; aquí das seguimiento.'" />

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="No se registró el dictamen"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    <x-tabla.marco titulo="Dictámenes de pertinencia académica" :total="$dictamenes->total()">
        <x-slot:herramientas>
            <x-tabla.filtros-estado parametro="dictamen" :actual="$filtro" etiqueta="Filtrar por dictamen" :opciones="[
                ['valor' => 'pendiente', 'texto' => 'Pendientes', 'conteo' => $resumen['pendientes'], 'familia' => 'ambar'],
                ['valor' => 'aprobatorio', 'texto' => 'Aprobatorios', 'conteo' => $resumen['aprobatorios'], 'familia' => 'menta'],
                ['valor' => 'negativo', 'texto' => 'Negativos', 'conteo' => $resumen['negativos'], 'familia' => 'rosa'],
                ['valor' => 'todos', 'texto' => 'Todos', 'conteo' => $resumen['pendientes'] + $resumen['aprobatorios'] + $resumen['negativos']],
            ]" />
            <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                <input type="hidden" name="dictamen" value="{{ $filtro }}">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar por empresa o RFC</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Empresa o RFC" class="campo-filtro pl-9 sm:w-full">
                </div>
                @if ($carreras->count() > 1)
                    <label for="carrera_id" class="sr-only">Carrera</label>
                    <select id="carrera_id" name="carrera_id" class="campo-filtro sm:max-w-xs" onchange="this.form.requestSubmit()">
                        <option value="">Todas las carreras</option>
                        @foreach ($carreras as $c)
                            <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="boton boton-secundario">Buscar</button>
                @if ($hayFiltros)
                    <a href="{{ url()->current() }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                @endif
            </form>
        </x-slot:herramientas>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th px-6">Organización</th>
                    <th class="tabla-th">Carrera</th>
                    <th class="tabla-th">Dictamen</th>
                    <th class="tabla-th">Observaciones y documento</th>
                    <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            @forelse ($dictamenes as $d)
                @php
                    $pdf = $d->getFirstMedia('dictamen_pertinencia');
                    $capturable = $esCoordinacion
                        && $d->dictamen === Dictamen::Pendiente
                        && $d->convenio->estado === \App\Enums\ConvenioEstado::EnValidacionAcademica;
                    $empresa = $d->convenio->empresa;
                    $abrirConError = $capturable && (int) old('dictamen_id') === $d->id;
                @endphp
                {{-- Cada solicitud es su propio tbody: la fila y su panel desplegable comparten el estado de Alpine --}}
                <tbody class="border-t border-gray-100" x-data="{ abierto: @js($abrirConError), pestana: @js($abrirConError ? 'dictaminar' : 'propuesta'), resultado: @js($abrirConError ? old('dictamen', '') : '') }">
                    <tr class="tabla-fila">
                        <td class="px-4 py-3">
                            @php $subDictamen = $d->convenio->plazas_estimadas . ' ' . ((int) $d->convenio->plazas_estimadas === 1 ? 'plaza estimada' : 'plazas estimadas') . ($d->convenio->fecha_recepcion ? ' · recibida ' . $d->convenio->fecha_recepcion->format('d/m/Y') : ''); @endphp
                            @if ($esCoordinacion)
                                <x-tabla.ficha x-on:click="abierto = !abierto; pestana = 'propuesta'" ver="Ver propuesta" :titulo="$empresa->razon_social"
                                    :inicial="mb_strtoupper(mb_substr($empresa->razon_social, 0, 1))" :subtitulo="$subDictamen" />
                            @else
                                <x-tabla.ficha :href="route('admin.convenios.show', $d->convenio)" ver="Ver convenio" :titulo="$empresa->razon_social"
                                    :inicial="mb_strtoupper(mb_substr($empresa->razon_social, 0, 1))" :subtitulo="$subDictamen" />
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-start gap-2">
                                <span class="shrink-0 rounded-md bg-udg-50 px-1.5 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100">{{ $d->carrera->carrera_clave }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-800">{{ $d->carrera->nombre }}</p>
                                    <p class="text-xs text-gray-500">{{ $d->carrera->division->nombre }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <x-ui.estatus :estado="$d->dictamen" />
                            @if ($d->fecha_dictamen)
                                <p class="mt-1 text-xs text-gray-500">{{ $d->fecha_dictamen->format('d/m/Y') }}</p>
                                @if ($d->dictaminador?->user)<p class="text-xs text-gray-500">{{ $d->dictaminador->user->name }}</p>@endif
                            @else
                                <p class="dato-vacio mt-1">{{ $d->convenio->estado === \App\Enums\ConvenioEstado::EnValidacionAcademica ? 'Esperando a la coordinación' : 'No se emitió' }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            @if ($d->observaciones)
                                <p class="line-clamp-3 text-sm leading-relaxed text-gray-700">{{ $d->observaciones }}</p>
                            @else
                                <p class="dato-vacio">Sin observaciones</p>
                            @endif
                            @if ($pdf)
                                <a href="{{ route($rutaArchivo, [$d, $pdf]) }}" class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-real-600 hover:text-real-700">
                                    <x-dashboard.icono nombre="descargar" clase="size-3.5" /> Dictamen en PDF
                                </a>
                            @else
                                <p class="dato-vacio mt-1">Sin PDF adjunto</p>
                            @endif
                        </td>
                        <td class="acciones whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if ($capturable)
                                    <button type="button" class="boton boton-primario boton-chico" x-on:click="abierto = !abierto; pestana = 'propuesta'" :aria-expanded="abierto.toString()" aria-controls="panel-{{ $d->id }}">
                                        <x-dashboard.icono nombre="editar" clase="size-3.5" /> <span x-text="abierto ? 'Cerrar' : 'Revisar y dictaminar'">Revisar y dictaminar</span>
                                    </button>
                                @elseif ($esCoordinacion)
                                    <button type="button" class="boton boton-secundario boton-chico" x-on:click="abierto = !abierto; pestana = 'propuesta'" :aria-expanded="abierto.toString()" aria-controls="panel-{{ $d->id }}">
                                        <x-dashboard.icono nombre="ver" clase="size-3.5" /> <span x-text="abierto ? 'Cerrar' : 'Ver propuesta'">Ver propuesta</span>
                                    </button>
                                @else
                                    <x-ui.boton-icono :href="route('admin.convenios.show', $d->convenio)" icono="ver" etiqueta="Ver convenio" />
                                @endif
                            </div>
                        </td>
                    </tr>

                    @if ($esCoordinacion)
                        <tr id="panel-{{ $d->id }}" x-show="abierto" x-cloak>
                            <td colspan="5" class="bg-gray-50/70 px-4 py-5 sm:px-6">
                                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                    {{-- Pestañas: primero leer la propuesta y la organización, luego dictaminar --}}
                                    <div class="flex flex-wrap gap-1 border-b border-gray-100 px-3 pt-2" role="tablist">
                                        <button type="button" role="tab" x-on:click="pestana = 'propuesta'" :aria-selected="(pestana === 'propuesta').toString()"
                                            :class="pestana === 'propuesta' ? 'border-udg-900 text-udg-900' : 'border-transparent text-gray-500 hover:text-gray-800'"
                                            class="-mb-px border-b-2 px-3 py-2 text-sm font-semibold">Objetivos y actividades</button>
                                        <button type="button" role="tab" x-on:click="pestana = 'empresa'" :aria-selected="(pestana === 'empresa').toString()"
                                            :class="pestana === 'empresa' ? 'border-udg-900 text-udg-900' : 'border-transparent text-gray-500 hover:text-gray-800'"
                                            class="-mb-px border-b-2 px-3 py-2 text-sm font-semibold">La organización</button>
                                        @if ($capturable)
                                            <button type="button" role="tab" x-on:click="pestana = 'dictaminar'" :aria-selected="(pestana === 'dictaminar').toString()"
                                                :class="pestana === 'dictaminar' ? 'border-udg-900 text-udg-900' : 'border-transparent text-gray-500 hover:text-gray-800'"
                                                class="-mb-px border-b-2 px-3 py-2 text-sm font-semibold">Emitir dictamen</button>
                                        @endif
                                    </div>

                                    <div x-show="pestana === 'propuesta'" class="grid grid-cols-1 gap-5 p-5 lg:grid-cols-2" role="tabpanel">
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Objetivos</h4>
                                            <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-gray-800">{{ $d->convenio->objetivos ?: 'Sin objetivos capturados.' }}</p>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Actividades propuestas</h4>
                                            <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-gray-800">{{ $d->convenio->actividades_propuestas ?: 'Sin actividades capturadas.' }}</p>
                                        </div>
                                        <dl class="grid grid-cols-2 gap-3 rounded-xl bg-gray-50 p-4 text-sm sm:grid-cols-4 lg:col-span-2">
                                            <div><dt class="text-xs text-gray-500">Plazas por ciclo</dt><dd class="font-semibold text-gray-900">{{ $d->convenio->plazas_estimadas ?? '—' }}</dd></div>
                                            <div><dt class="text-xs text-gray-500">Experiencia con practicantes</dt><dd class="font-semibold text-gray-900">{{ $d->convenio->experiencia_previa ?: '—' }}</dd></div>
                                            <div class="col-span-2"><dt class="text-xs text-gray-500">Carreras solicitadas</dt>
                                                <dd class="mt-1 flex flex-wrap gap-1">
                                                    @foreach ($d->convenio->dictamenes as $otra)
                                                        <span title="{{ $otra->carrera->nombre }}" @class(['rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset', 'bg-udg-900 text-white ring-udg-900' => $otra->id === $d->id, 'bg-white text-gray-700 ring-gray-200' => $otra->id !== $d->id])>{{ $otra->carrera->carrera_clave }}</span>
                                                    @endforeach
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <div x-show="pestana === 'empresa'" x-cloak class="grid grid-cols-1 gap-4 p-5 text-sm md:grid-cols-2" role="tabpanel">
                                        <div><p class="text-xs text-gray-500">Tipo de organismo</p><p class="font-semibold text-gray-900">{{ $empresa->tipo_organismo?->label() ?? '—' }}{{ $empresa->giro ? ' · ' . $empresa->giro : '' }}</p></div>
                                        <div><p class="text-xs text-gray-500">Ubicación</p><p class="font-semibold text-gray-900">{{ collect([$empresa->municipio, $empresa->entidad])->filter()->join(', ') ?: '—' }}</p></div>
                                        <div class="md:col-span-2"><p class="text-xs text-gray-500">A qué se dedica</p><p class="whitespace-pre-line leading-relaxed text-gray-800">{{ $empresa->descripcion ?: '—' }}</p></div>
                                        <div class="md:col-span-2"><p class="text-xs text-gray-500">Principales proyectos o servicios</p><p class="whitespace-pre-line leading-relaxed text-gray-800">{{ $empresa->proyectos_principales ?: '—' }}</p></div>
                                    </div>

                                    @if ($capturable)
                                        <form x-show="pestana === 'dictaminar'" x-cloak x-ref="form" method="POST" enctype="multipart/form-data" action="{{ route('coordinacion.dictamenes.registrar', $d) }}"
                                            class="space-y-5 p-5" role="tabpanel"
                                            :data-confirmar="'¿Registrar el dictamen ' + resultado + '? Un dictamen registrado ya no se puede cambiar.'" data-confirmar-titulo="Registrar dictamen" data-confirmar-boton="Sí, registrar" :data-confirmar-tono="resultado === 'negativo' ? 'peligro' : 'normal'">
                                            @csrf
                                            <input type="hidden" name="dictamen_id" value="{{ $d->id }}">
                                            <fieldset>
                                                <legend class="etiqueta">¿Las actividades corresponden al perfil de egreso de {{ $d->carrera->carrera_clave }}? <span class="text-rose-500">*</span></legend>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition" :class="resultado === 'aprobatorio' ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200' : 'border-gray-200 hover:bg-gray-50'">
                                                        <input type="radio" name="dictamen" value="aprobatorio" x-model="resultado" required class="mt-1">
                                                        <span><span class="block text-sm font-semibold text-gray-900">Aprobatorio</span><span class="block text-xs text-gray-500">La carrera entra al convenio y podrá recibir vacantes.</span></span>
                                                    </label>
                                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition" :class="resultado === 'negativo' ? 'border-rose-400 bg-rose-50 ring-2 ring-rose-200' : 'border-gray-200 hover:bg-gray-50'">
                                                        <input type="radio" name="dictamen" value="negativo" x-model="resultado" class="mt-1">
                                                        <span><span class="block text-sm font-semibold text-gray-900">Negativo</span><span class="block text-xs text-gray-500">La carrera queda fuera; la empresa recibe tus observaciones por correo.</span></span>
                                                    </label>
                                                </div>
                                            </fieldset>
                                            <div>
                                                <label for="obs-{{ $d->id }}" class="etiqueta">Observaciones <span x-show="resultado === 'negativo'" class="font-normal text-rose-600">* obligatorias: la empresa las usará para ajustar sus actividades</span></label>
                                                <textarea id="obs-{{ $d->id }}" name="observaciones" rows="4" maxlength="2000" :required="resultado === 'negativo'"
                                                    placeholder="Ej. Las actividades son administrativas; para esta carrera deben incluir tareas propias del perfil de egreso." class="campo">{{ $abrirConError ? old('observaciones') : '' }}</textarea>
                                            </div>
                                            <x-ui.zona-archivo name="documento" etiqueta="Dictamen de pertinencia firmado y sellado (PDF)" ayuda="Obligatorio en ambos casos: el dictamen escaneado con firma y sello de la coordinación." />
                                            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                                                {{-- Cancelar: no registra nada, limpia lo capturado y cierra el panel --}}
                                                <button type="button" class="boton boton-secundario" x-on:click="$refs.form.reset(); resultado = ''; pestana = 'propuesta'; abierto = false">Cancelar</button>
                                                <button class="boton boton-primario" :disabled="resultado === ''" :class="resultado === '' && 'cursor-not-allowed opacity-50'">
                                                    <x-dashboard.icono nombre="check" clase="size-4" /> Registrar dictamen
                                                </button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="5" class="px-6 py-4">
                            <x-dashboard.vacio icono="academico"
                                titulo="{{ $filtro === 'pendiente' && !request()->hasAny(['q', 'carrera_id']) ? 'No hay dictámenes pendientes' : 'No hay dictámenes con ese filtro' }}"
                                texto="{{ $filtro === 'pendiente' ? 'Cuando Vinculación turne un convenio a las carreras aparecerá aquí.' : 'Prueba con otro estado o quita la búsqueda.' }}" />
                        </td>
                    </tr>
                </tbody>
            @endforelse
        </table>

        <x-slot:pie>{{ $dictamenes->links() }}</x-slot:pie>
    </x-tabla.marco>

    {{-- Convenios en los que ninguna carrera aprobó --}}
    @unless ($esCoordinacion)
        <x-dashboard.panel titulo="Convenios con todos los dictámenes negativos" icono="alerta"
            descripcion="Si ninguna carrera aprueba, el trámite se cierra como rechazado.">
            @forelse ($todasNegativas as $c)
                <div class="-mx-2 flex flex-wrap items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $c->empresa->razon_social }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            @foreach ($c->dictamenes as $dn)
                                <span class="rounded-md bg-rose-50 px-1.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-200" title="{{ $dn->carrera->nombre }}">{{ $dn->carrera->carrera_clave }}</span>
                            @endforeach
                            <x-ui.estatus :estado="$c->estado" />
                        </div>
                    </div>
                    <x-ui.boton-icono :href="route('admin.convenios.show', $c)" icono="ver" etiqueta="Ver convenio" />
                </div>
            @empty
                <x-dashboard.vacio titulo="Ninguno por ahora" texto="Todos los convenios dictaminados tienen al menos una carrera que los aprueba." />
            @endforelse
        </x-dashboard.panel>
    @endunless
</div>
@endsection
