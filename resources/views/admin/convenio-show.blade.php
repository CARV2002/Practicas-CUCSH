@extends('layouts.app')

@section('content')
@php
    use App\Enums\ConvenioEstado as E;
    use App\Enums\EtapaConvenio as Et;
    use App\Enums\Dictamen;

    $estado = $convenio->estado;
    $etapa = $etapaAbierta?->etapa;
    $input = 'focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-3 focus:outline-hidden';
    $btn = 'bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2 text-sm font-medium text-white';
    $card = 'rounded-2xl border border-gray-200 bg-white p-6';
@endphp

<x-common.page-breadcrumb pageTitle="Convenio · {{ $empresa->razon_social }}" />

<div class="space-y-6">
    <a href="{{ route('admin.lista-convenios') }}" class="text-sm text-gray-500 hover:text-gray-700">← Volver a la bandeja</a>

    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Resumen --}}
    <div class="{{ $card }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900">{{ $empresa->razon_social }}</h3>
                <p class="text-sm text-gray-500">{{ $empresa->rfc }} · {{ $empresa->tipo_organismo->label() }} · Contacto: {{ $empresa->contacto_nombre }} ({{ $empresa->user?->email }})</p>
            </div>
            <div class="text-right">
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $estado->label() }}</span>
                @if ($convenio->folio)
                    <p class="mt-2 text-xs text-gray-500">Folio {{ $convenio->folio }} · {{ $convenio->fecha_inicio_vigencia?->format('d/m/Y') }} – {{ $convenio->fecha_fin_vigencia?->format('d/m/Y') }}</p>
                @endif
            </div>
        </div>
        <dl class="mt-5 grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
            <div><dt class="text-xs font-bold uppercase text-gray-400">Objetivos</dt><dd class="mt-1 text-gray-700">{{ $convenio->objetivos }}</dd></div>
            <div><dt class="text-xs font-bold uppercase text-gray-400">Actividades propuestas</dt><dd class="mt-1 text-gray-700">{{ $convenio->actividades_propuestas }}</dd></div>
            <div><dt class="text-xs font-bold uppercase text-gray-400">Plazas estimadas</dt><dd class="mt-1 text-gray-700">{{ $convenio->plazas_estimadas }}</dd></div>
        </dl>
        @if ($etapaAbierta)
            <p class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                <strong>Etapa abierta:</strong> {{ $etapa->label() }} · {{ $etapa->responsable() }} · {{ $etapaAbierta->diasHabiles() }} día(s) hábil(es)
                @if ($etapaAbierta->excedeEspera()) <span class="font-bold text-red-600">· excede el máximo documentado</span> @endif
                @if ($etapaAbierta->comentario) <br><span class="text-gray-500">{{ $etapaAbierta->comentario }}</span> @endif
            </p>
        @endif
    </div>

    {{-- Expediente de la empresa --}}
    <div class="{{ $card }}">
        <h4 class="mb-4 text-base font-bold text-gray-900">Expediente de la empresa</h4>
        <div class="divide-y divide-gray-100">
            @foreach ($colecciones as $col)
                @php
                    $media = $empresa->documentoVigente($col);
                    $st = $media?->getCustomProperty('estado') ?? ($media ? 'pendiente' : null);
                @endphp
                <div class="flex flex-col gap-3 py-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ \Illuminate\Support\Str::headline($col) }}</p>
                        @if ($media)
                            <a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $media]) }}" class="text-brand-600 text-xs">{{ $media->file_name }}</a>
                            @if ($media->getCustomProperty('motivo')) <span class="text-xs text-red-600">· {{ $media->getCustomProperty('motivo') }}</span> @endif
                        @else
                            <span class="text-xs text-gray-400">Sin archivo</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($st)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $st === 'validada' ? 'bg-green-50 text-green-700' : ($st === 'rechazada' ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700') }}">{{ ucfirst($st) }}</span>
                            @if ($estado === E::Solicitado)
                                <form method="POST" action="{{ route('admin.convenios.documentos.resolver', [$convenio, $media]) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="motivo" placeholder="Motivo (si rechaza)" class="{{ $input }} w-44">
                                    <button name="estado" value="validada" class="rounded-lg border border-green-500 px-3 py-1.5 text-xs font-medium text-green-700">Validar</button>
                                    <button name="estado" value="rechazada" class="rounded-lg border border-red-500 px-3 py-1.5 text-xs font-medium text-red-700">Rechazar</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($estado === E::Solicitado)
            <form method="POST" action="{{ route('admin.convenios.validacion-academica', $convenio) }}" class="mt-4">
                @csrf
                <button class="{{ $btn }}">Turnar a las carreras</button>
            </form>
        @endif
    </div>

    {{-- Dictámenes por carrera --}}
    <div class="{{ $card }}">
        <h4 class="mb-4 text-base font-bold text-gray-900">Dictámenes de pertinencia por carrera</h4>
        <div class="divide-y divide-gray-100">
            @foreach ($convenio->dictamenes as $d)
                <div class="py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $d->carrera->nombre }}</p>
                            @if ($d->observaciones) <p class="text-xs text-gray-500">{{ $d->observaciones }}</p> @endif
                            @if ($d->fecha_dictamen) <p class="text-xs text-gray-400">{{ $d->fecha_dictamen->format('d/m/Y') }}</p> @endif
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $d->dictamen === Dictamen::Aprobatorio ? 'bg-green-50 text-green-700' : ($d->dictamen === Dictamen::Negativo ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700') }}">{{ ucfirst($d->dictamen->value) }}</span>
                    </div>
                    @if ($estado === E::EnValidacionAcademica && $d->dictamen === Dictamen::Pendiente)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.dictamenes.registrar', [$convenio, $d]) }}" class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-5">
                            @csrf
                            <select name="dictamen" required class="{{ $input }}">
                                <option value="aprobatorio">Aprobatorio</option>
                                <option value="negativo">Negativo</option>
                            </select>
                            <input type="text" name="observaciones" placeholder="Observaciones (obligatorio si es negativo)" class="{{ $input }} md:col-span-2">
                            <input type="file" name="documento" accept="application/pdf" class="{{ $input }}">
                            <button class="{{ $btn }}">Registrar</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Borrador y Jurídica --}}
    @if (in_array($estado, [E::EnValidacionAcademica, E::EnRevisionJuridica], true) || $borradores->isNotEmpty())
        <div class="{{ $card }}">
            <h4 class="mb-4 text-base font-bold text-gray-900">Borrador y Unidad Jurídica</h4>

            @if ($borradores->isNotEmpty())
                <ul class="mb-4 space-y-1 text-sm">
                    @foreach ($borradores as $i => $b)
                        <li><a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $b]) }}" class="text-brand-600">v{{ $i + 1 }} · {{ $b->file_name }}</a> <span class="text-xs text-gray-400">{{ $b->created_at->format('d/m/Y H:i') }}</span></li>
                    @endforeach
                </ul>
            @endif

            @if ($etapa === Et::RedaccionBorrador)
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.borrador', $convenio) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="borrador" accept=".pdf,.docx" required class="{{ $input }}">
                        <button class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Adjuntar</button>
                    </form>
                    <form method="POST" action="{{ route('admin.convenios.juridica.turnar', $convenio) }}">
                        @csrf
                        <button class="{{ $btn }}">Turnar a Jurídica</button>
                    </form>
                </div>
            @endif

            @if ($estado === E::EnRevisionJuridica && $etapa === Et::DictamenJuridico)
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.juridica.dictamen', $convenio) }}" class="grid grid-cols-1 gap-2 md:grid-cols-5">
                    @csrf
                    <select name="resultado" required class="{{ $input }}">
                        <option value="aprobado">Aprobado</option>
                        <option value="observado">Observado</option>
                        <option value="rechazado">Rechazado</option>
                    </select>
                    <input type="text" name="comentario" placeholder="Comentario (obligatorio si no aprueba)" class="{{ $input }} md:col-span-2">
                    <input type="file" name="documento" accept="application/pdf" class="{{ $input }}">
                    <button class="{{ $btn }}">Registrar dictamen</button>
                </form>
            @endif
        </div>
    @endif

    {{-- Firmas y formalización --}}
    @if ($estado === E::EnFirmas)
        <div class="{{ $card }}">
            <h4 class="mb-4 text-base font-bold text-gray-900">Firmas y formalización</h4>

            @if (in_array($etapa, [Et::FirmaEmpresa, Et::FirmaRectoria], true))
                <form method="POST" action="{{ route('admin.convenios.firmas', $convenio) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="hidden" name="etapa" value="{{ $etapa->value }}">
                    <input type="text" name="comentario" placeholder="Comentario (opcional)" class="{{ $input }} max-w-sm">
                    <button class="{{ $btn }}">Registrar: {{ $etapa->label() }}</button>
                </form>
            @elseif ($etapa === Et::ArchivoDistribucion)
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.formalizar', $convenio) }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    @csrf
                    <div><label class="mb-1 block text-xs font-medium text-gray-600">Folio</label><input type="text" name="folio" required maxlength="30" class="{{ $input }}"></div>
                    <div><label class="mb-1 block text-xs font-medium text-gray-600">Inicio de vigencia</label><input type="date" name="fecha_inicio_vigencia" required class="{{ $input }}"></div>
                    <div><label class="mb-1 block text-xs font-medium text-gray-600">Fin de vigencia</label><input type="date" name="fecha_fin_vigencia" required class="{{ $input }}"></div>
                    <div><label class="mb-1 block text-xs font-medium text-gray-600">Escaneo firmado (PDF)</label><input type="file" name="convenio_firmado" accept="application/pdf" class="{{ $input }}"></div>
                    <div class="md:col-span-3"><input type="text" name="comentario" placeholder="Comentario (opcional)" class="{{ $input }}"></div>
                    <button class="{{ $btn }}">Formalizar convenio</button>
                </form>
            @endif
        </div>
    @endif

    @if ($firmado)
        <div class="{{ $card }}">
            <a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $firmado]) }}" class="text-brand-600 text-sm font-semibold">Descargar convenio firmado ({{ $firmado->file_name }})</a>
        </div>
    @endif

    {{-- Rechazar --}}
    @unless ($estado->esFinal())
        <details class="{{ $card }}">
            <summary class="cursor-pointer text-sm font-semibold text-red-600">Rechazar convenio</summary>
            <form method="POST" action="{{ route('admin.convenios.rechazar', $convenio) }}" class="mt-4 flex flex-wrap items-center gap-2" onsubmit="return confirm('¿Cerrar el trámite como rechazado?')">
                @csrf
                <input type="text" name="motivo" required maxlength="1000" placeholder="Motivo" class="{{ $input }} max-w-lg">
                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Rechazar</button>
            </form>
        </details>
    @endunless

    {{-- Bitácora --}}
    <div class="{{ $card }}">
        <h4 class="mb-4 text-base font-bold text-gray-900">Bitácora</h4>
        <ol class="space-y-3">
            @foreach ($convenio->seguimientos as $s)
                <li class="flex gap-3 border-l-2 pl-4 {{ $s->estado->value === 'pendiente' ? 'border-yellow-400' : ($s->estado->value === 'aprobado' ? 'border-green-500' : 'border-red-500') }}">
                    <div class="text-sm">
                        <p class="font-semibold text-gray-800">{{ $s->etapa->label() }} <span class="font-normal text-gray-500">· {{ ucfirst($s->estado->value) }}</span></p>
                        <p class="text-xs text-gray-500">
                            {{ $s->fecha_registro->format('d/m/Y') }}{{ $s->fecha_resolucion ? ' → '.$s->fecha_resolucion->format('d/m/Y') : '' }}
                            · {{ $s->diasHabiles() }} día(s) hábil(es)
                            @if ($s->registrador?->user) · {{ $s->registrador->user->name }} @endif
                        </p>
                        @if ($s->comentario) <p class="text-xs text-gray-600">{{ $s->comentario }}</p> @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</div>
@endsection