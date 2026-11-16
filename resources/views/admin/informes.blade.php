@extends('layouts.app')

@section('content')
@php
    $filtro = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
    $card = 'rounded-2xl border border-gray-200 bg-white p-6';
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500';
    $exportes = [
        ['tipo' => 'practicas', 'titulo' => 'Prácticas', 'desc' => 'Una fila por práctica: alumno, empresa, fechas, horas validadas y estado.', 'fechas' => 'Fecha de inicio de la práctica', 'ciclo' => false],
        ['tipo' => 'postulaciones', 'titulo' => 'Postulaciones', 'desc' => 'Quién se postuló a qué programa, con su porcentaje de créditos.', 'fechas' => 'Fecha de postulación', 'ciclo' => false],
        ['tipo' => 'convenios', 'titulo' => 'Convenios', 'desc' => 'Estado, vigencia, días para vencer y carreras aprobadas.', 'fechas' => 'Fecha de recepción', 'ciclo' => false],
        ['tipo' => 'programas', 'titulo' => 'Programas', 'desc' => 'Ofertas con cupos totales, ocupados y disponibles.', 'fechas' => null, 'ciclo' => true],
    ];
@endphp

<x-common.page-breadcrumb pageTitle="Informes" />

<div class="space-y-6">
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Resumen de prácticas --}}
    <div class="{{ $card }}">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Resumen de prácticas</h3>
                <p class="text-sm text-gray-500">Filtra por carrera y por fecha de inicio de la práctica.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <select name="carrera_id" class="{{ $filtro }} !w-52">
                    <option value="">Todas las carreras</option>
                    @foreach ($carreras as $c)
                        <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                    @endforeach
                </select>
                <input type="date" name="desde" value="{{ request('desde') }}" class="{{ $filtro }} !w-40" title="Desde">
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="{{ $filtro }} !w-40" title="Hasta">
                <button class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Aplicar</button>
            </form>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-semibold uppercase text-gray-500">Prácticas</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ $resumen['total'] }}</p></div>
            <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-semibold uppercase text-gray-500">Activas</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ $resumen['activas'] }}</p></div>
            <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-semibold uppercase text-gray-500">Acreditadas</p><p class="mt-1 text-2xl font-bold text-green-600">{{ $resumen['acreditadas'] }}</p></div>
            <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-semibold uppercase text-gray-500">Horas validadas</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($resumen['horas']) }}</p></div>
        </div>

        <div class="mt-5 max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Carrera</th>
                        <th class="{{ $th }}">Total</th><th class="{{ $th }}">Activas</th>
                        <th class="{{ $th }}">Acreditadas</th><th class="{{ $th }}">Canceladas</th>
                        <th class="{{ $th }}">Avance de horas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($porCarrera as $c)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-800"><span class="font-mono text-xs font-bold text-gray-500">{{ $c['clave'] }}</span> {{ $c['nombre'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $c['total'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $c['activas'] }}</td>
                            <td class="px-4 py-3 text-sm text-green-700">{{ $c['acreditadas'] }}</td>
                            <td class="px-4 py-3 text-sm text-red-600">{{ $c['canceladas'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-28 rounded-full bg-gray-100"><div class="bg-brand-500 h-1.5 rounded-full" style="width: {{ $c['avance'] }}%"></div></div>
                                    <span class="text-xs font-semibold text-gray-600">{{ $c['avance'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No hay prácticas con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Convenios y cuellos de botella --}}
    <div class="{{ $card }}">
        <h3 class="text-lg font-bold text-gray-900">Convenios</h3>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-semibold uppercase text-gray-500">Vigentes</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ $conveniosResumen['vigentes'] }}</p></div>
            <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-semibold uppercase text-gray-500">Vencen en 60 días</p><p class="mt-1 text-2xl font-bold {{ $conveniosResumen['por_vencer'] ? 'text-red-600' : 'text-gray-900' }}">{{ $conveniosResumen['por_vencer'] }}</p></div>
            <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-semibold uppercase text-gray-500">En trámite</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ $conveniosResumen['en_tramite'] }}</p></div>
        </div>

        <h4 class="mt-6 text-sm font-bold text-gray-900">Cuánto tarda cada etapa</h4>
        <p class="mb-2 text-xs text-gray-500">Promedio real de las etapas ya cerradas, en días hábiles, contra el máximo documentado.</p>
        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Etapa</th>
                        <th class="{{ $th }}">Responsable</th><th class="{{ $th }}">Casos</th>
                        <th class="{{ $th }}">Promedio</th><th class="{{ $th }}">Máximo documentado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($etapas as $e)
                        @php $excede = $e['maximo'] !== null && $e['promedio'] > $e['maximo']; @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $e['etapa'] }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $e['responsable'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $e['casos'] }}</td>
                            <td class="px-4 py-3 text-sm font-semibold {{ $excede ? 'text-red-600' : 'text-gray-800' }}">{{ $e['promedio'] }} d</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $e['maximo'] !== null ? $e['maximo'].' d' : 'sin plazo fijo' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">Aún no hay etapas cerradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Exportaciones --}}
    <div>
        <h3 class="text-lg font-bold text-gray-900">Exportar a CSV</h3>
        <p class="mb-3 text-sm text-gray-500">Los archivos contienen datos personales: úsalos solo con fines administrativos. Cada descarga queda registrada en la auditoría.</p>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($exportes as $e)
                <form method="GET" action="{{ route('admin.informes.exportar', $e['tipo']) }}" class="{{ $card }} space-y-3">
                    <div>
                        <h4 class="text-base font-bold text-gray-900">{{ $e['titulo'] }}</h4>
                        <p class="text-xs text-gray-500">{{ $e['desc'] }}</p>
                    </div>

                    <select name="carrera_id" class="{{ $filtro }}">
                        <option value="">Todas las carreras</option>
                        @foreach ($carreras as $c)
                            <option value="{{ $c->id }}">{{ $c->carrera_clave }} · {{ $c->nombre }}</option>
                        @endforeach
                    </select>

                    <select name="estado" class="{{ $filtro }}">
                        <option value="">Todos los estados</option>
                        @foreach ($estados[$e['tipo']] as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>

                    @if ($e['ciclo'])
                        <select name="ciclo" class="{{ $filtro }}">
                            <option value="">Todos los ciclos</option>
                            @foreach ($ciclos as $ciclo)<option value="{{ $ciclo }}">{{ $ciclo }}</option>@endforeach
                        </select>
                    @endif

                    @if ($e['fechas'])
                        <div>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" name="desde" class="{{ $filtro }}" title="Desde">
                                <input type="date" name="hasta" class="{{ $filtro }}" title="Hasta">
                            </div>
                            <p class="mt-1 text-xs text-gray-400">{{ $e['fechas'] }}</p>
                        </div>
                    @endif

                    <button class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2 text-sm font-medium text-white">Descargar CSV</button>
                </form>
            @endforeach
        </div>
    </div>
</div>
@endsection