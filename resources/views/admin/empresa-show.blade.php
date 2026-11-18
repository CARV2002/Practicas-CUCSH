@extends('layouts.app')

@section('content')
@php
    $activo = $empresa->user?->activo ?? false;
    $estadosDoc = ['validada' => ['menta', 'Validado'], 'rechazada' => ['rosa', 'Rechazado'], 'pendiente' => ['ambar', 'Por revisar']];
    $subidos = collect($colecciones)->filter(fn ($c) => $empresa->documentoVigente($c))->count();
    $familiaTipo = match ($empresa->tipo_organismo?->value) { 'publico' => 'cielo', 'privado' => 'lavanda', 'sociedad_civil' => 'menta', default => 'gris' };
@endphp

<div class="space-y-6">
    <x-pagina.encabezado :titulo="$empresa->razon_social" :volver="route('admin.lista-empresas')" volver-texto="Empresas">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm text-gray-600">{{ $empresa->rfc }}</span>
            <x-ui.estatus :familia="$familiaTipo" :texto="$empresa->tipo_organismo?->label() ?? 'Tipo sin definir'" />
            <x-ui.estatus :familia="$activo ? 'menta' : 'rosa'" :texto="$activo ? 'Cuenta activa' : 'Cuenta inactiva'" />
        </div>
        <x-slot:acciones>
            @if ($empresa->user)
                <x-ui.boton-icono :accion="route('admin.empresas.activo', $empresa)" :icono="$activo ? 'apagar' : 'encender'"
                    :etiqueta="$activo ? 'Desactivar cuenta' : 'Activar cuenta'" :tono="$activo ? 'peligro' : 'exito'" con-texto
                    :confirmar="($activo ? '¿Desactivar' : '¿Activar') . ' la cuenta de esta empresa?'" />
            @endif
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="Revisa los datos"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Convenios --}}
            <x-dashboard.panel titulo="Convenios" icono="vigente" descripcion="Historial de solicitudes de esta organización.">
                @forelse ($empresa->convenios->sortByDesc('id') as $c)
                    @php $aprobadas = $c->dictamenes->where('dictamen', \App\Enums\Dictamen::Aprobatorio); @endphp
                    <div class="-mx-2 flex flex-col gap-3 rounded-xl px-2 py-3.5 transition-colors hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-gray-900">{{ $c->folio ? 'Folio ' . $c->folio : 'Sin folio aún' }}</p>
                                <x-ui.estatus :estado="$c->estado" />
                            </div>
                            <x-convenio.pista :estado="$c->estado" class="mt-2" />
                            <p class="mt-2 text-xs text-gray-500">
                                Recibido {{ $c->fecha_recepcion->format('d/m/Y') }}
                                @if ($c->fecha_fin_vigencia) · vigente {{ $c->fecha_inicio_vigencia->format('d/m/Y') }} – {{ $c->fecha_fin_vigencia->format('d/m/Y') }}@endif
                            </p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                @forelse ($aprobadas as $d)
                                    <span class="rounded-md bg-udg-50 px-1.5 py-0.5 text-[11px] font-semibold text-udg-900 ring-1 ring-inset ring-udg-100" title="{{ $d->carrera->nombre }}">{{ $d->carrera->carrera_clave }}</span>
                                @empty
                                    <span class="dato-vacio">Sin carreras aprobadas todavía</span>
                                @endforelse
                            </div>
                        </div>
                        <x-ui.boton-icono :href="route('admin.convenios.show', $c)" icono="flecha" :etiqueta="$c->estado->esFinal() ? 'Ver' : 'Gestionar'" tono="primario" con-texto class="self-start sm:self-center" />
                    </div>
                @empty
                    <x-dashboard.vacio icono="documento" titulo="Aún no solicita convenio" texto="Cuando la organización envíe su solicitud, la verás aquí y en la bandeja de convenios." />
                @endforelse
            </x-dashboard.panel>

            {{-- Datos editables --}}
            <x-dashboard.panel titulo="Datos de la empresa" icono="editar" descripcion="Vinculación corrige aquí los datos legales y de contacto.">
                <form method="POST" action="{{ route('admin.empresas.update', $empresa) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <fieldset class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <legend class="mb-3 text-sm font-semibold text-gray-900">Datos legales</legend>
                        <div class="md:col-span-2"><label class="etiqueta" for="e-razon">Razón social</label><input id="e-razon" type="text" name="razon_social" value="{{ old('razon_social', $empresa->razon_social) }}" required maxlength="255" class="campo h-11"></div>
                        <div><label class="etiqueta" for="e-rfc">RFC</label><input id="e-rfc" type="text" name="rfc" value="{{ old('rfc', $empresa->rfc) }}" required maxlength="13" class="campo h-11 font-mono uppercase"></div>
                        <div><label class="etiqueta" for="e-tipo">Tipo de organismo</label>
                            <select id="e-tipo" name="tipo_organismo" required class="campo h-11">
                                @foreach (\App\Enums\TipoOrganismo::cases() as $t)
                                    <option value="{{ $t->value }}" @selected(old('tipo_organismo', $empresa->tipo_organismo?->value) === $t->value)>{{ $t->label() }}</option>
                                @endforeach
                            </select></div>
                        <div class="md:col-span-2"><label class="etiqueta" for="e-dom">Domicilio fiscal</label><input id="e-dom" type="text" name="domicilio_fiscal" value="{{ old('domicilio_fiscal', $empresa->domicilio_fiscal) }}" maxlength="255" placeholder="Sin registrar" class="campo h-11"></div>
                        <div class="md:col-span-3"><label class="etiqueta" for="e-rep">Representante legal</label><input id="e-rep" type="text" name="representante_legal" value="{{ old('representante_legal', $empresa->representante_legal) }}" maxlength="255" placeholder="Sin registrar" class="campo h-11"></div>
                    </fieldset>
                    <fieldset class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-5 md:grid-cols-3">
                        <legend class="mb-3 text-sm font-semibold text-gray-900">Contacto</legend>
                        <div><label class="etiqueta" for="e-con">Persona de contacto</label><input id="e-con" type="text" name="contacto_nombre" value="{{ old('contacto_nombre', $empresa->contacto_nombre) }}" required maxlength="255" class="campo h-11"></div>
                        <div><label class="etiqueta" for="e-cargo">Cargo</label><input id="e-cargo" type="text" name="contacto_cargo" value="{{ old('contacto_cargo', $empresa->contacto_cargo) }}" maxlength="255" placeholder="Sin registrar" class="campo h-11"></div>
                        <div><label class="etiqueta" for="e-tel">Teléfono</label><input id="e-tel" type="text" name="contacto_telefono" value="{{ old('contacto_telefono', $empresa->contacto_telefono) }}" maxlength="20" placeholder="Sin registrar" class="campo h-11"></div>
                        <div class="md:col-span-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div><label class="etiqueta" for="e-desc">Descripción</label><textarea id="e-desc" name="descripcion" rows="3" maxlength="3000" placeholder="La organización aún no describe a qué se dedica" class="campo">{{ old('descripcion', $empresa->descripcion) }}</textarea></div>
                            <div><label class="etiqueta" for="e-proy">Proyectos principales</label><textarea id="e-proy" name="proyectos_principales" rows="3" maxlength="3000" placeholder="Sin proyectos registrados" class="campo">{{ old('proyectos_principales', $empresa->proyectos_principales) }}</textarea></div>
                        </div>
                    </fieldset>
                    <div class="flex justify-end"><button class="boton boton-primario w-full sm:w-auto">Guardar cambios</button></div>
                </form>
            </x-dashboard.panel>
        </div>

        {{-- Expediente --}}
        <aside class="space-y-6" aria-label="Expediente">
            <x-dashboard.panel titulo="Expediente" icono="documento" descripcion="La validación se hace desde el convenio en trámite.">
                <div class="mb-4 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $expedienteCompleto ? 'bg-emerald-500' : 'bg-gradient-to-r from-udg-800 to-oro-400' }}" style="width: {{ $subidos / max(1, count($colecciones)) * 100 }}%"></div></div>
                    <span class="text-xs font-semibold tabular-nums text-gray-700">{{ $subidos }}/{{ count($colecciones) }}</span>
                </div>
                <x-ui.estatus :familia="$expedienteCompleto ? 'menta' : 'ambar'" :texto="$expedienteCompleto ? 'Completo y validado' : 'Incompleto o por validar'" />
                <ul class="mt-4 space-y-2">
                    @foreach ($colecciones as $col)
                        @php
                            $media = $empresa->documentoVigente($col);
                            $st = $media?->getCustomProperty('estado') ?? ($media ? 'pendiente' : null);
                        @endphp
                        <li class="flex items-start justify-between gap-2 rounded-xl bg-gray-50 px-3 py-2.5 ring-1 ring-inset ring-gray-100">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ \App\Models\Empresa::nombreDocumento($col) }}</p>
                                @if ($media)
                                    <p class="truncate text-xs text-gray-500" title="{{ $media->file_name }}">{{ $media->file_name }}</p>
                                    @if ($media->getCustomProperty('motivo'))<p class="text-xs text-rose-600">{{ $media->getCustomProperty('motivo') }}</p>@endif
                                @else
                                    <p class="dato-vacio">Pendiente de subir</p>
                                @endif
                            </div>
                            @if ($st)
                                <x-ui.estatus :familia="$estadosDoc[$st][0]" :texto="$estadosDoc[$st][1]" class="shrink-0" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-dashboard.panel>
        </aside>
    </div>
</div>
@endsection
