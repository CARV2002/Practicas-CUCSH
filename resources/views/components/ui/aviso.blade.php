@props([
    'tipo' => 'info',         // info | exito | advertencia | error
    'titulo' => null,
    'descartable' => false,   // muestra botón para cerrar
    'autocerrar' => null,     // milisegundos para ocultarse solo; los de éxito lo hacen por defecto (6 s). false = nunca
])

{{--
    Aviso / alerta (DESIGN_SYSTEM.md §1.3 y §5): entra con un deslizamiento suave, puede cerrarse y respeta
    «reducir movimiento». Los de éxito («Guardado», «Enviado») se confirman y desaparecen solos a los pocos
    segundos, con una barra que muestra el tiempo; pasar el mouse encima lo pausa para alcanzar a leerlo.
    Los de error y advertencia se quedan hasta que se corrige lo que señalan.
--}}
@php
    $estilos = [
        'info' => ['caja' => 'border-real-200 bg-real-50 text-real-700', 'icono' => 'text-real-600', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>'],
        'exito' => ['caja' => 'border-success-200 bg-success-50 text-success-800', 'icono' => 'text-success-600', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>'],
        'advertencia' => ['caja' => 'border-warning-200 bg-warning-50 text-warning-800', 'icono' => 'text-warning-600', 'svg' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>'],
        'error' => ['caja' => 'border-error-200 bg-error-50 text-error-700', 'icono' => 'text-error-600', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>'],
    ];
    $e = $estilos[$tipo] ?? $estilos['info'];
    $ms = $autocerrar === false || $autocerrar === 'false' ? 0 : (int) ($autocerrar ?? ($tipo === 'exito' ? 6000 : 0));
@endphp

<div x-data="{
        visible: true, total: {{ $ms }}, restante: {{ $ms }}, pausa: false,
        init() {
            if (!this.total) return;
            const paso = () => {
                if (!this.visible) return;
                if (!this.pausa) this.restante -= 100;
                if (this.restante <= 0) { this.visible = false; return; }
                setTimeout(paso, 100);
            };
            setTimeout(paso, 100);
        }
    }" x-show="visible"
    @if ($ms) x-on:mouseenter="pausa = true" x-on:mouseleave="pausa = false" x-on:focusin="pausa = true" x-on:focusout="pausa = false" @endif
    x-transition:leave="transition ease-in duration-300 motion-reduce:transition-none"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-2"
    role="{{ $tipo === 'error' ? 'alert' : 'status' }}"
    {{ $attributes->merge(['class' => "relative flex animate-entrada items-start gap-3 overflow-hidden rounded-xl border p-4 text-sm shadow-theme-xs motion-reduce:animate-none {$e['caja']}"]) }}>
    <svg class="mt-0.5 size-5 shrink-0 {{ $e['icono'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $e['svg'] !!}</svg>
    <div class="min-w-0 flex-1 leading-relaxed">
        @if ($titulo)
            <p class="font-semibold">{{ $titulo }}</p>
        @endif
        <div @class(['mt-0.5' => $titulo])>{{ $slot }}</div>
    </div>
    @if ($descartable || $ms)
        <button type="button" @click="visible = false"
            class="-m-1 flex size-8 shrink-0 items-center justify-center rounded-lg opacity-70 transition hover:bg-black/5 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current"
            aria-label="Cerrar aviso">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    @endif
    @if ($ms)
        {{-- Tiempo que le queda en pantalla --}}
        <span class="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 origin-left bg-current opacity-30 transition-transform duration-100 ease-linear motion-reduce:hidden"
            :style="`transform: scaleX(${Math.max(0, restante / total)})`" aria-hidden="true"></span>
    @endif
</div>
