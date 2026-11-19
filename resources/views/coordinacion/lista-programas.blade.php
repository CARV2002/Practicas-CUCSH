{{-- resources/views/coordinacion/lista-programas.blade.php --}}
@extends('layouts.app')

@section('content')
@php
    $input = 'campo h-11';
    $filtro = 'campo-filtro';
    $label = 'etiqueta';
    $vacio = ['id' => null, 'modalidad' => 'interna', 'convenio_id' => '', 'carrera_id' => '', 'ciclo' => '', 'titulo' => '', 'descripcion' => '', 'actividades' => '', 'perfil_requerido' => '', 'horario' => '', 'sede' => '', 'cupos_totales' => ''];
    $conveniosJs = $convenios->map(fn ($c) => [
        'id' => $c->id,
        'nombre' => $c->empresa->razon_social.' · '.$c->folio,
        'carreras' => $c->dictamenes->where('dictamen', \App\Enums\Dictamen::Aprobatorio)->pluck('carrera_id')->values(),
    ])->values();
@endphp

<div x-data="{
    modal: false,
    p: @js($vacio),
    vacio: @js($vacio),
    convenios: @js($conveniosJs),
    urlUpdate: '{{ route('coordinacion.programas.update', 0) }}',
    urlStore: '{{ route('coordinacion.programas.store') }}',
    nuevo() { this.p = { ...this.vacio }; this.modal = true; },
    editar(d) { this.p = { ...this.vacio, ...d }; this.modal = true; },
    get action() { return this.p.id ? this.urlUpdate.replace(/0$/, this.p.id) : this.urlStore; },
    get conveniosDeCarrera() {
        if (!this.p.carrera_id) return this.convenios;
        return this.convenios.filter(c => c.carreras.includes(Number(this.p.carrera_id)));
    }
}" class="space-y-6">

    <x-pagina.encabezado titulo="Programas y vacantes" descripcion="Aprueba las vacantes que proponen las organizaciones y crea proyectos internos para tu carrera.">
        <x-slot:acciones>
            <button type="button" @click="nuevo()" class="boton boton-primario"><x-dashboard.icono nombre="mas" clase="size-4" /> Proyecto interno</button>
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    <x-tabla.marco titulo="Programas de prácticas" descripcion="Publicar exige convenio vigente y dictamen aprobatorio de la carrera." :total="$programas->total()">
        <x-slot:herramientas>
            <x-tabla.filtros-estado :actual="request('estado', '')" :opciones="collect($estados)->map(fn ($e) => ['valor' => $e->value, 'texto' => $e->label(), 'familia' => $e->familia()])->prepend(['valor' => '', 'texto' => 'Todos'])->all()" />
            <form method="GET" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap" role="search">
                @if (request('estado'))<input type="hidden" name="estado" value="{{ request('estado') }}">@endif
                <div class="relative flex-1 sm:max-w-xs">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar por título o empresa</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Título o empresa" class="campo-filtro pl-9 sm:w-full">
                </div>
                @if ($carreras->count() > 1)
                    <select name="carrera_id" class="campo-filtro" aria-label="Carrera" onchange="this.form.requestSubmit()">
                        <option value="">Todas las carreras</option>
                        @foreach ($carreras as $c)
                            <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="modalidad" class="campo-filtro" aria-label="Modalidad" onchange="this.form.requestSubmit()">
                    <option value="">Cualquier modalidad</option>
                    @foreach ($modalidades as $m)
                        <option value="{{ $m->value }}" @selected(request('modalidad') === $m->value)>{{ ucfirst($m->value) }}</option>
                    @endforeach
                </select>
                <select name="ciclo" class="campo-filtro" aria-label="Ciclo" onchange="this.form.requestSubmit()">
                    <option value="">Cualquier ciclo</option>
                    @foreach ($ciclos as $c)
                        <option value="{{ $c }}" @selected(request('ciclo') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
                <button class="boton boton-secundario">Buscar</button>
                @if (request()->hasAny(['q', 'carrera_id', 'estado', 'modalidad', 'ciclo']))
                    <a href="{{ route('coordinacion.lista-programas') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                @endif
            </form>
        </x-slot:herramientas>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th px-6">Programa</th>
                    <th class="tabla-th">Carrera y ciclo</th>
                    <th class="tabla-th">Cupos</th>
                    <th class="tabla-th">Estado</th>
                    <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($programas as $pr)
                    @php
                        $ocupados = (int) $pr->cupos_ocupados;
                        $pct = $pr->cupos_totales > 0 ? min(100, round($ocupados / $pr->cupos_totales * 100)) : 0;
                        $pendiente = $pr->estado === \App\Enums\ProgramaEstado::PendienteAprobacion;
                        $deEmpresa = in_array($pr->estado, [\App\Enums\ProgramaEstado::PendienteAprobacion, \App\Enums\ProgramaEstado::Rechazado], true);
                        $datos = [
                            'id' => $pr->id, 'modalidad' => $pr->modalidad->value, 'convenio_id' => $pr->convenio_id ?? '',
                            'carrera_id' => $pr->carrera_id, 'ciclo' => $pr->ciclo, 'titulo' => $pr->titulo,
                            'descripcion' => $pr->descripcion, 'actividades' => implode("\n", $pr->actividades ?? []),
                            'perfil_requerido' => $pr->perfil_requerido, 'horario' => $pr->horario ?? '',
                            'sede' => $pr->sede ?? '', 'cupos_totales' => $pr->cupos_totales,
                        ];
                    @endphp
                    <tr class="tabla-fila">
                        <td class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <span @class([
                                    'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset',
                                    'bg-udg-50 text-udg-900 ring-udg-100' => !$pr->esInterno(),
                                    'bg-oro-50 text-oro-700 ring-oro-100' => $pr->esInterno(),
                                ]) title="{{ $pr->esInterno() ? 'Proyecto interno' : 'Vacante externa' }}">
                                    <x-dashboard.icono :nombre="$pr->esInterno() ? 'academico' : 'empresa'" clase="size-[18px]" />
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $pr->titulo }}</p>
                                    <p class="text-xs text-gray-500">{{ $pr->convenio?->empresa->razon_social ?? 'Proyecto interno (sin convenio)' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <span class="rounded-md bg-udg-50 px-1.5 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100" title="{{ $pr->carrera->nombre }}">{{ $pr->carrera->carrera_clave }}</span>
                            <p class="mt-1 text-xs text-gray-500">Ciclo {{ $pr->ciclo }} · {{ ucfirst($pr->modalidad->value) }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $ocupados }}<span class="font-normal text-gray-400"> / {{ $pr->cupos_totales }}</span></p>
                            <div class="mt-1.5 h-1.5 w-20 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-udg-800" style="width: {{ $pct }}%"></div></div>
                        </td>
                        <td class="px-4 py-4">
                            <x-ui.estatus :estado="$pr->estado" />
                            @if ($pr->motivo_rechazo && $pr->estado === \App\Enums\ProgramaEstado::Rechazado)
                                <p class="mt-1 max-w-[14rem] text-xs text-rose-600">{{ $pr->motivo_rechazo }}</p>
                            @endif
                        </td>
                        <td class="acciones px-6 py-4 text-right" x-data="{ rechazar: false }">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @if ($pendiente && ($faltaAprobar = \App\Support\Requisitos::aprobarVacante($pr)))
                                    <x-ui.accion-bloqueada :motivo="$faltaAprobar" tamano="chico">Aprobar</x-ui.accion-bloqueada>
                                @elseif ($pendiente)
                                    <x-ui.boton-icono :accion="route('coordinacion.programas.aprobar', $pr)" icono="check" etiqueta="Aprobar y publicar" tono="exito" con-texto />
                                    <x-ui.boton-icono icono="equis" etiqueta="Rechazar" tono="peligro" x-on:click="rechazar = !rechazar" />
                                @endif
                                @unless ($deEmpresa)
                                    @if ($pr->estado !== \App\Enums\ProgramaEstado::Publicado)
                                        <x-ui.boton-icono :accion="route('coordinacion.programas.estado', $pr)" icono="enviar" etiqueta="Publicar" tono="exito"><input type="hidden" name="estado" value="publicado"></x-ui.boton-icono>
                                    @else
                                        <x-ui.boton-icono :accion="route('coordinacion.programas.estado', $pr)" icono="candado" etiqueta="Cerrar postulaciones"><input type="hidden" name="estado" value="cerrado"></x-ui.boton-icono>
                                    @endif
                                    @if ($pr->estado !== \App\Enums\ProgramaEstado::Borrador)
                                        <x-ui.boton-icono :accion="route('coordinacion.programas.estado', $pr)" icono="deshacer" etiqueta="Regresar a borrador"><input type="hidden" name="estado" value="borrador"></x-ui.boton-icono>
                                    @endif
                                @endunless
                                @if ($pr->esInterno())
                                    <x-ui.boton-icono icono="editar" etiqueta="Editar" x-on:click="editar(@js($datos))" />
                                    <x-ui.boton-icono :accion="route('coordinacion.programas.destroy', $pr)" metodo="DELETE" icono="basura" etiqueta="Eliminar" tono="peligro" confirmar="¿Eliminar este programa? No se puede deshacer." />
                                @endif
                            </div>
                            @if ($pendiente)
                                <form x-show="rechazar" x-cloak x-transition.opacity.duration.150ms method="POST" action="{{ route('coordinacion.programas.rechazar', $pr) }}"
                                    class="mt-2 flex items-center justify-end gap-2"
                                    onsubmit="return this.motivo.value.trim() !== '' || (alert('Escribe el motivo del rechazo.'), false)">
                                    @csrf
                                    <label for="motivo-{{ $pr->id }}" class="sr-only">Motivo del rechazo de {{ $pr->titulo }}</label>
                                    <input id="motivo-{{ $pr->id }}" type="text" name="motivo" maxlength="1000" placeholder="Motivo del rechazo" class="campo h-9 w-full text-xs sm:w-56">
                                    <button class="boton boton-chico boton-peligro shrink-0">Rechazar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4">
                            <x-dashboard.vacio icono="vacante" titulo="{{ request()->hasAny(['q', 'carrera_id', 'estado', 'modalidad', 'ciclo']) ? 'No hay programas con ese filtro' : 'Aún no hay programas' }}"
                                texto="Las vacantes que propongan las organizaciones aparecerán aquí. También puedes crear un proyecto interno." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:pie>{{ $programas->links() }}</x-slot:pie>
    </x-tabla.marco>

    {{-- Modal crear/editar --}}
    <div x-show="modal" x-cloak x-transition.opacity.duration.200ms @keydown.escape.window="modal = false" class="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto p-5">
        <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" @click="modal = false"></div>
        <form method="POST" :action="action" @click.stop class="relative animate-entrada motion-reduce:animate-none my-8 w-full max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-theme-xl">
            @csrf
            <template x-if="p.id"><input type="hidden" name="_method" value="PUT"></template>
            <h3 class="text-lg font-semibold text-gray-800" x-text="p.id ? 'Editar proyecto interno' : 'Nuevo proyecto interno'"></h3>
            <p class="text-xs text-gray-500">Proyectos dentro de la UdeG (bufetes, revistas, centros de investigación). No llevan convenio: al activar la práctica se exige el Oficio de Aceptación Interno del área receptora. Las vacantes externas las registra la empresa y aquí solo las apruebas.</p>
            <input type="hidden" name="modalidad" value="interna">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="{{ $label }}">Carrera</label>
                    <select name="carrera_id" x-model="p.carrera_id" required class="{{ $input }}">
                        <option value="">Selecciona</option>
                        @foreach ($carreras as $c)
                            <option value="{{ $c->id }}">{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Ciclo</label>
                    <input type="text" name="ciclo" x-model="p.ciclo" required placeholder="2027A" maxlength="5" class="{{ $input }} uppercase">
                </div>
            </div>

            {{-- Proyecto interno: sin convenio --}}
            <div>
                <label class="{{ $label }}">Título</label>
                <input type="text" name="titulo" x-model="p.titulo" required maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label class="{{ $label }}">Descripción corta</label>
                <textarea name="descripcion" x-model="p.descripcion" required rows="2" maxlength="1000" class="{{ $input }} h-auto py-2.5"></textarea>
            </div>
            <div>
                <label class="{{ $label }}">Actividades <span class="text-xs font-normal text-gray-400">(una por línea)</span></label>
                <textarea name="actividades" x-model="p.actividades" required rows="4" maxlength="3000" class="{{ $input }} h-auto py-2.5"></textarea>
            </div>
            <div>
                <label class="{{ $label }}">Perfil requerido</label>
                <textarea name="perfil_requerido" x-model="p.perfil_requerido" required rows="2" maxlength="2000" class="{{ $input }} h-auto py-2.5"></textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div><label class="{{ $label }}">Horario</label><input type="text" name="horario" x-model="p.horario" maxlength="255" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Sede</label><input type="text" name="sede" x-model="p.sede" maxlength="255" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Cupos totales</label><input type="number" name="cupos_totales" x-model="p.cupos_totales" required min="1" max="500" class="{{ $input }}"></div>
            </div>

            <p class="text-xs text-gray-500">Se guarda como borrador. Usa «Publicar» en la tabla cuando esté listo.</p>

            <div class="flex justify-end gap-2">
                <button type="button" @click="modal = false" class="boton boton-secundario">Cancelar</button>
                <button type="submit" class="boton boton-primario">Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>[x-cloak]{display:none !important;}</style>
@endsection