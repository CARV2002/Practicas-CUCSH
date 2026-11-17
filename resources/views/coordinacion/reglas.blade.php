@extends('layouts.app')

@section('content')
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

    <p class="text-sm text-gray-500">Las horas y créditos se congelan en cada práctica al activarla: los cambios solo aplican a prácticas nuevas.</p>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Carrera</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Horas requeridas</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">% mínimo de créditos</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Créditos que otorga</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($carreras as $carrera)
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <p class="text-sm font-semibold text-gray-800">{{ $carrera->carrera_clave }} · {{ $carrera->nombre }}</p>
                                <p class="text-xs text-gray-500">{{ $carrera->division->nombre }}</p>
                            </td>
                            <td colspan="4" class="px-4 py-3">
                                <form method="POST" action="{{ route('coordinacion.reglas.update', $carrera) }}" class="flex flex-wrap items-center gap-3">
                                    @csrf
                                    @method('PUT')
                                    <input type="number" name="horas_requeridas" min="1" max="2000" required value="{{ $carrera->horas_requeridas }}"
                                        aria-label="Horas requeridas de {{ $carrera->carrera_clave }}"
                                        class="h-10 w-28 rounded-lg border border-gray-300 px-3 text-sm">
                                    <input type="number" name="porcentaje_creditos_minimo" min="0" max="100" step="0.01" required value="{{ $carrera->porcentaje_creditos_minimo }}"
                                        aria-label="Porcentaje mínimo de créditos de {{ $carrera->carrera_clave }}"
                                        class="h-10 w-28 rounded-lg border border-gray-300 px-3 text-sm">
                                    <input type="number" name="creditos_otorgados" min="0" max="50" required value="{{ $carrera->creditos_otorgados }}"
                                        aria-label="Créditos que otorga {{ $carrera->carrera_clave }}"
                                        class="h-10 w-28 rounded-lg border border-gray-300 px-3 text-sm">
                                    <button type="submit" class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2 text-sm font-medium text-white">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">No tienes carreras asignadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
