@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Convenios" />

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    @if ($porVencer > 0)
        <div class="rounded-xl border border-yellow-500 bg-yellow-50 p-4 text-sm text-yellow-800">
            {{ $porVencer }} convenio(s) vencen en los próximos 60 días.
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5">
        <div class="mb-5 flex flex-col gap-4 px-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Bandeja de convenios</h3>
                <p class="mt-0.5 text-sm text-gray-500">Estado, etapa abierta y tiempo de espera de cada trámite.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Empresa o RFC..."
                    class="focus:border-brand-300 focus:ring-brand-500/10 h-10 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden">
                <select name="estado" class="h-10 rounded-lg border border-gray-300 px-3 text-sm">
                    <option value="">Todos los estados</option>
                    @foreach ($estados as $e)
                        <option value="{{ $e->value }}" @selected(request('estado') === $e->value)>{{ $e->label() }}</option>
                    @endforeach
                </select>
                <button class="bg-brand-500 hover:bg-brand-600 h-10 rounded-lg px-4 text-sm font-medium text-white">Filtrar</button>
            </form>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Empresa</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Folio</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Estado</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Etapa abierta</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Días hábiles</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Vigencia</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($convenios as $convenio)
                        @php
                            $abierta = $convenio->seguimientos
                                ->filter(fn ($s) => $s->estado === \App\Enums\SeguimientoEstado::Pendiente)
                                ->last();
                            $color = match ($convenio->estado) {
                                \App\Enums\ConvenioEstado::Formalizado => 'bg-green-50 text-green-700',
                                \App\Enums\ConvenioEstado::Rechazado => 'bg-red-50 text-red-700',
                                default => 'bg-blue-50 text-blue-700',
                            };
                        @endphp
                        <tr class="transition-colors hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $convenio->empresa->razon_social }}</span>
                                <span class="text-xs text-gray-500">{{ $convenio->empresa->rfc }} · recibido {{ $convenio->fecha_recepcion->format('d/m/Y') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-600">{{ $convenio->folio ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $color }}">{{ $convenio->estado->label() }}</span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700">
                                @if ($abierta)
                                    <span class="block">{{ $abierta->etapa->label() }}</span>
                                    <span class="text-xs text-gray-500">{{ $abierta->etapa->responsable() }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm">
                                @if ($abierta)
                                    <span class="font-semibold {{ $abierta->excedeEspera() ? 'text-red-600' : 'text-gray-700' }}">{{ $abierta->diasHabiles() }}</span>
                                    @if ($abierta->excedeEspera())
                                        <span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-700">Excede</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-500">
                                @if ($convenio->fecha_fin_vigencia)
                                    {{ $convenio->fecha_inicio_vigencia->format('d/m/Y') }} – {{ $convenio->fecha_fin_vigencia->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <a href="{{ route('admin.convenios.show', $convenio) }}" class="text-brand-600 hover:text-brand-700 text-xs font-semibold">Gestionar →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">No hay convenios que coincidan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $convenios->links() }}</div>
    </div>
</div>
@endsection