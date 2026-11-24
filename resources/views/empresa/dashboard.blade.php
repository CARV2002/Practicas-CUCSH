@extends('layouts.app')

@section('content')
@php
    $ocupacion = $kpis['cupos_totales'] > 0 ? (int) round($kpis['cupos_ocupados'] / $kpis['cupos_totales'] * 100) : 0;
    $subtitulo = match (true) {
        (bool) $vigente => 'Tu convenio con el CUCSH está vigente hasta el ' . $vigente->fecha_fin_vigencia->format('d/m/Y') . '.',
        (bool) $enTramite => 'Tu solicitud de convenio está en proceso: ' . mb_strtolower($enTramite->estado->etiquetaEmpresa()) . '.',
        default => 'Solicita tu convenio con el CUCSH para empezar a recibir practicantes.',
    };
    $subtitulo .= $totalPendientes
        ? ' Tienes ' . $totalPendientes . ' ' . ($totalPendientes === 1 ? 'pendiente' : 'pendientes') . ' por atender.'
        : ' No tienes pendientes por atender.';
@endphp

<div class="space-y-6">

    <x-dashboard.encabezado :eyebrow="$empresa->razon_social" :titulo="'Hola, ' . $nombre" :subtitulo="$subtitulo">
        <x-slot:acciones>
            @if ($vigente)
                <a href="{{ route('empresa.lista-programas') }}" class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50">
                    <x-dashboard.icono nombre="vacante" clase="size-4" /> Mis vacantes
                </a>
            @else
                <a href="{{ route('empresa.convenio') }}" class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50">
                    <x-dashboard.icono nombre="documento" clase="size-4" /> {{ $enTramite ? 'Ver mi solicitud' : 'Solicitar convenio' }}
                </a>
            @endif
            <a href="{{ route('empresa.documentos') }}" class="boton border border-white/25 text-white hover:bg-white/10">Documentos</a>
        </x-slot:acciones>
    </x-dashboard.encabezado>

    {{-- Lo pendiente vive aquí, no en la campana --}}
    <x-dashboard.por-atender :pendientes="$pendientes" />

    {{-- Avisos --}}
    @if ($vigente && $diasRestantes <= 60)
        <x-ui.aviso tipo="advertencia" titulo="Tu convenio vence en {{ $diasRestantes }} {{ $diasRestantes === 1 ? 'día' : 'días' }}">
            Para seguir recibiendo practicantes, <a href="{{ route('empresa.convenio') }}" class="font-semibold underline underline-offset-2">solicita la renovación</a>.
        </x-ui.aviso>
    @endif
    @if (!$expedienteCompleto)
        <x-ui.aviso tipo="info" titulo="Tu expediente necesita atención">
            Hay documentos faltantes o sin validar. <a href="{{ route('empresa.documentos') }}" class="font-semibold underline underline-offset-2">Revisar documentos</a>.
        </x-ui.aviso>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Convenio: estado y vigencia --}}
        <x-dashboard.panel titulo="Mi convenio" icono="vigente" :enlace="route('empresa.convenio')" enlace-texto="Detalle">
            @if ($vigente)
                <div class="flex items-center gap-3">
                    <span class="relative flex size-3"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60 motion-reduce:hidden"></span><span class="relative inline-flex size-3 rounded-full bg-emerald-500"></span></span>
                    <p class="font-display text-2xl font-bold text-emerald-700">Vigente</p>
                </div>
                <p class="mt-1 text-sm text-gray-600">{{ $vigente->folio ? 'Folio ' . $vigente->folio : 'Folio sin asignar' }}</p>
                <div class="mt-5">
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>{{ $vigente->fecha_inicio_vigencia?->format('d/m/Y') }}</span>
                        <span>{{ $vigente->fecha_fin_vigencia->format('d/m/Y') }}</span>
                    </div>
                    <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ $avanceVigencia ?? 0 }}" aria-valuemin="0" aria-valuemax="100" aria-label="Tiempo transcurrido de la vigencia">
                        <div @class(['h-full rounded-full', 'bg-gradient-to-r from-udg-800 to-oro-400' => $diasRestantes > 60, 'bg-amber-400' => $diasRestantes <= 60]) style="width: {{ $avanceVigencia ?? 0 }}%"></div>
                    </div>
                    <p class="mt-2 text-sm font-semibold {{ $diasRestantes <= 60 ? 'text-amber-700' : 'text-gray-800' }}">
                        Quedan {{ number_format($diasRestantes) }} {{ $diasRestantes === 1 ? 'día' : 'días' }}
                    </p>
                </div>
                @if ($vigente->dictamenes->isNotEmpty())
                    <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-gray-500">Carreras con convenio</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($vigente->dictamenes as $d)
                            <span class="rounded-md bg-udg-50 px-2 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100" title="{{ $d->carrera->nombre }}">{{ $d->carrera->carrera_clave }}</span>
                        @endforeach
                    </div>
                @endif
            @elseif ($enTramite)
                @php $paso = $enTramite->estado->pasoEmpresa(); @endphp
                <x-ui.estatus :estado="$enTramite->estado" :texto="$enTramite->estado->etiquetaEmpresa()" tamano="normal" />
                <ol class="mt-5 space-y-0">
                    @foreach (\App\Enums\ConvenioEstado::pasosEmpresa() as $i => $nombrePaso)
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @if (!$loop->last)<span class="absolute left-[11px] top-6 h-full w-0.5 {{ $i < $paso ? 'bg-udg-800' : 'bg-gray-200' }}" aria-hidden="true"></span>@endif
                            <span @class([
                                'relative z-1 flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                'bg-udg-800 text-white' => $i < $paso,
                                'bg-oro-400 text-udg-950 ring-4 ring-oro-100' => $i === $paso,
                                'bg-gray-100 text-gray-400' => $i > $paso,
                            ])>
                                @if ($i < $paso)<x-dashboard.icono nombre="check" clase="size-3.5" />@else{{ $i + 1 }}@endif
                            </span>
                            <span class="pt-0.5 text-sm {{ $i === $paso ? 'font-semibold text-gray-900' : 'text-gray-500' }}">
                                {{ $nombrePaso }}@if ($i === $paso)<span class="sr-only"> (paso actual)</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-dashboard.vacio icono="documento" titulo="Aún no tienes convenio"
                    texto="Sube tus documentos y envía tu solicitud. Te avisaremos en cada etapa." />
                <a href="{{ route('empresa.convenio') }}" class="boton boton-primario w-full">Solicitar convenio</a>
            @endif
        </x-dashboard.panel>

        {{-- KPIs operativos --}}
        <section class="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 xl:col-span-2" aria-label="Resumen de vacantes y practicantes">
            <x-dashboard.kpi :href="route('empresa.lista-programas')" etiqueta="Vacantes activas" :valor="$kpis['programas_publicados']"
                icono="vacante" tono="menta" :orden="0"
                :detalle="$kpis['cupos_libres'] . ' ' . ($kpis['cupos_libres'] === 1 ? 'cupo libre' : 'cupos libres') . ' · ' . $ocupacion . '% de ocupación'" accion="Ver vacantes">
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $ocupacion }}%"></div></div>
            </x-dashboard.kpi>
            <x-dashboard.kpi :href="route('empresa.lista-programas', ['estado' => 'pendiente_aprobacion'])" etiqueta="Vacantes en revisión" :valor="$programasSinPublicar"
                icono="reloj" tono="ambar" :orden="1" detalle="Esperan la aprobación de la coordinación de la carrera" accion="Ver vacantes" />
            <x-dashboard.kpi :href="route('empresa.lista-practicantes')" etiqueta="Practicantes activos" :valor="$kpis['practicantes_activos']"
                icono="usuarios" tono="cielo" :orden="2" :detalle="$kpis['acreditados'] . ' ' . ($kpis['acreditados'] === 1 ? 'acreditado' : 'acreditados') . ' en total'" accion="Ver practicantes" />
            <x-dashboard.kpi :href="route('empresa.lista-practicantes')" etiqueta="Reportes por comentar" :valor="$reportesSinComentar"
                icono="comentario" tono="lavanda" :orden="3" detalle="Tu retroalimentación ayuda a validar sus horas" accion="Comentar"
                :alerta="$reportesSinComentar ? 'Pendiente' : null" />
        </section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Retroalimentación pendiente --}}
        <x-dashboard.panel titulo="Reportes sin tu retroalimentación" icono="comentario"
            descripcion="Reportes de horas de tus practicantes que aún no comentas." :enlace="route('empresa.lista-practicantes')" enlace-texto="Practicantes">
            @forelse ($reportesPendientes as $r)
                @php $al = $r->practica->postulacion->alumno; @endphp
                <div class="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-ui.user-avatar :user="$al->user" size="size-10 text-xs" />
                        <div class="min-w-0">
                            <p class="break-words text-sm font-semibold text-gray-900">{{ $al->user->name }}</p>
                            <p class="break-words text-xs text-gray-500">{{ $r->tipo->label() }} · {{ $r->horas_reportadas }} h · {{ $r->periodo_inicio->format('d/m') }}–{{ $r->periodo_fin->format('d/m/Y') }}</p>
                        </div>
                    </div>
                    <a href="{{ route('empresa.practicantes.show', $r->practica) }}" class="boton boton-chico boton-primario shrink-0">Comentar</a>
                </div>
            @empty
                <x-dashboard.vacio titulo="Sin reportes pendientes" texto="Cuando tus practicantes suban reportes, aparecerán aquí para que los comentes." />
            @endforelse
        </x-dashboard.panel>

        {{-- Candidatos con carta de presentación --}}
        <x-dashboard.panel titulo="Alumnos por presentarse" icono="alumno"
            descripcion="Validados por su coordinación. Al aceptarlos, entrega la carta de aceptación sellada al alumno.">
            @forelse ($candidatos as $po)
                <div class="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-ui.user-avatar :user="$po->alumno->user" size="size-10 text-xs" />
                        <div class="min-w-0">
                            <p class="break-words text-sm font-semibold text-gray-900">{{ $po->alumno->user->name }}</p>
                            <p class="break-words text-xs text-gray-500">{{ $po->alumno->carrera->nombre }} · {{ $po->programa->titulo }}</p>
                        </div>
                    </div>
                    <a href="{{ route('empresa.programas.postulantes', $po->programa) }}" class="enlace shrink-0 text-xs">Ver programa →</a>
                </div>
            @empty
                <x-dashboard.vacio icono="alumno" titulo="Nadie por presentarse" texto="Aquí verás a los alumnos con carta de presentación para tus vacantes." />
            @endforelse
        </x-dashboard.panel>
    </div>
</div>
@endsection
