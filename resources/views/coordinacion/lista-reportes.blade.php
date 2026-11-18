@extends('layouts.app')

@section('content')
    @php
        use App\Enums\ReporteEstado as RE;

        $input = 'campo';
        $filtro = 'campo-filtro';
        $card = 'tarjeta overflow-hidden pt-5';
        $th = 'tabla-th';
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Reportes de horas" descripcion="Valida los reportes de tus practicantes: solo las horas validadas cuentan para su avance." />

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
        @endif

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Resumen">
            <x-dashboard.kpi :href="request()->fullUrlWithQuery(['estado' => 'pendiente', 'page' => null])" etiqueta="Reportes por validar" :valor="$pendientes" icono="reporte" :tono="$pendientes ? 'ambar' : 'gris'" :orden="0" accion="Ver" />
            <x-dashboard.kpi etiqueta="Prácticas activas" :valor="$avance->total()" icono="reloj" tono="turquesa" :orden="1" />
            <x-dashboard.kpi etiqueta="Próximas a cerrar" :valor="$porCerrar->count()" icono="cierre" tono="cielo" :orden="2" detalle="Terminan en 30 días o ya cubren sus horas" />
        </section>

        {{-- Reportes --}}
        <x-tabla.marco titulo="Reportes" :total="$reportes->total()">
            <x-slot:herramientas>
                <x-tabla.filtros-estado :actual="request('estado', '')" :opciones="collect($estados)->map(fn ($e) => ['valor' => $e->value, 'texto' => ucfirst($e->value), 'familia' => $e->familia()])->prepend(['valor' => '', 'texto' => 'Todos'])->all()" />
                <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                    @if (request('estado'))<input type="hidden" name="estado" value="{{ request('estado') }}">@endif
                    <div class="relative flex-1 sm:max-w-xs">
                        <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <label for="q" class="sr-only">Buscar por nombre o código</label>
                        <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o código" class="campo-filtro pl-9 sm:w-full">
                    </div>
                    @if ($carreras->count() > 1)
                        <select name="carrera_id" class="campo-filtro" aria-label="Carrera" onchange="this.form.requestSubmit()">
                            <option value="">Todas las carreras</option>
                            @foreach ($carreras as $c)
                                <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select name="tipo" class="campo-filtro" aria-label="Tipo de reporte" onchange="this.form.requestSubmit()">
                        <option value="">Cualquier tipo</option>
                        @foreach ($tipos as $t)
                            <option value="{{ $t->value }}" @selected(request('tipo') === $t->value)>{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    <button class="boton boton-secundario">Buscar</button>
                    @if (request()->hasAny(['q', 'carrera_id', 'tipo', 'estado']))
                        <a href="{{ route('coordinacion.lista-reportes') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                    @endif
                </form>
            </x-slot:herramientas>

            <table class="tabla">
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Practicante</th>
                        <th class="tabla-th">Reporte</th>
                        <th class="tabla-th text-right">Horas</th>
                        <th class="tabla-th">Estado y comentarios</th>
                        <th class="tabla-th px-6">Validación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($reportes as $r)
                        @php
                            $al = $r->practica->postulacion->alumno;
                            $pdf = $r->getFirstMedia('reporte_firmado');
                        @endphp
                        <tr class="align-top tabla-fila">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <x-ui.user-avatar :user="$al->user" size="mt-0.5 size-9 shrink-0 text-xs ring-2 ring-white" />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $al->user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }}</p>
                                        <p class="text-xs text-gray-500">{{ $r->practica->postulacion->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm font-medium text-gray-900">{{ $r->tipo->label() }}</p>
                                <p class="text-xs text-gray-500">{{ $r->periodo_inicio->format('d/m/Y') }} – {{ $r->periodo_fin->format('d/m/Y') }}</p>
                                @if ($pdf)
                                    <a href="{{ route('coordinacion.reportes.archivos', [$r, $pdf]) }}" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Reporte firmado</a>
                                @else
                                    <p class="dato-vacio mt-1">El alumno aún no sube el PDF firmado</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <p class="font-display text-lg font-bold tabular-nums text-gray-900">{{ $r->horas_reportadas }}<span class="ml-0.5 font-sans text-xs font-medium text-gray-500">h</span></p>
                            </td>
                            <td class="px-4 py-4">
                                <x-ui.estatus :estado="$r->estado" />
                                <div class="mt-2 max-w-[16rem] space-y-1.5 text-xs leading-relaxed">
                                    @if ($r->evaluacion_profesor)<p class="text-gray-600"><span class="font-semibold text-gray-800">Coordinación:</span> {{ $r->evaluacion_profesor }}</p>@endif
                                    @if ($r->retroalimentacion_empresa)
                                        <p class="text-gray-600"><span class="font-semibold text-gray-800">Empresa:</span> {{ $r->retroalimentacion_empresa }}</p>
                                    @else
                                        <p class="dato-vacio">Sin comentarios de la empresa</p>
                                    @endif
                                </div>
                            </td>
                            <td class="acciones px-6 py-4">
                                @if ($r->estado === RE::Pendiente)
                                    <form method="POST" action="{{ route('coordinacion.reportes.resolver', $r) }}" class="flex min-w-56 flex-col gap-2">
                                        @csrf
                                        <label class="sr-only" for="eval-{{ $r->id }}">Evaluación o motivo</label>
                                        <input id="eval-{{ $r->id }}" type="text" name="evaluacion_profesor" placeholder="Evaluación (o motivo si rechazas)" maxlength="2000" class="campo h-9 text-xs">
                                        <div class="grid grid-cols-2 gap-2">
                                            <button name="estado" value="validado" data-sin-bloqueo="1" class="inline-flex h-9 items-center justify-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95"><x-dashboard.icono nombre="check" clase="size-3.5" /> Validar</button>
                                            <button name="estado" value="rechazado" data-sin-bloqueo="1" class="inline-flex h-9 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-white text-xs font-semibold text-rose-600 transition hover:bg-rose-50 active:scale-95"><x-dashboard.icono nombre="equis" clase="size-3.5" /> Rechazar</button>
                                        </div>
                                    </form>
                                @else
                                    <span class="dato-vacio">Ya resuelto</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-4"><x-dashboard.vacio icono="reporte" titulo="{{ request()->hasAny(['q', 'carrera_id', 'tipo', 'estado']) ? 'No hay reportes con ese filtro' : 'No hay reportes todavía' }}" texto="Los reportes que suban tus practicantes aparecerán aquí para validarlos." /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:pie>{{ $reportes->links() }}</x-slot:pie>
        </x-tabla.marco>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Próximas a cerrar --}}
            <x-dashboard.panel titulo="Prácticas próximas a cerrar" icono="cierre" descripcion="Terminan en los próximos 30 días o ya cubren las horas requeridas.">
                @forelse ($porCerrar as $p)
                    @php $al = $p->postulacion->alumno; @endphp
                    <div class="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-3 transition-colors hover:bg-gray-50">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-ui.user-avatar :user="$al->user" size="size-9 shrink-0 text-xs" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $al->user->name }}</p>
                                <p class="text-xs text-gray-500">Termina {{ $p->fecha_fin->format('d/m/Y') }} · {{ $p->progresoTexto() }} h</p>
                            </div>
                        </div>
                        @if ($p->horasCompletas())
                            <x-ui.estatus familia="menta" texto="Horas completas" class="shrink-0" />
                        @else
                            <x-ui.estatus familia="ambar" :texto="'Faltan ' . ($p->horas_requeridas - $p->horas_acumuladas) . ' h'" class="shrink-0" />
                        @endif
                    </div>
                @empty
                    <x-dashboard.vacio titulo="Ninguna por ahora" texto="Aquí verás las prácticas que están por terminar." />
                @endforelse
            </x-dashboard.panel>

            {{-- Avance por practicante --}}
            <x-tabla.marco class="xl:col-span-2" titulo="Avance por practicante" descripcion="Horas validadas contra horas requeridas de cada práctica activa." :total="$avance->total()">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th class="tabla-th px-6">Practicante</th>
                            <th class="tabla-th">Progreso</th>
                            <th class="tabla-th">Termina</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($avance as $p)
                            @php
                                $al = $p->postulacion->alumno;
                                $pct = $p->horas_requeridas > 0 ? min(100, round($p->horas_acumuladas / $p->horas_requeridas * 100)) : 0;
                                $atrasada = $p->fecha_fin->isPast() && !$p->horasCompletas();
                            @endphp
                            <tr class="tabla-fila">
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <x-ui.user-avatar :user="$al->user" size="size-9 shrink-0 text-xs ring-2 ring-white" />
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900">{{ $al->user->name }}</p>
                                            <p class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="w-48">
                                        <div class="mb-1 flex items-baseline justify-between">
                                            <span class="text-sm font-bold text-gray-900">{{ (int) $p->horas_acumuladas }}<span class="text-xs font-normal text-gray-400"> / {{ $p->horas_requeridas }} h</span></span>
                                            <span class="text-xs font-semibold text-udg-900">{{ $pct }}%</span>
                                        </div>
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $pct }}%"></div></div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5">
                                    <p class="text-sm {{ $atrasada ? 'font-semibold text-rose-600' : 'text-gray-800' }}">{{ $p->fecha_fin->format('d/m/Y') }}</p>
                                    @if ($atrasada)<x-ui.estatus familia="rosa" texto="Vencida sin completar" class="mt-1" />@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-4"><x-dashboard.vacio icono="reloj" titulo="No hay prácticas activas" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
                <x-slot:pie>{{ $avance->links() }}</x-slot:pie>
            </x-tabla.marco>
        </div>
    </div>
@endsection
