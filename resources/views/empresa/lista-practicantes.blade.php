@extends('layouts.app')

@section('content')
    @php
        use App\Enums\PracticaEstado as PrE;

        $filtro = 'campo-filtro';
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Practicantes" descripcion="Alumnos que realizan su práctica en tu organización. Sus horas cuentan cuando la coordinación de su carrera valida cada reporte." />

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Resumen">
            <x-dashboard.kpi :href="request()->fullUrlWithQuery(['estado' => 'en_curso', 'page' => null])" etiqueta="Practicantes activos" :valor="$resumen['activos']" icono="usuarios" tono="turquesa" :orden="0" accion="Ver" />
            <x-dashboard.kpi :href="request()->fullUrlWithQuery(['estado' => 'en_cierre', 'page' => null])" etiqueta="En cierre" :valor="$resumen['cierre']" icono="cierre" tono="ambar" :orden="1" accion="Ver" />
            <x-dashboard.kpi :href="request()->fullUrlWithQuery(['estado' => 'acreditada', 'page' => null])" etiqueta="Acreditados" :valor="$resumen['acreditados']" icono="vigente" tono="menta" :orden="2" accion="Ver" />
        </section>

        <x-tabla.marco titulo="Practicantes asignados" :total="$practicas->total()">
            <x-slot:herramientas>
                <x-tabla.filtros-estado :actual="request('estado', '')" :opciones="collect($estados)->map(fn ($e) => ['valor' => $e->value, 'texto' => $e->label(), 'familia' => $e->familia()])->prepend(['valor' => '', 'texto' => 'Todos'])->all()" />
                <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                    @if (request('estado'))<input type="hidden" name="estado" value="{{ request('estado') }}">@endif
                    <div class="relative flex-1 sm:max-w-xs">
                        <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <label for="q" class="sr-only">Buscar por nombre o código</label>
                        <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o código" class="campo-filtro pl-9 sm:w-full">
                    </div>
                    <select name="programa_id" class="campo-filtro sm:max-w-xs" aria-label="Programa" onchange="this.form.requestSubmit()">
                        <option value="">Todos los programas</option>
                        @foreach ($programas as $p)
                            <option value="{{ $p->id }}" @selected((int) request('programa_id') === $p->id)>{{ $p->titulo }} ({{ $p->ciclo }})</option>
                        @endforeach
                    </select>
                    <button class="boton boton-secundario">Buscar</button>
                    @if (request()->hasAny(['q', 'programa_id', 'estado']))
                        <a href="{{ route('empresa.lista-practicantes') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                    @endif
                </form>
            </x-slot:herramientas>

            <table class="tabla">
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Practicante</th>
                        <th class="tabla-th">Horas validadas</th>
                        <th class="tabla-th">Periodo</th>
                        <th class="tabla-th">Supervisor UdeG</th>
                        <th class="tabla-th">Estado</th>
                        <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($practicas as $pr)
                        @php
                            $al = $pr->postulacion->alumno;
                            $horas = (int) $pr->horas_acumuladas;
                            $pct = $pr->horas_requeridas > 0 ? min(100, round($horas / $pr->horas_requeridas * 100)) : 0;
                        @endphp
                        <tr class="tabla-fila">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <x-ui.user-avatar :user="$al->user" size="mt-0.5 size-9 shrink-0 text-xs ring-2 ring-white" />
                                    <div class="min-w-0">
                                        <a href="{{ route('empresa.practicantes.show', $pr) }}" class="font-semibold text-gray-900 hover:text-udg-900 hover:underline underline-offset-2">{{ $al->user->name }}</a>
                                        <p class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->nombre }}</p>
                                        <p class="text-xs text-gray-500">{{ $pr->postulacion->programa->titulo }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="w-40">
                                    <div class="mb-1 flex items-baseline justify-between">
                                        <span class="text-sm font-bold text-gray-900">{{ $horas }}<span class="text-xs font-normal text-gray-400"> / {{ $pr->horas_requeridas }} h</span></span>
                                        <span class="text-xs font-semibold text-udg-900">{{ $pct }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $pct }}%"></div></div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm text-gray-800">{{ $pr->fecha_inicio->format('d/m/Y') }}</p>
                                <p class="text-xs text-gray-500">al {{ $pr->fecha_fin->format('d/m/Y') }}</p>
                            </td>
                            <td class="px-4 py-4">{!! $pr->supervisor?->nombre ? '<span class="text-sm text-gray-800">' . e($pr->supervisor->nombre) . '</span>' : '<span class="dato-vacio">Sin supervisor asignado</span>' !!}</td>
                            <td class="whitespace-nowrap px-4 py-4"><x-ui.estatus :estado="$pr->estado" /></td>
                            <td class="acciones whitespace-nowrap px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-ui.boton-icono :href="route('empresa.practicantes.show', $pr)" icono="comentario" etiqueta="Reportes" tono="primario" con-texto />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-4"><x-dashboard.vacio icono="usuarios" titulo="{{ request()->hasAny(['q', 'programa_id', 'estado']) ? 'No hay practicantes con ese filtro' : 'Aún no tienes practicantes asignados' }}" texto="Cuando la coordinación active la práctica de un alumno en tus vacantes, aparecerá aquí." /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:pie>{{ $practicas->links() }}</x-slot:pie>
        </x-tabla.marco>
    </div>
@endsection
