@extends('layouts.app')

@section('content')
@php
    use App\Enums\PracticaEstado as PrE;

    $filtro = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
@endphp

<x-common.page-breadcrumb pageTitle="Practicantes" />

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Practicantes activos</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $resumen['activos'] }}</h3>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">En cierre</p>
            <h3 class="mt-1 text-2xl font-bold text-yellow-600">{{ $resumen['cierre'] }}</h3>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Acreditados</p>
            <h3 class="mt-1 text-2xl font-bold text-green-600">{{ $resumen['acreditados'] }}</h3>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5">
        <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Practicantes asignados</h3>
                <p class="mt-0.5 text-sm text-gray-500">Horas validadas por la Coordinación y estado de cada práctica.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre o código..." class="{{ $filtro }} w-44">
                <select name="programa_id" class="{{ $filtro }}">
                    <option value="">Todos los programas</option>
                    @foreach ($programas as $p)
                        <option value="{{ $p->id }}" @selected((int) request('programa_id') === $p->id)>{{ $p->titulo }} ({{ $p->ciclo }})</option>
                    @endforeach
                </select>
                <select name="estado" class="{{ $filtro }}">
                    <option value="">Todos los estados</option>
                    @foreach ($estados as $e)
                        <option value="{{ $e->value }}" @selected(request('estado') === $e->value)>{{ $e->label() }}</option>
                    @endforeach
                </select>
                <button class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Filtrar</button>
            </form>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Practicante</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Programa</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Horas</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Periodo</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Supervisor UdeG</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($practicas as $pr)
                        @php
                            $al = $pr->postulacion->alumno;
                            $horas = (int) $pr->horas_acumuladas;
                            $pct = $pr->horas_requeridas > 0 ? min(100, round($horas / $pr->horas_requeridas * 100)) : 0;
                            $color = match ($pr->estado) {
                                PrE::Acreditada => 'bg-green-50 text-green-700',
                                PrE::Cancelada => 'bg-red-50 text-red-700',
                                PrE::EnCierre => 'bg-yellow-50 text-yellow-700',
                                default => 'bg-blue-50 text-blue-700',
                            };
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $al->user->name }}</span>
                                <span class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->nombre }}</span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700">{{ $pr->postulacion->programa->titulo }}</td>
                            <td class="px-4 py-4">
                                <div class="w-40">
                                    <div class="mb-1 flex items-baseline justify-between">
                                        <span class="text-sm font-bold text-gray-900">{{ $horas }}<span class="text-xs font-normal text-gray-400"> / {{ $pr->horas_requeridas }} h</span></span>
                                        <span class="text-brand-600 text-xs font-semibold">{{ $pct }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-gray-100"><div class="bg-brand-500 h-1.5 rounded-full" style="width: {{ $pct }}%"></div></div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-600">{{ $pr->fecha_inicio->format('d/m/Y') }} – {{ $pr->fecha_fin->format('d/m/Y') }}</td>
                            <td class="px-4 py-4 text-sm text-gray-700">{{ $pr->supervisor?->nombre ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $color }}">{{ $pr->estado->label() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <a href="{{ route('empresa.practicantes.show', $pr) }}" class="text-brand-600 hover:text-brand-700 text-xs font-semibold">Reportes →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">Aún no tienes practicantes asignados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $practicas->links() }}</div>
    </div>
</div>
@endsection