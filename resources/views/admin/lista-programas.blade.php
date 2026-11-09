{{-- resources/views/admin/lista-programas.blade.php --}}
@extends('layouts.app')

@section('content')
@php
    $input = 'focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden';
    $filtro = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700';
    $vacio = ['id' => null, 'modalidad' => 'externa', 'convenio_id' => '', 'carrera_id' => '', 'ciclo' => '', 'titulo' => '', 'descripcion' => '', 'actividades' => '', 'perfil_requerido' => '', 'horario' => '', 'sede' => '', 'cupos_totales' => ''];
    $conveniosJs = $convenios->map(fn ($c) => [
        'id' => $c->id,
        'nombre' => $c->empresa->razon_social.' · '.$c->folio,
        'carreras' => $c->dictamenes->where('dictamen', \App\Enums\Dictamen::Aprobatorio)->pluck('carrera_id')->values(),
    ])->values();
@endphp

<x-common.page-breadcrumb pageTitle="Programas" />

<div x-data="{
    modal: false,
    p: @js($vacio),
    vacio: @js($vacio),
    convenios: @js($conveniosJs),
    urlUpdate: '{{ route('admin.programas.update', 0) }}',
    urlStore: '{{ route('admin.programas.store') }}',
    nuevo() { this.p = { ...this.vacio }; this.modal = true; },
    editar(d) { this.p = { ...this.vacio, ...d }; this.modal = true; },
    get action() { return this.p.id ? this.urlUpdate.replace(/0$/, this.p.id) : this.urlStore; },
    get conveniosDeCarrera() {
        if (!this.p.carrera_id) return this.convenios;
        return this.convenios.filter(c => c.carreras.includes(Number(this.p.carrera_id)));
    }
}" class="space-y-6">

    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5">
        <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Programas de prácticas</h3>
                <p class="mt-0.5 text-sm text-gray-500">Ofertas por carrera. Publicar exige convenio vigente y dictamen aprobatorio de la carrera.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Título o empresa..." class="{{ $filtro }} w-44">
                    <select name="carrera_id" class="{{ $filtro }}">
                        <option value="">Todas las carreras</option>
                        @foreach ($carreras as $c)
                            <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }}</option>
                        @endforeach
                    </select>
                    <select name="estado" class="{{ $filtro }}">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $e)
                            <option value="{{ $e->value }}" @selected(request('estado') === $e->value)>{{ ucfirst($e->value) }}</option>
                        @endforeach
                    </select>
                    <select name="modalidad" class="{{ $filtro }}">
                        <option value="">Modalidad</option>
                        @foreach ($modalidades as $m)
                            <option value="{{ $m->value }}" @selected(request('modalidad') === $m->value)>{{ ucfirst($m->value) }}</option>
                        @endforeach
                    </select>
                    <select name="ciclo" class="{{ $filtro }}">
                        <option value="">Ciclo</option>
                        @foreach ($ciclos as $c)
                            <option value="{{ $c }}" @selected(request('ciclo') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                    <button class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Filtrar</button>
                </form>
                <button type="button" @click="nuevo()" class="bg-brand-500 hover:bg-brand-600 h-10 rounded-lg px-4 text-sm font-medium text-white">+ Crear programa</button>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Programa / Empresa</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Carrera</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Ciclo</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Modalidad</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Cupos</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($programas as $pr)
                        @php
                            $ocupados = (int) $pr->cupos_ocupados;
                            $pct = $pr->cupos_totales > 0 ? min(100, round($ocupados / $pr->cupos_totales * 100)) : 0;
                            $color = match ($pr->estado) {
                                \App\Enums\ProgramaEstado::Publicado => 'bg-green-50 text-green-700',
                                \App\Enums\ProgramaEstado::Cerrado => 'bg-red-50 text-red-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                            $datos = [
                                'id' => $pr->id, 'modalidad' => $pr->modalidad->value, 'convenio_id' => $pr->convenio_id ?? '',
                                'carrera_id' => $pr->carrera_id, 'ciclo' => $pr->ciclo, 'titulo' => $pr->titulo,
                                'descripcion' => $pr->descripcion, 'actividades' => implode("\n", $pr->actividades ?? []),
                                'perfil_requerido' => $pr->perfil_requerido, 'horario' => $pr->horario ?? '',
                                'sede' => $pr->sede ?? '', 'cupos_totales' => $pr->cupos_totales,
                            ];
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50/40">
                            <td class="max-w-[300px] px-6 py-4">
                                <span class="block truncate text-sm font-semibold text-gray-900">{{ $pr->titulo }}</span>
                                <span class="text-xs text-gray-500">{{ $pr->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 font-mono text-xs font-bold text-gray-600">{{ $pr->carrera->carrera_clave }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-600">{{ $pr->ciclo }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-600">{{ ucfirst($pr->modalidad->value) }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-800">{{ $ocupados }}/{{ $pr->cupos_totales }}</span>
                                    <div class="h-1.5 w-16 rounded-full bg-gray-100"><div class="bg-brand-500 h-1.5 rounded-full" style="width: {{ $pct }}%"></div></div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $color }}">{{ ucfirst($pr->estado->value) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button type="button" @click="editar(@js($datos))" class="text-brand-600 hover:text-brand-700 text-xs font-semibold">Editar</button>
                                    <form method="POST" action="{{ route('admin.programas.estado', $pr) }}" class="flex gap-2">
                                        @csrf
                                        @if ($pr->estado !== \App\Enums\ProgramaEstado::Publicado)
                                            <button name="estado" value="publicado" class="text-xs font-semibold text-green-600 hover:text-green-700">Publicar</button>
                                        @endif
                                        @if ($pr->estado === \App\Enums\ProgramaEstado::Publicado)
                                            <button name="estado" value="cerrado" class="text-xs font-semibold text-yellow-600 hover:text-yellow-700">Cerrar</button>
                                        @endif
                                        @if ($pr->estado !== \App\Enums\ProgramaEstado::Borrador)
                                            <button name="estado" value="borrador" class="text-xs font-semibold text-gray-500 hover:text-gray-700">A borrador</button>
                                        @endif
                                    </form>
                                    <form method="POST" action="{{ route('admin.programas.destroy', $pr) }}" onsubmit="return confirm('¿Eliminar este programa?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs font-semibold text-red-600 hover:text-red-700">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">No hay programas que coincidan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $programas->links() }}</div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-show="modal" x-cloak @keydown.escape.window="modal = false" class="fixed inset-0 z-99999 flex items-start justify-center overflow-y-auto p-5">
        <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="modal = false"></div>
        <form method="POST" :action="action" @click.stop class="relative my-8 w-full max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-theme-xl">
            @csrf
            <template x-if="p.id"><input type="hidden" name="_method" value="PUT"></template>
            <h3 class="text-lg font-semibold text-gray-800" x-text="p.id ? 'Editar programa' : 'Nuevo programa'"></h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="{{ $label }}">Modalidad</label>
                    <select name="modalidad" x-model="p.modalidad" required class="{{ $input }}">
                        <option value="externa">Externa (con convenio)</option>
                        <option value="interna">Interna (proyecto universitario)</option>
                    </select>
                </div>
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

            <div x-show="p.modalidad === 'externa'">
                <label class="{{ $label }}">Convenio vigente <span class="text-xs font-normal text-gray-400">(solo los que aprobaron la carrera elegida)</span></label>
                <select name="convenio_id" x-model="p.convenio_id" :disabled="p.modalidad !== 'externa'" class="{{ $input }}">
                    <option value="">Selecciona un convenio</option>
                    <template x-for="c in conveniosDeCarrera" :key="c.id">
                        <option :value="c.id" x-text="c.nombre" :selected="c.id == p.convenio_id"></option>
                    </template>
                </select>
            </div>

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
                <button type="button" @click="modal = false" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2.5 text-sm font-medium text-white">Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>[x-cloak]{display:none !important;}</style>
@endsection