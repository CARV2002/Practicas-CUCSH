@extends('layouts.app')

@section('content')
    @php
        use App\Enums\PostulacionEstado as PE;
        use App\Enums\PracticaEstado as PrE;
        use App\Enums\ReporteEstado as RE;

        $estadoDe = fn ($m) => $m ? ($m->getCustomProperty('estado') ?? 'pendiente') : null;

        // Expediente (kardex + IMSS): se entrega junto y se valida ANTES de postularse
        $expediente = $alumno->estadoExpediente();
        $kardex = $alumno->documentoVigente('kardex');
        $kardexSt = $estadoDe($kardex);
        $imssSt = $estadoDe($imss);
        $imssVencido = $imssSt === 'validada' && !$alumno->tieneImssVigenteHasta(now());   // validado pero ya venció: hay que renovarlo
        if ($imssVencido) { $imssSt = null; }

        $pendiente = $postulaciones->first(fn ($p) => $p->estado === PE::Pendiente);
        $enCurso = $postulaciones->first(fn ($p) => in_array($p->estado, [PE::CartaPresentacionEmitida, PE::CartaAceptacionRecibida], true));
        $practicaActiva = $practicas->first(fn ($p) => in_array($p->estado, PrE::activas(), true));
        $puedePostularse = \App\Support\Requisitos::alumnoVerOferta($alumno) === null;

        // Guía: los mismos pasos que el panel (App\Support\TramiteAlumno)
        $tramite = \App\Support\TramiteAlumno::de($alumno);
        $pasos = $tramite->pasos();
        $minimo = \App\Support\Formato::porcentaje($alumno->carrera->porcentaje_creditos_minimo);
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Mis documentos" descripcion="Todo lo que entregas para tu práctica, en orden. Cada documento te dice si está en revisión, validado o qué debes corregir." />

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="No se pudo enviar">{{ $errors->first('expediente') ?: 'Revisa el mensaje en rojo junto al documento y vuelve a intentarlo.' }}</x-ui.aviso>
        @endif

        {{-- Todo lo que te devolvieron, en un solo lugar: qué, por qué y dónde corregirlo (marcado en rojo abajo) --}}
        @php
            $devueltos = collect();
            if ($kardexSt === 'rechazada') { $devueltos->push(['Kardex', $kardex?->getCustomProperty('motivo'), '#expediente', 'Tu expediente']); }
            if ($imssSt === 'rechazada') { $devueltos->push(['Constancia IMSS', $imss?->getCustomProperty('motivo'), '#expediente', 'Tu expediente']); }
            foreach ($practicas as $prDev) {
                foreach ($prDev->reportes->where('estado', RE::Rechazado) as $rDev) {
                    $devueltos->push([($rDev->numero ? 'Reporte ' . $rDev->numero : 'Reporte') . ' (' . $rDev->periodo_inicio->format('d/m') . ' al ' . $rDev->periodo_fin->format('d/m/Y') . ')', $rDev->evaluacion_profesor, '#reporte-' . $rDev->id, 'Reportes']);
                }
                if ($prDev->cartaTermino?->estado_validacion?->value === 'rechazada') {
                    $devueltos->push(['Carta de término', $prDev->cartaTermino->getFirstMedia('carta_termino')?->getCustomProperty('motivo'), '#carta-' . $prDev->id, 'Carta de término']);
                }
            }
        @endphp
        @if ($devueltos->isNotEmpty())
            <x-ui.correccion :titulo="'Tu coordinación te devolvió ' . $devueltos->count() . ($devueltos->count() === 1 ? ' documento para corregir' : ' documentos para corregir')">
                corrige cada uno en su apartado, marcado en rojo más abajo:
                <ul class="mt-2 space-y-1.5">
                    @foreach ($devueltos as [$doc, $motivoDev, $ancla, $apartado])
                        <li class="flex flex-wrap items-baseline gap-x-2 rounded-lg bg-white/70 px-3 py-2 ring-1 ring-inset ring-rose-100">
                            <span class="font-semibold">{{ $doc }}</span>
                            <span class="text-rose-800">{{ $motivoDev ? '«' . $motivoDev . '»' : 'Sin motivo escrito: pregunta a tu coordinación.' }}</span>
                            <a href="{{ $ancla }}" class="ml-auto font-semibold text-real-700 underline underline-offset-2 hover:text-real-800">Ir a «{{ $apartado }}» →</a>
                        </li>
                    @endforeach
                </ul>
            </x-ui.correccion>
        @endif

        {{-- Guía: en qué paso vas --}}
        <nav aria-label="Avance de tu trámite" class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs no-scrollbar sm:p-5">
            <ol class="flex min-w-max items-center gap-2 sm:min-w-0 sm:gap-0">
                @foreach ($pasos as $i => ['titulo' => $nombre, 'estado' => $estadoPaso])
                    @php $n = $i + 1; $hecho = $estadoPaso === 'hecho'; $alerta = $estadoPaso === 'alerta'; $actual = $estadoPaso === 'actual' || $alerta; @endphp
                    <li class="flex flex-1 items-center gap-2 sm:gap-3" @if ($actual) aria-current="step" @endif>
                        <span @class([
                            'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-4 transition',
                            'bg-udg-800 text-white ring-udg-100' => $hecho,
                            'bg-oro-400 text-udg-950 ring-oro-100' => $actual && !$alerta,
                            'bg-rose-500 text-white ring-rose-100' => $alerta,
                            'bg-gray-100 text-gray-400 ring-transparent' => !$hecho && !$actual,
                        ])>
                            @if ($hecho)<x-dashboard.icono nombre="check" clase="size-4" />@else{{ $alerta ? '!' : $n }}@endif
                        </span>
                        <span @class(['text-xs font-semibold leading-tight sm:text-sm', 'text-gray-900' => $actual, 'text-gray-500' => !$actual])>{{ $nombre }}</span>
                        @unless ($loop->last)<span class="mx-1 hidden h-px flex-1 bg-gray-200 sm:block" aria-hidden="true"></span>@endunless
                    </li>
                @endforeach
            </ol>
        </nav>

        @if ($tramite->bloqueo)
            <x-ui.aviso tipo="error" titulo="No puedes postularte por ahora">{{ $alumno->textoBloqueo() }}</x-ui.aviso>
        @endif

        {{-- Expediente: kardex + IMSS --}}
        <section id="expediente" aria-labelledby="titulo-expediente" class="scroll-mt-24 space-y-3">
            <div>
                <h2 id="titulo-expediente" class="font-display text-lg font-bold text-gray-900">Tu expediente para prácticas</h2>
                <p class="text-sm text-gray-600">Entrega tu <strong>kardex</strong> y tu <strong>constancia IMSS</strong> a tu coordinación. Los revisa juntos y, cuando los valide, <strong>podrás postularte a una vacante</strong>.</p>
            </div>

            @switch($expediente)
                @case('en_revision')
                    <x-ui.aviso tipo="info" titulo="Tu coordinación está revisando tu expediente">
                        Revisa juntos tu kardex y tu constancia IMSS. Si están correctos, te avisaremos por correo y podrás postularte a una vacante.
                    </x-ui.aviso>
                    @break
                @case('devuelto')
                    <x-ui.aviso tipo="error" titulo="Tu coordinación te pidió corregir un documento">
                        Sube el PDF corregido en la tarjeta marcada en rojo. En cuanto lo envíes vuelve a revisión; no tienes que enviar todo de nuevo.
                    </x-ui.aviso>
                    @break
                @case('validado')
                    <div class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="flex items-start gap-2 text-sm text-emerald-900">
                            <x-dashboard.icono nombre="check" clase="mt-0.5 size-5 shrink-0" />
                            <span><span class="font-semibold">Tu kardex y tu constancia IMSS están validados.</span>
                                {{ $puedePostularse ? 'Ya puedes postularte a una vacante de tu carrera.' : 'Tu expediente está completo.' }}</span>
                        </p>
                        @if ($puedePostularse)
                            <a href="{{ route('alumno.oferta-programas') }}" class="boton boton-exito shrink-0"><x-dashboard.icono nombre="buscar" clase="size-4" /> Ver vacantes</a>
                        @endif
                    </div>
                    @break
                @case('imss_vencido')
                    <x-ui.aviso tipo="advertencia" titulo="Tu constancia IMSS venció">
                        Sube una constancia vigente; cuando tu coordinación la valide podrás seguir con tu trámite.
                    </x-ui.aviso>
                    @break
            @endswitch

            @if ($expediente === 'sin_enviar' && !$kardex && !$imss)
                {{-- Primera entrega: todo en un solo envío --}}
                <form method="POST" action="{{ route('alumno.documentos.expediente') }}" enctype="multipart/form-data" novalidate
                    x-data="{ enviando: false,
                        enviar(e) {
                            let primero = null;
                            this.$el.querySelectorAll('[data-zona-archivo]').forEach(z => {
                                const i = z.querySelector('input[type=file]');
                                if (i.required && !i.files.length) { Alpine.$data(z).error = 'Falta adjuntar este archivo.'; primero ??= z; }
                            });
                            if (primero) { e.preventDefault(); primero.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
                            if (!this.$el.checkValidity()) { e.preventDefault(); this.$el.reportValidity(); return; }
                            this.enviando = true;
                        } }"
                    x-on:submit="enviar($event)"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs sm:p-6">
                    @csrf
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <div class="space-y-4">
                            <p class="font-display text-base font-bold text-gray-900">Kardex</p>
                            <x-ui.campo-paso paso="1" etiqueta="Porcentaje de créditos cursados" name="porcentaje_creditos" type="number"
                                step="0.01" min="{{ $alumno->carrera->porcentaje_creditos_minimo }}" max="100" placeholder="Ej. 72.5"
                                ayuda="Lo ves en tu kardex. Tu carrera pide al menos {{ $minimo }}%." />
                            <x-ui.zona-archivo paso="2" name="kardex" etiqueta="Kardex simple del ciclo actual" accept=".pdf" formatos="PDF"
                                ayuda="Descárgalo de SIIAU. Si es de un ciclo anterior, tu coordinación lo devolverá." />
                        </div>
                        <div class="space-y-4">
                            <p class="font-display text-base font-bold text-gray-900">Constancia IMSS</p>
                            <x-ui.campo-paso paso="3" etiqueta="¿Hasta qué fecha está vigente tu seguro?" name="vigencia_imss" type="date"
                                min="{{ now()->addDay()->toDateString() }}" ayuda="Aparece en tu constancia como «Vigencia» o «Vigente hasta»." />
                            <x-ui.zona-archivo paso="4" name="imss" etiqueta="Constancia de vigencia de derechos" accept=".pdf" formatos="PDF"
                                ayuda="Descárgala en imss.gob.mx con tu NSS. Si aparece «dado de baja», no se podrá validar." />
                        </div>
                    </div>
                    <div class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-gray-500">Tu coordinación los revisará juntos y te avisará por correo.</p>
                        <button class="boton boton-primario justify-center" :disabled="enviando">
                            <span x-show="!enviando" class="inline-flex items-center gap-2"><x-dashboard.icono nombre="enviar" clase="size-4" /> Enviar a mi coordinación</span>
                            <span x-show="enviando" x-cloak>Enviando…</span>
                        </button>
                    </div>
                </form>
            @else
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <x-ui.carga-documento
                        :action="route('alumno.documentos.kardex')"
                        titulo="Kardex simple"
                        instrucciones="Descárgalo de SIIAU (Kardex simple) del ciclo actual."
                        accept=".pdf" formatos="PDF"
                        :estado="$kardexSt" :motivo="$kardex?->getCustomProperty('motivo')"
                        :archivo="$kardex?->file_name" :url-archivo="$kardex ? route('alumno.documentos.descargar', $kardex) : null"
                        :fecha="$kardex?->created_at?->format('d/m/Y')"
                        :boton="$kardexSt === 'rechazada' ? 'Enviar kardex corregido' : ($kardex ? 'Reemplazar kardex' : 'Enviar kardex')">
                        <p class="mt-2 text-xs text-gray-500">Créditos declarados: <span class="font-semibold text-gray-800">{{ $alumno->porcentaje_creditos !== null ? \App\Support\Formato::porcentaje($alumno->porcentaje_creditos) . '%' : 'sin capturar' }}</span> · mínimo {{ $minimo }}%</p>
                    </x-ui.carga-documento>

                    <x-ui.carga-documento
                        :action="route('alumno.documentos.imss')"
                        titulo="Constancia de vigencia del IMSS"
                        :instrucciones="$imssVencido
                            ? 'Tu constancia venció el ' . $alumno->vigencia_imss->format('d/m/Y') . '. Descarga una actualizada en imss.gob.mx y súbela.'
                            : 'Descárgala en imss.gob.mx con tu NSS. Debe estar vigente durante toda tu práctica.'"
                        accept=".pdf" formatos="PDF"
                        :estado="$imssSt" :motivo="$imss?->getCustomProperty('motivo')"
                        :archivo="$imss?->file_name" :url-archivo="$imss ? route('alumno.documentos.descargar', $imss) : null"
                        :fecha="$imss?->created_at?->format('d/m/Y')"
                        :paso-archivo="2"
                        :boton="$imssSt === 'rechazada' ? 'Enviar constancia corregida' : ($imss ? 'Reemplazar constancia' : 'Enviar constancia')">
                        <x-slot:campos>
                            <x-ui.campo-paso paso="1" etiqueta="¿Hasta qué fecha está vigente tu seguro?" name="vigencia_imss" type="date"
                                :value="$alumno->vigencia_imss?->toDateString()" min="{{ now()->addDay()->toDateString() }}"
                                ayuda="Aparece en tu constancia como «Vigencia» o «Vigente hasta». Usa esa misma fecha." />
                        </x-slot:campos>
                        @if ($imssSt === 'validada')
                            <p class="mt-2 text-xs text-gray-500">Vigente hasta el <span class="font-semibold text-gray-800">{{ $alumno->vigencia_imss->format('d/m/Y') }}</span>.</p>
                        @endif
                    </x-ui.carga-documento>
                </div>
            @endif
        </section>

        {{-- Cartas de cada postulación --}}
        @foreach ($postulaciones as $po)
            @php
                $presentacion = $po->cartaPresentacion();
                $aceptacion = $po->getFirstMedia('carta_aceptacion');
                $aceptacionRegistrada = in_array($po->estado, [PE::CartaAceptacionRecibida, PE::Aceptada], true);
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
                    <p class="px-5 py-4 text-sm text-gray-600 sm:px-6">
                        Esta postulación ya no está activa.
                        @if ($puedePostularse)
                            Tu expediente sigue validado: puedes postularte a otra vacante de tu carrera.
                        @elseif ($motivoOferta = \App\Support\Requisitos::alumnoVerOferta($alumno))
                            {{ $motivoOferta }}
                        @endif
                    </p>
                @else
                    <div class="grid grid-cols-1 gap-4 p-5 sm:p-6 lg:grid-cols-2">
                        <x-ui.carga-documento titulo="Carta de presentación" compacto
                            :estado="$presentacion ? 'validada' : null" etiqueta-estado="Generada"
                            mensaje-listo="Carta generada: imprímela y preséntala en la organización. Ahí te entregarán tu carta de aceptación firmada y sellada."
                            :archivo="$presentacion?->file_name"
                            :url-archivo="$presentacion ? route('alumno.documentos.descargar', $presentacion) : null"
                            :bloqueo="$presentacion ? null : 'Tu coordinación la está generando; te avisaremos por correo cuando esté lista.'" />

                        <x-ui.carga-documento compacto
                            :action="$po->estado === PE::CartaPresentacionEmitida ? route('alumno.documentos.aceptacion', $po) : null"
                            titulo="Carta de aceptación"
                            instrucciones="En hoja membretada de la organización, firmada y sellada. Escanéala completa."
                            :estado="$aceptacion ? ($aceptacionRegistrada ? 'validada' : 'pendiente') : null"
                            etiqueta-estado="Registrada"
                            mensaje-listo="Tu coordinación registró tu carta de aceptación. Te avisaremos cuando active tu práctica."
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
                            @if ($r->actividades && $r->estado !== RE::Anulado)
                                <div class="-mb-1 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-udg-50/60 px-3 py-2 text-xs text-udg-900 ring-1 ring-inset ring-udg-100">
                                    <span>{{ $pdf ? 'Formato generado del reporte ' . $r->numero : 'Paso 1: descarga el formato, imprímelo y recaba firma y sello de tu jefe directo. Paso 2: súbelo escaneado aquí abajo.' }}</span>
                                    <a href="{{ route('alumno.documentos.reporte.formato', $r) }}" class="boton boton-chico boton-secundario" data-sin-vista><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Formato PDF</a>
                                </div>
                            @endif
                            <x-ui.carga-documento compacto id="reporte-{{ $r->id }}" class="scroll-mt-24"
                                :action="$activa && in_array($r->estado, [RE::Pendiente, RE::Rechazado], true) ? route('alumno.documentos.reporte', $r) : null"
                                :titulo="($r->numero ? 'Reporte ' . $r->numero . ' · ' : 'Reporte ') . mb_strtolower($r->tipo->label()) . ' · ' . $r->horas_reportadas . ' h'"
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
                        @if ($pr->estado === PrE::Cancelada)
                            {{-- Práctica dada de baja: no hay carta de término; se explica qué pasó con las horas --}}
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                                <p class="font-semibold text-gray-900">Esta práctica se dio de baja</p>
                                <p class="mt-1">{{ $pr->motivoBajaTexto() ?? 'Baja registrada por tu coordinación' }}{{ $pr->detalle_baja ? ': ' . $pr->detalle_baja : '.' }}</p>
                                @if ($pr->tipoBajaTexto())
                                    <p class="mt-2 inline-flex rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200">{{ $pr->tipoBajaTexto() }}{{ $pr->fecha_baja ? ' · ' . $pr->fecha_baja->format('d/m/Y') : '' }}</p>
                                @endif
                                <p class="mt-2">
                                    @if ($pr->horasQueConserva() > 0)
                                        <span class="font-semibold">Conservas tus {{ $pr->horasQueConserva() }} horas validadas</span>: en tu siguiente plaza solo cubrirás las horas restantes.
                                    @elseif ($pr->tipo_baja === 'sancion')
                                        Por ser una baja por sanción, <span class="font-semibold">tus horas no se conservan</span>.
                                    @else
                                        No tenías reportes validados: <span class="font-semibold">tus horas no se conservan</span> y tu siguiente plaza empieza de cero.
                                    @endif
                                </p>
                                @if ($alumno->estaBloqueado())
                                    <p class="mt-2 font-medium text-rose-700">{{ $alumno->textoBloqueo() }}</p>
                                @endif
                                @if ($puedePostularse && $practicas->first(fn ($x) => $x->estado !== PrE::Cancelada) === null)
                                    <a href="{{ route('alumno.oferta-programas') }}" class="boton boton-primario mt-3"><x-dashboard.icono nombre="buscar" clase="size-4" /> Buscar otra vacante</a>
                                @endif
                            </div>
                        @endif
                        @if ($activa && !$pr->horasCompletas())
                            {{-- Reporte híbrido: redacta aquí → el sistema genera el PDF → firma y sello → sube el escaneo --}}
                            <form method="POST" action="{{ route('alumno.documentos.reporte.crear', $pr) }}" enctype="multipart/form-data"
                                class="space-y-4 rounded-2xl border border-gray-200 p-4" x-data="{ propio: {{ old('archivo') ? 'true' : 'false' }} }">
                                @csrf
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">Registrar el reporte {{ (int) $pr->reportes->max('numero') + 1 }}</h4>
                                    <p class="mt-0.5 text-xs leading-relaxed text-gray-500">Redáctalo aquí: el sistema genera el formato en PDF para que lo firme y selle tu jefe directo. Después lo escaneas y lo subes en este mismo reporte.</p>
                                </div>
                                <div>
                                    <label class="etiqueta" for="tipo-{{ $pr->id }}">Tipo de reporte</label>
                                    <select id="tipo-{{ $pr->id }}" name="tipo" required class="campo h-11">
                                        @foreach ($alumno->carrera->esquema_reportes->tipos() as $t)
                                            <option value="{{ $t->value }}" @selected(old('tipo') === $t->value)>{{ $t->label() }}</option>
                                        @endforeach
                                    </select>
                                    <p class="ayuda">Tu carrera entrega reportes con esquema {{ mb_strtolower($alumno->carrera->esquema_reportes->label()) }}.</p>
                                    @error('tipo')<p class="mt-1 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <x-ui.campo-paso etiqueta="Del" name="periodo_inicio" type="date" min="{{ $pr->fecha_inicio->toDateString() }}" max="{{ today()->min($pr->fecha_fin)->toDateString() }}" />
                                    <x-ui.campo-paso etiqueta="Al" name="periodo_fin" type="date" min="{{ $pr->fecha_inicio->toDateString() }}" max="{{ today()->min($pr->fecha_fin)->toDateString() }}" />
                                </div>
                                <x-ui.campo-paso etiqueta="Horas realizadas en el periodo" name="horas_reportadas" type="number" min="1" max="{{ max(1, $pr->horas_requeridas - $horas) }}"
                                    ayuda="Te faltan {{ max(0, $pr->horas_requeridas - $horas) }} horas validadas." />
                                <div>
                                    <label class="etiqueta" for="act-{{ $pr->id }}">Actividades realizadas <span class="text-rose-500">*</span></label>
                                    <textarea id="act-{{ $pr->id }}" name="actividades" rows="5" required minlength="30" maxlength="5000" class="campo"
                                        placeholder="Describe qué hiciste en el periodo: tareas, proyectos, documentos elaborados…">{{ old('actividades') }}</textarea>
                                    @error('actividades')<p class="mt-1 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="etiqueta" for="log-{{ $pr->id }}">Logros y aprendizajes <span class="font-normal text-gray-400">(opcional)</span></label>
                                    <textarea id="log-{{ $pr->id }}" name="logros" rows="3" maxlength="3000" class="campo">{{ old('logros') }}</textarea>
                                </div>
                                <label class="flex items-start gap-2 text-xs text-gray-700">
                                    <input type="checkbox" x-model="propio" class="mt-0.5 rounded border-gray-300">
                                    Mi coordinación me pide su propio formato: ya lo tengo firmado y sellado y lo subo ahora.
                                </label>
                                <div x-show="propio" x-cloak>
                                    <x-ui.zona-archivo name="archivo" etiqueta="Formato de tu carrera, firmado y sellado (PDF)" :requerido="false" />
                                </div>
                                <button class="boton boton-primario w-full"><x-dashboard.icono nombre="documento" clase="size-4" /> <span x-text="propio ? 'Registrar y enviar' : 'Registrar y generar formato'">Registrar y generar formato</span></button>
                            </form>
                        @endif

                        @unless ($pr->estado === PrE::Cancelada)
                        <x-ui.carga-documento id="carta-{{ $pr->id }}" class="scroll-mt-24"
                            :action="$motivoCarta === null ? route('alumno.documentos.termino', $pr) : null"
                            titulo="Carta de término"
                            instrucciones="La emite la organización al completar tus horas: hoja membretada, firmada y sellada."
                            :estado="$cartaSt" :motivo="$carta?->getCustomProperty('motivo')"
                            :archivo="$carta?->file_name" :url-archivo="$carta ? route('alumno.documentos.descargar', $carta) : null"
                            :bloqueo="$motivoCarta"
                            :boton="$carta ? 'Reemplazar carta' : 'Enviar carta de término'" compacto />
                        @endunless
                    </div>
                </div>
            </section>
        @endforeach
    </div>
@endsection
