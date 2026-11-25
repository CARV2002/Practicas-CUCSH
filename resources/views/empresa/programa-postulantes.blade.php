@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-pagina.encabezado :titulo="$programa->titulo" :volver="route('empresa.lista-programas')" volver-texto="Mis vacantes"
            :descripcion="$programa->carrera->nombre . ' · ciclo ' . $programa->ciclo">
            <x-slot:acciones><x-ui.estatus :estado="$programa->estado" tamano="normal" /></x-slot:acciones>
        </x-pagina.encabezado>

        {{-- La carta de aceptación va en la papelería de la organización: estos datos evitan que la coordinación la rechace --}}
        <x-ui.requisitos-carta tipo="aceptacion" para="empresa" :horas="$programa->carrera->horas_requeridas" class="bg-white shadow-theme-xs" />

        <x-tabla.marco titulo="Alumnos con carta de presentación" descripcion="Validados por la coordinación de su carrera. Si aceptas a un alumno, entrégale la carta de aceptación sellada." :total="$postulaciones->count()">
            <table class="tabla">
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Alumno</th>
                        <th class="tabla-th">Carrera</th>
                        <th class="tabla-th">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($postulaciones as $po)
                        <tr class="tabla-fila">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <x-ui.user-avatar :user="$po->alumno->user" size="size-9 shrink-0 text-xs ring-2 ring-white" />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $po->alumno->user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $po->alumno->num_control }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-md bg-udg-50 px-1.5 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100">{{ $po->alumno->carrera->carrera_clave }}</span>
                                <p class="mt-1 text-xs text-gray-500">{{ $po->alumno->carrera->nombre }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><x-ui.estatus :estado="$po->estado" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-4"><x-dashboard.vacio icono="usuarios" titulo="Aún no hay alumnos con carta de presentación" texto="Cuando la coordinación emita la carta de un alumno para esta vacante, aparecerá aquí." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-tabla.marco>
    </div>
@endsection
