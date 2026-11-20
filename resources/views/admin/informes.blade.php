@extends('layouts.app')

@section('content')
@php
    $exportes = [
        ['tipo' => 'practicas', 'titulo' => 'Prácticas', 'icono' => 'reloj', 'desc' => 'Una fila por práctica: alumno, empresa, fechas, horas validadas y estado.', 'fechas' => 'Fecha de inicio de la práctica', 'ciclo' => false],
        ['tipo' => 'postulaciones', 'titulo' => 'Postulaciones', 'icono' => 'usuarios', 'desc' => 'Quién se postuló a qué programa, con su porcentaje de créditos.', 'fechas' => 'Fecha de postulación', 'ciclo' => false],
        ['tipo' => 'convenios', 'titulo' => 'Convenios', 'icono' => 'documento', 'desc' => 'Estado, vigencia, días para vencer y carreras aprobadas.', 'fechas' => 'Fecha de recepción', 'ciclo' => false],
        ['tipo' => 'programas', 'titulo' => 'Programas', 'icono' => 'vacante', 'desc' => 'Ofertas con cupos totales, ocupados y disponibles.', 'fechas' => null, 'ciclo' => true],
        ['tipo' => 'empresas', 'titulo' => 'Empresas', 'icono' => 'empresa', 'desc' => 'Cada organización con su situación (vigente, por vencer, en trámite, vencida, rechazada, sin solicitud o inactiva), representante y contacto.', 'fechas' => null, 'ciclo' => false, 'carrera' => false],
    ];
    // Vinculación ve y exporta convenios y empresas; lo académico es del administrador
    $exportes = $completo ? $exportes : array_values(array_filter($exportes, fn ($e) => in_array($e['tipo'], ['convenios', 'empresas'], true)));
    $estados['empresas'] = collect(\App\Models\Empresa::SITUACIONES)->map(fn ($s) => $s[0])->all();
    $maxEtapa = max(1, collect($etapas)->map(fn ($e) => max($e['promedio'], $e['maximo'] ?? 0))->max() ?? 1);
@endphp

<div class="space-y-6">
    <x-pagina.encabezado :titulo="$completo ? 'Informes' : 'Informes de convenios'"
        :descripcion="$completo ? 'Panorama de prácticas y convenios, y descargas en CSV.' : 'Cuántos convenios hay, cuánto tarda cada etapa y descargas en CSV.'" />

    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="No se pudo generar el informe"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    {{-- Resumen de prácticas (solo administrador) --}}
    @if ($completo)
        <x-tabla.marco titulo="Resumen de prácticas" descripcion="Filtra por carrera y por fecha de inicio de la práctica.">
            <x-slot:herramientas>
                <form method="GET" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                    <div>
                        <label for="r-carrera" class="etiqueta">Carrera</label>
                        <select id="r-carrera" name="carrera_id" class="campo-filtro sm:w-56">
                            <option value="">Todas las carreras</option>
                            @foreach ($carreras as $c)
                                <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="r-desde" class="etiqueta">Desde</label><input id="r-desde" type="date" name="desde" value="{{ request('desde') }}" class="campo-filtro sm:w-40"></div>
                    <div><label for="r-hasta" class="etiqueta">Hasta</label><input id="r-hasta" type="date" name="hasta" value="{{ request('hasta') }}" class="campo-filtro sm:w-40"></div>
                    <button class="boton boton-secundario">Aplicar</button>
                </form>
                <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ([['Prácticas', $resumen['total'], 'text-gray-900'], ['Activas', $resumen['activas'], 'text-teal-700'], ['Acreditadas', $resumen['acreditadas'], 'text-emerald-700'], ['Horas validadas', number_format($resumen['horas']), 'text-udg-900']] as [$k, $v, $color])
                        <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-inset ring-gray-100">
                            <dt class="text-xs font-medium text-gray-500">{{ $k }}</dt>
                            <dd class="mt-1 font-display text-2xl font-bold tabular-nums {{ $color }}">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-slot:herramientas>

            <table class="tabla">
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Carrera</th>
                        <th class="tabla-th text-right">Total</th>
                        <th class="tabla-th text-right">Activas</th>
                        <th class="tabla-th text-right">Acreditadas</th>
                        <th class="tabla-th text-right">Canceladas</th>
                        <th class="tabla-th">Avance de horas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($porCarrera as $c)
                        <tr class="tabla-fila">
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-md bg-udg-50 px-1.5 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100">{{ $c['clave'] }}</span>
                                    <span class="text-sm text-gray-800">{{ $c['nombre'] }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right text-sm font-semibold text-gray-900">{{ $c['total'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right text-sm text-gray-700">{{ $c['activas'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right text-sm text-emerald-700">{{ $c['acreditadas'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-right text-sm {{ $c['canceladas'] ? 'text-rose-600' : 'text-gray-400' }}">{{ $c['canceladas'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-28 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $c['avance'] }}%"></div></div>
                                    <span class="text-xs font-semibold tabular-nums text-gray-600">{{ $c['avance'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-4"><x-dashboard.vacio icono="informe" titulo="No hay prácticas con esos filtros" texto="Cambia la carrera o el rango de fechas." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-tabla.marco>
    @endif

    {{-- Convenios --}}
    <section aria-label="Resumen de convenios" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.kpi :href="route('admin.lista-convenios', ['estado' => 'formalizado'])" etiqueta="Convenios vigentes" :valor="$conveniosResumen['vigentes']" icono="vigente" tono="menta" :orden="0" accion="Ver convenios" />
        <x-dashboard.kpi :href="route('admin.lista-convenios')" etiqueta="Vencen en 60 días" :valor="$conveniosResumen['por_vencer']" icono="calendario" :tono="$conveniosResumen['por_vencer'] ? 'ambar' : 'gris'" :orden="1"
            :alerta="$conveniosResumen['por_vencer'] ? 'Renovar' : null" accion="Ver convenios" />
        <x-dashboard.kpi :href="route('admin.lista-convenios')" etiqueta="En trámite" :valor="$conveniosResumen['en_tramite']" icono="reloj" tono="cielo" :orden="2" accion="Ver convenios" />
    </section>

    <x-tabla.marco titulo="Cuánto tarda cada etapa" descripcion="Promedio real de las etapas ya cerradas, en días hábiles, contra el máximo documentado.">
        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th px-6">Etapa</th>
                    <th class="tabla-th">Responsable</th>
                    <th class="tabla-th text-right">Casos</th>
                    <th class="tabla-th">Promedio contra máximo</th>
                    <th class="tabla-th text-right">Máximo documentado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($etapas as $e)
                    @php $excede = $e['maximo'] !== null && $e['promedio'] > $e['maximo']; @endphp
                    <tr class="tabla-fila">
                        <td class="px-6 py-3.5 text-sm font-medium text-gray-900">{{ $e['etapa'] }}</td>
                        <td class="px-4 py-3.5 text-sm text-gray-600">{{ $e['responsable'] }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-right text-sm text-gray-700">{{ $e['casos'] }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="relative h-2 w-36 rounded-full bg-gray-100" aria-hidden="true">
                                    <div class="h-full rounded-full {{ $excede ? 'bg-rose-500' : 'bg-udg-800' }}" style="width: {{ min(100, $e['promedio'] / $maxEtapa * 100) }}%"></div>
                                    @if ($e['maximo'] !== null)
                                        <span class="absolute -top-1 h-4 w-0.5 rounded bg-oro-400" style="left: {{ min(100, $e['maximo'] / $maxEtapa * 100) }}%" title="Máximo documentado"></span>
                                    @endif
                                </div>
                                <span class="text-sm font-semibold {{ $excede ? 'text-rose-600' : 'text-gray-900' }}">{{ $e['promedio'] }} d</span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-right text-sm text-gray-500">{{ $e['maximo'] !== null ? $e['maximo'].' d' : 'sin plazo fijo' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-4"><x-dashboard.vacio icono="reloj" titulo="Aún no hay etapas cerradas" texto="Los promedios aparecen cuando se resuelvan las primeras etapas de algún convenio." /></td></tr>
                @endforelse
            </tbody>
        </table>
        <x-slot:pie>
            <p class="flex items-center gap-2 text-xs text-gray-500"><span class="inline-block h-3 w-0.5 rounded bg-oro-400"></span> La marca dorada indica el máximo documentado de cada etapa.</p>
        </x-slot:pie>
    </x-tabla.marco>

    {{-- Exportaciones --}}
    <section aria-labelledby="titulo-exportar">
        <h2 id="titulo-exportar" class="font-display text-lg font-bold text-gray-900">Descargar en CSV</h2>
        <p class="mt-0.5 max-w-2xl text-sm text-gray-600">Los archivos contienen datos personales: úsalos solo con fines administrativos. Cada descarga queda registrada en la auditoría.</p>

        <div @class(['mt-4 grid grid-cols-1 gap-4', 'lg:grid-cols-2' => count($exportes) > 1, 'max-w-2xl' => count($exportes) === 1])>
            @foreach ($exportes as $e)
                <form method="GET" action="{{ route('admin.informes.exportar', $e['tipo']) }}" x-data="{ filtros: false }"
                    class="flex flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs transition hover:border-udg-200 hover:shadow-theme-md">
                    <div class="flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-udg-50 text-udg-900 ring-1 ring-inset ring-udg-100"><x-dashboard.icono :nombre="$e['icono']" /></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-display text-base font-bold text-gray-900">{{ $e['titulo'] }}</h3>
                            <p class="mt-0.5 text-sm leading-relaxed text-gray-600">{{ $e['desc'] }}</p>
                        </div>
                    </div>

                    <button type="button" @click="filtros = !filtros" :aria-expanded="filtros.toString()"
                        class="mt-4 inline-flex items-center gap-1.5 self-start rounded-lg text-sm font-semibold text-real-600 hover:text-real-700">
                        <svg class="size-4 transition-transform" :class="filtros && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        <span x-text="filtros ? 'Ocultar filtros' : 'Filtrar antes de descargar'">Filtrar antes de descargar</span>
                    </button>

                    <div x-show="filtros" x-cloak x-transition.opacity.duration.150ms class="mt-3 grid grid-cols-1 gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2">
                        <div @class(['hidden' => !($e['carrera'] ?? true)])>
                            <label for="{{ $e['tipo'] }}-carrera" class="etiqueta">Carrera</label>
                            <select id="{{ $e['tipo'] }}-carrera" name="carrera_id" class="campo h-10">
                                <option value="">Todas las carreras</option>
                                @foreach ($carreras as $c)
                                    <option value="{{ $c->id }}">{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="{{ $e['tipo'] }}-estado" class="etiqueta">Estado</label>
                            <select id="{{ $e['tipo'] }}-estado" name="estado" class="campo h-10">
                                <option value="">Todos los estados</option>
                                @foreach ($estados[$e['tipo']] as $valor => $texto)
                                    <option value="{{ $valor }}">{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($e['ciclo'])
                            <div>
                                <label for="{{ $e['tipo'] }}-ciclo" class="etiqueta">Ciclo</label>
                                <select id="{{ $e['tipo'] }}-ciclo" name="ciclo" class="campo h-10">
                                    <option value="">Todos los ciclos</option>
                                    @foreach ($ciclos as $ciclo)<option value="{{ $ciclo }}">{{ $ciclo }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                        @if ($e['fechas'])
                            <div class="sm:col-span-2">
                                <p class="etiqueta">{{ $e['fechas'] }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="date" name="desde" class="campo h-10" aria-label="Desde">
                                    <input type="date" name="hasta" class="campo h-10" aria-label="Hasta">
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button class="boton boton-primario" data-sin-bloqueo="1"><x-dashboard.icono nombre="descargar" clase="size-4" /> Descargar CSV</button>
                    </div>
                </form>
            @endforeach
        </div>
    </section>
</div>
@endsection
