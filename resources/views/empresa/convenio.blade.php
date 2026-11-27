@extends('layouts.app')

@section('content')
    @php

        $input = 'campo';
        $label = 'etiqueta';
        $card = 'tarjeta p-6';
        $mostrarForm = $puedeSolicitar || $puedeRenovar;
        $carrerasPorDivision = $carreras->groupBy(fn($c) => $c->division->nombre);
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Mi convenio" descripcion="Tu convenio de prácticas profesionales con el CUCSH: la solicitud, en qué etapa va, su vigencia y la renovación." />

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
        @endif
        @if ($juegosPendientes->isNotEmpty())
            @php $limite = $juegosPendientes->min('fecha_compromiso'); @endphp
            <x-ui.aviso :tipo="$limite && $limite->isPast() ? 'error' : 'advertencia'" titulo="Tienes {{ $juegosPendientes->count() }} {{ $juegosPendientes->count() === 1 ? 'juego original' : 'juegos originales' }} del convenio por firmar">
                Fírmalos al calce en todas sus hojas y entrégalos en Vinculación{{ $limite ? ($limite->isPast() ? '; la fecha acordada fue el ' : ' a más tardar el ') . $limite->format('d/m/Y') : '' }}. Sin ellos no puede pasar a la firma de Rectoría.
            </x-ui.aviso>
        @endif

        {{-- Convenio vigente --}}
        @if ($vigente)
            @php
                $totalDias = max(1, $vigente->fecha_inicio_vigencia->diffInDays($vigente->fecha_fin_vigencia));
                $transcurrido = min(100, max(0, round($vigente->fecha_inicio_vigencia->diffInDays(now()) / $totalDias * 100)));
                $porVencer = $diasRestantes <= 60;
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs" aria-labelledby="titulo-vigente">
                <div class="flex flex-col gap-4 border-b border-gray-100 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Folio <span class="font-semibold text-gray-800">{{ $vigente->folio ?? 'sin asignar' }}</span></p>
                        <h2 id="titulo-vigente" class="mt-1 font-display text-xl font-bold text-gray-900">Convenio específico en materia de prácticas profesionales</h2>
                        <p class="mt-1 text-sm text-gray-500">Celebrado con el CUCSH de la Universidad de Guadalajara.</p>
                    </div>
                    <x-ui.estatus :familia="$porVencer ? 'ambar' : 'menta'" :texto="$porVencer ? 'Próximo a vencer' : 'Vigente'" tamano="normal" class="self-start" />
                </div>
                <div class="grid grid-cols-1 gap-6 p-5 sm:p-6 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <div class="flex items-end justify-between gap-3 text-sm">
                            <div><p class="text-xs text-gray-500">Inicio</p><p class="font-semibold text-gray-900">{{ $vigente->fecha_inicio_vigencia->translatedFormat('d \d\e F \d\e Y') }}</p></div>
                            <div class="text-right"><p class="text-xs text-gray-500">Fin</p><p class="font-semibold text-gray-900">{{ $vigente->fecha_fin_vigencia->translatedFormat('d \d\e F \d\e Y') }}</p></div>
                        </div>
                        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ $transcurrido }}" aria-valuemin="0" aria-valuemax="100" aria-label="Vigencia transcurrida">
                            <div class="h-full rounded-full {{ $porVencer ? 'bg-amber-400' : 'bg-gradient-to-r from-udg-800 to-oro-400' }}" style="width: {{ $transcurrido }}%"></div>
                        </div>
                        <p class="mt-2 text-sm font-semibold {{ $porVencer ? 'text-amber-700' : 'text-gray-800' }}">Quedan {{ number_format($diasRestantes) }} {{ $diasRestantes === 1 ? 'día' : 'días' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Carreras autorizadas</p>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @forelse ($vigente->dictamenes->where('dictamen', \App\Enums\Dictamen::Aprobatorio) as $d)
                                <span class="rounded-md bg-udg-50 px-2 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100" title="{{ $d->carrera->nombre }}">{{ $d->carrera->carrera_clave }}</span>
                            @empty
                                <span class="dato-vacio">Sin carreras registradas</span>
                            @endforelse
                        </div>
                        @if ($vigente->getMedia('convenio_firmado')->isNotEmpty())
                            <a href="{{ route('empresa.convenio.archivo', $vigente) }}" class="boton boton-secundario mt-4 w-full"><x-dashboard.icono nombre="descargar" clase="size-4" /> Convenio firmado</a>
                        @else
                            <p class="dato-vacio mt-4">El convenio firmado aún no está disponible para descarga.</p>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- Trámite en curso --}}
        @if ($enTramite)
            @php $paso = $enTramite->estado->pasoEmpresa(); @endphp
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs sm:p-6" aria-labelledby="titulo-tramite">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 id="titulo-tramite" class="font-display text-base font-bold text-gray-900">Solicitud en trámite</h2>
                        <p class="text-xs text-gray-500">Recibida el {{ $enTramite->fecha_recepcion->format('d/m/Y') }}. Te avisaremos en la campana en cada cambio.</p>
                    </div>
                    <x-ui.estatus :estado="$enTramite->estado" :texto="$enTramite->estado->etiquetaEmpresa()" tamano="normal" />
                </div>
                <ol class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach (\App\Enums\ConvenioEstado::pasosEmpresa() as $i => $nombre)
                        @php $hecho = $i < $paso; $actual = $i === $paso; @endphp
                        <li @class([
                            'rounded-xl border p-3',
                            'border-udg-200 bg-udg-50/60' => $hecho,
                            'border-transparent bg-white shadow-theme-sm ring-2 ring-oro-300' => $actual,
                            'border-gray-200' => !$hecho && !$actual,
                        ])>
                            <div class="flex items-start gap-2">
                                <span @class(['flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold', 'bg-udg-800 text-white' => $hecho, 'bg-oro-400 text-udg-950' => $actual, 'bg-gray-100 text-gray-400' => !$hecho && !$actual])>
                                    @if ($hecho)<x-dashboard.icono nombre="check" clase="size-3.5" />@else{{ $i + 1 }}@endif
                                </span>
                                <span @class(['text-sm font-semibold leading-6', 'text-udg-900' => $hecho || $actual, 'text-gray-400' => !$hecho && !$actual])>{{ $nombre }}</span>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500">{{ $hecho ? 'Completado' : ($actual ? 'En curso' : 'Pendiente') }}</p>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-4 flex items-start gap-2 rounded-xl bg-udg-50/60 px-3.5 py-2.5 text-sm text-udg-900 ring-1 ring-inset ring-udg-100">
                    <x-dashboard.icono nombre="reloj" clase="mt-0.5 size-4 shrink-0" />
                    <span><span class="font-semibold">Qué sigue:</span> {{ $enTramite->estado->estimadoEmpresa() }}</span>
                </p>
                @if ($enTramite->estado === \App\Enums\ConvenioEstado::Solicitado && !$expedienteCompleto)
                    <x-ui.aviso tipo="advertencia" class="mt-4">Tu expediente tiene documentos sin validar. <a href="{{ route('empresa.documentos') }}" class="font-semibold underline underline-offset-2">Revisar documentos</a></x-ui.aviso>
                @endif
            </section>

            {{-- Datos que Vinculación devolvió: solo esos se pueden editar --}}
            @if ($enTramite->estado === \App\Enums\ConvenioEstado::Solicitado && $enTramite->tieneCorreccionesPendientes())
                @include('empresa.partials.correcciones', ['convenio' => $enTramite])
            @endif
        @endif

        @php $ultimoRechazo = $historial->first(fn ($h) => $h->estado === \App\Enums\ConvenioEstado::Rechazado); @endphp
        @php $propuestaAnterior = (!$vigente && !$enTramite && $ultimoRechazo && $historial->first()?->is($ultimoRechazo)) ? $ultimoRechazo : null; @endphp
        @php
            // ¿Ya corrigió? Guardó «Perfiles requeridos» completo después del rechazo: el aviso rojo desaparece
            $guardadoPerfiles = ($empresa->solicitud_borrador['perfiles_guardado_at'] ?? null)
                ? \Illuminate\Support\Carbon::parse($empresa->solicitud_borrador['perfiles_guardado_at'])
                // Guardado antes de registrar la fecha: se toma la última actualización de la empresa
                : (filled($empresa->solicitud_borrador['objetivos'] ?? null) ? $empresa->updated_at : null);
            $propuestaCorregida = $propuestaAnterior && $guardadoPerfiles && $guardadoPerfiles->gte($propuestaAnterior->updated_at) && empty($faltantes['perfiles']);
        @endphp
        @if ($propuestaAnterior && $propuestaCorregida)
            <x-ui.aviso tipo="exito" autocerrar="6000">Corregiste tu propuesta. {{ collect($faltantes)->flatten()->isEmpty() ? 'Ya puedes enviarla a Vinculación con el botón de abajo.' : 'Completa las secciones que faltan y envíala a Vinculación.' }}</x-ui.aviso>
        @elseif ($propuestaAnterior)
            <x-ui.correccion titulo="Tu última solicitud fue rechazada" quien="Vinculación" :motivo="$propuestaAnterior->seguimientos->where('estado', \App\Enums\SeguimientoEstado::Rechazado)->last()?->comentario">
                Puedes empezar el trámite de nuevo{{ $mostrarForm ? ' en la solicitud de abajo (sección «Perfiles requeridos», marcada en rojo)' : '' }}: la llenamos con tu propuesta anterior para que ajustes las actividades y elijas otra vez las carreras. Tu expediente se conserva; Vinculación lo revisa de nuevo y las carreras vuelven a dictaminar.
                @php $negativos = $propuestaAnterior->dictamenes->where('dictamen', \App\Enums\Dictamen::Negativo)->filter(fn ($d) => filled($d->observaciones)); @endphp
                @if ($negativos->isNotEmpty())
                    <p class="mt-2 font-semibold">Lo que dijo cada carrera:</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        @foreach ($negativos as $d)<li><span class="font-semibold">{{ $d->carrera->nombre }}:</span> {{ $d->observaciones }}</li>@endforeach
                    </ul>
                @endif
            </x-ui.correccion>
        @endif

        @if (!$vigente && !$enTramite && !$mostrarForm)
            <x-dashboard.vacio icono="documento" titulo="No tienes convenio ni solicitud en curso" texto="Cuando puedas solicitarlo, aquí aparecerá el formulario." class="rounded-2xl border border-gray-200 bg-white" />
        @endif

        {{-- Renovación --}}
        @if ($puedeRenovar)
            <x-ui.aviso tipo="advertencia" titulo="Tu convenio vence en {{ $diasRestantes }} días">Para no suspender tus programas activos, envía la solicitud de renovación con el formulario de abajo.</x-ui.aviso>
        @endif

        {{-- Solicitud / renovación por secciones, con borrador --}}
        @if ($mostrarForm)
            @include('empresa.partials.solicitud')
        @endif

        {{-- Historial --}}
        @if ($historial->isNotEmpty())
            <x-dashboard.panel titulo="Historial de solicitudes" icono="reloj">
                @foreach ($historial as $h)
                    <div class="-mx-2 flex flex-wrap items-center justify-between gap-2 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $h->folio ? 'Folio ' . $h->folio : 'Sin folio' }}</p>
                            <p class="text-xs text-gray-500">Recibido {{ $h->fecha_recepcion->format('d/m/Y') }}@if ($h->fecha_fin_vigencia) · vigencia hasta {{ $h->fecha_fin_vigencia->format('d/m/Y') }}@endif</p>
                        </div>
                        <x-ui.estatus :estado="$h->estado" :texto="$h->estado->etiquetaEmpresa()" />
                    </div>
                @endforeach
            </x-dashboard.panel>
        @endif
    </div>
@endsection
