@props(['estado', 'grande' => false])

{{--
    Pista de fases del convenio: Documentos → Académica → Jurídica → Firmas.
    Hecha = marino; actual = color de su estado con halo; pendiente = gris. Rechazado = toda la pista en rosa tenue.
    Versión chica para filas de tabla; grande (con nombres) para el detalle del convenio.
--}}
@php
    use App\Enums\ConvenioEstado as E;

    $fases = [
        ['nombre' => 'Documentos', 'estado' => E::Solicitado, 'punto' => 'bg-sky-400', 'halo' => 'ring-sky-100'],
        ['nombre' => 'Académica', 'estado' => E::EnValidacionAcademica, 'punto' => 'bg-violet-400', 'halo' => 'ring-violet-100'],
        ['nombre' => 'Jurídica', 'estado' => E::EnRevisionJuridica, 'punto' => 'bg-indigo-400', 'halo' => 'ring-indigo-100'],
        ['nombre' => 'Firmas', 'estado' => E::EnFirmas, 'punto' => 'bg-amber-400', 'halo' => 'ring-amber-100'],
    ];
    $orden = [E::Solicitado->value => 0, E::EnValidacionAcademica->value => 1, E::EnRevisionJuridica->value => 2, E::EnFirmas->value => 3, E::Formalizado->value => 4];
    $rechazado = $estado === E::Rechazado;
    $actual = $orden[$estado->value] ?? -1;
    $descripcion = $rechazado ? 'Trámite rechazado' : ($actual >= 4 ? 'Todas las fases completas' : 'Fase ' . ($actual + 1) . ' de 4: ' . $fases[$actual]['nombre']);
@endphp

@if ($grande)
    <ol {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 sm:grid-cols-4']) }} aria-label="{{ $descripcion }}">
        @foreach ($fases as $i => $f)
            @php $hecha = !$rechazado && $i < $actual; $esActual = !$rechazado && $i === $actual; @endphp
            <li @class([
                'relative rounded-xl border p-3 transition',
                'border-udg-200 bg-udg-50/60' => $hecha,
                'border-transparent bg-white shadow-theme-sm ring-2 ' . str_replace('ring-', 'ring-', $f['halo']) => $esActual,
                'border-gray-200 bg-white' => !$hecha && !$esActual,
                'opacity-60' => $rechazado,
            ])>
                <div class="flex items-center gap-2">
                    <span @class([
                        'flex size-6 items-center justify-center rounded-full text-[11px] font-bold',
                        'bg-udg-800 text-white' => $hecha,
                        $f['punto'] . ' text-white' => $esActual,
                        'bg-gray-100 text-gray-400' => !$hecha && !$esActual,
                    ])>
                        @if ($hecha)<x-dashboard.icono nombre="check" clase="size-3.5" />@else{{ $i + 1 }}@endif
                    </span>
                    <span @class(['text-sm font-semibold', 'text-udg-900' => $hecha || $esActual, 'text-gray-400' => !$hecha && !$esActual])>{{ $f['nombre'] }}</span>
                </div>
                <p class="mt-1.5 text-xs text-gray-500">{{ $hecha ? 'Completada' : ($esActual ? 'En curso' : 'Pendiente') }}</p>
            </li>
        @endforeach
    </ol>
@else
    <div {{ $attributes->merge(['class' => 'flex items-center']) }} role="img" aria-label="{{ $descripcion }}" title="{{ $descripcion }}">
        @foreach ($fases as $i => $f)
            @php $hecha = !$rechazado && $i < $actual; $esActual = !$rechazado && $i === $actual; @endphp
            @if ($i > 0)
                <span @class(['h-0.5 w-3', 'bg-udg-800' => $hecha || $esActual, 'bg-gray-200' => !$hecha && !$esActual, '!bg-rose-200' => $rechazado])></span>
            @endif
            <span @class([
                'rounded-full transition',
                'size-2 bg-udg-800' => $hecha,
                'size-2.5 ring-4 ' . $f['punto'] . ' ' . $f['halo'] => $esActual,
                'size-2 bg-gray-200' => !$hecha && !$esActual && !$rechazado,
                'size-2 bg-rose-300' => $rechazado,
            ])></span>
        @endforeach
    </div>
@endif
