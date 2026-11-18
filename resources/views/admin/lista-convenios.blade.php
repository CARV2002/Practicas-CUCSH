@extends('layouts.app')

@section('content')
@php
    use App\Enums\SeguimientoEstado;

    $opciones = collect($estados)->map(fn ($e) => [
        'valor' => $e->value, 'texto' => $e === \App\Enums\ConvenioEstado::Solicitado ? 'Documentos' : $e->label(),
        'conteo' => (int) ($conteos[$e->value] ?? 0), 'familia' => $e->familia(),
    ])->prepend(['valor' => '', 'texto' => 'Todos', 'conteo' => (int) $conteos->sum()])->all();
@endphp

<div class="space-y-6">
    <x-pagina.encabezado titulo="Convenios" descripcion="Bandeja de trámites: en qué fase va cada convenio, quién lo tiene y cuánto lleva esperando.">
        <x-slot:acciones>
            <a href="{{ route('admin.informes') }}" class="boton boton-secundario"><x-dashboard.icono nombre="informe" clase="size-4" /> Informes</a>
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($porVencer > 0)
        <x-ui.aviso tipo="advertencia" titulo="{{ $porVencer }} {{ $porVencer === 1 ? 'convenio vence' : 'convenios vencen' }} en los próximos 60 días">
            Avisa a las organizaciones para que soliciten su renovación a tiempo.
        </x-ui.aviso>
    @endif

    <x-tabla.marco titulo="Bandeja de convenios" :total="$convenios->total()">
        <x-slot:herramientas>
            <x-tabla.filtros-estado :opciones="$opciones" :actual="request('estado', '')" />
            <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                @if (request('estado'))<input type="hidden" name="estado" value="{{ request('estado') }}">@endif
                <div class="relative flex-1 sm:max-w-sm">
                    <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <label for="q" class="sr-only">Buscar por empresa o RFC</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por empresa o RFC" class="campo-filtro pl-9 sm:w-full">
                </div>
                <button class="boton boton-secundario">Buscar</button>
                @if (request()->hasAny(['q', 'estado']))
                    <a href="{{ route('admin.lista-convenios') }}" class="boton text-gray-500 hover:bg-gray-100 hover:text-gray-800">Limpiar</a>
                @endif
            </form>
        </x-slot:herramientas>

        <table class="tabla">
            <thead>
                <tr>
                    <th class="tabla-th px-6">Organización</th>
                    <th class="tabla-th">Fase</th>
                    <th class="tabla-th">En qué va</th>
                    <th class="tabla-th text-right">Espera</th>
                    <th class="tabla-th">Vigencia</th>
                    <th class="tabla-th px-6 text-right"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($convenios as $convenio)
                    @php
                        $abierta = $convenio->seguimientos->filter(fn ($s) => $s->estado === SeguimientoEstado::Pendiente)->last();
                        $max = $abierta?->etapa->diasHabilesDocumentados()[1] ?? null;
                        $dias = $abierta?->diasHabiles();
                        $avance = $abierta && $max ? min(100, (int) round($dias / max(1, $max) * 100)) : null;
                        $excede = $abierta?->excedeEspera();
                    @endphp
                    <tr class="tabla-fila">
                        <td class="px-6 py-4">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-udg-50 font-display text-sm font-bold text-udg-900 ring-1 ring-inset ring-udg-100" aria-hidden="true">{{ mb_strtoupper(mb_substr($convenio->empresa->razon_social, 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.convenios.show', $convenio) }}" class="block font-semibold text-gray-900 hover:text-udg-900 hover:underline underline-offset-2">{{ $convenio->empresa->razon_social }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500"><span class="font-mono">{{ $convenio->empresa->rfc }}</span></p>
                                    <p class="text-xs text-gray-500">{{ $convenio->folio ? 'Folio ' . $convenio->folio : 'Sin folio aún' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <x-ui.estatus :estado="$convenio->estado" />
                            <x-convenio.pista :estado="$convenio->estado" class="mt-2" />
                        </td>
                        <td class="px-4 py-4">
                            @php $det = $convenio->estadoDetallado(); @endphp
                            <div class="md:min-w-56">
                                <p class="flex items-center gap-1.5 text-sm font-semibold text-gray-900">
                                    <span class="size-2 shrink-0 rounded-full {{ \App\Support\Estatus::punto($det['familia']) }}" aria-hidden="true"></span>{{ $det['texto'] }}
                                </p>
                                <p class="text-xs text-gray-600">{{ $det['detalle'] ?? ($abierta ? $abierta->etapa->label() : 'Sin detalle') }}</p>
                                <p class="mt-0.5 text-[11px] text-gray-400">Lo tiene: {{ $det['responsable'] }}</p>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-right">
                            @if ($abierta)
                                <p @class(['font-display text-lg font-bold leading-none', 'text-rose-600' => $excede, 'text-gray-900' => !$excede])>{{ $dias }}<span class="ml-1 font-sans text-xs font-medium text-gray-500">{{ $dias === 1 ? 'día' : 'días' }}</span></p>
                                @if ($max)
                                    <div class="ml-auto mt-1.5 h-1 w-20 overflow-hidden rounded-full bg-gray-100" title="Máximo documentado: {{ $max }} días hábiles">
                                        <div @class(['h-full rounded-full', 'bg-rose-500' => $excede, 'bg-amber-400' => !$excede && $avance >= 80, 'bg-udg-800' => !$excede && $avance < 80]) style="width: {{ $avance }}%"></div>
                                    </div>
                                    <p class="mt-1 text-[11px] {{ $excede ? 'font-semibold text-rose-600' : 'text-gray-400' }}">{{ $excede ? 'Fuera de plazo' : 'de ' . $max . ' hábiles' }}</p>
                                @else
                                    <p class="mt-1 text-[11px] text-gray-400">Sin plazo fijo</p>
                                @endif
                            @else
                                <span class="dato-vacio">Sin espera</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">
                            @if ($convenio->fecha_fin_vigencia)
                                <p class="text-sm font-medium text-gray-800">{{ $convenio->fecha_fin_vigencia->format('d/m/Y') }}</p>
                                <p class="text-xs text-gray-500">desde {{ $convenio->fecha_inicio_vigencia->format('d/m/Y') }}</p>
                            @else
                                <p class="text-xs text-gray-500">Recibido {{ $convenio->fecha_recepcion->format('d/m/Y') }}</p>
                                <span class="dato-vacio">Vigencia al formalizar</span>
                            @endif
                        </td>
                        <td class="acciones whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.boton-icono :href="route('admin.convenios.show', $convenio)" icono="flecha" :etiqueta="$convenio->estado->esFinal() ? 'Ver' : 'Gestionar'" tono="primario" con-texto />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4">
                            <x-dashboard.vacio icono="documento" titulo="{{ request()->hasAny(['q', 'estado']) ? 'No hay convenios con ese filtro' : 'Aún no hay convenios' }}"
                                texto="{{ request()->hasAny(['q', 'estado']) ? 'Prueba con otra búsqueda o quita los filtros.' : 'Cuando una organización envíe su solicitud aparecerá aquí.' }}" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <x-slot:pie>{{ $convenios->links() }}</x-slot:pie>
    </x-tabla.marco>
</div>
@endsection
