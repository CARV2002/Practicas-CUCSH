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

<div x-data="programaFormulario(@js([
    'vacio' => $vacio,
    'convenios' => $conveniosJs,
    'urlStore' => route('coordinacion.programas.store'),
    'urlUpdate' => route('coordinacion.programas.update', 0),
]))" class="space-y-6">

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
                    <th class="tabla-th px-6">Programa <span class="font-normal normal-case text-gray-400">· clic para ver la ficha</span></th>
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
                        $datos = [
                            'id' => $pr->id, 'modalidad' => $pr->modalidad->value, 'convenio_id' => $pr->convenio_id ?? '',
                            'carrera_id' => $pr->carrera_id, 'ciclo' => $pr->ciclo, 'titulo' => $pr->titulo,
                            'descripcion' => $pr->descripcion, 'actividades' => implode("\n", $pr->actividades ?? []),
                            'perfil_requerido' => $pr->perfil_requerido, 'horario' => $pr->horario ?? '',
                            'sede' => $pr->sede ?? '', 'cupos_totales' => $pr->cupos_totales,
                        ];
                        $faltaAprobar = $bloqueosAprobar[$pr->id] ?? null;
                        $ficha = [
                            'titulo' => $pr->titulo,
                            'organizacion' => $pr->convenio?->empresa->razon_social ?? 'Proyecto interno (sin convenio)',
                            'convenio' => $pr->convenio ? 'Convenio ' . ($pr->convenio->folio ?: 'sin folio') . ($pr->convenio->fecha_fin_vigencia ? ' · vigente al ' . $pr->convenio->fecha_fin_vigencia->format('d/m/Y') : '') : null,
                            'carrera' => $pr->carrera->carrera_clave . ' · ' . $pr->carrera->nombre,
                            'ciclo' => $pr->ciclo,
                            'modalidad' => $pr->esInterno() ? 'Proyecto interno' : 'Vacante externa',
                            'estado' => $pr->estado->label(),
                            'descripcion' => $pr->descripcion,
                            'actividades' => array_values(array_filter($pr->actividades ?? [])),
                            'perfil' => $pr->perfil_requerido,
                            'horario' => $pr->horario,
                            'sede' => $pr->sede,
                            'cupos' => $pr->cupos_totales,
                            'ocupados' => $ocupados,
                            'enviada' => $pr->created_at?->format('d/m/Y'),
                            'motivoRechazo' => $pr->estado === \App\Enums\ProgramaEstado::Rechazado ? $pr->motivo_rechazo : null,
                            'pendiente' => $pendiente,
                            'bloqueo' => $faltaAprobar,
                            'urlAprobar' => route('coordinacion.programas.aprobar', $pr),
                            'urlRechazar' => route('coordinacion.programas.rechazar', $pr),
                            'urlEstado' => route('coordinacion.programas.estado', $pr),
                            'observacionesCierre' => $pr->observaciones_cierre,
                            'cerrar' => false,
                        ];
                    @endphp
                    @php
                        $E = \App\Enums\ProgramaEstado::class;
                        $sinPostulaciones = !$pr->postulaciones_count;
                    @endphp
                    <tr @class(['tabla-fila', 'bg-amber-50/50 shadow-[inset_3px_0_0_var(--color-amber-400)]' => $pendiente])>
                        <td class="px-4 py-3">
                            {{-- Toda la celda abre la ficha completa: cambia de color y muestra «Ver ficha» al pasar el cursor --}}
                            <button type="button" x-on:click="ver({{ Js::from($ficha) }})" title="Ver la ficha completa"
                                class="group/ficha flex w-full items-start gap-3 rounded-xl px-2 py-1.5 text-left transition duration-200 ease-out hover:bg-udg-50/80 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:transition-none">
                                <span @class([
                                    'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset transition duration-200 ease-out group-hover/ficha:scale-105 group-hover/ficha:bg-udg-800 group-hover/ficha:text-white group-hover/ficha:ring-udg-800 motion-reduce:transform-none',
                                    'bg-udg-50 text-udg-900 ring-udg-100' => !$pr->esInterno(),
                                    'bg-oro-50 text-oro-700 ring-oro-100' => $pr->esInterno(),
                                ])>
                                    <x-dashboard.icono :nombre="$pr->esInterno() ? 'academico' : 'empresa'" clase="size-[18px]" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold leading-snug text-gray-900 transition-colors duration-200 group-hover/ficha:text-udg-800">{{ $pr->titulo }}</span>
                                    <span class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                                        <span class="truncate">{{ $pr->convenio?->empresa->razon_social ?? 'Proyecto interno · sin convenio' }}</span>
                                        <span class="inline-flex shrink-0 -translate-x-1 items-center gap-0.5 font-semibold text-udg-800 opacity-0 transition duration-200 group-hover/ficha:translate-x-0 group-hover/ficha:opacity-100 group-focus-visible/ficha:opacity-100 motion-reduce:transform-none">
                                            Ver ficha <x-dashboard.icono nombre="flecha" clase="size-3" />
                                        </span>
                                    </span>
                                    @if ($pendiente)
                                        <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700">
                                            <span class="size-1.5 animate-pulse rounded-full bg-amber-500 motion-reduce:animate-none" aria-hidden="true"></span>
                                            Espera tu revisión{{ $pr->created_at ? ' · enviada el ' . $pr->created_at->format('d/m/Y') : '' }}
                                        </span>
                                    @endif
                                </span>
                            </button>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <span class="rounded-md bg-udg-50 px-1.5 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100" title="{{ $pr->carrera->nombre }}">{{ $pr->carrera->carrera_clave }}</span>
                            <p class="mt-1 text-xs text-gray-500">Ciclo {{ $pr->ciclo }} · {{ $pr->esInterno() ? 'Interna' : 'Externa' }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            <p class="text-sm font-semibold tabular-nums text-gray-900">{{ $ocupados }}<span class="font-normal text-gray-400"> / {{ $pr->cupos_totales }}</span></p>
                            <div class="mt-1.5 h-1.5 w-20 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-udg-800 transition-[width] duration-500" style="width: {{ $pct }}%"></div></div>
                        </td>
                        <td class="px-4 py-4">
                            <x-ui.estatus :estado="$pr->estado" />
                            @if ($pr->motivo_rechazo && $pr->estado === $E::Rechazado)
                                <p class="mt-1 max-w-[14rem] text-xs text-rose-600">{{ $pr->motivo_rechazo }}</p>
                            @elseif ($pr->observaciones_cierre && $pr->estado === $E::Cerrado)
                                <p class="mt-1 max-w-[14rem] text-xs text-rose-600">Cerrada por la coordinación: {{ $pr->observaciones_cierre }}</p>
                            @endif
                        </td>
                        <td class="acciones whitespace-nowrap py-4 pl-2 pr-6">
                            {{--
                                Dos columnas fijas en todas las filas: [acción principal] [barra de herramientas].
                                Internos: los administra la coordinación (publicar, cerrar, reabrir, editar, eliminar).
                                Externos: revisar (aprobar/rechazar) y, si hay alguna irregularidad, cerrar con observaciones;
                                reabrir, editar o eliminar le corresponde a la empresa.
                            --}}
                            <div class="ml-auto grid w-max grid-cols-[11.5rem_6.9rem] items-center gap-2">
                                {{-- Columna 1: lo que toca hacer ahora (o por qué no hay nada que hacer) --}}
                                @if ($pendiente)
                                    <x-ui.accion-principal tono="primario" icono="ver" etiqueta="Revisar vacante" x-on:click="ver({{ Js::from($ficha) }})" />
                                @elseif ($pr->esInterno() && $pr->estado === $E::Borrador)
                                    <x-ui.accion-principal tono="exito" icono="enviar" etiqueta="Publicar" :accion="route('coordinacion.programas.estado', $pr)"
                                        confirmar="¿Publicar «{{ $pr->titulo }}»? Los alumnos de la carrera podrán verlo y postularse."><input type="hidden" name="estado" value="publicado"></x-ui.accion-principal>
                                @elseif ($pr->esInterno() && $pr->estado === $E::Cerrado)
                                    <x-ui.accion-principal icono="enviar" etiqueta="Reabrir postulaciones" :accion="route('coordinacion.programas.estado', $pr)"
                                        confirmar="¿Reabrir las postulaciones de «{{ $pr->titulo }}»? Los alumnos podrán postularse de nuevo."><input type="hidden" name="estado" value="publicado"></x-ui.accion-principal>
                                @elseif ($pr->esInterno() && $pr->estado === $E::Publicado)
                                    <x-ui.accion-principal icono="candado" etiqueta="Cerrar postulaciones" :accion="route('coordinacion.programas.estado', $pr)"
                                        confirmar="¿Cerrar las postulaciones de «{{ $pr->titulo }}»? Ya no llegarán alumnos nuevos; los que están en trámite siguen igual."><input type="hidden" name="estado" value="cerrado"></x-ui.accion-principal>
                                @elseif ($pr->estado === $E::Publicado)
                                    {{-- Externa publicada: se cierra desde la ficha, escribiendo las observaciones --}}
                                    <x-ui.accion-principal icono="candado" etiqueta="Cerrar postulaciones" x-on:click="ver({{ Js::from(array_merge($ficha, ['cerrar' => true])) }})" />
                                @else
                                    {{-- Externa cerrada, rechazada o sin enviar: la decisión es de la empresa --}}
                                    <p class="flex items-center justify-center gap-1.5 text-center text-xs font-medium text-gray-400">
                                        <x-dashboard.icono nombre="reloj" clase="size-3.5 shrink-0" />
                                        {{ match ($pr->estado) { $E::Rechazado => 'Espera corrección de la empresa', $E::Cerrado => 'La empresa decide si la reabre', default => 'La empresa aún no la envía' } }}
                                    </p>
                                @endif

                                {{-- Columna 2: herramientas, solo para proyectos internos (los administra la coordinación) --}}
                                @if ($pr->esInterno())
                                    <x-ui.herramientas class="justify-self-start">
                                        @if ($pr->estado === $E::Publicado && $sinPostulaciones)
                                            <x-ui.herramienta icono="deshacer" etiqueta="Despublicar para corregir" :accion="route('coordinacion.programas.estado', $pr)"
                                                confirmar="¿Despublicar «{{ $pr->titulo }}»? Los alumnos dejarán de verlo hasta que lo publiques de nuevo."><input type="hidden" name="estado" value="borrador"></x-ui.herramienta>
                                        @endif
                                        <x-ui.herramienta icono="editar" etiqueta="Editar" x-on:click="editar({{ Js::from($datos) }})" />
                                        @if ($sinPostulaciones)
                                            <x-ui.herramienta icono="basura" etiqueta="Eliminar" tono="peligro" metodo="DELETE" :accion="route('coordinacion.programas.destroy', $pr)"
                                                confirmar="¿Seguro que quieres eliminar «{{ $pr->titulo }}»? Se borra definitivamente y no se puede deshacer." />
                                        @endif
                                    </x-ui.herramientas>
                                @else
                                    <span aria-hidden="true"></span>
                                @endif
                            </div>
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

    {{-- Ventana de revisión de la vacante --}}
    <div x-show="detalle" x-cloak x-transition.opacity.duration.200ms x-on:keydown.escape.window="cerrarDetalle()"
        class="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="titulo-vacante">
        <div class="fixed inset-0 bg-udg-950/50 backdrop-blur-sm" x-on:click="cerrarDetalle()"></div>
        <template x-if="detalle">
            <div x-on:click.stop class="relative my-8 w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-theme-xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="detalle.modalidad + ' · ' + detalle.estado"></p>
                        <h3 id="titulo-vacante" class="font-display text-lg font-bold text-gray-900" x-text="detalle.titulo"></h3>
                        <p class="text-sm text-gray-600" x-text="detalle.organizacion"></p>
                        <p class="text-xs text-gray-500" x-show="detalle.convenio" x-text="detalle.convenio"></p>
                    </div>
                    <button type="button" x-on:click="cerrarDetalle()" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Cerrar">
                        <x-dashboard.icono nombre="equis" clase="size-5" />
                    </button>
                </div>

                <div class="space-y-5 px-6 py-5">
                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Puesto</dt><dd class="text-sm font-semibold text-gray-900" x-text="detalle.titulo"></dd></div>
                        <div class="rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Carrera</dt><dd class="text-sm font-semibold text-gray-900" x-text="detalle.carrera"></dd></div>
                        <div class="rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Ciclo</dt><dd class="text-sm font-semibold text-gray-900" x-text="detalle.ciclo"></dd></div>
                        <div class="rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Cupos disponibles</dt><dd class="text-sm font-semibold text-gray-900"><span x-text="detalle.cupos"></span> <span class="font-normal text-gray-500" x-show="detalle.ocupados > 0" x-text="'(' + detalle.ocupados + ' ocupados)'"></span></dd></div>
                        <div class="col-span-2 rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Horario y días de asistencia</dt><dd class="text-sm font-semibold text-gray-900" x-text="detalle.horario || 'No lo indicó'"></dd></div>
                        <div class="col-span-2 rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Ubicación física (sede)</dt><dd class="text-sm font-semibold text-gray-900" x-text="detalle.sede || 'No la indicó'"></dd></div>
                    </dl>

                    <section>
                        <h4 class="text-sm font-semibold text-gray-900">Descripción</h4>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700" x-text="detalle.descripcion"></p>
                    </section>
                    <section>
                        <h4 class="text-sm font-semibold text-gray-900">Actividades y tareas del estudiante</h4>
                        <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-700">
                            <template x-for="(act, i) in detalle.actividades" :key="i"><li x-text="act"></li></template>
                        </ol>
                    </section>
                    <section>
                        <h4 class="text-sm font-semibold text-gray-900">Perfil requerido</h4>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700" x-text="detalle.perfil"></p>
                    </section>
                    <p class="text-xs text-gray-500" x-show="detalle.enviada" x-text="'Registrada el ' + detalle.enviada"></p>

                    <div x-show="detalle.motivoRechazo" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                        <strong>Observaciones del rechazo:</strong> <span x-text="detalle.motivoRechazo"></span>
                    </div>
                    <div x-show="detalle.observacionesCierre" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <strong>La coordinación la había cerrado:</strong> <span x-text="detalle.observacionesCierre"></span>
                    </div>

                    {{-- Cerrar una vacante externa publicada: solo por alguna irregularidad y con observaciones para la empresa --}}
                    <template x-if="detalle.cerrar">
                        <form method="POST" :action="detalle.urlEstado" id="form-cierre-vacante" class="space-y-2 rounded-xl border border-gray-200 p-4"
                            x-on:submit="if (motivo.trim().length < 10) { $event.preventDefault(); $refs.obsCierre.focus(); }">
                            @csrf
                            <input type="hidden" name="estado" value="cerrado">
                            <label for="obs-cierre" class="etiqueta">Observaciones <span class="font-normal text-gray-400">(obligatorias: por qué cierras la vacante; le llegan a la empresa)</span></label>
                            <textarea id="obs-cierre" name="observaciones" x-ref="obsCierre" x-model="motivo" rows="3" maxlength="1000"
                                placeholder="Ej. El horario publicado no coincide con el que reportan los alumnos." class="campo h-auto py-2.5"></textarea>
                            <p class="ayuda">Los alumnos dejarán de verla. Reabrirla le corresponde a la empresa y volverá a tu revisión.</p>
                        </form>
                    </template>

                    {{-- Decisión: solo para vacantes pendientes --}}
                    <template x-if="detalle.pendiente">
                        <div class="space-y-3 rounded-xl border border-gray-200 p-4">
                            <div x-show="detalle.bloqueo" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800" x-text="'Aún no se puede aprobar: ' + detalle.bloqueo"></div>
                            <form method="POST" :action="detalle.urlRechazar" id="form-rechazo-vacante"
                                x-on:submit="if (motivo.trim() === '') { $event.preventDefault(); $refs.motivo.focus(); }">
                                @csrf
                                <label for="motivo-vacante" class="etiqueta">Observaciones del rechazo <span class="font-normal text-gray-400">(obligatorio si la rechazas; le llega a la empresa)</span></label>
                                <textarea id="motivo-vacante" name="motivo" x-ref="motivo" x-model="motivo" rows="3" maxlength="1000"
                                    placeholder="Ej. Las actividades son administrativas y no corresponden al perfil de la carrera." class="campo h-auto py-2.5"></textarea>
                            </form>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                    <button type="button" x-on:click="cerrarDetalle()" class="boton boton-secundario" x-text="(detalle.pendiente || detalle.cerrar) ? 'Cancelar' : 'Cerrar'"></button>
                    <template x-if="detalle.cerrar">
                        <button type="submit" form="form-cierre-vacante" class="boton boton-peligro" :disabled="motivo.trim().length < 10"
                            :title="motivo.trim().length < 10 ? 'Escribe las observaciones para poder cerrarla' : ''">Cerrar postulaciones</button>
                    </template>
                    <template x-if="detalle.pendiente">
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" form="form-rechazo-vacante" class="boton boton-peligro" :disabled="motivo.trim() === ''"
                                :title="motivo.trim() === '' ? 'Escribe el motivo para poder rechazar' : ''">✕ Rechazar</button>
                            <form method="POST" :action="detalle.urlAprobar">
                                @csrf
                                <button type="submit" class="boton boton-primario" :disabled="!!detalle.bloqueo">✓ Aprobar y publicar</button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

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
                <div><label class="{{ $label }}">Horario y días de asistencia</label><input type="text" name="horario" x-model="p.horario" maxlength="255" placeholder="Lun a vie, 9:00 a 13:00" class="{{ $input }}"></div>
                <div><label class="{{ $label }}">Ubicación física (sede)</label><input type="text" name="sede" x-model="p.sede" maxlength="255" placeholder="Dirección o área donde se presenta" class="{{ $input }}"></div>
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

@endsection