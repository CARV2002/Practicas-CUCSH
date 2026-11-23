@props([
    'titulo',                 // qué se devolvió y quién: «La Unidad Jurídica devolvió el borrador v2 con observaciones»
    'motivo' => null,         // la observación tal cual la escribieron
    'fecha' => null,          // cuándo se devolvió
    'quien' => null,          // quién lo devolvió (si no va en el título)
    'resuelta' => false,      // ya se subió la corrección: el aviso pasa a «en revisión»
    'resueltaTexto' => 'Ya enviaste la corrección: espera la nueva revisión.',
])

{{--
    Aviso estándar de «te devolvieron algo para corregir», igual en todo el sistema:
      1. Qué se devolvió y quién (título).
      2. La observación textual, citada.
      3. Qué hacer y en qué apartado (slot), con la sección a corregir marcada en rojo en la misma pantalla.
    Cuando ya se corrigió (resuelta), el aviso desaparece: ya no hay nada que hacer.
--}}
@php $fecha = $fecha ? \Illuminate\Support\Carbon::parse($fecha) : null; @endphp

@unless ($resuelta)

<div role="alert" {{ $attributes->class([
    'rounded-xl border-l-4 px-4 py-3.5 ring-1 ring-inset',
    'border-rose-500 bg-rose-50 ring-rose-200' => !$resuelta,
    'border-amber-400 bg-amber-50 ring-amber-200' => $resuelta,
]) }}>
    <div class="flex items-start gap-3">
        <span @class(['mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full', 'bg-rose-100 text-rose-600' => !$resuelta, 'bg-amber-100 text-amber-700' => $resuelta])>
            <x-dashboard.icono :nombre="$resuelta ? 'reloj' : 'alerta'" clase="size-4" />
        </span>
        <div class="min-w-0 flex-1 text-sm">
            <p @class(['font-semibold', 'text-rose-800' => !$resuelta, 'text-amber-900' => $resuelta])>
                {{ $titulo }}
                @if ($fecha || $quien)
                    <span class="font-normal opacity-80">· {{ collect([$quien, $fecha?->format('d/m/Y')])->filter()->join(' · ') }}</span>
                @endif
            </p>
            @if (filled($motivo))
                <blockquote @class(['mt-1.5 rounded-lg bg-white/70 px-3 py-2 italic ring-1 ring-inset', 'text-rose-900 ring-rose-100' => !$resuelta, 'text-amber-900 ring-amber-100' => $resuelta])>«{{ $motivo }}»</blockquote>
            @endif
            @if ($resuelta)
                <p class="mt-1.5 text-amber-900">{{ $resueltaTexto }}</p>
            @elseif (trim($slot) !== '')
                <div class="mt-1.5 leading-relaxed text-rose-900"><span class="font-semibold">Qué hacer:</span> {{ $slot }}</div>
            @endif
        </div>
    </div>
</div>
@endunless
