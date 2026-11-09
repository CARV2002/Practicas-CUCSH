@extends('layouts.app')

@section('content')
@php
    $input = 'focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700';
    $card = 'rounded-2xl border border-gray-200 bg-white p-6';
    $activo = $empresa->user?->activo ?? false;
@endphp

<x-common.page-breadcrumb pageTitle="Empresa · {{ $empresa->razon_social }}" />

<div class="space-y-6">
    <a href="{{ route('admin.lista-empresas') }}" class="text-sm text-gray-500 hover:text-gray-700">← Volver a empresas</a>

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
                <p class="text-sm text-gray-500">
                    <span class="font-mono">{{ $empresa->rfc }}</span> · {{ $empresa->tipo_organismo->label() }}
                </p>
                <p class="mt-1 text-sm text-gray-500">{{ $empresa->contacto_nombre }} · {{ $empresa->user?->email }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $activo ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                    {{ $activo ? 'Cuenta activa' : 'Cuenta inactiva' }}
                </span>
                @if ($empresa->user)
                    <form method="POST" action="{{ route('admin.empresas.activo', $empresa) }}"
                        onsubmit="return confirm('{{ $activo ? '¿Desactivar' : '¿Activar' }} la cuenta de esta empresa?')">
                        @csrf
                        <button class="rounded-lg border px-3 py-1.5 text-xs font-medium {{ $activo ? 'border-red-500 text-red-700' : 'border-green-500 text-green-700' }}">
                            {{ $activo ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Datos editables --}}
    <form method="POST" action="{{ route('admin.empresas.update', $empresa) }}" class="{{ $card }} space-y-5">
        @csrf
        @method('PUT')
        <h4 class="text-base font-bold text-gray-900">Datos de la empresa</h4>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="{{ $label }}">Razón social</label>
                <input type="text" name="razon_social" value="{{ old('razon_social', $empresa->razon_social) }}" required maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">RFC</label>
                <input type="text" name="rfc" value="{{ old('rfc', $empresa->rfc) }}" required maxlength="13" class="{{ $input }} uppercase">
            </div>
            <div>
                <label class="{{ $label }}">Tipo de organismo</label>
                <select name="tipo_organismo" required class="{{ $input }}">
                    @foreach (\App\Enums\TipoOrganismo::cases() as $t)
                        <option value="{{ $t->value }}" @selected(old('tipo_organismo', $empresa->tipo_organismo->value) === $t->value)>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">Domicilio fiscal</label>
                <input type="text" name="domicilio_fiscal" value="{{ old('domicilio_fiscal', $empresa->domicilio_fiscal) }}" maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Representante legal</label>
                <input type="text" name="representante_legal" value="{{ old('representante_legal', $empresa->representante_legal) }}" maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Contacto</label>
                <input type="text" name="contacto_nombre" value="{{ old('contacto_nombre', $empresa->contacto_nombre) }}" required maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Cargo</label>
                <input type="text" name="contacto_cargo" value="{{ old('contacto_cargo', $empresa->contacto_cargo) }}" maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Teléfono</label>
                <input type="text" name="contacto_telefono" value="{{ old('contacto_telefono', $empresa->contacto_telefono) }}" maxlength="20" class="{{ $input }}">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="{{ $label }}">Descripción</label>
                <textarea name="descripcion" rows="3" maxlength="3000" class="{{ $input }} h-auto py-2.5">{{ old('descripcion', $empresa->descripcion) }}</textarea>
            </div>
            <div>
                <label class="{{ $label }}">Proyectos principales</label>
                <textarea name="proyectos_principales" rows="3" maxlength="3000" class="{{ $input }} h-auto py-2.5">{{ old('proyectos_principales', $empresa->proyectos_principales) }}</textarea>
            </div>
        </div>

        <div class="flex justify-end">
            <button class="bg-brand-500 hover:bg-brand-600 rounded-lg px-5 py-2.5 text-sm font-medium text-white">Guardar cambios</button>
        </div>
    </form>

    {{-- Expediente --}}
    <div class="{{ $card }}">
        <div class="mb-4 flex items-center justify-between">
            <h4 class="text-base font-bold text-gray-900">Expediente</h4>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $expedienteCompleto ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700' }}">
                {{ $expedienteCompleto ? 'Completo y validado' : 'Incompleto' }}
            </span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach ($colecciones as $col)
                @php
                    $media = $empresa->documentoVigente($col);
                    $st = $media?->getCustomProperty('estado') ?? ($media ? 'pendiente' : null);
                @endphp
                <div class="flex items-center justify-between gap-3 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ \Illuminate\Support\Str::headline($col) }}</p>
                        <p class="text-xs text-gray-500">{{ $media?->file_name ?? 'Sin archivo' }}
                            @if ($media?->getCustomProperty('motivo')) · <span class="text-red-600">{{ $media->getCustomProperty('motivo') }}</span> @endif
                        </p>
                    </div>
                    @if ($st)
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $st === 'validada' ? 'bg-green-50 text-green-700' : ($st === 'rechazada' ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700') }}">{{ ucfirst($st) }}</span>
                    @else
                        <span class="text-xs text-gray-400">Pendiente de subir</span>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-gray-400">La validación de documentos se hace desde el convenio en trámite.</p>
    </div>

    {{-- Convenios --}}
    <div class="{{ $card }}">
        <h4 class="mb-4 text-base font-bold text-gray-900">Convenios</h4>
        <div class="divide-y divide-gray-100">
            @forelse ($empresa->convenios->sortByDesc('id') as $c)
                <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">
                            {{ $c->folio ?? 'Sin folio' }}
                            <span class="ml-2 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">{{ $c->estado->label() }}</span>
                        </p>
                        <p class="text-xs text-gray-500">
                            Recibido {{ $c->fecha_recepcion->format('d/m/Y') }}
                            @if ($c->fecha_fin_vigencia) · vigencia {{ $c->fecha_inicio_vigencia->format('d/m/Y') }} – {{ $c->fecha_fin_vigencia->format('d/m/Y') }} @endif
                            · Carreras aprobadas: {{ $c->dictamenes->where('dictamen', \App\Enums\Dictamen::Aprobatorio)->map(fn ($d) => $d->carrera->carrera_clave)->join(', ') ?: '—' }}
                        </p>
                    </div>
                    <a href="{{ route('admin.convenios.show', $c) }}" class="text-brand-600 hover:text-brand-700 text-xs font-semibold">Gestionar →</a>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-400">Esta empresa aún no ha solicitado convenio.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection