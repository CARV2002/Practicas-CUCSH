@extends('layouts.app')

@section('content')
    @php
        $instrucciones = [
            'acta_constitutiva' => 'Completa, con todas sus hojas y la inscripción en el Registro Público de Comercio.',
            'documento_creacion' => 'Ley, decreto o acuerdo publicado que crea tu dependencia.',
            'nombramiento_representante' => 'Nombramiento oficial vigente del titular o de quien firma el convenio.',
            'constancia_situacion_fiscal' => 'Descárgala del portal del SAT. Que no tenga más de 3 meses.',
            'identificacion_representante' => 'INE o pasaporte vigente de quien firma, por ambos lados en un solo PDF.',
            'comprobante_domicilio' => 'Luz, agua, teléfono o predial a nombre de la organización. Que no tenga más de 3 meses.',
            'poder_notarial' => 'El poder notarial con el que firma tu representante.',
            'cv_instancia' => 'Una o dos páginas: a qué se dedica la organización, sus proyectos y clientes.',
        ];
        $estadoDe = fn ($d) => $d['media'] ? ($d['media']->getCustomProperty('estado') ?? 'pendiente') : null;
        $obligatorios = collect($documentos)->filter(fn ($d) => $d['obligatorio']);
        $subidos = $obligatorios->filter(fn ($d) => $d['media'])->count();
        $validados = $obligatorios->filter(fn ($d) => $estadoDe($d) === 'validada')->count();
        $orden = ['rechazada' => 0, null => 1, 'pendiente' => 2];
        $porAtender = collect($documentos)->filter(fn ($d) => $estadoDe($d) !== 'validada')
            ->sortBy(fn ($d) => ($orden[$estadoDe($d)] ?? 3) * 10 + ($d['obligatorio'] ? 0 : 5));
        $editables = $porAtender->filter(fn ($d) => $d['puedeSubir']);
        $yaValidados = collect($documentos)->filter(fn ($d) => $estadoDe($d) === 'validada');
        $devueltos = $porAtender->filter(fn ($d) => $estadoDe($d) === 'rechazada');
        $faltan = $obligatorios->filter(fn ($d) => !$d['media'])->count();
        $max = \App\Models\Empresa::MAX_MB;
        $pct = (int) round($subidos / max(1, $obligatorios->count()) * 100);
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Documentos" descripcion="Tu expediente para el convenio. Vinculación revisa cada documento y te avisa por correo si alguno necesita corrección.">
            @if ($enSolicitud)
                <x-slot:acciones>
                    <a href="{{ route('empresa.convenio', ['seccion' => 'documentos']) }}" class="boton boton-secundario"><x-dashboard.icono nombre="flecha" clase="size-4 rotate-180" /> Volver a Mi convenio</a>
                </x-slot:acciones>
            @endif
        </x-pagina.encabezado>

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="No se enviaron los documentos">
                <p class="mb-1">Corrige lo siguiente y vuelve a elegir los archivos:</p>
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-ui.aviso>
        @endif

        {{-- Situación del expediente: un solo mensaje, el más importante --}}
        @if ($devueltos->isNotEmpty())
            <x-ui.aviso tipo="error" titulo="Vinculación te devolvió {{ $devueltos->count() }} {{ $devueltos->count() === 1 ? 'documento' : 'documentos' }}">
                Lee el motivo en cada uno (marcados en rojo) y sube solo esos corregidos.
            </x-ui.aviso>
        @elseif ($bloqueado)
            <x-ui.aviso tipo="info" titulo="Tu solicitud está en revisión">Tus documentos quedan bloqueados mientras Vinculación los revisa. Si te devuelve alguno, aquí podrás reemplazar solo ese.</x-ui.aviso>
        @elseif ($enSolicitud && $faltan > 0)
            <x-ui.aviso tipo="advertencia" titulo="Paso 4 de tu solicitud: te faltan {{ $faltan }} {{ $faltan === 1 ? 'documento' : 'documentos' }}">
                Elige los archivos que tengas listos y envíalos juntos. Al enviarlos regresarás a «Mi convenio» para enviar tu solicitud.
            </x-ui.aviso>
        @elseif ($enSolicitud)
            <x-ui.aviso tipo="exito" :autocerrar="false" titulo="Ya subiste todos los documentos obligatorios">
                <a href="{{ route('empresa.convenio', ['seccion' => 'documentos']) }}" class="font-semibold underline underline-offset-2">Regresa a Mi convenio</a> para enviar tu solicitud a Vinculación.
            </x-ui.aviso>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs" aria-labelledby="titulo-expediente">
            <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="titulo-expediente" class="font-display text-base font-bold text-gray-900">Expediente de la organización</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Solo PDF, máximo {{ $max }} MB por archivo. Los documentos dependen de tu tipo de organismo ({{ mb_strtolower($empresa->tipo_organismo->label()) }}). <span class="text-rose-500">*</span> Obligatorio.</p>
                    </div>
                    <x-ui.estatus :familia="$expedienteEnOrden ? 'menta' : 'ambar'" tamano="normal"
                        :texto="$expedienteEnOrden ? ($expedienteCompleto ? 'Completo y validado' : 'En orden (convenio vigente)') : 'Incompleto'" />
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Documentos subidos">
                        <div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400 transition-[width] duration-700 ease-out motion-reduce:transition-none" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-xs font-semibold tabular-nums text-gray-700">{{ $subidos }} de {{ $obligatorios->count() }} subidos · {{ $validados }} validados</span>
                </div>
            </div>

            @if ($porAtender->isNotEmpty())
                {{-- Un solo envío: cada documento se valida por separado y los errores se juntan arriba --}}
                <form method="POST" action="{{ route('empresa.documentos.subir') }}" enctype="multipart/form-data"
                    x-data="{ elegidos: 0 }" x-on:change="elegidos = [...$el.querySelectorAll('input[type=file]')].filter(i => i.files.length).length">
                    @csrf
                    <ul class="space-y-3 p-4 sm:p-6">
                        @foreach ($porAtender as $col => $d)
                            @php
                                $st = $estadoDe($d);
                                $media = $d['media'];
                                $tono = ['rechazada' => 'rosa', 'pendiente' => 'cielo'][$st] ?? ($d['obligatorio'] ? 'ambar' : 'gris');
                            @endphp
                            <li @class([
                                'grid grid-cols-1 gap-4 rounded-2xl border p-4 transition sm:p-5 lg:grid-cols-[minmax(0,1fr)_22rem]',
                                'border-rose-200 bg-rose-50/50 shadow-[inset_4px_0_0_var(--color-rose-500)]' => $st === 'rechazada',
                                'border-gray-200 bg-white' => $st !== 'rechazada',
                            ])>
                                <div class="min-w-0">
                                    <div class="flex items-start gap-3">
                                        <span @class(['flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset',
                                            'bg-rose-100 text-rose-600 ring-rose-200' => $st === 'rechazada', 'bg-sky-50 text-sky-600 ring-sky-100' => $st === 'pendiente',
                                            'bg-amber-50 text-amber-600 ring-amber-100' => !$st])>
                                            <x-dashboard.icono :nombre="$st === 'rechazada' ? 'alerta' : ($st === 'pendiente' ? 'reloj' : 'subir')" clase="size-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900">{{ \App\Models\Empresa::nombreDocumento($col) }}@if ($d['obligatorio'])<span class="ml-0.5 text-rose-500" aria-hidden="true">*</span>@endif</p>
                                            <p class="mt-0.5 text-sm text-gray-600">{{ $instrucciones[$col] ?? '' }}</p>
                                            <x-ui.estatus class="mt-2" :familia="$tono"
                                                :texto="['rechazada' => 'Devuelto: súbelo corregido', 'pendiente' => 'En revisión de Vinculación'][$st] ?? ($d['obligatorio'] ? 'Falta subirlo' : 'Opcional')" />
                                        </div>
                                    </div>
                                    @if ($st === 'rechazada' && $media->getCustomProperty('motivo'))
                                        <div class="mt-3 flex items-start gap-2 rounded-xl bg-white px-3.5 py-2.5 text-sm text-rose-800 ring-1 ring-inset ring-rose-200">
                                            <x-dashboard.icono nombre="alerta" clase="mt-0.5 size-4 shrink-0" />
                                            <p><span class="font-semibold">Motivo de Vinculación:</span> {{ $media->getCustomProperty('motivo') }}</p>
                                        </div>
                                    @endif
                                    @if ($media)
                                        {{-- Archivo actual, con su vista previa --}}
                                        <div class="mt-3 flex flex-wrap items-center gap-3 rounded-xl bg-gray-50 px-3.5 py-2.5 ring-1 ring-inset ring-gray-100">
                                            <x-dashboard.icono nombre="documento" clase="size-5 shrink-0 text-gray-400" />
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-gray-800" title="{{ $media->file_name }}">{{ $media->file_name }}</p>
                                                <p class="text-xs text-gray-500">{{ $st === 'rechazada' ? 'Archivo devuelto' : 'Archivo actual' }} · subido el {{ $media->created_at->format('d/m/Y') }}</p>
                                            </div>
                                            <a href="{{ route('empresa.documentos.descargar', $media->id) }}" class="boton boton-secundario h-10 shrink-0"><x-dashboard.icono nombre="descargar" clase="size-4" /> Descargar</a>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    @if ($d['puedeSubir'])
                                        <x-ui.zona-archivo :name="'archivos[' . $col . ']'" :max-mb="$max" :requerido="false"
                                            :etiqueta="$st === 'rechazada' ? 'Sube el documento corregido' : ($media ? 'Reemplazar por otro archivo' : 'Elige el archivo')"
                                            :ayuda="$media ? 'El archivo actual se sustituye por el nuevo cuando envíes.' : null" />
                                    @else
                                        <p class="flex h-full items-center gap-2 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-500 ring-1 ring-inset ring-gray-100">
                                            <x-dashboard.icono nombre="candado" clase="size-4 shrink-0" /> Vinculación lo está revisando: podrás cambiarlo si te lo devuelve.
                                        </p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if ($editables->isNotEmpty())
                        <div class="sticky bottom-0 z-10 flex flex-col gap-2 border-t border-gray-100 bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <p class="text-sm text-gray-600" x-text="elegidos ? `Elegiste ${elegidos} ${elegidos === 1 ? 'archivo' : 'archivos'}: se enviarán juntos.` : 'Elige los archivos que tengas listos; se envían juntos con un solo botón.'"></p>
                            <button class="boton boton-primario shrink-0" :disabled="elegidos === 0" :class="elegidos === 0 && 'cursor-not-allowed opacity-60'">
                                <x-dashboard.icono nombre="enviar" clase="size-4" /> <span x-text="elegidos > 1 ? `Enviar ${elegidos} documentos` : 'Enviar documento'">Enviar documentos</span>
                            </button>
                        </div>
                    @endif
                </form>
            @else
                <div class="m-5 flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-100 sm:m-6">
                    <x-dashboard.icono nombre="check" clase="size-5 shrink-0" />
                    <p><span class="font-semibold">No tienes documentos por atender.</span> Vinculación ya validó todo tu expediente.</p>
                </div>
            @endif

            @if ($yaValidados->isNotEmpty())
                <div class="border-t border-gray-100 px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-gray-900">Validados por Vinculación <span class="font-normal text-gray-500">· {{ $yaValidados->count() }}</span></h3>
                    <ul class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                        @foreach ($yaValidados as $col => $d)
                            <li class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50/40 px-3.5 py-2.5">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700"><x-dashboard.icono nombre="check" clase="size-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-sm font-medium text-gray-900">{{ \App\Models\Empresa::nombreDocumento($col) }}</p>
                                    <p class="text-xs text-gray-500">Subido el {{ $d['media']->created_at->format('d/m/Y') }}</p>
                                </div>
                                <a href="{{ route('empresa.documentos.descargar', $d['media']->id) }}" class="boton boton-chico boton-secundario shrink-0"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Descargar</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 text-sm leading-relaxed text-gray-600 shadow-theme-xs">
                <p class="font-semibold text-gray-900">Tus datos legales están en «Mi convenio»</p>
                <p class="mt-1">El acta constitutiva, el representante y sus facultades se capturan en la sección «Declaraciones legales» de tu solicitud.</p>
                <a href="{{ route('empresa.convenio', ['seccion' => 'legales']) }}" class="boton boton-secundario mt-3">Ir a Declaraciones legales</a>
            </section>
            <section class="rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm leading-relaxed text-gray-600">
                <p class="font-semibold text-gray-900">¿Necesitas cambiar un documento ya validado?</p>
                <p class="mt-1">Por ejemplo, un cambio de apoderado o de domicilio fiscal. Escribe a Vinculación:</p>
                <a href="{{ \App\Support\Correo::gmail(config('practicas.contacto.correo'), 'Cambio de documento del expediente') }}" target="_blank" rel="noopener" class="enlace mt-2 inline-block break-all">{{ config('practicas.contacto.correo') }}</a>
            </section>
        </div>
    </div>
@endsection
