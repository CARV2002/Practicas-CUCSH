@extends('layouts.app')

@section('content')
@php
    use App\Enums\SeguimientoEstado as SE;

    $input = 'focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700';
    $card = 'rounded-2xl border border-gray-200 bg-white p-6';
    $mostrarForm = $puedeSolicitar || $puedeRenovar;
    $carrerasPorDivision = $carreras->groupBy(fn ($c) => $c->division->nombre);
@endphp

<x-common.page-breadcrumb pageTitle="Convenio CUCSH" />

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Convenio vigente --}}
    @if ($vigente)
        <div class="{{ $card }}">
            <div class="flex flex-col gap-4 border-b border-gray-100 pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="rounded-md border border-gray-200 bg-gray-100 px-2.5 py-1 font-mono text-xs font-bold text-gray-500">{{ $vigente->folio }}</span>
                    <h3 class="mt-2 text-xl font-bold text-gray-900">Convenio específico en materia de prácticas profesionales</h3>
                    <p class="mt-1 text-xs text-gray-400">Instrumento celebrado con el CUCSH de la Universidad de Guadalajara.</p>
                </div>
                @if ($diasRestantes <= 60)
                    <span class="self-start rounded-full border border-yellow-300 bg-yellow-50 px-3 py-1 text-xs font-bold text-yellow-700">Próximo a vencer</span>
                @else
                    <span class="self-start rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-bold text-green-700">Convenio activo</span>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-6 py-5 md:grid-cols-3">
                <div>
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Inicio de vigencia</h4>
                    <p class="mt-0.5 text-sm font-semibold text-gray-800">{{ $vigente->fecha_inicio_vigencia->translatedFormat('d \d\e F \d\e Y') }}</p>
                </div>
                <div>
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Fin de vigencia</h4>
                    <p class="mt-0.5 text-sm font-semibold text-gray-800">
                        {{ $vigente->fecha_fin_vigencia->translatedFormat('d \d\e F \d\e Y') }}
                        <span class="bg-brand-50 text-brand-700 ml-1 rounded px-1.5 py-0.5 text-xs font-bold">{{ $diasRestantes }} días</span>
                    </p>
                </div>
                <div>
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Carreras autorizadas</h4>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($vigente->dictamenes->where('dictamen', \App\Enums\Dictamen::Aprobatorio) as $d)
                            <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $d->carrera->carrera_clave }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($vigente->getMedia('convenio_firmado')->isNotEmpty())
                <div class="border-t border-gray-100 pt-4">
                    <a href="{{ route('empresa.convenio.archivo', $vigente) }}" class="text-brand-600 text-sm font-semibold hover:underline">Descargar convenio firmado (PDF)</a>
                </div>
            @endif
        </div>
    @endif

    {{-- Trámite en curso --}}
    @if ($enTramite)
        <div class="{{ $card }}">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 class="text-base font-bold text-gray-900">Solicitud en trámite</h4>
                    <p class="text-xs text-gray-500">Recibida el {{ $enTramite->fecha_recepcion->format('d/m/Y') }}</p>
                </div>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $enTramite->estado->label() }}</span>
            </div>

            @php $abierta = $enTramite->seguimientos->filter(fn ($s) => $s->estado === SE::Pendiente)->last(); @endphp
            @if ($abierta)
                <p class="mb-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                    <strong>Etapa actual:</strong> {{ $abierta->etapa->label() }} · a cargo de {{ $abierta->etapa->responsable() }}
                    @if ($abierta->etapa === \App\Enums\EtapaConvenio::RevisionDocumentos && ! $expedienteCompleto)
                        <br><span class="text-yellow-700">Tu expediente está incompleto o con documentos sin validar.
                            <a href="{{ route('empresa.documentos') }}" class="font-semibold underline">Revisar documentos</a></span>
                    @endif
                </p>
            @endif

            <h5 class="mb-3 text-sm font-semibold text-gray-800">Dictámenes de las carreras</h5>
            <div class="mb-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($enTramite->dictamenes as $d)
                    <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                        <span class="text-sm text-gray-700">{{ $d->carrera->nombre }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->dictamen === \App\Enums\Dictamen::Aprobatorio ? 'bg-green-50 text-green-700' : ($d->dictamen === \App\Enums\Dictamen::Negativo ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700') }}">{{ ucfirst($d->dictamen->value) }}</span>
                    </div>
                @endforeach
            </div>

            <h5 class="mb-3 text-sm font-semibold text-gray-800">Avance</h5>
            <ol class="space-y-3">
                @foreach ($enTramite->seguimientos as $s)
                    <li class="border-l-2 pl-4 {{ $s->estado === SE::Pendiente ? 'border-yellow-400' : ($s->estado === SE::Aprobado ? 'border-green-500' : 'border-red-500') }}">
                        <p class="text-sm font-semibold text-gray-800">{{ $s->etapa->label() }} <span class="font-normal text-gray-500">· {{ ucfirst($s->estado->value) }}</span></p>
                        <p class="text-xs text-gray-500">{{ $s->fecha_registro->format('d/m/Y') }}{{ $s->fecha_resolucion ? ' → '.$s->fecha_resolucion->format('d/m/Y') : '' }}</p>
                        @if ($s->comentario)<p class="text-xs text-gray-600">{{ $s->comentario }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Aviso de renovación --}}
    @if ($puedeRenovar)
        <div class="flex items-start gap-3 rounded-2xl border border-yellow-300 bg-yellow-50/60 p-5">
            <div class="text-xl">⏳</div>
            <div>
                <h4 class="text-sm font-bold text-gray-900">Tu convenio vence en {{ $diasRestantes }} días</h4>
                <p class="mt-1 text-xs text-gray-600">Para no suspender tus programas activos, envía la solicitud de renovación con el formulario de abajo.</p>
            </div>
        </div>
    @endif

    {{-- Formulario de solicitud / renovación --}}
    @if ($mostrarForm)
        <form method="POST" action="{{ route('empresa.convenio.solicitar') }}" class="{{ $card }} space-y-5">
            @csrf
            <div>
                <h4 class="text-base font-bold text-gray-900">{{ $puedeRenovar ? 'Solicitar renovación' : 'Solicitar convenio con el CUCSH' }}</h4>
                <p class="text-xs text-gray-500">Describe la colaboración propuesta. La Coordinación revisará tu expediente y lo turnará a las carreras.</p>
            </div>

            <div>
                <label class="{{ $label }}">Objetivos de la colaboración</label>
                <textarea name="objetivos" rows="3" required maxlength="3000" class="{{ $input }}">{{ old('objetivos') }}</textarea>
            </div>
            <div>
                <label class="{{ $label }}">Actividades que realizarían los practicantes</label>
                <textarea name="actividades_propuestas" rows="3" required maxlength="3000" class="{{ $input }}">{{ old('actividades_propuestas') }}</textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="{{ $label }}">Plazas estimadas por ciclo</label>
                    <input type="number" name="plazas_estimadas" value="{{ old('plazas_estimadas') }}" required min="1" max="500" class="{{ $input }}">
                </div>
                <div>
                    <label class="{{ $label }}">¿Cómo conociste el programa? <span class="text-xs font-normal text-gray-400">(opcional)</span></label>
                    <input type="text" name="medio_conocimiento" value="{{ old('medio_conocimiento') }}" maxlength="255" class="{{ $input }}">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Experiencia previa con practicantes <span class="text-xs font-normal text-gray-400">(opcional)</span></label>
                <textarea name="experiencia_previa" rows="2" maxlength="3000" class="{{ $input }}">{{ old('experiencia_previa') }}</textarea>
            </div>

            <div>
                <label class="{{ $label }}">Carreras de interés</label>
                <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                    @foreach ($carrerasPorDivision as $division => $lista)
                        <div>
                            <p class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-400">{{ $division }}</p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($lista as $c)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="carreras[]" value="{{ $c->id }}" class="rounded border-gray-300"
                                            @checked(in_array($c->id, (array) old('carreras', [])))>
                                        {{ $c->nombre }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button class="bg-brand-500 hover:bg-brand-600 rounded-lg px-5 py-2.5 text-sm font-medium text-white">
                    {{ $puedeRenovar ? 'Enviar renovación' : 'Enviar solicitud' }}
                </button>
            </div>
        </form>
    @endif

    {{-- Historial --}}
    @if ($historial->isNotEmpty())
        <div class="{{ $card }}">
            <h4 class="mb-3 text-base font-bold text-gray-900">Historial</h4>
            <div class="divide-y divide-gray-100">
                @foreach ($historial as $h)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $h->folio ?? 'Sin folio' }}</p>
                            <p class="text-xs text-gray-500">Recibido {{ $h->fecha_recepcion->format('d/m/Y') }}
                                @if ($h->fecha_fin_vigencia) · vigencia hasta {{ $h->fecha_fin_vigencia->format('d/m/Y') }} @endif</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $h->estado === \App\Enums\ConvenioEstado::Rechazado ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-600' }}">{{ $h->estado->label() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection