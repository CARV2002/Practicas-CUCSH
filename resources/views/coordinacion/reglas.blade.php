@extends('layouts.app')

@section('content')
@php
    $input = 'campo h-10';
    $label = 'mb-1 block text-xs font-medium text-gray-600';
@endphp

<div class="space-y-6">
    <x-pagina.encabezado titulo="Reglas de mi carrera"
        descripcion="Horas, créditos y reportes que pide tu carrera, y el contacto que ven tus alumnos. Los cambios aplican a prácticas nuevas: las activas conservan sus reglas." />

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    @forelse ($carreras as $carrera)
        <form method="POST" action="{{ route('coordinacion.reglas.update', $carrera) }}"
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs"
            x-data="{ correo: @js($carrera->coordinacion_correo ?? ''), tel: @js($carrera->coordinacion_telefono ?? '') }">
            @csrf
            @method('PUT')
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="rounded-lg bg-udg-900 px-2 py-1 text-xs font-bold text-white">{{ $carrera->carrera_clave }}</span>
                    <div>
                        <h2 class="font-display text-base font-bold text-gray-900">{{ $carrera->nombre }}</h2>
                        <p class="text-xs text-gray-500">{{ $carrera->division->nombre }}</p>
                    </div>
                </div>
                <button type="submit" class="boton boton-primario">Guardar cambios</button>
            </div>

            <div class="grid grid-cols-1 gap-6 p-5 sm:p-6 xl:grid-cols-3">
                <fieldset class="space-y-4 xl:col-span-2">
                    <legend class="mb-1 text-sm font-semibold text-gray-900">Requisitos académicos</legend>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="horas-{{ $carrera->id }}" class="etiqueta">Horas requeridas</label>
                            <input id="horas-{{ $carrera->id }}" type="number" name="horas_requeridas" min="1" max="2000" required value="{{ $carrera->horas_requeridas }}" class="campo h-11 tabular-nums">
                        </div>
                        <div>
                            <label for="pct-{{ $carrera->id }}" class="etiqueta">% mínimo de créditos</label>
                            <input id="pct-{{ $carrera->id }}" type="number" name="porcentaje_creditos_minimo" min="0" max="100" step="0.01" required value="{{ $carrera->porcentaje_creditos_minimo }}" class="campo h-11 tabular-nums">
                            <p class="ayuda">Para poder postularse.</p>
                        </div>
                        <div>
                            <label for="cred-{{ $carrera->id }}" class="etiqueta">Créditos que otorga</label>
                            <input id="cred-{{ $carrera->id }}" type="number" name="creditos_otorgados" min="0" max="50" required value="{{ $carrera->creditos_otorgados }}" class="campo h-11 tabular-nums">
                            <p class="ayuda">Al acreditar la práctica.</p>
                        </div>
                    </div>
                    <div>
                        <p class="etiqueta">Periodicidad de reportes</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            @foreach ($esquemas as $e)
                                <label class="group flex cursor-pointer items-start gap-2.5 rounded-xl border border-gray-200 p-3 text-sm transition hover:border-udg-200 has-[:checked]:border-udg-800 has-[:checked]:bg-udg-50 has-[:checked]:ring-1 has-[:checked]:ring-udg-800">
                                    <input type="radio" name="esquema_reportes" value="{{ $e->value }}" required @checked($carrera->esquema_reportes === $e) class="mt-0.5 size-4 border-gray-300 text-udg-800 focus:ring-real-500/30">
                                    <span class="text-gray-800">{{ $e->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="mb-1 text-sm font-semibold text-gray-900">Contacto que ven tus alumnos</legend>
                    <div>
                        <label for="correo-{{ $carrera->id }}" class="etiqueta">Correo de la coordinación</label>
                        <input id="correo-{{ $carrera->id }}" type="email" name="coordinacion_correo" maxlength="255" x-model="correo" placeholder="coordinacion@csh.udg.mx" class="campo h-11">
                    </div>
                    <div>
                        <label for="tel-{{ $carrera->id }}" class="etiqueta">Teléfono o extensión</label>
                        <input id="tel-{{ $carrera->id }}" type="text" name="coordinacion_telefono" maxlength="40" x-model="tel" placeholder="(33) 3819-3300 ext. 00000" class="campo h-11">
                    </div>
                    {{-- Vista previa de «Tu coordinación» --}}
                    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4" aria-live="polite">
                        <p class="text-xs font-medium text-gray-500">Así lo verán en «Tu coordinación»</p>
                        <p class="mt-1.5 text-sm font-semibold text-gray-900">Coordinación de {{ $carrera->nombre }}</p>
                        <p class="mt-1 break-all text-sm" :class="correo ? 'text-gray-700' : 'italic text-amber-700'" x-text="correo || 'Sin correo: tus alumnos no sabrán a quién escribir'">{{ $carrera->coordinacion_correo ?: 'Sin correo: tus alumnos no sabrán a quién escribir' }}</p>
                        <p class="text-sm" :class="tel ? 'text-gray-700' : 'italic text-gray-400'" x-text="tel || 'Sin teléfono'">{{ $carrera->coordinacion_telefono ?: 'Sin teléfono' }}</p>
                    </div>
                </fieldset>
            </div>
        </form>
    @empty
        <x-dashboard.vacio icono="academico" titulo="No tienes carreras asignadas" texto="Pide al administrador técnico que asigne tu carrera o división a tu cuenta." class="rounded-2xl border border-gray-200 bg-white" />
    @endforelse
</div>
@endsection
