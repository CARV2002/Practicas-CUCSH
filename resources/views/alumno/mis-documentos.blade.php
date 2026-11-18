@extends('layouts.app')

@section('content')
    @php
        use App\Enums\PostulacionEstado as PE;
        use App\Enums\PracticaEstado as PrE;
        use App\Enums\ReporteEstado as RE;

        $input = 'campo';
        $btn = 'boton boton-chico boton-primario';
        $card = 'tarjeta overflow-hidden';
        $head = 'border-b border-gray-100 px-6 py-4';

        $estadoDoc = fn($m) => $m ? ($m->getCustomProperty('estado') ?? 'pendiente') : null;
        $colorDoc = fn($s) => match ($s) {
            'validada' => 'bg-green-50 text-green-700',
            'rechazada' => 'bg-red-50 text-red-700',
            'pendiente' => 'bg-yellow-50 text-yellow-700',
            default => 'bg-gray-100 text-gray-500',
        };
        $imssSt = $estadoDoc($imss);
    @endphp

    @php
        $estadosDoc = ['validada' => ['menta', 'Validado'], 'rechazada' => ['rosa', 'Rechazado'], 'pendiente' => ['ambar', 'En revisión']];
        $icono = fn ($s) => match ($s) { 'validada' => ['check', 'bg-emerald-50 text-emerald-600 ring-emerald-100'], 'rechazada' => ['equis', 'bg-rose-50 text-rose-600 ring-rose-100'], 'pendiente' => ['reloj', 'bg-amber-50 text-amber-600 ring-amber-100'], default => ['documento', 'bg-gray-50 text-gray-400 ring-gray-200'] };
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Mis documentos" descripcion="Todo lo que necesitas subir para tu práctica, en un solo lugar. Tu coordinación revisa cada documento y te avisa en la campana." />

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
        @endif

        {{-- IMSS --}}
        @php [$icImss, $colImss] = $icono($imssSt); @endphp
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs" aria-labelledby="titulo-imss">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset {{ $colImss }}"><x-dashboard.icono nombre="imss" /></span>
                    <div class="min-w-0">
                        <h2 id="titulo-imss" class="font-display text-base font-bold text-gray-900">Seguro facultativo (IMSS)</h2>
                        <p class="mt-0.5 text-sm text-gray-600">Requisito para postularte y activar tu práctica. Debe estar vigente durante todo el periodo.</p>
                        @if ($imss)
                            <a href="{{ route('alumno.documentos.descargar', $imss) }}" class="mt-2 inline-flex max-w-full items-center gap-1 text-sm font-medium text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-4 shrink-0" /><span class="truncate">{{ $imss->file_name }}</span></a>
                            <p class="text-xs text-gray-500">Vigencia declarada: <span class="{{ $alumno->vigencia_imss ? 'font-semibold text-gray-800' : 'italic text-gray-400' }}">{{ $alumno->vigencia_imss?->format('d/m/Y') ?? 'sin capturar' }}</span></p>
                            @if ($imssSt === 'rechazada' && $imss->getCustomProperty('motivo'))<p class="mt-1 text-xs font-medium text-rose-600">Motivo: {{ $imss->getCustomProperty('motivo') }}</p>@endif
                        @else
                            <p class="dato-vacio mt-2">Aún no subes tu constancia</p>
                        @endif
                    </div>
                </div>
                <x-ui.estatus :familia="$imssSt ? $estadosDoc[$imssSt][0] : 'gris'" :texto="$imssSt ? $estadosDoc[$imssSt][1] : 'Pendiente de subir'" tamano="normal" class="shrink-0 self-start" />
            </div>
            @if ($imssSt !== 'validada')
                <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.imss') }}" class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50/60 px-5 py-4 sm:flex-row sm:items-end sm:px-6">
                    @csrf
                    <div>
                        <label for="vig-imss" class="etiqueta">Vigencia de tu seguro</label>
                        <input id="vig-imss" type="date" name="vigencia_imss" required min="{{ now()->addDay()->toDateString() }}" value="{{ old('vigencia_imss') }}" class="campo h-9 w-44 text-sm">
                    </div>
                    <x-ui.subir-archivo accept=".pdf,.jpg,.jpeg,.png" :boton="$imss ? 'Reemplazar constancia' : 'Subir constancia'" ayuda="PDF o imagen de tu constancia de vigencia de derechos." />
                </form>
            @endif
        </section>

        {{-- Postulaciones --}}
        <section aria-labelledby="titulo-postulaciones" class="space-y-3">
            <h2 id="titulo-postulaciones" class="font-display text-lg font-bold text-gray-900">Documentos de mis postulaciones</h2>
            @forelse ($postulaciones as $po)
                @php
                    $kardex = $po->getFirstMedia('kardex');
                    $presentacion = $po->getFirstMedia('carta_presentacion');
                    $aceptacion = $po->getFirstMedia('carta_aceptacion');
                    $abierta = in_array($po->estado, [PE::Pendiente, PE::CartaPresentacionEmitida], true);
                    $kardexSt = $estadoDoc($kardex);
                @endphp
                <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $po->programa->titulo }}</p>
                            <p class="text-xs text-gray-500">{{ $po->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }} · ciclo {{ $po->programa->ciclo }}</p>
                        </div>
                        <x-ui.estatus :estado="$po->estado" />
                    </div>
                    <ol class="grid grid-cols-1 divide-y divide-gray-100 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                        {{-- 1. Kardex --}}
                        <li class="flex flex-col gap-2 p-5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900"><span class="mr-1.5 text-gray-400">1</span>Kardex</p>
                                <x-ui.estatus :familia="$kardexSt ? $estadosDoc[$kardexSt][0] : 'gris'" :texto="$kardexSt ? $estadosDoc[$kardexSt][1] : 'Sin archivo'" />
                            </div>
                            @if ($kardex)
                                <a href="{{ route('alumno.documentos.descargar', $kardex) }}" class="inline-flex max-w-full items-center gap-1 text-xs font-medium text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5 shrink-0" /><span class="truncate">{{ $kardex->file_name }}</span></a>
                                @if ($kardexSt === 'rechazada' && $kardex->getCustomProperty('motivo'))<p class="text-xs text-rose-600">Motivo: {{ $kardex->getCustomProperty('motivo') }}</p>@endif
                            @else
                                <p class="dato-vacio">Aún no subes tu kardex</p>
                            @endif
                            @if ($abierta)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.kardex', $po) }}" class="mt-auto pt-1">
                                    @csrf
                                    <x-ui.subir-archivo accept=".pdf,.jpg,.jpeg,.png" :boton="$kardex ? 'Reemplazar' : 'Subir'" />
                                </form>
                            @endif
                        </li>
                        {{-- 2. Carta de presentación --}}
                        <li class="flex flex-col gap-2 p-5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900"><span class="mr-1.5 text-gray-400">2</span>Carta de presentación</p>
                                <x-ui.estatus :familia="$presentacion ? 'menta' : 'gris'" :texto="$presentacion ? 'Emitida' : 'Pendiente'" />
                            </div>
                            @if ($presentacion)
                                <a href="{{ route('alumno.documentos.descargar', $presentacion) }}" class="boton boton-chico boton-secundario self-start"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Descargar carta</a>
                                <p class="text-xs text-gray-500">Preséntate con la organización con esta carta.</p>
                            @elseif ($po->estado === PE::Pendiente)
                                <p class="text-xs text-gray-500">Tu coordinación la emite al validar tus créditos y tu kardex.</p>
                            @else
                                <p class="dato-vacio">No se emitió carta para esta postulación</p>
                            @endif
                        </li>
                        {{-- 3. Carta de aceptación --}}
                        <li class="flex flex-col gap-2 p-5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900"><span class="mr-1.5 text-gray-400">3</span>Carta de aceptación</p>
                                <x-ui.estatus :familia="$aceptacion ? 'menta' : 'gris'" :texto="$aceptacion ? 'Entregada' : 'Pendiente'" />
                            </div>
                            @if ($aceptacion)
                                <a href="{{ route('alumno.documentos.descargar', $aceptacion) }}" class="inline-flex max-w-full items-center gap-1 text-xs font-medium text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5 shrink-0" /><span class="truncate">{{ $aceptacion->file_name }}</span></a>
                            @else
                                <p class="dato-vacio">{{ $po->estado === PE::CartaPresentacionEmitida ? 'Súbela cuando la organización te la entregue sellada' : 'Se sube después de la carta de presentación' }}</p>
                            @endif
                            @if ($po->estado === PE::CartaPresentacionEmitida)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.aceptacion', $po) }}" class="mt-auto pt-1">
                                    @csrf
                                    <x-ui.subir-archivo :boton="$aceptacion ? 'Reemplazar' : 'Subir'" />
                                </form>
                            @endif
                        </li>
                    </ol>
                </article>
            @empty
                <x-dashboard.vacio icono="buscar" titulo="Aún no te has postulado a ningún programa" texto="Cuando te postules, aquí verás tu kardex y tus cartas." class="rounded-2xl border border-gray-200 bg-white" />
                <div class="flex justify-center"><a href="{{ route('alumno.oferta-programas') }}" class="boton boton-primario">Explorar programas</a></div>
            @endforelse
        </section>

        {{-- Prácticas --}}
        @foreach ($practicas as $pr)
            @php
                $carta = $pr->cartaTermino?->getFirstMedia('carta_termino');
                $cartaSt = $pr->cartaTermino ? $pr->cartaTermino->estado_validacion->value : null;
                $motivoCarta = $pr->motivoSinCartaTermino();
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs" aria-labelledby="titulo-practica-{{ $pr->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 id="titulo-practica-{{ $pr->id }}" class="font-display text-base font-bold text-gray-900">{{ $pr->postulacion->programa->titulo }}</h2>
                        <p class="text-xs text-gray-500">{{ $pr->fecha_inicio->format('d/m/Y') }} al {{ $pr->fecha_fin->format('d/m/Y') }} · reportes y carta de término</p>
                    </div>
                    <x-ui.estatus :estado="$pr->estado" />
                </div>

                @if ($pr->estado === PrE::EnCurso)
                    <details class="group border-b border-gray-100" @if ($errors->hasAny(['tipo', 'periodo_inicio', 'periodo_fin', 'horas_reportadas'])) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-udg-50/50 px-5 py-3 text-sm font-semibold text-udg-900 hover:bg-udg-50 sm:px-6">
                            <span class="flex items-center gap-2"><x-dashboard.icono nombre="mas" clase="size-4" /> Registrar un reporte nuevo</span>
                            <svg class="size-4 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.reporte.crear', $pr) }}" class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                            @csrf
                            <div>
                                <label class="etiqueta" for="tipo-{{ $pr->id }}">Tipo</label>
                                <select id="tipo-{{ $pr->id }}" name="tipo" required class="campo h-10">
                                    @foreach ($alumno->carrera->esquema_reportes->tipos() as $t)
                                        <option value="{{ $t->value }}" @selected(old('tipo') === $t->value)>{{ $t->label() }}</option>
                                    @endforeach
                                </select>
                                <p class="ayuda">Esquema: {{ $alumno->carrera->esquema_reportes->label() }}</p>
                            </div>
                            <div><label class="etiqueta" for="ini-{{ $pr->id }}">Del</label><input id="ini-{{ $pr->id }}" type="date" name="periodo_inicio" value="{{ old('periodo_inicio') }}" required class="campo h-10"></div>
                            <div><label class="etiqueta" for="fin-{{ $pr->id }}">Al</label><input id="fin-{{ $pr->id }}" type="date" name="periodo_fin" value="{{ old('periodo_fin') }}" required class="campo h-10"></div>
                            <div><label class="etiqueta" for="hrs-{{ $pr->id }}">Horas del periodo</label><input id="hrs-{{ $pr->id }}" type="number" name="horas_reportadas" value="{{ old('horas_reportadas') }}" min="1" max="1000" required class="campo h-10 tabular-nums"></div>
                            <div class="sm:col-span-2 lg:col-span-4">
                                <p class="etiqueta">Reporte firmado (PDF)</p>
                                <x-ui.subir-archivo boton="Enviar reporte" />
                            </div>
                        </form>
                    </details>
                @endif

                <ul class="divide-y divide-gray-100">
                    @forelse ($pr->reportes->sortByDesc('periodo_fin') as $r)
                        @php $pdf = $r->media->firstWhere('collection_name', 'reporte_firmado'); @endphp
                        <li class="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-gray-50/60 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-udg-50 text-udg-900 ring-1 ring-inset ring-udg-100">
                                    <span class="font-display text-sm font-bold leading-none tabular-nums">{{ $r->horas_reportadas }}</span><span class="text-[10px] font-medium">h</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">Reporte {{ mb_strtolower($r->tipo->label()) }}</p>
                                    <p class="text-xs text-gray-500">{{ $r->periodo_inicio->format('d/m/Y') }} – {{ $r->periodo_fin->format('d/m/Y') }}</p>
                                    @if ($pdf)
                                        <a href="{{ route('alumno.documentos.descargar', $pdf) }}" class="inline-flex max-w-full items-center gap-1 text-xs font-medium text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5 shrink-0" /><span class="truncate">{{ $pdf->file_name }}</span></a>
                                    @else
                                        <p class="dato-vacio">Falta subir el PDF firmado</p>
                                    @endif
                                    @if ($r->evaluacion_profesor)<p class="mt-1 text-xs text-gray-600"><span class="font-semibold text-gray-800">Tu coordinación:</span> {{ $r->evaluacion_profesor }}</p>@endif
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <x-ui.estatus :estado="$r->estado" />
                                @if ($pr->estado === PrE::EnCurso && in_array($r->estado, [RE::Pendiente, RE::Rechazado], true))
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.reporte', $r) }}">
                                        @csrf
                                        <x-ui.subir-archivo :boton="$pdf ? 'Reemplazar' : 'Subir firmado'" />
                                    </form>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-4 sm:px-6"><x-dashboard.vacio icono="reporte" titulo="Aún no hay reportes registrados" texto="Registra tu primer reporte con el botón de arriba." /></li>
                    @endforelse

                    {{-- Carta de término --}}
                    @php [$icCarta, $colCarta] = $icono($cartaSt); @endphp
                    <li class="flex flex-col gap-3 bg-gray-50/60 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ $colCarta }}"><x-dashboard.icono nombre="cierre" clase="size-[18px]" /></span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">Carta de término</p>
                                @if ($carta)
                                    <a href="{{ route('alumno.documentos.descargar', $carta) }}" class="inline-flex max-w-full items-center gap-1 text-xs font-medium text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5 shrink-0" /><span class="truncate">{{ $carta->file_name }}</span></a>
                                @else
                                    <p class="dato-vacio">Se sube al completar tus horas</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                            @if ($cartaSt)<x-ui.estatus :familia="$estadosDoc[$cartaSt][0]" :texto="$estadosDoc[$cartaSt][1]" />@endif
                            @if ($motivoCarta === null)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.documentos.termino', $pr) }}">
                                    @csrf
                                    <x-ui.subir-archivo :boton="$carta ? 'Reemplazar' : 'Subir carta'" />
                                </form>
                            @elseif ($cartaSt !== 'validada')
                                <p class="flex max-w-xs items-center gap-1 text-xs text-gray-500"><x-dashboard.icono nombre="candado" clase="size-3.5 shrink-0" /> {{ $motivoCarta }}</p>
                            @endif
                        </div>
                    </li>
                </ul>
            </section>
        @endforeach
    </div>
@endsection
