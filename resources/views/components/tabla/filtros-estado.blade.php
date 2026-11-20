@props([
    'opciones',          // [['valor' => 'x', 'texto' => 'X', 'conteo' => 3, 'familia' => 'cielo'], …] (valor '' = todos)
    'parametro' => 'estado',
    'actual' => '',
    'etiqueta' => 'Filtrar por estado',
])

{{--
    Filtro por estado en forma de pestañas con conteo. Conserva el resto de la búsqueda (q, tipo, …)
    y vuelve a la página 1. En celular se desplaza a los lados.
--}}
@php
    $punto = [
        'gris' => 'bg-slate-400', 'cielo' => 'bg-sky-400', 'lavanda' => 'bg-violet-400', 'indigo' => 'bg-indigo-400',
        'ambar' => 'bg-amber-400', 'menta' => 'bg-emerald-500', 'turquesa' => 'bg-teal-500', 'rosa' => 'bg-rose-500', 'piedra' => 'bg-stone-400',
    ];
@endphp
<nav aria-label="{{ $etiqueta }}" {{ $attributes->merge(['class' => '-mx-4 overflow-x-auto px-4 no-scrollbar sm:mx-0 sm:px-0']) }}>
    <ul class="flex w-max gap-1 rounded-xl bg-gray-100/80 p-1 lg:w-auto lg:flex-wrap">
        @foreach ($opciones as $o)
            @php
                $activo = (string) $actual === (string) $o['valor'];
                $url = request()->fullUrlWithQuery([$parametro => $o['valor'] === '' ? null : $o['valor'], 'page' => null]);
            @endphp
            <li>
                <a href="{{ $url }}" @if ($activo) aria-current="page" @endif
                    @class([
                        'flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40',
                        'bg-white text-udg-900 shadow-theme-xs ring-1 ring-gray-200' => $activo,
                        'text-gray-600 hover:bg-white/70 hover:text-gray-900' => !$activo,
                    ])>
                    @if (!empty($o['familia']))
                        <span class="size-2 rounded-full {{ $punto[$o['familia']] ?? 'bg-gray-300' }}" aria-hidden="true"></span>
                    @endif
                    {{ $o['texto'] }}
                    @isset($o['conteo'])
                        <span @class([
                            'min-w-5 rounded-full px-1.5 text-center text-xs font-semibold tabular-nums',
                            'bg-udg-900 text-white' => $activo,
                            'bg-white text-gray-500 ring-1 ring-inset ring-gray-200' => !$activo,
                        ])>{{ $o['conteo'] }}</span>
                    @endisset
                </a>
            </li>
        @endforeach
    </ul>
</nav>
