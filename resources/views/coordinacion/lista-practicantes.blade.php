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

    <x-common.page-breadcrumb pageTitle="Practicantes" />

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)
                <li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Postulaciones en trámite --}}
        <div class="{{ $card }}">
            <div class="mb-5 px-6">
                <h3 class="text-lg font-bold text-gray-900">Postulaciones en trámite</h3>
                <p class="mt-0.5 text-sm text-gray-500">Validar, emitir carta de presentación, registrar carta de aceptación
                    y activar la práctica.</p>
            </div>

            <div class="tabla-scroll">
                <table class="tabla">
                    <thead class="border-y border-gray-100 bg-gray-50/70">
                        <tr>
                            <th class="tabla-th px-6">Alumno</th>
                            <th class="{{ $th }}">Programa</th>
                            <th class="{{ $th }}">% créditos</th>
                            <th class="{{ $th }}">Estado</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($postulaciones as $po)
                            @php
                                $kardex = $po->documentoVigente('kardex');
                                $aceptacion = $po->getFirstMedia('carta_aceptacion');
                                $presentacion = $po->documentoVigente('carta_presentacion');
                                $oficioInterno = $po->documentoVigente('oficio_aceptacion_interno');
                                $imss = $po->alumno->documentoVigente('constancia_imss');
                                $interno = $po->programa->esInterno();
                                $min = $po->alumno->carrera->porcentaje_creditos_minimo;
                                $cumple = (float) $po->porcentaje_creditos_declarado >= (float) $min;
                            @endphp
                            <tr class="align-top tabla-fila">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <x-ui.user-avatar :user="$po->alumno->user" size="h-9 w-9 text-xs" />
                                        <div>
                                            <span
                                                class="block text-sm font-semibold text-gray-900">{{ $po->alumno->user->name }}</span>
                                            <span class="text-xs text-gray-500">{{ $po->alumno->num_control }} ·
                                                {{ $po->alumno->carrera->carrera_clave }}</span>
                                            <span
                                                class="block text-xs {{ $po->alumno->tieneImssVigenteHasta(now()) ? 'text-gray-500' : 'text-red-600' }}">
                                                IMSS
                                                {{ $po->alumno->vigencia_imss ? 'hasta ' . $po->alumno->vigencia_imss->format('d/m/Y') : 'sin registrar' }}
                                            </span>
                                            <span class="block text-xs text-gray-500">
                                                {{ $po->alumno->grado ? $po->alumno->grado . '.º sem.' : 'Sin grado' }} ·
                                                {{ $po->alumno->turno?->label() ?? 'sin turno' }}
                                            </span>
                                            <x-coordinacion.documento :media="$imss" etiqueta="Constancia IMSS"
                                                :descarga="$imss ? route('coordinacion.alumnos.archivos', [$po->alumno, $imss]) : null" />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="block text-sm text-gray-800">{{ $po->programa->titulo }}</span>
                                    <span
                                        class="text-xs text-gray-500">{{ $po->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }}
                                        · {{ $po->programa->ciclo }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-sm">
                                    <span
                                        class="font-semibold {{ $cumple ? 'text-green-700' : 'text-red-600' }}">{{ rtrim(rtrim((string) $po->porcentaje_creditos_declarado, '0'), '.') }}%</span>
                                    <span class="text-xs text-gray-400">/ mín.
                                        {{ rtrim(rtrim((string) $min, '0'), '.') }}%</span>
                                    <x-coordinacion.documento :media="$kardex" etiqueta="Kardex"
                                        :descarga="$kardex ? route('coordinacion.postulaciones.archivos', [$po, $kardex]) : null" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <x-ui.estatus :estado="$po->estado" />
                                    @if ($presentacion)
                                        <a href="{{ route('coordinacion.postulaciones.archivos', [$po, $presentacion]) }}"
                                            class="text-real-600 mt-1 block text-xs font-semibold">Carta de presentación</a>
                                    @endif
                                    @if ($aceptacion)
                                        <a href="{{ route('coordinacion.postulaciones.archivos', [$po, $aceptacion]) }}"
                                            class="text-real-600 mt-1 block text-xs font-semibold">Carta de aceptación</a>
                                    @endif
                                    @if ($interno)
                                        @if ($oficioInterno)
                                            <a href="{{ route('coordinacion.postulaciones.archivos', [$po, $oficioInterno]) }}"
                                                class="text-real-600 mt-1 block text-xs font-semibold">Oficio de aceptación interno</a>
                                        @elseif (in_array($po->estado, [PE::CartaPresentacionEmitida, PE::CartaAceptacionRecibida], true))
                                            <span class="mt-1 block text-xs text-red-600">Falta oficio de aceptación interno</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col items-end gap-2">
                                        @if ($po->estado === PE::Pendiente)
                                            @php $faltaCarta = \App\Support\Requisitos::emitirCarta($po); @endphp
                                            @if ($faltaCarta)
                                                <x-ui.accion-bloqueada :motivo="$faltaCarta" tamano="chico">Emitir carta de presentación</x-ui.accion-bloqueada>
                                            @else
                                                <form method="POST" action="{{ route('coordinacion.postulaciones.carta', $po) }}">
                                                    @csrf
                                                    <button class="{{ $btn }} !py-1.5 text-xs">Emitir carta de presentación</button>
                                                </form>
                                            @endif
                                        @elseif ($po->estado === PE::CartaPresentacionEmitida)
                                            <form method="POST" enctype="multipart/form-data"
                                                action="{{ route('coordinacion.postulaciones.aceptacion', $po) }}"
                                                class="flex items-center gap-2">
                                                @csrf
                                                <input type="file" name="archivo" accept="application/pdf" @required(!$aceptacion)
                                                    class="{{ $input }} !w-44 !py-1 text-xs">
                                                <button
                                                    class="{{ $btn }} !py-1.5 text-xs">{{ $aceptacion ? 'Confirmar carta del alumno' : 'Registrar aceptación' }}</button>
                                            </form>
                                        @elseif ($po->estado === PE::CartaAceptacionRecibida && ($faltaActivar = \App\Support\Requisitos::activarPractica($po)))
                                            <x-ui.accion-bloqueada :motivo="$faltaActivar" tamano="chico">Activar práctica</x-ui.accion-bloqueada>
                                        @elseif ($po->estado === PE::CartaAceptacionRecibida)
                                            <form method="POST" action="{{ route('coordinacion.postulaciones.activar', $po) }}"
                                                class="grid grid-cols-2 gap-2">
                                                @csrf
                                                <input type="date" name="fecha_inicio" required class="{{ $input }} text-xs">
                                                <input type="date" name="fecha_fin" required class="{{ $input }} text-xs">
                                                <select name="profesor_id" class="{{ $input }} col-span-2 text-xs">
                                                    <option value="">Supervisor (opcional)</option>
                                                    @foreach ($profesores->where('division_id', $po->alumno->carrera->division_id) as $pf)
                                                        <option value="{{ $pf->id }}">{{ $pf->nombre }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="{{ $btn }} col-span-2 !py-1.5 text-xs">Activar práctica</button>
                                            </form>
                                        @endif

                                        @if ($interno && !$oficioInterno && in_array($po->estado, [PE::CartaPresentacionEmitida, PE::CartaAceptacionRecibida], true))
                                            <form method="POST" enctype="multipart/form-data"
                                                action="{{ route('coordinacion.postulaciones.oficio-interno', $po) }}"
                                                class="flex items-center gap-2">
                                                @csrf
                                                <input type="file" name="archivo" accept="application/pdf" required
                                                    aria-label="Oficio de aceptación interno"
                                                    class="{{ $input }} !w-44 !py-1 text-xs">
                                                <button class="{{ $btn }} !py-1.5 text-xs">Subir oficio interno</button>
                                            </form>
                                        @endif

                                        @if (in_array($po->estado, [PE::Pendiente, PE::CartaPresentacionEmitida], true))
                                            <form method="POST" action="{{ route('coordinacion.postulaciones.rechazar', $po) }}"
                                                onsubmit="return confirm('¿Rechazar esta postulación?')">
                                                @csrf
                                                <button
                                                    class="text-xs font-semibold text-red-600 hover:text-red-700">Rechazar</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">No hay postulaciones en
                                    trámite.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-6 py-4">{{ $postulaciones->links() }}</div>
        </div>

        {{-- Prácticas --}}
        <div class="{{ $card }}">
            <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Seguimiento de prácticas</h3>
                    <p class="mt-0.5 text-sm text-gray-500">Horas validadas, estado y supervisor de cada practicante.</p>
                </div>
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre o código..."
                        class="{{ $filtro }} sm:w-44">
                    <select name="carrera_id" class="{{ $filtro }}">
                        <option value="">Todas las carreras</option>
                        @foreach ($carreras as $c)
                            <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }}
                            </option>
                        @endforeach
                    </select>
                    <select name="estado" class="{{ $filtro }}">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $e)
                            <option value="{{ $e->value }}" @selected(request('estado') === $e->value)>{{ $e->label() }}</option>
                        @endforeach
                    </select>
                    <button
                        class="boton boton-secundario w-full sm:w-auto">Filtrar</button>
                </form>
            </div>

            <div class="tabla-scroll">
                <table class="tabla">
                    <thead class="border-y border-gray-100 bg-gray-50/70">
                        <tr>
                            <th class="tabla-th px-6">Practicante
                            </th>
                            <th class="{{ $th }}">Institución</th>
                            <th class="{{ $th }}">Progreso de horas</th>
                            <th class="{{ $th }}">Periodo</th>
                            <th class="{{ $th }}">Supervisor</th>
                            <th class="{{ $th }}">Estado</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Acciones</th>
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
                                    <div class="flex items-center gap-3">
                                        <x-ui.user-avatar :user="$al->user" size="h-9 w-9 text-xs" />
                                        <div>
                                            <span class="block text-sm font-semibold text-gray-900">{{ $al->user->name }}</span>
                                            <span class="text-xs text-gray-500">{{ $al->num_control }} ·
                                                {{ $al->carrera->carrera_clave }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700">
                                    {{ $pr->postulacion->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="w-44">
                                        <div class="mb-1 flex items-baseline justify-between">
                                            <span class="text-sm font-bold text-gray-900">{{ $horas }}<span
                                                    class="text-xs font-normal text-gray-400"> / {{ $pr->horas_requeridas }}
                                                    h</span></span>
                                            <span class="text-real-600 text-xs font-semibold">{{ $pct }}%</span>
                                        </div>
                                        <div class="h-1.5 w-full rounded-full bg-gray-100">
                                            <div class="bg-udg-800 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-600">
                                    {{ $pr->fecha_inicio->format('d/m/Y') }} – {{ $pr->fecha_fin->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700">
                                    @if ($activa)
                                        <form method="POST" action="{{ route('coordinacion.practicas.supervisor', $pr) }}"
                                            class="flex items-center gap-1">
                                            @csrf
                                            <select name="profesor_id" required class="{{ $input }} !w-40 text-xs">
                                                <option value="">{{ $pr->supervisor?->nombre ?? 'Asignar...' }}</option>
                                                @foreach ($profesores->where('division_id', $al->carrera->division_id) as $pf)
                                                    <option value="{{ $pf->id }}">{{ $pf->nombre }}</option>
                                                @endforeach
                                            </select>
                                            <button class="text-real-600 text-xs font-semibold">OK</button>
                                        </form>
                                    @else
                                        {{ $pr->supervisor?->nombre ?? '—' }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <x-ui.estatus :estado="$pr->estado" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    @if ($activa)
                                        <form method="POST" action="{{ route('coordinacion.practicas.cancelar', $pr) }}"
                                            onsubmit="return confirm('¿Cancelar esta práctica?')">
                                            @csrf
                                            <button class="text-xs font-semibold text-red-600 hover:text-red-700">Cancelar</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">No hay prácticas que
                                    coincidan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-6 py-4">{{ $practicas->links() }}</div>
        </div>
    </div>
@endsection