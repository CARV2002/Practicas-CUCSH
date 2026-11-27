@extends('layouts.app')

@section('content')
@php
    use App\Enums\ProgramaEstado as PE;

    $input = 'campo h-11';
    $filtro = 'campo-filtro';
    $label = 'etiqueta';
    $vacio = ['id' => null, 'convenio_id' => '', 'carrera_id' => '', 'ciclo' => '', 'titulo' => '', 'descripcion' => '', 'actividades' => '', 'perfil_requerido' => '', 'horario' => '', 'sede' => '', 'cupos_totales' => ''];
    $conveniosJs = $convenios->map(fn ($c) => [
        'id' => $c->id,
        'nombre' => ($c->folio ?? 'Convenio').' · vigente hasta '.$c->fecha_fin_vigencia->format('d/m/Y'),
        'carreras' => $c->dictamenes->map(fn ($d) => ['id' => $d->carrera_id, 'nombre' => $d->carrera->nombre])->values(),
    ])->values();
@endphp

<div x-data="programaFormulario(@js([
    'vacio' => $vacio,
    'convenios' => $conveniosJs,
    'urlStore' => route('empresa.programas.store'),
    'urlUpdate' => route('empresa.programas.update', 0),
    // Si el servidor rechazó el programa, la ventana se reabre con lo capturado (old) y sin perder la edición
    'inicial' => $errors->any() && old('titulo') !== null ? \Illuminate\Support\Arr::except(old(), ['_token', '_method']) : null,
    'preseleccionarConvenio' => true,
]))" class="space-y-6">

    <x-pagina.encabezado titulo="Mis vacantes" descripcion="Propón vacantes para las carreras aprobadas en tu convenio. La coordinación de cada carrera las revisa antes de publicarlas a los alumnos.">
        <x-slot:acciones>
            @if ($puedeCrear)
                <button type="button" @click="nuevo()" class="boton boton-primario"><x-dashboard.icono nombre="mas" clase="size-4" /> Proponer vacante</button>
            @else
                <x-ui.accion-bloqueada :motivo="$motivoBloqueo">Proponer vacante</x-ui.accion-bloqueada>
            @endif
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif
    @if ($motivoBloqueo)
        <x-ui.aviso tipo="advertencia" titulo="Aún no puedes proponer vacantes">{{ $motivoBloqueo }}
            <a href="{{ route('empresa.convenio') }}" class="font-semibold underline underline-offset-2">Ver el estado de mi convenio</a></x-ui.aviso>
    @endif

    {{-- Vacantes que la Coordinación devolvió o cerró: qué, por qué y dónde corregir (filas marcadas en rojo) --}}
    @php $devueltas = $programas->getCollection()->filter(fn ($p) => $p->estado === PE::Rechazado || ($p->estado === PE::Cerrado && $p->observaciones_cierre)); @endphp
    @if ($devueltas->isNotEmpty())
        <x-ui.correccion :titulo="'La Coordinación te devolvió ' . $devueltas->count() . ($devueltas->count() === 1 ? ' vacante' : ' vacantes')">
            en la tabla, en la fila marcada en rojo, pulsa <strong>Editar</strong> (lápiz), corrige lo que te piden y guarda: se envía sola a revisión.
            <ul class="mt-2 space-y-1.5">
                @foreach ($devueltas as $dv)
                    <li class="rounded-lg bg-white/70 px-3 py-2 ring-1 ring-inset ring-rose-100">
                        <span class="font-semibold">{{ $dv->titulo }}</span> · {{ $dv->estado === PE::Rechazado ? 'Rechazada' : 'Cerrada por la Coordinación' }}:
                        <span class="text-rose-800">«{{ $dv->motivo_rechazo ?? $dv->observaciones_cierre }}»</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.correccion>
    @endif

    <x-tabla.marco titulo="Programas" :total="$programas->total()">
        <x-slot:herramientas>
            <x-tabla.filtros-estado :actual="request('estado', '')" :opciones="collect($estados)->map(fn ($e) => ['valor' => $e->value, 'texto' => $e->label(), 'familia' => $e->familia()])->prepend(['valor' => '', 'texto' => 'Todos'])->all()" />
            <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                @if (request('estado'))<input type="hidden" name="estado" value="{{ request('estado') }}">@endif
                <div class="relative flex-1 sm:max-w-xs">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar por título</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por título" class="campo-filtro pl-9 sm:w-full">
                </div>
                <button class="boton boton-secundario">Buscar</button>
                @if (request()->hasAny(['q', 'estado']))
                    <a href="{{ route('empresa.lista-programas') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                @endif
            </form>
        </x-slot:herramientas>

        <table class="tabla table-fixed md:[&_td:first-child>*]:min-w-0!">
            <colgroup><col><col class="w-28"><col class="w-24"><col class="w-36"><col class="w-72"></colgroup>
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
                        $datos = [
                            'id' => $pr->id, 'convenio_id' => $pr->convenio_id, 'carrera_id' => $pr->carrera_id,
                            'ciclo' => $pr->ciclo, 'titulo' => $pr->titulo, 'descripcion' => $pr->descripcion,
                            'actividades' => implode("\n", $pr->actividades ?? []),
                            'perfil_requerido' => $pr->perfil_requerido, 'horario' => $pr->horario ?? '',
                            'sede' => $pr->sede ?? '', 'cupos_totales' => $pr->cupos_totales,
                        ];
                    @endphp
                    <tr @class(['tabla-fila', 'bg-rose-50/60 shadow-[inset_4px_0_0_var(--color-rose-500)]' => $pr->estado === PE::Rechazado || ($pr->estado === PE::Cerrado && $pr->observaciones_cierre)])>
                        <td class="px-4 py-3">
                            <x-tabla.ficha :href="route('empresa.programas.postulantes', $pr)" ver="Ver postulantes" :titulo="$pr->titulo" icono="vacante"
                                :subtitulo="$pr->sede ?: 'Sede por definir'">{{ $pr->horario ?: 'Horario por definir' }}</x-tabla.ficha>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <span class="rounded-md bg-udg-50 px-1.5 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100" title="{{ $pr->carrera->nombre }}">{{ $pr->carrera->carrera_clave }}</span>
                            <p class="mt-1 text-xs text-gray-500">Ciclo {{ $pr->ciclo }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $ocupados }}<span class="font-normal text-gray-400"> / {{ $pr->cupos_totales }}</span></p>
                            <div class="mt-1.5 h-1.5 w-20 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-udg-800" style="width: {{ $pct }}%"></div></div>
                        </td>
                        <td class="px-4 py-4">
                            <x-ui.estatus :estado="$pr->estado" />
                            @if ($pr->estado === PE::Rechazado && $pr->motivo_rechazo)
                                <p class="mt-1 text-xs text-rose-600">Motivo: {{ $pr->motivo_rechazo }}</p>
                            @elseif ($pr->estado === PE::Cerrado && $pr->observaciones_cierre)
                                <p class="mt-1 max-w-[16rem] text-xs text-rose-600">Cerrada por la Coordinación: {{ $pr->observaciones_cierre }}</p>
                            @elseif ($pr->estado === PE::PendienteAprobacion)
                                <p class="mt-1 text-xs text-gray-500">La coordinación de la carrera la revisa.</p>
                            @endif
                        </td>
                        <td class="acciones whitespace-nowrap py-4 pl-2 pr-6 text-right">
                            {{-- Siempre el mismo orden y lugar: Postulantes · Editar · Cerrar · Eliminar (el hueco conserva la alineación) --}}
                            <div class="inline-grid grid-cols-[auto_2.25rem_2.25rem_2.25rem] items-center gap-2">
                                <x-ui.boton-icono :href="route('empresa.programas.postulantes', $pr)" icono="usuarios" etiqueta="Postulantes" tono="primario" con-texto />
                                <x-ui.boton-icono icono="editar" etiqueta="Editar" x-on:click="editar({{ Js::from($datos) }})" />
                                @if ($pr->estado === PE::Publicado)
                                    <x-ui.boton-icono :accion="route('empresa.programas.estado', $pr)" icono="candado" etiqueta="Cerrar postulaciones" confirmar="¿Cerrar las postulaciones de esta vacante?"><input type="hidden" name="estado" value="cerrado"></x-ui.boton-icono>
                                @elseif ($pr->estado === PE::Cerrado)
                                    <x-ui.boton-icono :accion="route('empresa.programas.estado', $pr)" icono="enviar" etiqueta="Reabrir postulaciones" tono="exito"
                                        :confirmar="$pr->observaciones_cierre ? 'La Coordinación cerró esta vacante. Al reabrirla volverá a su revisión antes de publicarse. ¿Continuar?' : '¿Reabrir las postulaciones de esta vacante?'"><input type="hidden" name="estado" value="publicado"></x-ui.boton-icono>
                                @else
                                    <span aria-hidden="true"></span>
                                @endif
                                <x-ui.boton-icono :accion="route('empresa.programas.destroy', $pr)" metodo="DELETE" icono="basura" etiqueta="Eliminar" tono="peligro" confirmar="¿Eliminar este programa? No se puede deshacer." />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-4"><x-dashboard.vacio icono="vacante" titulo="{{ request()->hasAny(['q', 'estado']) ? 'No hay programas con ese filtro' : 'Aún no tienes programas' }}" texto="{{ $puedeCrear ? 'Propón tu primera vacante con el botón de arriba.' : 'Podrás proponer vacantes cuando tu convenio esté vigente.' }}" /></td></tr>
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
            <input type="hidden" name="id" :value="p.id ?? ''">
            <h3 class="text-lg font-semibold text-gray-800" x-text="p.id ? 'Editar programa' : 'Nuevo programa'"></h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="{{ $label }}">Convenio</label>
                    <select name="convenio_id" x-model="p.convenio_id" required class="{{ $input }}" @change="p.carrera_id = ''">
                        <option value="">Selecciona un convenio</option>
                        <template x-for="c in convenios" :key="c.id">
                            <option :value="c.id" x-text="c.nombre" :selected="c.id == p.convenio_id"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}">Ciclo</label>
                    <input type="text" name="ciclo" x-model="p.ciclo" required placeholder="2027A" maxlength="5" class="{{ $input }} uppercase">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Carrera <span class="text-xs font-normal text-gray-400">(las aprobadas en el convenio)</span></label>
                <select name="carrera_id" x-model="p.carrera_id" required class="{{ $input }}">
                    <option value="">Selecciona una carrera</option>
                    <template x-for="c in carreras" :key="c.id">
                        <option :value="c.id" x-text="c.nombre" :selected="c.id == p.carrera_id"></option>
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
                <div><label class="{{ $label }}">Horario y días de asistencia</label><input type="text" name="horario" x-model="p.horario" maxlength="255" placeholder="Lun a vie, 9:00 a 13:00" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Ubicación física (sede)</label><input type="text" name="sede" x-model="p.sede" maxlength="255" placeholder="Dirección o área donde se presenta" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Cupos totales</label><input type="number" name="cupos_totales" x-model="p.cupos_totales" required min="1" max="500" class="{{ $input }}"></div>
            </div>

            <p class="text-xs text-gray-500">La Coordinación de la carrera revisará horario y actividades antes de publicarla. Cada cambio vuelve a revisión.</p>

            <div class="flex justify-end gap-2">
                <button type="button" @click="modal = false" class="boton boton-secundario">Cancelar</button>
                <button type="submit" class="boton boton-primario">Guardar</button>
            </div>
        </form>
    </div>
</div>

@endsection