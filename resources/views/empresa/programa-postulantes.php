@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Postulantes · {{ $programa->titulo }}" />

<div class="space-y-6">
    <a href="{{ route('empresa.lista-programas') }}" class="text-sm text-gray-500 hover:text-gray-700">← Volver a mis programas</a>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-lg font-bold text-gray-900">{{ $programa->titulo }}</h3>
            <p class="text-sm text-gray-500">{{ $programa->carrera->nombre }} · ciclo {{ $programa->ciclo }}</p>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($postulaciones as $po)
                <div class="flex flex-wrap items-center justify-between gap-2 px-6 py-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $po->alumno->user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $po->alumno->num_control }} · {{ $po->alumno->carrera->carrera_clave }}</p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">{{ $po->estado->label() }}</span>
                </div>
            @empty
                <p class="px-6 py-10 text-center text-sm text-gray-400">Aún no hay alumnos con carta de presentación en este programa.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection