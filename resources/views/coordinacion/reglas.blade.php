@extends('layouts.app')

@section('content')
@php
    $input = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
    $label = 'mb-1 block text-xs font-medium text-gray-600';
@endphp

<x-common.page-breadcrumb pageTitle="Reglas de mi carrera" />

<div class="space-y-6">

    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-sm text-gray-500">
        Las horas y créditos se congelan en cada práctica al activarla: los cambios solo aplican a prácticas nuevas.
        El correo y teléfono aparecen a tus alumnos en «Tu coordinación».
    </p>

    @forelse ($carreras as $carrera)
        <form method="POST" action="{{ route('coordinacion.reglas.update', $carrera) }}" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')
            <div>
                <h3 class="text-base font-semibold text-gray-800">{{ $carrera->carrera_clave }} · {{ $carrera->nombre }}</h3>
                <p class="text-xs text-gray-500">{{ $carrera->division->nombre }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="horas-{{ $carrera->id }}" class="{{ $label }}">Horas requeridas</label>
                    <input id="horas-{{ $carrera->id }}" type="number" name="horas_requeridas" min="1" max="2000" required value="{{ $carrera->horas_requeridas }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="pct-{{ $carrera->id }}" class="{{ $label }}">% mínimo de créditos</label>
                    <input id="pct-{{ $carrera->id }}" type="number" name="porcentaje_creditos_minimo" min="0" max="100" step="0.01" required value="{{ $carrera->porcentaje_creditos_minimo }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="cred-{{ $carrera->id }}" class="{{ $label }}">Créditos que otorga</label>
                    <input id="cred-{{ $carrera->id }}" type="number" name="creditos_otorgados" min="0" max="50" required value="{{ $carrera->creditos_otorgados }}" class="{{ $input }}">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="esq-{{ $carrera->id }}" class="{{ $label }}">Periodicidad de reportes</label>
                    <select id="esq-{{ $carrera->id }}" name="esquema_reportes" required class="{{ $input }}">
                        @foreach ($esquemas as $e)
                            <option value="{{ $e->value }}" @selected($carrera->esquema_reportes === $e)>{{ $e->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="correo-{{ $carrera->id }}" class="{{ $label }}">Correo de la coordinación</label>
                    <input id="correo-{{ $carrera->id }}" type="email" name="coordinacion_correo" maxlength="255" value="{{ $carrera->coordinacion_correo }}" placeholder="coordinacion@csh.udg.mx" class="{{ $input }}">
                </div>
                <div>
                    <label for="tel-{{ $carrera->id }}" class="{{ $label }}">Teléfono / extensión</label>
                    <input id="tel-{{ $carrera->id }}" type="text" name="coordinacion_telefono" maxlength="40" value="{{ $carrera->coordinacion_telefono }}" placeholder="(33) 3819-3300 ext. 00000" class="{{ $input }}">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2 text-sm font-medium text-white">Guardar</button>
            </div>
        </form>
    @empty
        <p class="rounded-2xl border border-gray-200 bg-white px-6 py-8 text-center text-sm text-gray-400">No tienes carreras asignadas.</p>
    @endforelse
</div>
@endsection
