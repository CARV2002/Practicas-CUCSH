@extends('layouts.app')

@section('content')
@php
    $familiaTipo = fn ($t) => match ($t?->value) { 'publico' => 'cielo', 'privado' => 'lavanda', 'sociedad_civil' => 'menta', default => 'gris' };
@endphp

<div class="space-y-6">
    <x-pagina.encabezado titulo="Empresas" descripcion="Organizaciones registradas: su expediente, su cuenta y el estado de su convenio." />

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif

    <x-tabla.marco titulo="Empresas e instituciones" :total="$empresas->total()">
        <x-slot:herramientas>
            <x-tabla.filtros-estado parametro="convenio" :actual="request('convenio', '')" etiqueta="Filtrar por convenio" :opciones="[
                ['valor' => '', 'texto' => 'Todas', 'conteo' => $conteos['todas']],
                ['valor' => 'vigente', 'texto' => 'Con convenio vigente', 'conteo' => $conteos['vigente'], 'familia' => 'menta'],
                ['valor' => 'sin', 'texto' => 'Sin convenio vigente', 'conteo' => $conteos['sin'], 'familia' => 'gris'],
            ]" />
            <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                @if (request('convenio'))<input type="hidden" name="convenio" value="{{ request('convenio') }}">@endif
                <div class="relative flex-1 sm:max-w-sm">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar empresa</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Razón social, RFC o contacto" class="campo-filtro pl-9 sm:w-full">
                </div>
                <label for="tipo" class="sr-only">Tipo de organismo</label>
                <select id="tipo" name="tipo" class="campo-filtro" onchange="this.form.requestSubmit()">
                    <option value="">Todos los tipos</option>
                    @foreach ($tipos as $t)
                        <option value="{{ $t->value }}" @selected(request('tipo') === $t->value)>{{ $t->label() }}</option>
                    @endforeach
                </select>
                <button class="boton boton-secundario">Buscar</button>
                @if (request()->hasAny(['q', 'tipo', 'convenio']))
                    <a href="{{ route('admin.lista-empresas') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                @endif
            </form>
        </x-slot:herramientas>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th px-6">Organización</th>
                    <th class="tabla-th">Contacto</th>
                    <th class="tabla-th">Convenio</th>
                    <th class="tabla-th">Cuenta</th>
                    <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($empresas as $empresa)
                    @php
                        $vigente = $empresa->convenios->first(fn ($c) => $c->estaVigente());
                        $ultimo = $empresa->convenios->sortByDesc('id')->first();
                        $activo = $empresa->user?->activo ?? false;
                    @endphp
                    <tr class="tabla-fila">
                        <td class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-udg-50 font-display text-sm font-bold text-udg-900 ring-1 ring-inset ring-udg-100" aria-hidden="true">{{ mb_strtoupper(mb_substr($empresa->razon_social, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.empresas.show', $empresa) }}" class="block font-semibold text-gray-900 hover:text-udg-900 hover:underline underline-offset-2">{{ $empresa->razon_social }}</a>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs text-gray-500">{{ $empresa->rfc }}</span>
                                        <x-ui.estatus :familia="$familiaTipo($empresa->tipo_organismo)" :texto="$empresa->tipo_organismo?->label() ?? 'Tipo sin definir'" />
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-sm font-medium text-gray-800">{{ $empresa->contacto_nombre ?: 'Contacto sin registrar' }}</p>
                            <p class="break-all text-xs text-gray-500">{{ $empresa->user?->email ?? 'Sin cuenta de acceso' }}</p>
                            @if ($empresa->contacto_telefono)
                                <p class="text-xs text-gray-500">{{ $empresa->contacto_telefono }}</p>
                            @else
                                <p class="dato-vacio">Sin teléfono</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            @if ($vigente)
                                <x-ui.estatus familia="menta" texto="Vigente" />
                                <p class="mt-1 text-xs text-gray-500">hasta {{ $vigente->fecha_fin_vigencia->format('d/m/Y') }}</p>
                            @elseif ($ultimo)
                                <x-ui.estatus :estado="$ultimo->estado" />
                                <p class="mt-1 text-xs text-gray-500">{{ $empresa->convenios_count }} {{ $empresa->convenios_count === 1 ? 'solicitud' : 'solicitudes' }}</p>
                            @else
                                <span class="dato-vacio">Aún no solicita convenio</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <x-ui.estatus :familia="$activo ? 'menta' : 'rosa'" :texto="$activo ? 'Activa' : 'Inactiva'" />
                        </td>
                        <td class="acciones whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if ($empresa->user)
                                    <x-ui.boton-icono :accion="route('admin.empresas.activo', $empresa)"
                                        :icono="$activo ? 'apagar' : 'encender'" :etiqueta="$activo ? 'Desactivar cuenta' : 'Activar cuenta'"
                                        :tono="$activo ? 'peligro' : 'exito'"
                                        :confirmar="($activo ? '¿Desactivar' : '¿Activar') . ' la cuenta de ' . $empresa->razon_social . '?'" />
                                @endif
                                <x-ui.boton-icono :href="route('admin.empresas.show', $empresa)" icono="ver" etiqueta="Ver expediente" tono="primario" con-texto />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4">
                            <x-dashboard.vacio icono="empresa" titulo="{{ request()->hasAny(['q', 'tipo', 'convenio']) ? 'No hay empresas con ese filtro' : 'Aún no hay empresas registradas' }}"
                                texto="{{ request()->hasAny(['q', 'tipo', 'convenio']) ? 'Prueba con otra búsqueda o quita los filtros.' : 'Las organizaciones se registran desde el sitio público.' }}" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:pie>{{ $empresas->links() }}</x-slot:pie>
    </x-tabla.marco>
</div>
@endsection
