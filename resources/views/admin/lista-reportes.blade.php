@extends('layouts.app')

@section('content')
@php
    use App\Enums\ReporteEstado as RE;

    $input = 'focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-3 focus:outline-hidden';
    $filtro = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
    $card = 'overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5';
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500';
@endphp

<x-common.page-breadcrumb pageTitle="Reportes" />

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Resumen --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Reportes pendientes</p>
            <h3 class="mt-1 text-2xl font-bold text-yellow-600">{{ $pendientes }}</h3>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Prácticas activas</p>
            <h3 class="mt-1 text-2xl font-bold text-gray-900">{{ $avance->total() }}</h3>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Próximas a cerrar</p>
            <h3 class="mt-1 text-2xl font-bold text-brand-600">{{ $porCerrar->count() }}</h3>
        </div>
    </div>

    {{-- Próximas a cerrar --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <h4 class="text-base font-bold text-gray-900">Prácticas próximas a cerrar</h4>
        <p class="mb-4 text-xs text-gray-500">Terminan en los próximos 30 días o ya cubren las horas requeridas.</p>
        <div class="divide-y divide-gray-100">
            @forelse ($porCerrar as $p)
                @php $al = $p->postulacion->alumno; @endphp
                <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ $al->user->name }}
                            <span class="font-normal text-gray-500">· {{ $al->carrera->carrera_clave }}</span></p>
                        <p class="text-xs text-gray-500">Termina {{ $p->fecha_fin->format('d/m/Y') }} · {{ $p->progresoTexto() }} h</p>
                    </div>
                    @if ($p->horasCompletas())
                        <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700">Horas completas</span>
                    @else
                        <span class="rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-semibold text-yellow-700">Faltan {{ $p->horas_requeridas - $p->horas_acumuladas }} h</span>
                    @endif
                </div>
            @empty
                <p class="py-3 text-center text-sm text-gray-400">Ninguna por ahora.</p>
            @endforelse
        </div>
    </div>

    {{-- Reportes --}}
    <div class="{{ $card }}">
        <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Reportes de horas</h3>
                <p class="mt-0.5 text-sm text-gray-500">Solo las horas de reportes validados cuentan para el avance.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre o código..." class="{{ $filtro }} w-44">
                <select name="carrera_id" class="{{ $filtro }}">
                    <option value="">Todas las carreras</option>
                    @foreach ($carreras as $c)
                        <option value="{{ $c->id }}" @selected((int) request('carrera_id') === $c->id)>{{ $c->carrera_clave }}</option>
                    @endforeach
                </select>
                <select name="tipo" class="{{ $filtro }}">
                    <option value="">Tipo</option>
                    @foreach ($tipos as $t)
                        <option value="{{ $t->value }}" @selected(request('tipo') === $t->value)>{{ ucfirst($t->value) }}</option>
                    @endforeach
                </select>
                <select name="estado" class="{{ $filtro }}">
                    <option value="">Todos los estados</option>
                    @foreach ($estados as $e)
                        <option value="{{ $e->value }}" @selected(request('estado') === $e->value)>{{ ucfirst($e->value) }}</option>
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
                        <th class="{{ $th }}">Tipo / periodo</th>
                        <th class="{{ $th }}">Horas</th>
                        <th class="{{ $th }}">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($reportes as $r)
                        @php
                            $al = $r->practica->postulacion->alumno;
                            $pdf = $r->getFirstMedia('reporte_firmado');
                            $color = match ($r->estado) {
                                RE::Validado => 'bg-green-50 text-green-700',
                                RE::Rechazado => 'bg-red-50 text-red-700',
                                default => 'bg-yellow-50 text-yellow-700',
                            };
                        @endphp
                        <tr class="align-top transition-colors hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $al->user->name }}</span>
                                <span class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }}</span>
                                <span class="block text-xs text-gray-400">{{ $r->practica->postulacion->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700">
                                {{ ucfirst($r->tipo->value) }}
                                <span class="block text-xs text-gray-500">{{ $r->periodo_inicio->format('d/m/Y') }} – {{ $r->periodo_fin->format('d/m/Y') }}</span>
                                @if ($pdf)
                                    <a href="{{ route('admin.reportes.archivos', [$r, $pdf]) }}" class="text-brand-600 text-xs font-semibold">Descargar PDF</a>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm font-bold text-gray-800">{{ $r->horas_reportadas }} h</td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $color }}">{{ ucfirst($r->estado->value) }}</span>
                                @if ($r->evaluacion_profesor)<p class="mt-1 max-w-[220px] text-xs text-gray-500">Profesor: {{ $r->evaluacion_profesor }}</p>@endif
                                @if ($r->retroalimentacion_empresa)<p class="mt-1 max-w-[220px] text-xs text-gray-500">Empresa: {{ $r->retroalimentacion_empresa }}</p>@endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if ($r->estado === RE::Pendiente)
                                    <form method="POST" action="{{ route('admin.reportes.resolver', $r) }}" class="flex flex-col items-end gap-2">
                                        @csrf
                                        <input type="text" name="evaluacion_profesor" placeholder="Evaluación / motivo" maxlength="2000" class="{{ $input }} !w-52 text-xs">
                                        <div class="flex gap-2">
                                            <button name="estado" value="validado" class="rounded-lg border border-green-500 px-3 py-1.5 text-xs font-medium text-green-700">Validar</button>
                                            <button name="estado" value="rechazado" class="rounded-lg border border-red-500 px-3 py-1.5 text-xs font-medium text-red-700">Rechazar</button>
                                        </div>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">No hay reportes que coincidan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4">{{ $reportes->links() }}</div>
    </div>

    {{-- Avance por practicante --}}
    <div class="{{ $card }}">
        <div class="mb-5 px-6">
            <h3 class="text-lg font-bold text-gray-900">Avance por practicante</h3>
            <p class="mt-0.5 text-sm text-gray-500">Horas acumuladas (validadas) / horas requeridas de cada práctica activa.</p>
        </div>
        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Practicante</th>
                        <th class="{{ $th }}">Progreso</th>
                        <th class="{{ $th }}">Termina</th>
                        <th class="{{ $th }}">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($avance as $p)
                        @php
                            $al = $p->postulacion->alumno;
                            $pct = $p->horas_requeridas > 0 ? min(100, round($p->horas_acumuladas / $p->horas_requeridas * 100)) : 0;
                            $atrasada = $p->fecha_fin->isPast() && ! $p->horasCompletas();
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $al->user->name }}</span>
                                <span class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="w-48">
                                    <div class="mb-1 flex items-baseline justify-between">
                                        <span class="text-sm font-bold text-gray-900">{{ (int) $p->horas_acumuladas }}<span class="text-xs font-normal text-gray-400"> / {{ $p->horas_requeridas }} h</span></span>
                                        <span class="text-brand-600 text-xs font-semibold">{{ $pct }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-gray-100"><div class="bg-brand-500 h-1.5 rounded-full" style="width: {{ $pct }}%"></div></div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm {{ $atrasada ? 'font-semibold text-red-600' : 'text-gray-600' }}">{{ $p->fecha_fin->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">{{ $p->estado->label() }}</span>
                                @if ($atrasada)<span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-700">Vencida</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400">No hay prácticas activas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4">{{ $avance->links() }}</div>
    </div>
</div>
@endsection