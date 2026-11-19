@extends('layouts.app')

@section('content')
@php
    $carreras = $divisiones->flatMap->carreras;
    $sinCoordinacion = $divisiones->sum(fn ($d) => $d->personal_count > 0 ? 0 : $d->carreras->where('personal_count', 0)->count());
    $pct = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
@endphp

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

    <x-pagina.encabezado titulo="Divisiones y carreras" descripcion="Estructura académica del CUCSH. Las horas y créditos de cada carrera se congelan en la práctica al activarla, así que un cambio aquí solo afecta a las prácticas nuevas.">
        <x-slot:acciones>
            <button type="button" x-on:click="nuevaDivision()" class="boton boton-secundario"><x-dashboard.icono nombre="mas" clase="size-4" /> Nueva división</button>
            <button type="button" x-on:click="nuevaCarrera()" class="boton boton-primario"><x-dashboard.icono nombre="mas" clase="size-4" /> Nueva carrera</button>
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="No se guardó el cambio"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-dashboard.kpi etiqueta="Divisiones" :valor="$divisiones->count()" icono="empresa" tono="udg" detalle="Unidades académicas" :orden="0" />
        <x-dashboard.kpi etiqueta="Carreras" :valor="$carreras->count()" icono="academico" tono="lavanda" detalle="Con reglas de prácticas" :orden="1" />
        <x-dashboard.kpi etiqueta="Alumnos registrados" :valor="$carreras->sum('alumnos_count')" icono="alumno" tono="menta" detalle="En todas las carreras" :orden="2" />
        <x-dashboard.kpi :href="route('admin.lista-usuarios', ['rol' => 'coordinador'])" etiqueta="Sin coordinación" :valor="$sinCoordinacion" icono="alerta" tono="ambar"
            :detalle="$sinCoordinacion ? 'Nadie atiende a sus alumnos' : 'Todas tienen quien las atienda'" :alerta="$sinCoordinacion ? 'Da de alta su coordinación' : null" accion="Dar de alta" :orden="3" />
    </div>

    @forelse ($divisiones as $division)
        <x-tabla.marco>
            <x-slot:herramientas>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-udg-50 font-display text-sm font-bold text-udg-900 ring-1 ring-inset ring-udg-100" aria-hidden="true">{{ mb_strtoupper(mb_substr(preg_replace('/^Divisi[oó]n (de |del )?/iu', '', $division->nombre), 0, 1)) }}</span>
                        <div class="min-w-0">
                            <h2 class="font-display text-base font-bold text-gray-900">{{ $division->nombre }}</h2>
                            <p class="text-xs text-gray-500">
                                {{ $division->carreras->count() }} {{ $division->carreras->count() === 1 ? 'carrera' : 'carreras' }}
                                · {{ $division->carreras->sum('alumnos_count') }} alumnos
                                @if ($division->personal_count) · coordinación divisional asignada @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" x-on:click="editarDivision(@js($division->only(['id', 'nombre'])))" class="boton boton-chico boton-secundario"><x-dashboard.icono nombre="editar" clase="size-3.5" /> Renombrar</button>
                        <button type="button" x-on:click="nuevaCarrera({{ $division->id }})" class="boton boton-chico text-real-600 ring-1 ring-inset ring-real-200 hover:bg-real-50"><x-dashboard.icono nombre="mas" clase="size-3.5" /> Carrera</button>
                    </div>
                </div>
            </x-slot:herramientas>

            <table class="tabla">
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Carrera</th>
                        <th class="tabla-th text-right">Horas</th>
                        <th class="tabla-th text-right">Créditos mínimos</th>
                        <th class="tabla-th text-right">Créditos que otorga</th>
                        <th class="tabla-th">Coordinación</th>
                        <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($division->carreras as $carrera)
                        <tr class="tabla-fila">
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-7 min-w-14 shrink-0 items-center justify-center rounded-md bg-gray-50 px-2 font-mono text-xs font-bold text-gray-700 ring-1 ring-inset ring-gray-200">{{ $carrera->carrera_clave }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900">{{ $carrera->nombre }}</p>
                                        <p class="text-xs text-gray-500">{{ $carrera->alumnos_count }} {{ $carrera->alumnos_count === 1 ? 'alumno registrado' : 'alumnos registrados' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right"><span class="font-display text-base font-bold tabular-nums text-gray-900">{{ $carrera->horas_requeridas }}</span> <span class="text-xs text-gray-500">h</span></td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                <span class="font-display text-base font-bold tabular-nums text-gray-900">{{ $pct($carrera->porcentaje_creditos_minimo) }}</span><span class="text-xs text-gray-500">%</span>
                                <div class="ml-auto mt-1 h-1 w-16 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-udg-800" style="width: {{ min(100, (float) $carrera->porcentaje_creditos_minimo) }}%"></div></div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right"><span class="font-display text-base font-bold tabular-nums text-gray-900">{{ $carrera->creditos_otorgados }}</span> <span class="text-xs text-gray-500">créditos</span></td>
                            <td class="whitespace-nowrap px-4 py-3.5">
                                @if ($carrera->personal_count > 0)
                                    <x-ui.estatus familia="menta" :texto="$carrera->personal_count === 1 ? 'Asignada' : $carrera->personal_count . ' personas'" />
                                @elseif ($division->personal_count > 0)
                                    <x-ui.estatus familia="cielo" texto="Por la división" />
                                @else
                                    <a href="{{ route('admin.lista-usuarios', ['rol' => 'coordinador']) }}" title="Da de alta su coordinación"><x-ui.estatus familia="ambar" texto="Sin coordinación" /></a>
                                @endif
                            </td>
                            <td class="acciones whitespace-nowrap px-6 py-3.5 text-right">
                                <button type="button" x-on:click="editarCarrera(@js($carrera->only(['id', 'division_id', 'carrera_clave', 'nombre', 'horas_requeridas', 'porcentaje_creditos_minimo', 'creditos_otorgados'])))"
                                    class="group/btn relative inline-flex size-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 shadow-theme-xs transition hover:border-udg-200 hover:bg-udg-50 hover:text-udg-900 active:scale-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25"
                                    aria-label="Editar {{ $carrera->carrera_clave }}">
                                    <x-dashboard.icono nombre="editar" clase="size-4" />
                                    <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-udg-950 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-theme-md transition group-hover/btn:opacity-100" aria-hidden="true">Editar carrera</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4">
                                <x-dashboard.vacio icono="academico" titulo="Esta división aún no tiene carreras" texto="Agrégala con el botón «Carrera» para que sus alumnos puedan registrarse." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-tabla.marco>
    @empty
        <x-dashboard.vacio icono="empresa" titulo="No hay divisiones registradas" texto="Crea la primera división y después agrega sus carreras." class="rounded-2xl border border-gray-200 bg-white" />
    @endforelse

    {{-- Modal: división --}}
    <div x-show="modalDivision" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="modalDivision = false"
        class="fixed inset-0 z-[100050] flex items-center justify-center p-5" role="dialog" aria-modal="true" aria-labelledby="titulo-division">
        <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="modalDivision = false"></div>
        <form method="POST" :action="actionDivision" x-on:click.stop class="relative w-full max-w-md animate-entrada overflow-hidden rounded-2xl bg-white shadow-theme-xl motion-reduce:animate-none">
            @csrf
            <template x-if="division.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 id="titulo-division" class="font-display text-lg font-bold text-gray-900" x-text="division.id ? 'Renombrar división' : 'Nueva división'"></h3>
            </div>
            <div class="px-6 py-5">
                <label class="etiqueta" for="div-nombre">Nombre</label>
                <input id="div-nombre" type="text" name="nombre" x-model="division.nombre" required maxlength="255" placeholder="División de Estudios Jurídicos" class="campo h-11">
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                <button type="button" x-on:click="modalDivision = false" class="boton boton-secundario">Cancelar</button>
                <button type="submit" class="boton boton-primario">Guardar división</button>
            </div>
        </form>
    </div>

    {{-- Modal: carrera --}}
    <div x-show="modalCarrera" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="modalCarrera = false"
        class="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto p-4 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-carrera">
        <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="modalCarrera = false"></div>
        <form method="POST" :action="actionCarrera" x-on:click.stop class="relative w-full max-w-lg animate-entrada overflow-hidden rounded-2xl bg-white shadow-theme-xl motion-reduce:animate-none">
            @csrf
            <template x-if="carrera.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 id="titulo-carrera" class="font-display text-lg font-bold text-gray-900" x-text="carrera.id ? 'Editar carrera' : 'Nueva carrera'"></h3>
                <p class="text-xs text-gray-500">Los cambios de horas y créditos aplican a las prácticas que se activen después.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div>
                    <label class="etiqueta" for="car-div">División</label>
                    <select id="car-div" name="division_id" x-model="carrera.division_id" required class="campo h-11">
                        <option value="">Selecciona una división</option>
                        @foreach ($divisiones as $d)
                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="etiqueta" for="car-clave">Clave</label>
                        <input id="car-clave" type="text" name="carrera_clave" x-model="carrera.carrera_clave" required maxlength="10" class="campo h-11 font-mono uppercase">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="etiqueta" for="car-nombre">Nombre</label>
                        <input id="car-nombre" type="text" name="nombre" x-model="carrera.nombre" required maxlength="255" class="campo h-11">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="etiqueta" for="car-horas">Horas requeridas</label>
                        <input id="car-horas" type="number" name="horas_requeridas" x-model="carrera.horas_requeridas" required min="1" max="2000" class="campo h-11 tabular-nums">
                    </div>
                    <div>
                        <label class="etiqueta" for="car-pct">% de créditos mínimo</label>
                        <input id="car-pct" type="number" step="0.01" name="porcentaje_creditos_minimo" x-model="carrera.porcentaje_creditos_minimo" required min="0" max="100" class="campo h-11 tabular-nums">
                    </div>
                    <div>
                        <label class="etiqueta" for="car-cred">Créditos que otorga</label>
                        <input id="car-cred" type="number" name="creditos_otorgados" x-model="carrera.creditos_otorgados" required min="0" max="50" class="campo h-11 tabular-nums">
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                <button type="button" x-on:click="modalCarrera = false" class="boton boton-secundario">Cancelar</button>
                <button type="submit" class="boton boton-primario">Guardar carrera</button>
            </div>
        </form>
    </div>
</div>
@endsection
