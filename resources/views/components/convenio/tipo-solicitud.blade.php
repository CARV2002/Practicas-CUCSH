@props(['convenio', 'antecedente' => null])

{{-- «Reenvío» (la solicitud anterior se rechazó) o «Renovación» (ya tuvo un convenio formalizado). Nada si es la primera. --}}
@php $a = $antecedente ?? $convenio->antecedente(); @endphp
@if ($a)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset '
        . ($a['tipo'] === 'reenvio' ? 'bg-orange-50 text-orange-800 ring-orange-200' : 'bg-sky-50 text-sky-800 ring-sky-200')]) }}
        title="{{ $a['tipo'] === 'reenvio' ? 'La solicitud anterior fue rechazada por ' . $a['origen'] : 'Renovación del convenio ' . ($a['anterior']->folio ?? '') }}">
        <x-dashboard.icono :nombre="$a['tipo'] === 'reenvio' ? 'deshacer' : 'vigente'" clase="size-3" />
        {{ $a['tipo'] === 'reenvio' ? 'Reenvío tras rechazo' : 'Renovación' }}
    </span>
@endif