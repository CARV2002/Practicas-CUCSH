@props([
    'tipo',            // 'aceptacion' | 'termino'
    'horas' => null,   // horas que exige la carrera (ej. 350), para mostrar el dato exacto
    'para' => 'alumno', // 'alumno' (revisa antes de subir) | 'empresa' (la elabora)
])

@php
    $horasTxt = $horas ? "{$horas} horas" : 'el total de horas';
    $requisitos = $tipo === 'termino'
        ? [
            ['Papelería', 'Hoja membretada de la organización.'],
            ['Destinatario', 'Dirigida al Coordinador(a) de la carrera en el CUCSH.'],
            ['Estudiante', 'Nombre completo, código UdeG (9 dígitos) y licenciatura.'],
            ['Cumplimiento', "Que concluyó satisfactoriamente sus prácticas: periodo exacto (inicio y término), {$horasTxt} cubiertas (100%) y actividades principales."],
            ['Validación', 'Firma autógrafa del responsable directo y sello oficial.'],
        ]
        : [
            ['Papelería', 'Hoja membretada de la organización.'],
            ['Destinatario', 'Dirigida al Coordinador(a) de la carrera en el CUCSH.'],
            ['Estudiante', 'Nombre completo, código UdeG (9 dígitos) y licenciatura.'],
            ['Práctica', "Programa o área asignada, fecha exacta de inicio, horario y total de horas ({$horasTxt})."],
            ['Validación', 'Firma autógrafa del responsable, jefe directo o Recursos Humanos, y sello oficial (si cuenta con él).'],
        ];
    $titulo = $para === 'empresa'
        ? ($tipo === 'termino' ? 'Qué debe incluir la carta de término' : 'Qué debe incluir la carta de aceptación')
        : 'Requisitos que debe incluir tu carta';
@endphp

{{--
    Datos obligatorios de las cartas que emite la organización (aceptación y término). Cada organización usa su
    propia papelería, así que no hay formato fijo: este recuadro evita cartas sin fecha, sin horas o sin destinatario.
--}}
<div {{ $attributes->merge(['class' => 'rounded-xl bg-udg-50/60 px-3.5 py-3 text-left ring-1 ring-inset ring-udg-100']) }}>
    <p class="flex items-center gap-1.5 text-xs font-semibold text-udg-900">
        <x-dashboard.icono nombre="documento" clase="size-3.5" /> {{ $titulo }}
    </p>
    <ul class="mt-2 space-y-1 text-xs leading-relaxed text-gray-700">
        @foreach ($requisitos as [$dato, $texto])
            <li class="flex gap-1.5">
                <x-dashboard.icono nombre="check" clase="mt-0.5 size-3 shrink-0 text-emerald-600" />
                <span><span class="font-semibold text-gray-900">{{ $dato }}:</span> {{ $texto }}</span>
            </li>
        @endforeach
    </ul>
</div>
