@extends('layouts.app')

@section('content')
    @php
        use App\Enums\PostulacionEstado as PE;
        use App\Enums\PracticaEstado as PrE;
        use App\Enums\ReporteEstado as RE;

        $estadoDe = fn ($m) => $m ? ($m->getCustomProperty('estado') ?? 'pendiente') : null;

        // Postulación en revisión de documentos (solo puede haber una en trámite a la vez)
        $enRevision = $postulaciones->first(fn ($p) => $p->estado === PE::Pendiente);
        $enCurso = $postulaciones->first(fn ($p) => in_array($p->estado, [PE::CartaPresentacionEmitida, PE::CartaAceptacionRecibida], true));
        $kardex = $enRevision?->documentoVigente('kardex');
        $kardexSt = $estadoDe($kardex);

        // IMSS: validado y vigente = listo; validado pero vencido = hay que renovarlo
        $imssSt = $estadoDe($imss);
        $imssVencido = $imssSt === 'validada' && !$alumno->tieneImssVigenteHasta(now());
        if ($imssVencido) { $imssSt = null; }

        $practicaActiva = $practicas->first(fn ($p) => in_array($p->estado, PrE::activas(), true));

        // Paso actual de la guía
        $paso = match (true) {
            $alumno->yaAcreditado() => 6,
            $practicaActiva !== null => 5,
            $enCurso !== null => 4,
            $enRevision !== null => ($imssSt === 'rechazada' || $kardexSt === 'rechazada') ? 2 : 3,
            default => 1,
        };
        $pasos = ['Postúlate con tu kardex e IMSS', 'Corrige lo que te pidan', 'Revisión de tu coordinación', 'Cartas de presentación y aceptación', 'Práctica y reportes', 'Acreditada'];
        $devuelto = $enRevision && ($imssSt === 'rechazada' || $kardexSt === 'rechazada');
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Mis documentos" descripcion="Todo lo que entregas para tu práctica, en orden. Cada documento te dice si está en revisión, validado o qué debes corregir." />

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="No se pudo enviar">Revisa el mensaje en rojo junto al documento y vuelve a intentarlo.</x-ui.aviso>
        @endif

        {{-- Guía: en qué paso vas --}}
        <nav aria-label="Avance de tu trámite" class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs no-scrollbar sm:p-5">
            <ol class="flex min-w-max items-center gap-2 sm:min-w-0 sm:gap-0">
                @foreach ($pasos as $i => $nombre)
                    @continue($i === 1 && !$devuelto && $paso !== 2)
                    @php $n = $i + 1; $hecho = $n < $paso; $actual = $n === $paso; $numero = ($numero ?? 0) + 1; @endphp
                    <li class="flex flex-1 items-center gap-2 sm:gap-3" @if ($actual) aria-current="step" @endif>
                        <span @class([
                            'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-4 transition',
                            'bg-udg-800 text-white ring-udg-100' => $hecho,
                            'bg-oro-400 text-udg-950 ring-oro-100' => $actual && $n !== 2,
                            'bg-rose-500 text-white ring-rose-100' => $actual && $n === 2,
                            'bg-gray-100 text-gray-400 ring-transparent' => !$hecho && !$actual,
                        ])>
                            @if ($hecho)<x-dashboard.icono nombre="check" clase="size-4" />@else{{ $n === 2 ? '!' : $numero }}@endif
                        </span>
                        <span @class(['text-xs font-semibold leading-tight sm:text-sm', 'text-gray-900' => $actual, 'text-gray-500' => !$actual])>{{ $nombre }}</span>
                        @unless ($loop->last)<span class="mx-1 hidden h-px flex-1 bg-gray-200 sm:block" aria-hidden="true"></span>@endunless
                    </li>
                @endforeach
            </ol>
        </nav>

        @if ($devuelto)
            <x-ui.aviso tipo="error" titulo="Tu coordinación te pidió corregir un documento">
                Sube el PDF corregido en la tarjeta marcada en rojo. En cuanto lo envíes, tu solicitud vuelve a revisión; no necesitas postularte de nuevo.
            </x-ui.aviso>
        @endif

        {{-- Documentos de la solicitud: se entregan y se revisan juntos --}}
        <section aria-labelledby="titulo-solicitud" class="space-y-3">
            <div>
                <h2 id="titulo-solicitud" class="font-display text-lg font-bold text-gray-900">Documentos para autorizar tu práctica</h2>
                <p class="text-sm text-gray-600">Tu coordinación revisa tu <strong>kardex</strong> y tu <strong>constancia IMSS</strong> al mismo tiempo. Si los dos están bien, te autoriza y emite tu carta de presentación.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- IMSS --}}
                <x-ui.carga-documento
                    :action="route('alumno.documentos.imss')"
                    titulo="Constancia de vigencia del IMSS"
                    :instrucciones="$imssVencido
                        ? 'Tu constancia venció el ' . $alumno->vigencia_imss->format('d/m/Y') . '. Descarga una actualizada en imss.gob.mx y súbela.'
                        : 'Descárgala en imss.gob.mx (Constancia de vigencia de derechos) con tu NSS. Debe estar vigente durante toda tu práctica.'"
                    accept=".pdf,.jpg,.jpeg,.png" formatos="PDF, JPG o PNG"
                    :estado="$imssSt" :motivo="$imss?->getCustomProperty('motivo')"
                    :archivo="$imss?->file_name" :url-archivo="$imss ? route('alumno.documentos.descargar', $imss) : null"
                    :fecha="$imss?->created_at?->format('d/m/Y')"
                    :paso-archivo="2"
                    :boton="$imssSt === 'rechazada' ? 'Enviar constancia corregida' : ($imss ? 'Reemplazar constancia' : 'Guardar constancia')">
                    <x-slot:campos>
                        <x-ui.campo-paso paso="1" etiqueta="¿Hasta qué fecha está vigente tu seguro?" name="vigencia_imss" type="date"
                            :value="$alumno->vigencia_imss?->toDateString()" min="{{ now()->addDay()->toDateString() }}"
                            ayuda="Aparece en tu constancia como «Vigencia» o «Vigente hasta». Usa esa misma fecha." />
                    </x-slot:campos>
                    @if ($imssSt === 'validada')
                        <p class="mt-2 text-xs text-gray-500">Vigente hasta el <span class="font-semibold text-gray-800">{{ $alumno->vigencia_imss->format('d/m/Y') }}</span>.</p>
                    @elseif (!$enRevision && !$practicaActiva && $imss)
                        <p class="mt-3 text-xs text-gray-500">Se revisará junto con tu kardex cuando te postules a una vacante.</p>
                    @endif
                </x-ui.carga-documento>

                {{-- Kardex --}}
                @if ($enRevision)
                    <x-ui.carga-documento
                        :action="route('alumno.documentos.kardex', $enRevision)"
                        titulo="Kardex simple"
                        instrucciones="Descárgalo de SIIAU (Kardex simple) del ciclo actual. Debe mostrar tu porcentaje de créditos."
                        accept=".pdf,.jpg,.jpeg,.png" formatos="PDF, JPG o PNG"
                        :estado="$kardexSt" :motivo="$kardex?->getCustomProperty('motivo')"
                        :archivo="$kardex?->file_name" :url-archivo="$kardex ? route('alumno.documentos.descargar', $kardex) : null"
                        :fecha="$kardex?->created_at?->format('d/m/Y')"
                        :boton="$kardexSt === 'rechazada' ? 'Enviar kardex corregido' : 'Reemplazar kardex'">
                        <p class="mt-2 text-xs text-gray-500">Para «{{ $enRevision->programa->titulo }}» · créditos declarados: <span class="font-semibold text-gray-800">{{ rtrim(rtrim((string) $enRevision->porcentaje_creditos_declarado, '0'), '.') }}%</span></p>
                    </x-ui.carga-documento>
                @else
                    @php $kardexPrevio = ($enCurso ?? $postulaciones->first())?->documentoVigente('kardex'); @endphp
                    <x-ui.carga-documento titulo="Kardex simple"
                        :instrucciones="$kardexPrevio ? null : 'Lo adjuntas al postularte a una vacante, junto con tu constancia IMSS.'"
                        :estado="$estadoDe($kardexPrevio)" :archivo="$kardexPrevio?->file_name"
                        :url-archivo="$kardexPrevio ? route('alumno.documentos.descargar', $kardexPrevio) : null"
                        :bloqueo="$kardexPrevio ? null : 'Se entrega en el formulario de postulación de la vacante que elijas.'">
                        @unless ($kardexPrevio)
                            <a href="{{ route('alumno.oferta-programas') }}" class="boton boton-primario mt-4 w-full justify-center"><x-dashboard.icono nombre="buscar" clase="size-4" /> Ver vacantes de mi carrera</a>
                        @endunless
                    </x-ui.carga-documento>
                @endif
            </div>

            @if ($enRevision && !$devuelto)
                <p class="flex items-center gap-2 rounded-xl bg-udg-50/70 px-4 py-3 text-sm text-udg-900 ring-1 ring-inset ring-udg-100">
                    <x-dashboard.icono nombre="reloj" clase="size-4 shrink-0" />
                    Tu coordinación está revisando tus documentos. Te avisaremos en la campana y por correo cuando te autorice o si necesita una corrección.
                </p>
            @endif
        </section>

        {{-- Cartas de cada postulación --}}
        @foreach ($postulaciones->filter(fn ($p) => $p->estado !== PE::Pendiente) as $po)
            @php
                $presentacion = $po->getFirstMedia('carta_presentacion');
                $aceptacion = $po->getFirstMedia('carta_aceptacion');
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs" aria-label="Cartas de {{ $po->programa->titulo }}">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="font-display text-base font-bold text-gray-900">{{ $po->programa->titulo }}</p>
                        <p class="text-xs text-gray-500">{{ $po->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }} · ciclo {{ $po->programa->ciclo }}</p>
                    </div>
                    <x-ui.estatus :estado="$po->estado" />
                </div>

                @if (in_array($po->estado, [PE::Rechazada, PE::Cancelada], true))
                    <p class="px-5 py-4 text-sm text-gray-600 sm:px-6">Esta postulación ya no está activa. Puedes postularte a otra vacante de tu carrera.</p>
                @else
                    <div class="grid grid-cols-1 gap-4 p-5 sm:p-6 lg:grid-cols-2">
                        <x-ui.carga-documento titulo="Carta de presentación" compacto
                            :estado="$presentacion ? 'validada' : null" :archivo="$presentacion?->file_name"
                            :url-archivo="$presentacion ? route('alumno.documentos.descargar', $presentacion) : null"
                            :bloqueo="$presentacion ? null : 'Tu coordinación la emite al autorizar tus documentos.'">
                            @if ($presentacion)
                                <p class="mt-2 text-xs text-gray-600">Imprímela y entrégala en la organización. Ahí te darán tu carta de aceptación firmada y sellada.</p>
                            @endif
                        </x-ui.carga-documento>

                        <x-ui.carga-documento compacto
                            :action="$po->estado === PE::CartaPresentacionEmitida ? route('alumno.documentos.aceptacion', $po) : null"
                            titulo="Carta de aceptación"
                            instrucciones="En hoja membretada de la organización, firmada y sellada. Escanéala completa."
                            :estado="$aceptacion ? ($po->estado === PE::CartaPresentacionEmitida ? 'pendiente' : 'validada') : null"
                            :archivo="$aceptacion?->file_name" :url-archivo="$aceptacion ? route('alumno.documentos.descargar', $aceptacion) : null"
                            :fecha="$aceptacion?->created_at?->format('d/m/Y')"
                            :bloqueo="$po->estado === PE::Pendiente ? 'Se sube después de recibir tu carta de presentación.' : null"
                            :boton="$aceptacion ? 'Reemplazar carta' : 'Enviar carta de aceptación'" />
                    </div>
                @endif
            </section>
        @endforeach

        {{-- Prácticas: reportes y carta de término --}}
        @foreach ($practicas as $pr)
            @php
                $carta = $pr->cartaTermino?->getFirstMedia('carta_termino');
                $cartaSt = $pr->cartaTermino?->estado_validacion?->value;
                $motivoCarta = $pr->motivoSinCartaTermino();
                $horas = $pr->horas_acumuladas;
                $pct = $pr->horas_requeridas > 0 ? min(100, (int) round($horas / $pr->horas_requeridas * 100)) : 0;
                $activa = $pr->estado === PrE::EnCurso;
            @endphp
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs" aria-labelledby="titulo-practica-{{ $pr->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 id="titulo-practica-{{ $pr->id }}" class="font-display text-base font-bold text-gray-900">{{ $pr->postulacion->programa->titulo }}</h2>
                        <p class="text-xs text-gray-500">Del {{ $pr->fecha_inicio->format('d/m/Y') }} al {{ $pr->fecha_fin->format('d/m/Y') }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-40">
                            <div class="flex justify-between text-xs"><span class="font-semibold tabular-nums text-gray-900">{{ $horas }} / {{ $pr->horas_requeridas }} h</span><span class="text-gray-500">{{ $pct }}%</span></div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $pct }}%"></div></div>
                        </div>
                        <x-ui.estatus :estado="$pr->estado" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 p-5 sm:p-6 xl:grid-cols-[1fr_22rem]">
                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold text-gray-900">Reportes</h3>
                        @forelse ($pr->reportes->sortByDesc('periodo_fin') as $r)
                            @php
                                $pdf = $r->media->firstWhere('collection_name', 'reporte_firmado');
                                $rSt = match ($r->estado) { RE::Validado => 'validada', RE::Rechazado => 'rechazada', RE::Pendiente => $pdf ? 'pendiente' : null, default => null };
                            @endphp
                            <x-ui.carga-documento compacto
                                :action="$activa && in_array($r->estado, [RE::Pendiente, RE::Rechazado], true) ? route('alumno.documentos.reporte', $r) : null"
                                :titulo="'Reporte ' . mb_strtolower($r->tipo->label()) . ' · ' . $r->horas_reportadas . ' h'"
                                :instrucciones="$r->periodo_inicio->format('d/m/Y') . ' al ' . $r->periodo_fin->format('d/m/Y') . ' · firmado y sellado por tu jefe directo.'"
                                :estado="$rSt" :motivo="$r->evaluacion_profesor"
                                :archivo="$pdf?->file_name" :url-archivo="$pdf ? route('alumno.documentos.descargar', $pdf) : null"
                                :boton="$r->estado === RE::Rechazado ? 'Enviar reporte corregido' : ($pdf ? 'Reemplazar reporte' : 'Subir reporte firmado')"
                                :bloqueo="$r->estado === RE::Anulado ? 'Reporte anulado.' : null" />
                        @empty
                            <x-dashboard.vacio icono="reporte" titulo="Aún no registras reportes" texto="Registra el primero con el formulario de la derecha." />
                        @endforelse
                    </div>

                    <div class="space-y-4">
                        @if ($activa && !$pr->horasCompletas())
                            <x-ui.carga-documento :action="route('alumno.documentos.reporte.crear', $pr)" titulo="Registrar un reporte nuevo"
                                instrucciones="Captura el periodo y las horas, y sube el formato firmado y sellado por tu jefe directo."
                                :paso-archivo="4" boton="Enviar reporte" compacto>
                                <x-slot:campos>
                                    <div>
                                        <label class="etiqueta" for="tipo-{{ $pr->id }}"><span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-udg-800 text-[11px] font-bold text-white">1</span>Tipo de reporte</label>
                                        <select id="tipo-{{ $pr->id }}" name="tipo" required class="campo h-11">
                                            @foreach ($alumno->carrera->esquema_reportes->tipos() as $t)
                                                <option value="{{ $t->value }}" @selected(old('tipo') === $t->value)>{{ $t->label() }}</option>
                                            @endforeach
                                        </select>
                                        <p class="ayuda">Tu carrera entrega reportes con esquema {{ mb_strtolower($alumno->carrera->esquema_reportes->label()) }}.</p>
                                        @error('tipo')<p class="mt-1 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <x-ui.campo-paso paso="2" etiqueta="Del" name="periodo_inicio" type="date" min="{{ $pr->fecha_inicio->toDateString() }}" max="{{ today()->min($pr->fecha_fin)->toDateString() }}" />
                                        <x-ui.campo-paso etiqueta="Al" name="periodo_fin" type="date" min="{{ $pr->fecha_inicio->toDateString() }}" max="{{ today()->min($pr->fecha_fin)->toDateString() }}" />
                                    </div>
                                    <x-ui.campo-paso paso="3" etiqueta="Horas trabajadas en el periodo" name="horas_reportadas" type="number" min="1" max="{{ max(1, $pr->horas_requeridas - $horas) }}"
                                        ayuda="Te faltan {{ max(0, $pr->horas_requeridas - $horas) }} horas validadas." />
                                </x-slot:campos>
                            </x-ui.carga-documento>
                        @endif

                        <x-ui.carga-documento
                            :action="$motivoCarta === null ? route('alumno.documentos.termino', $pr) : null"
                            titulo="Carta de término"
                            instrucciones="La emite la organización al completar tus horas: hoja membretada, firmada y sellada."
                            :estado="$cartaSt" :motivo="$carta?->getCustomProperty('motivo')"
                            :archivo="$carta?->file_name" :url-archivo="$carta ? route('alumno.documentos.descargar', $carta) : null"
                            :bloqueo="$motivoCarta"
                            :boton="$carta ? 'Reemplazar carta' : 'Enviar carta de término'" compacto />
                    </div>
                </div>
            </section>
        @endforeach
    </div>
@endsection
