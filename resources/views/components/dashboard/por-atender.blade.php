{{--
    «Por atender»: lo que el usuario tiene pendiente según su situación actual, en su panel (ya no en la campana,
    para no duplicarlo con las notificaciones). Cada renglón lleva a la sección donde se resuelve.
    La lista la calcula el controlador del panel con App\Support\Pendientes::de().
--}}
@props(['pendientes' => []])

@if (count($pendientes))
    <section class="tarjeta overflow-hidden" aria-labelledby="titulo-por-atender">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 sm:px-6">
            <h2 id="titulo-por-atender" class="font-display text-base font-bold text-gray-900">Por atender</h2>
            <span class="rounded-full bg-oro-100 px-2.5 py-0.5 text-xs font-semibold text-oro-800">{{ collect($pendientes)->sum(fn ($p) => $p['n'] ?? 1) }}</span>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($pendientes as $p)
                <li>
                    <a href="{{ $p['url'] }}" class="group flex items-center gap-3 px-5 py-3 text-sm transition-colors hover:bg-gray-50 sm:px-6">
                        <span @class(['size-2 shrink-0 rounded-full',
                            'bg-rose-500' => $p['tipo'] === 'error', 'bg-amber-500' => $p['tipo'] === 'advertencia',
                            'bg-emerald-500' => $p['tipo'] === 'exito', 'bg-sky-500' => !in_array($p['tipo'], ['error', 'advertencia', 'exito'], true)])></span>
                        <span class="min-w-0 flex-1 text-gray-700 group-hover:text-gray-900">{{ $p['mensaje'] }}</span>
                        <span class="shrink-0 text-xs font-semibold text-udg-800 opacity-70 transition group-hover:translate-x-0.5 group-hover:opacity-100">Ir →</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
