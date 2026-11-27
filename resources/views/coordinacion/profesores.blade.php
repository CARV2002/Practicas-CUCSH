@extends('layouts.app')

@section('content')
@php
    $vacio = ['id' => null, 'nombre' => '', 'email' => '', 'telefono' => '', 'extension' => '', 'division_id' => $divisiones->count() === 1 ? $divisiones->first()->id : ''];
    $abrir = $errors->hasAny(['nombre', 'email', 'telefono', 'extension', 'division_id']);
    // La coordinación pertenece a una sola división: el profesor queda en ella sin preguntar
    $variasDivisiones = $divisiones->count() > 1;
@endphp

<div class="space-y-6" x-data="profesores(@js([
    'abierto' => $abrir,
    'inicial' => $abrir ? ['id' => old('profesor_id'), 'nombre' => old('nombre', ''), 'email' => old('email', ''), 'telefono' => old('telefono', ''), 'extension' => old('extension', ''), 'division_id' => old('division_id', $vacio['division_id'])] : $vacio,
    'vacio' => $vacio,
    'urlStore' => route('coordinacion.profesores.store'),
    'urlUpdate' => route('coordinacion.profesores.update', 0),
]))">
    <x-pagina.encabezado titulo="Profesores supervisores"
        descripcion="Catálogo de tu coordinación. Los profesores no entran al sistema: aquí los registras para asignarlos a cada práctica (quedan en la división de tu coordinación).">
        <x-slot:acciones>
            <button type="button" x-on:click="nuevo()" class="boton boton-primario"><x-dashboard.icono nombre="mas" clase="size-4" /> Agregar profesor</button>
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    <x-tabla.marco titulo="Catálogo" :total="$profesores->total()">
        <x-slot:herramientas>
            <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                <div class="relative flex-1 sm:max-w-sm">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar profesor</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o correo" class="campo-filtro pl-9 sm:w-full">
                </div>
                <label for="estado" class="sr-only">Estado</label>
                <select id="estado" name="estado" class="campo-filtro" onchange="this.form.requestSubmit()">
                    <option value="">Todos</option>
                    <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
                    <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
                </select>
                <button class="boton boton-secundario">Buscar</button>
            </form>
        </x-slot:herramientas>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th">Profesor</th>
                    @if ($variasDivisiones)<th class="tabla-th">División</th>@endif
                    <th class="tabla-th">Prácticas en curso</th>
                    <th class="tabla-th">Estado</th>
                    <th class="tabla-th text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($profesores as $pr)
                    <tr class="tabla-fila">
                        <td class="px-6 py-3.5">
                            <p class="text-sm font-semibold text-gray-900">{{ $pr->nombre }}</p>
                            <p class="text-xs text-gray-500">{{ $pr->email ?: 'Sin correo' }}{{ $pr->telefono ? ' · ' . $pr->telefono : '' }}</p>
                        </td>
                        @if ($variasDivisiones)<td class="px-4 py-3.5 text-sm text-gray-700">{{ $pr->division->nombre }}</td>@endif
                        <td class="px-4 py-3.5 text-sm font-semibold tabular-nums text-gray-900">{{ $pr->practicas_activas_count }}</td>
                        <td class="px-4 py-3.5"><x-ui.estatus :familia="$pr->activo ? 'menta' : 'piedra'" :texto="$pr->activo ? 'Activo' : 'Inactivo'" /></td>
                        <td class="acciones whitespace-nowrap px-6 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" class="boton boton-chico boton-secundario"
                                    x-on:click="editar(@js(['id' => $pr->id, 'nombre' => $pr->nombre, 'email' => $pr->email ?? '', 'telefono' => \App\Support\Formatos::separarTelefono($pr->telefono)[0], 'extension' => \App\Support\Formatos::separarTelefono($pr->telefono)[1], 'division_id' => $pr->division_id]))">
                                    <x-dashboard.icono nombre="editar" clase="size-3.5" /> Editar
                                </button>
                                <x-ui.boton-icono :accion="route('coordinacion.profesores.activo', $pr)" :icono="$pr->activo ? 'apagar' : 'encender'"
                                    :etiqueta="$pr->activo ? 'Desactivar' : 'Activar'" :tono="$pr->activo ? 'peligro' : 'exito'"
                                    :confirmar="$pr->activo ? '¿Desactivar a ' . $pr->nombre . '? Ya no aparecerá para nuevas asignaciones; sus prácticas actuales no cambian.' : null" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $variasDivisiones ? 5 : 4 }}" class="px-6 py-6"><x-dashboard.vacio icono="academico" titulo="Aún no hay profesores" texto="Agrega a los profesores que supervisarán las prácticas de tus alumnos." /></td></tr>
                @endforelse
            </tbody>
        </table>
        <x-slot:pie>{{ $profesores->links() }}</x-slot:pie>
    </x-tabla.marco>

    {{-- Modal: alta / edición --}}
    <div x-show="modal" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="cerrar()"
        class="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto p-4 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-profesor">
        <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="cerrar()"></div>
        <form method="POST" :action="accion" x-on:click.stop class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-theme-xl">
            @csrf
            <template x-if="p.id"><input type="hidden" name="_method" value="PUT"></template>
            <input type="hidden" name="profesor_id" :value="p.id">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 id="titulo-profesor" class="font-display text-lg font-bold text-gray-900" x-text="p.id ? 'Editar profesor' : 'Agregar profesor'"></h3>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div><label class="etiqueta" for="pr-nombre">Nombre completo <span class="text-rose-500">*</span></label>
                    <input id="pr-nombre" type="text" data-solo="letras" name="nombre" x-model="p.nombre" required minlength="5" maxlength="80" placeholder="Ej. Dra. Nombre Apellido Apellido" class="campo h-11"></div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="etiqueta" for="pr-correo">Correo</label><input id="pr-correo" type="email" name="email" x-model="p.email" maxlength="255" placeholder="usuario@dominio.com" class="campo h-11"></div>
                    <div class="grid grid-cols-[1fr_7rem] gap-2">
                        <div><label class="etiqueta" for="pr-tel">Teléfono</label><input id="pr-tel" type="tel" inputmode="numeric" data-solo="digitos" data-contador="dígitos" minlength="10" maxlength="10" pattern="{{ \App\Support\Formatos::TELEFONO_HTML }}" data-msg-formato="Escribe un teléfono real de 10 números (ej. 3312345678)." name="telefono" x-model="p.telefono" placeholder="Ej. 3312345678" class="campo h-11"></div>
                        <div><label class="etiqueta" for="pr-ext">Extensión</label><input id="pr-ext" type="text" inputmode="numeric" data-solo="digitos" maxlength="6" pattern="\d{1,6}" data-msg-formato="La extensión solo lleva números (hasta 6)." name="extension" x-model="p.extension" placeholder="1234" class="campo h-11"></div>
                    </div>
                </div>
                @if ($variasDivisiones)
                <div><label class="etiqueta" for="pr-div">División <span class="text-rose-500">*</span></label>
                    <select id="pr-div" name="division_id" x-model="p.division_id" required class="campo h-11">
                        <option value="">Selecciona…</option>
                        @foreach ($divisiones as $dv)
                            <option value="{{ $dv->id }}">{{ $dv->nombre }}</option>
                        @endforeach
                    </select>
                    <p class="ayuda">Solo puede supervisar alumnos de carreras de esta división.</p>
                </div>
                @else
                    <input type="hidden" name="division_id" :value="p.division_id">
                    <p class="ayuda">Queda registrado en la {{ $divisiones->first()?->nombre }}, la división de tu coordinación.</p>
                @endif
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                <button type="button" x-on:click="cerrar()" class="boton boton-secundario">Cancelar</button>
                <button type="submit" class="boton boton-primario">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
