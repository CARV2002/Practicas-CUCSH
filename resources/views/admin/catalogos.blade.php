@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Divisiones y carreras" />

<div x-data="{
    modalDivision: false,
    modalCarrera: false,
    division: { id: null, nombre: '' },
    carrera: { id: null, division_id: '', carrera_clave: '', nombre: '', horas_requeridas: '', porcentaje_creditos_minimo: '', creditos_otorgados: '' },
    urlDivision: '{{ route('admin.catalogos.divisiones.update', 0) }}',
    urlCarrera: '{{ route('admin.catalogos.carreras.update', 0) }}',
    nuevaDivision() { this.division = { id: null, nombre: '' }; this.modalDivision = true; },
    editarDivision(d) { this.division = { ...d }; this.modalDivision = true; },
    nuevaCarrera(divisionId = '') {
        this.carrera = { id: null, division_id: divisionId, carrera_clave: '', nombre: '', horas_requeridas: '', porcentaje_creditos_minimo: '', creditos_otorgados: '' };
        this.modalCarrera = true;
    },
    editarCarrera(c) { this.carrera = { ...c }; this.modalCarrera = true; },
    get actionDivision() {
        return this.division.id ? this.urlDivision.replace(/0$/, this.division.id) : '{{ route('admin.catalogos.divisiones.store') }}';
    },
    get actionCarrera() {
        return this.carrera.id ? this.urlCarrera.replace(/0$/, this.carrera.id) : '{{ route('admin.catalogos.carreras.store') }}';
    }
}" class="space-y-6">

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

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">Las horas y créditos de cada carrera se congelan en la práctica al activarla.</p>
        <div class="flex gap-2">
            <button type="button" @click="nuevaDivision()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                + Nueva división
            </button>
            <button type="button" @click="nuevaCarrera()"
                class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2.5 text-sm font-medium text-white">
                + Nueva carrera
            </button>
        </div>
    </div>

    @forelse ($divisiones as $division)
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">{{ $division->nombre }}</h3>
                    <p class="text-xs text-gray-500">{{ $division->carreras->count() }} carrera(s)</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="editarDivision(@js($division->only(['id', 'nombre'])))"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        Editar división
                    </button>
                    <button type="button" @click="nuevaCarrera({{ $division->id }})"
                        class="text-brand-600 border-brand-200 hover:bg-brand-25 rounded-lg border px-3 py-1.5 text-xs font-medium">
                        + Carrera
                    </button>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Clave</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Carrera</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Horas</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">% créditos mín.</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Créditos otorgados</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($division->carreras as $carrera)
                            <tr class="transition-colors hover:bg-gray-50/40">
                                <td class="whitespace-nowrap px-6 py-3">
                                    <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 font-mono text-xs font-bold text-gray-600">{{ $carrera->carrera_clave }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $carrera->nombre }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $carrera->horas_requeridas }} h</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ rtrim(rtrim((string) $carrera->porcentaje_creditos_minimo, '0'), '.') }}%</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $carrera->creditos_otorgados }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right">
                                    <button type="button"
                                        @click="editarCarrera(@js($carrera->only(['id', 'division_id', 'carrera_clave', 'nombre', 'horas_requeridas', 'porcentaje_creditos_minimo', 'creditos_otorgados'])))"
                                        class="text-brand-600 hover:text-brand-700 text-xs font-semibold">
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-6 text-center text-sm text-gray-400">Sin carreras en esta división.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
            No hay divisiones registradas. Ejecuta <code>php artisan db:seed --class=CarreraSeeder</code> o crea una nueva.
        </div>
    @endforelse

    {{-- Modal: división --}}
    <div x-show="modalDivision" x-cloak @keydown.escape.window="modalDivision = false"
        class="fixed inset-0 z-99999 flex items-center justify-center p-5">
        <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="modalDivision = false"></div>
        <form method="POST" :action="actionDivision" @click.stop class="relative w-full max-w-md space-y-5 rounded-2xl bg-white p-6 shadow-theme-xl">
            @csrf
            <template x-if="division.id"><input type="hidden" name="_method" value="PUT"></template>
            <h3 class="text-lg font-semibold text-gray-800" x-text="division.id ? 'Editar división' : 'Nueva división'"></h3>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" name="nombre" x-model="division.nombre" required maxlength="255"
                    class="focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="modalDivision = false" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2.5 text-sm font-medium text-white">Guardar</button>
            </div>
        </form>
    </div>

    {{-- Modal: carrera --}}
    <div x-show="modalCarrera" x-cloak @keydown.escape.window="modalCarrera = false"
        class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">
        <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="modalCarrera = false"></div>
        <form method="POST" :action="actionCarrera" @click.stop class="relative w-full max-w-lg space-y-5 rounded-2xl bg-white p-6 shadow-theme-xl">
            @csrf
            <template x-if="carrera.id"><input type="hidden" name="_method" value="PUT"></template>
            <h3 class="text-lg font-semibold text-gray-800" x-text="carrera.id ? 'Editar carrera' : 'Nueva carrera'"></h3>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">División</label>
                <select name="division_id" x-model="carrera.division_id" required
                    class="focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                    <option value="">Selecciona una división</option>
                    @foreach ($divisiones as $d)
                        <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Clave</label>
                    <input type="text" name="carrera_clave" x-model="carrera.carrera_clave" required maxlength="10"
                        class="focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm uppercase text-gray-800 focus:ring-3 focus:outline-hidden">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Nombre</label>
                    <input type="text" name="nombre" x-model="carrera.nombre" required maxlength="255"
                        class="focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Horas requeridas</label>
                    <input type="number" name="horas_requeridas" x-model="carrera.horas_requeridas" required min="1" max="2000"
                        class="focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">% créditos mín.</label>
                    <input type="number" step="0.01" name="porcentaje_creditos_minimo" x-model="carrera.porcentaje_creditos_minimo" required min="0" max="100"
                        class="focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Créditos otorgados</label>
                    <input type="number" name="creditos_otorgados" x-model="carrera.creditos_otorgados" required min="0" max="50"
                        class="focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 px-4 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" @click="modalCarrera = false" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2.5 text-sm font-medium text-white">Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>[x-cloak]{display:none !important;}</style>
@endsection