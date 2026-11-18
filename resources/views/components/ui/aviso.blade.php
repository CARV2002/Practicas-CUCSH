@props([
    'tipo' => 'info',         // info | exito | advertencia | error
    'titulo' => null,
    'descartable' => false,   // muestra botón para cerrar
    'autocerrar' => null,     // milisegundos para ocultarse solo (p. ej. mensajes de «guardado»)
])

{{--
    Aviso / alerta (DESIGN_SYSTEM.md §1.3 y §5): entra con un deslizamiento suave, puede cerrarse y respeta
    «reducir movimiento». Los de éxito se anuncian como estado; los de error como alerta.
--}}
@php
    $estilos = [
        'info' => ['caja' => 'border-real-200 bg-real-50 text-real-700', 'icono' => 'text-real-600', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>'],
        'exito' => ['caja' => 'border-success-200 bg-success-50 text-success-800', 'icono' => 'text-success-600', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>'],
        'advertencia' => ['caja' => 'border-warning-200 bg-warning-50 text-warning-800', 'icono' => 'text-warning-600', 'svg' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>'],
        'error' => ['caja' => 'border-error-200 bg-error-50 text-error-700', 'icono' => 'text-error-600', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>'],
    ];
    $e = $estilos[$tipo] ?? $estilos['info'];
@endphp

<div x-data="{ visible: true }" x-show="visible"
    @if ($autocerrar) x-init="setTimeout(() => visible = false, {{ (int) $autocerrar }})" @endif
    x-transition:leave="transition ease-in duration-200 motion-reduce:transition-none"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-1"
    role="{{ $tipo === 'error' ? 'alert' : 'status' }}"
    {{ $attributes->merge(['class' => "flex animate-entrada items-start gap-3 rounded-xl border p-4 text-sm shadow-theme-xs motion-reduce:animate-none {$e['caja']}"]) }}>
    <svg class="mt-0.5 size-5 shrink-0 {{ $e['icono'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $e['svg'] !!}</svg>
    <div class="min-w-0 flex-1 leading-relaxed">
        @if ($titulo)
            <p class="font-semibold">{{ $titulo }}</p>
        @endif
        <div @class(['mt-0.5' => $titulo])>{{ $slot }}</div>
    </div>
    @if ($descartable || $autocerrar)
        <button type="button" @click="visible = false"
            class="-m-1 flex size-8 shrink-0 items-center justify-center rounded-lg opacity-70 transition hover:bg-black/5 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current"
            aria-label="Cerrar aviso">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    @endif
</div>
