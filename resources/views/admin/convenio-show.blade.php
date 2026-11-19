@extends('layouts.app')

@section('content')
@php
    use App\Enums\ConvenioEstado as E;
    use App\Enums\EtapaConvenio as Et;
    use App\Enums\Dictamen;

    $estado = $convenio->estado;
    $etapa = $etapaAbierta?->etapa;
    $maxEtapa = $etapa?->diasHabilesDocumentados()[1] ?? null;
    $diasEtapa = $etapaAbierta?->diasHabiles();
    $estadosDoc = ['validada' => ['menta', 'Validado'], 'rechazada' => ['rosa', 'Rechazado'], 'pendiente' => ['ambar', 'Por revisar']];
    $bitacora = ['pendiente' => 'bg-amber-400', 'aprobado' => 'bg-emerald-500', 'observado' => 'bg-sky-400', 'rechazado' => 'bg-rose-500'];
@endphp

<div class="space-y-6">
    <x-pagina.encabezado :titulo="$empresa->razon_social" :volver="route('admin.lista-convenios')" volver-texto="Bandeja de convenios"
        :descripcion="'RFC ' . $empresa->rfc . ' · ' . ($empresa->tipo_organismo?->label() ?? 'Tipo sin definir')">
        <x-slot:acciones>
            <x-ui.estatus :estado="$estado" tamano="normal" />
        </x-slot:acciones>
    </x-pagina.encabezado>

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif

    {{-- Fase actual --}}
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs sm:p-6" aria-labelledby="titulo-fase">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="titulo-fase" class="font-display text-base font-bold text-gray-900">Avance del trámite</h2>
            <p class="text-xs text-gray-500">
                Recibido el {{ $convenio->fecha_recepcion->format('d/m/Y') }}
                @if ($convenio->folio) · Folio <span class="font-semibold text-gray-700">{{ $convenio->folio }}</span>@endif
            </p>
        </div>
        <x-convenio.pista :estado="$estado" grande class="mt-4" />

        @php $detalle = $convenio->estadoDetallado(); @endphp
        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-gray-100 px-4 py-3">
            <x-ui.estatus :familia="$detalle['familia']" :texto="$detalle['texto']" tamano="normal" />
            <p class="min-w-0 flex-1 text-sm text-gray-700">{{ $detalle['detalle'] ?? 'Sin detalle adicional' }}</p>
            <p class="text-xs text-gray-500">Lo tiene: <span class="font-semibold text-gray-800">{{ $detalle['responsable'] }}</span></p>
        </div>

        @if ($etapaAbierta)
            <div @class([
                'mt-4 flex flex-col gap-3 rounded-xl p-4 ring-1 ring-inset sm:flex-row sm:items-center sm:justify-between',
                'bg-rose-50 ring-rose-200' => $etapaAbierta->excedeEspera(),
                'bg-gray-50 ring-gray-100' => !$etapaAbierta->excedeEspera(),
            ])>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500">Etapa abierta</p>
                    <p class="font-semibold text-gray-900">{{ $etapa->label() }}</p>
                    <p class="text-sm text-gray-600">Responsable: {{ $etapa->responsable() }}</p>
                    @if ($etapaAbierta->comentario)<p class="mt-1 text-sm text-gray-600">{{ $etapaAbierta->comentario }}</p>@endif
                </div>
                <div class="shrink-0 sm:w-56 sm:text-right">
                    <p @class(['font-display text-2xl font-bold leading-none', 'text-rose-600' => $etapaAbierta->excedeEspera(), 'text-gray-900' => !$etapaAbierta->excedeEspera()])>
                        {{ $diasEtapa }} <span class="font-sans text-sm font-medium text-gray-500">{{ $diasEtapa === 1 ? 'día hábil' : 'días hábiles' }}</span>
                    </p>
                    @if ($maxEtapa)
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white ring-1 ring-inset ring-gray-200">
                            <div class="h-full rounded-full {{ $etapaAbierta->excedeEspera() ? 'bg-rose-500' : 'bg-udg-800' }}" style="width: {{ min(100, round($diasEtapa / max(1, $maxEtapa) * 100)) }}%"></div>
                        </div>
                        <p class="mt-1 text-xs {{ $etapaAbierta->excedeEspera() ? 'font-semibold text-rose-600' : 'text-gray-500' }}">
                            {{ $etapaAbierta->excedeEspera() ? 'Excede el máximo de ' . $maxEtapa . ' días' : 'Máximo documentado: ' . $maxEtapa . ' días' }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-gray-500">Etapa sin plazo fijo</p>
                    @endif
                </div>
            </div>
        @elseif ($estado->esFinal())
            <p class="mt-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-600 ring-1 ring-inset ring-gray-100">
                {{ $estado === E::Formalizado ? 'Trámite concluido: el convenio está formalizado.' : 'Trámite cerrado como rechazado.' }}
            </p>
        @endif
    </section>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Expediente --}}
            <x-dashboard.panel titulo="Expediente de la empresa" icono="documento"
                :descripcion="$estado === E::Solicitado ? 'Valida cada documento. Si rechazas uno, escribe el motivo: la empresa lo verá.' : 'Los documentos se validan durante la revisión de documentos.'">
                <ul class="-my-1 divide-y divide-gray-100">
                    @foreach ($colecciones as $col)
                        @php
                            $media = $empresa->documentoVigente($col);
                            $st = $media?->getCustomProperty('estado') ?? ($media ? 'pendiente' : null);
                        @endphp
                        <li class="flex flex-col gap-3 py-3.5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span @class([
                                    'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset',
                                    'bg-emerald-50 text-emerald-600 ring-emerald-100' => $st === 'validada',
                                    'bg-rose-50 text-rose-600 ring-rose-100' => $st === 'rechazada',
                                    'bg-amber-50 text-amber-600 ring-amber-100' => $st === 'pendiente',
                                    'bg-gray-50 text-gray-400 ring-gray-200' => !$st,
                                ])><x-dashboard.icono :nombre="$st === 'validada' ? 'check' : ($st === 'rechazada' ? 'equis' : 'documento')" clase="size-[18px]" /></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ \App\Models\Empresa::nombreDocumento($col) }}</p>
                                    @if ($media)
                                        <a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $media]) }}" class="inline-flex max-w-full items-center gap-1 text-xs font-medium text-real-600 hover:text-real-700">
                                            <x-dashboard.icono nombre="descargar" clase="size-3.5 shrink-0" /><span class="truncate">{{ $media->file_name }}</span>
                                        </a>
                                        @if ($media->getCustomProperty('motivo'))<p class="text-xs text-rose-600">Motivo: {{ $media->getCustomProperty('motivo') }}</p>@endif
                                    @else
                                        <p class="dato-vacio">La empresa aún no sube este documento</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                @if ($st)
                                    <x-ui.estatus :familia="$estadosDoc[$st][0]" :texto="$estadosDoc[$st][1]" />
                                    @if ($estado === E::Solicitado)
                                        <form method="POST" action="{{ route('admin.convenios.documentos.resolver', [$convenio, $media]) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            <label for="motivo-{{ $col }}" class="sr-only">Motivo del rechazo</label>
                                            <input id="motivo-{{ $col }}" type="text" name="motivo" placeholder="Motivo (si rechazas)" class="campo h-9 w-44 text-xs">
                                            <button name="estado" value="validada" data-sin-bloqueo="1" title="Validar documento"
                                                class="inline-flex h-9 items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95">
                                                <x-dashboard.icono nombre="check" clase="size-3.5" /> Validar
                                            </button>
                                            <button name="estado" value="rechazada" data-sin-bloqueo="1" title="Rechazar documento"
                                                class="inline-flex h-9 items-center gap-1 rounded-lg border border-rose-200 bg-white px-2.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 active:scale-95">
                                                <x-dashboard.icono nombre="equis" clase="size-3.5" /> Rechazar
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <x-ui.estatus familia="gris" texto="Sin archivo" />
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($estado === E::Solicitado)
                    <div class="mt-4 flex justify-end border-t border-gray-100 pt-4">
                        @if ($faltaTurnar = \App\Support\Requisitos::turnarAValidacionAcademica($convenio))
                            <x-ui.accion-bloqueada :motivo="$faltaTurnar">Turnar a las carreras</x-ui.accion-bloqueada>
                        @else
                            <form method="POST" action="{{ route('admin.convenios.validacion-academica', $convenio) }}">
                                @csrf
                                <button class="boton boton-primario"><x-dashboard.icono nombre="enviar" clase="size-4" /> Turnar a las carreras</button>
                            </form>
                        @endif
                    </div>
                @endif
            </x-dashboard.panel>

            {{-- Validación académica --}}
            <x-dashboard.panel titulo="Turno académico y dictámenes por carrera" icono="academico"
                descripcion="Secretaría Académica y la División solo turnan el oficio; cada coordinación de carrera emite su dictamen desde su panel.">
                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ([['Turnado a Secretaría Académica', $convenio->fecha_turno_secretaria], ['Turnado a la División', $convenio->fecha_turno_division]] as [$k, $f])
                        <div class="rounded-xl bg-gray-50 p-3.5 ring-1 ring-inset ring-gray-100">
                            <dt class="text-xs text-gray-500">{{ $k }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold {{ $f ? 'text-gray-900' : 'italic font-normal text-gray-400' }}">{{ $f?->format('d/m/Y') ?? 'Fecha sin registrar' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($estado === E::EnValidacionAcademica)
                    <form method="POST" action="{{ route('admin.convenios.turnos', $convenio) }}" class="mt-3 grid grid-cols-1 gap-3 rounded-xl border border-dashed border-gray-300 p-4 md:grid-cols-3 md:items-end">
                        @csrf
                        <div><label for="f-sec" class="etiqueta">Secretaría Académica</label>
                            <input id="f-sec" type="date" name="fecha_turno_secretaria" max="{{ today()->toDateString() }}" value="{{ old('fecha_turno_secretaria', $convenio->fecha_turno_secretaria?->toDateString()) }}" class="campo h-10"></div>
                        <div><label for="f-div" class="etiqueta">División</label>
                            <input id="f-div" type="date" name="fecha_turno_division" max="{{ today()->toDateString() }}" value="{{ old('fecha_turno_division', $convenio->fecha_turno_division?->toDateString()) }}" class="campo h-10"></div>
                        <button class="boton boton-secundario h-10">Guardar fechas</button>
                    </form>
                @endif

                <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @forelse ($convenio->dictamenes as $d)
                        <li class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $d->carrera->nombre }}</p>
                                    <p class="text-xs text-gray-500">{{ $d->carrera->carrera_clave }}</p>
                                </div>
                                <x-ui.estatus :estado="$d->dictamen" class="shrink-0" />
                            </div>
                            @if ($d->fecha_dictamen)
                                <p class="mt-2 text-xs text-gray-500">{{ $d->fecha_dictamen->format('d/m/Y') }}{{ $d->dictaminador?->user ? ' · ' . $d->dictaminador->user->name : '' }}</p>
                            @elseif ($estado === E::EnValidacionAcademica)
                                <p class="mt-2 text-xs text-amber-700">Esperando el dictamen de la coordinación.</p>
                            @else
                                <p class="dato-vacio mt-2">Sin dictamen</p>
                            @endif
                            @if ($d->observaciones)<p class="mt-1 text-xs leading-relaxed text-gray-600">{{ $d->observaciones }}</p>@endif
                        </li>
                    @empty
                        <li class="sm:col-span-2"><x-dashboard.vacio icono="academico" titulo="Aún no se turna a las carreras" texto="Al validar el expediente, turna el convenio para que cada carrera dictamine." /></li>
                    @endforelse
                </ul>
            </x-dashboard.panel>

            {{-- Borrador y Jurídica --}}
            @if (in_array($estado, [E::EnValidacionAcademica, E::EnRevisionJuridica], true) || $borradores->isNotEmpty())
                <x-dashboard.panel titulo="Borrador y Unidad Jurídica" icono="juridico" descripcion="Genera o adjunta el borrador del convenio y registra el dictamen jurídico.">
                    @if ($borradores->isNotEmpty())
                        <ul class="mb-4 space-y-2">
                            @foreach ($borradores as $i => $b)
                                <li class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3.5 py-2.5 ring-1 ring-inset ring-gray-100">
                                    <span class="flex min-w-0 items-center gap-2 text-sm">
                                        <span class="rounded-md bg-udg-900 px-1.5 py-0.5 text-[11px] font-bold text-white">v{{ $i + 1 }}</span>
                                        <span class="truncate text-gray-800">{{ $b->file_name }}</span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-2">
                                        <span class="hidden text-xs text-gray-500 sm:inline">{{ $b->created_at->format('d/m/Y H:i') }}</span>
                                        @php $pdfVersion = $convenio->getMedia('borrador_pdf')->first(fn ($p) => $p->getCustomProperty('version') === $b->getCustomProperty('version') && $b->getCustomProperty('version') !== null); @endphp
                                        @if ($pdfVersion)
                                            <x-ui.boton-icono :href="route('admin.convenios.archivos.descargar', [$convenio, $pdfVersion])" icono="documento" etiqueta="PDF para imprimir" tono="primario" con-texto />
                                        @endif
                                        <x-ui.boton-icono :href="route('admin.convenios.archivos.descargar', [$convenio, $b])" icono="descargar" etiqueta="Descargar Word" />
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="dato-vacio mb-4">Aún no hay borradores del convenio.</p>
                    @endif

                    @if ($etapa === Et::RedaccionBorrador)
                        @php $e = $empresa; @endphp
                        <details class="group mb-4 rounded-xl border border-gray-200" @if ($errors->any()) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-udg-900 hover:bg-gray-50">
                                <span class="flex items-center gap-2"><x-dashboard.icono nombre="documento" clase="size-4" /> Generar borrador desde el Formato de PP</span>
                                <svg class="size-4 text-gray-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            @php
                                $faltanDecl = collect([$e->representante_legal, $e->representante_cargo, $e->constitucion, $e->objeto_social, $e->facultad_firmante, $e->domicilio_fiscal])->filter(fn ($v) => blank($v))->count();
                            @endphp
                            <form method="POST" action="{{ route('admin.convenios.borrador.generar', $convenio) }}" class="space-y-4 border-t border-gray-100 p-4">
                                @csrf
                                {{-- Declaraciones de la ENTIDAD: las capturó la empresa; aquí solo se revisan contra el acta --}}
                                <details class="group/decl rounded-xl bg-gray-50 ring-1 ring-inset ring-gray-100" @if ($faltanDecl > 0 || $errors->hasAny(['representante_nombre', 'representante_cargo', 'constitucion', 'atribuciones', 'facultad_firmante', 'domicilio_legal'])) open @endif>
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-900">Declaraciones de la entidad</span>
                                            <span class="block text-xs {{ $faltanDecl ? 'font-medium text-amber-700' : 'text-gray-500' }}">{{ $faltanDecl ? "La empresa no capturó {$faltanDecl} dato(s): complétalos aquí o pídeselos" : 'Capturadas por la empresa · revísalas contra el acta constitutiva' }}</span>
                                        </span>
                                        <span class="text-xs font-semibold text-real-600 group-open/decl:hidden">Revisar o corregir</span>
                                    </summary>
                                    <div class="grid grid-cols-1 gap-4 border-t border-gray-100 p-4 md:grid-cols-2">
                                        <div><label class="etiqueta" for="b-rep">Representante (nombre completo)</label><input id="b-rep" type="text" name="representante_nombre" required value="{{ old('representante_nombre', $e->representante_legal) }}" class="campo h-11"></div>
                                        <div><label class="etiqueta" for="b-cargo">Cargo</label><input id="b-cargo" type="text" name="representante_cargo" required value="{{ old('representante_cargo', $e->representante_cargo) }}" class="campo h-11"></div>
                                        <div class="md:col-span-2"><label class="etiqueta" for="b-const">I. Creación o constitución</label><textarea id="b-const" name="constitucion" rows="2" required class="campo">{{ old('constitucion', $e->constitucion) }}</textarea></div>
                                        <div class="md:col-span-2"><label class="etiqueta" for="b-atr">II. Atribuciones u objeto social</label><textarea id="b-atr" name="atribuciones" rows="2" required class="campo">{{ old('atribuciones', $e->objeto_social) }}</textarea></div>
                                        <div class="md:col-span-2"><label class="etiqueta" for="b-fac">III. Facultad de quien firma</label><textarea id="b-fac" name="facultad_firmante" rows="2" required class="campo">{{ old('facultad_firmante', $e->facultad_firmante) }}</textarea></div>
                                        <div class="md:col-span-2"><label class="etiqueta" for="b-dom">IV. Domicilio legal</label><input id="b-dom" type="text" name="domicilio_legal" required value="{{ old('domicilio_legal', $e->domicilio_fiscal) }}" class="campo h-11"><p class="ayuda">V. RFC: <span class="font-mono font-semibold text-gray-700">{{ $e->rfc }}</span></p></div>
                                    </div>
                                </details>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="etiqueta" for="b-anios">Vigencia pactada (cláusula UNDÉCIMA)</label>
                                        <select id="b-anios" name="vigencia_anios" required class="campo h-11">
                                            @foreach (\App\Models\Convenio::DURACIONES as $a)
                                                <option value="{{ $a }}" @selected((int) old('vigencia_anios', $convenio->duracionAnios()) === $a)>{{ $a }} {{ $a === 1 ? 'año' : 'años' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div><label class="etiqueta" for="b-lugar">Ciudad de firma</label><input id="b-lugar" type="text" name="lugar_firma" required value="{{ old('lugar_firma', 'Guadalajara') }}" class="campo h-11"></div>
                                    <div><label class="etiqueta" for="b-fecha">Fecha de firma</label><input id="b-fecha" type="date" name="fecha_firma" required value="{{ old('fecha_firma', now()->toDateString()) }}" class="campo h-11"></div>
                                    <div class="md:col-span-3"><label class="etiqueta" for="b-apoyo">Ayuda o estímulo económico (opcional)</label><input id="b-apoyo" type="text" name="apoyo_economico" value="{{ old('apoyo_economico') }}" class="campo h-11"><p class="ayuda">Si lo dejas vacío se usa el texto estándar.</p></div>
                                </div>
                                <div class="flex justify-end"><button class="boton boton-primario"><x-dashboard.icono nombre="documento" clase="size-4" /> Generar borrador (Word + PDF)</button></div>
                            </form>
                        </details>

                        <div class="flex flex-col gap-3 rounded-xl border border-dashed border-gray-300 p-4 md:flex-row md:items-end md:justify-between">
                            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.borrador', $convenio) }}" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end">
                                @csrf
                                <div class="flex-1"><label class="etiqueta" for="b-archivo">Adjuntar borrador propio (PDF o DOCX)</label>
                                    <input id="b-archivo" type="file" name="borrador" accept=".pdf,.docx" required class="campo h-11 py-2 file:mr-3 file:rounded-md file:border-0 file:bg-udg-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-udg-900"></div>
                                <button class="boton boton-secundario h-11"><x-dashboard.icono nombre="subir" clase="size-4" /> Adjuntar</button>
                            </form>
                            @if ($borradores->isEmpty())
                                <x-ui.accion-bloqueada motivo="Genera o adjunta el borrador del convenio antes de turnarlo a Jurídica.">Turnar a Jurídica</x-ui.accion-bloqueada>
                            @else
                                <form method="POST" action="{{ route('admin.convenios.juridica.turnar', $convenio) }}">
                                    @csrf
                                    <button class="boton boton-primario h-11"><x-dashboard.icono nombre="enviar" clase="size-4" /> Turnar a Jurídica</button>
                                </form>
                            @endif
                        </div>
                    @endif

                    @if ($estado === E::EnRevisionJuridica && $etapa === Et::DictamenJuridico)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.juridica.dictamen', $convenio) }}"
                            class="grid grid-cols-1 gap-3 rounded-xl border border-dashed border-gray-300 p-4 md:grid-cols-[11rem_1fr_14rem_auto] md:items-end">
                            @csrf
                            <div><label class="etiqueta" for="j-res">Resultado</label>
                                <select id="j-res" name="resultado" required class="campo h-11">
                                    <option value="aprobado">Aprobado</option>
                                    <option value="observado">Observado</option>
                                    <option value="rechazado">Rechazado</option>
                                </select></div>
                            <div><label class="etiqueta" for="j-com">Comentario</label><input id="j-com" type="text" name="comentario" placeholder="Obligatorio si no aprueba" class="campo h-11"></div>
                            <div><label class="etiqueta" for="j-doc">Dictamen (PDF)</label><input id="j-doc" type="file" name="documento" accept="application/pdf" class="campo h-11 py-2 file:mr-2 file:rounded-md file:border-0 file:bg-udg-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-udg-900"></div>
                            <button class="boton boton-primario h-11">Registrar dictamen</button>
                        </form>
                    @endif
                </x-dashboard.panel>
            @endif

            {{-- Firmas y formalización --}}
            @if ($estado === E::EnFirmas)
                <x-dashboard.panel titulo="Firmas y formalización" icono="firma" descripcion="Registra cada firma y, al final, formaliza con folio y vigencia.">
                    @if (in_array($etapa, [Et::FirmaEmpresa, Et::FirmaRectoria], true))
                        <form method="POST" action="{{ route('admin.convenios.firmas', $convenio) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                            @csrf
                            <input type="hidden" name="etapa" value="{{ $etapa->value }}">
                            <div class="flex-1"><label class="etiqueta" for="fir-com">Comentario (opcional)</label><input id="fir-com" type="text" name="comentario" class="campo h-11"></div>
                            <button class="boton boton-primario h-11"><x-dashboard.icono nombre="firma" clase="size-4" /> Registrar: {{ $etapa->label() }}</button>
                        </form>
                    @elseif ($etapa === Et::ArchivoDistribucion)
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.convenios.formalizar', $convenio) }}" class="grid grid-cols-1 gap-4 md:grid-cols-4"
                            x-data="{ inicio: '', anios: {{ $convenio->duracionAnios() }},
                                get fin() { if (!this.inicio) return ''; const d = new Date(this.inicio + 'T00:00:00'); d.setFullYear(d.getFullYear() + Number(this.anios)); d.setDate(d.getDate() - 1); return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }); } }">
                            @csrf
                            <div><label class="etiqueta" for="fo-folio">Folio</label><input id="fo-folio" type="text" name="folio" required maxlength="30" class="campo h-11"></div>
                            <div><label class="etiqueta" for="fo-ini">Inicio de vigencia (fecha de firma)</label><input id="fo-ini" type="date" name="fecha_inicio_vigencia" required x-model="inicio" class="campo h-11"></div>
                            <div>
                                <label class="etiqueta" for="fo-anios">Duración pactada</label>
                                <select id="fo-anios" name="vigencia_anios" x-model="anios" class="campo h-11">
                                    @foreach (\App\Models\Convenio::DURACIONES as $a)
                                        <option value="{{ $a }}">{{ $a }} {{ $a === 1 ? 'año' : 'años' }}</option>
                                    @endforeach
                                </select>
                                <p class="ayuda" x-text="fin ? 'Vence el ' + fin + ' (avisos a 60 y 30 días)' : 'La fecha de fin se calcula sola'"></p>
                            </div>
                            <div><label class="etiqueta" for="fo-pdf">Escaneo firmado (PDF)</label><input id="fo-pdf" type="file" name="convenio_firmado" accept="application/pdf" data-max-mb="20" class="campo h-11 py-2 file:mr-2 file:rounded-md file:border-0 file:bg-udg-50 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-udg-900"></div>
                            <div class="md:col-span-3"><label class="etiqueta" for="fo-com">Comentario (opcional)</label><input id="fo-com" type="text" name="comentario" class="campo h-11"></div>
                            <div class="flex items-end"><button class="boton boton-exito h-11 w-full"><x-dashboard.icono nombre="vigente" clase="size-4" /> Formalizar convenio</button></div>
                        </form>
                    @else
                        <p class="dato-vacio">No hay una firma pendiente por registrar.</p>
                    @endif
                </x-dashboard.panel>
            @endif

            {{-- Rechazar --}}
            @unless ($estado->esFinal())
                <details class="group rounded-2xl border border-rose-200 bg-white shadow-theme-xs">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-rose-700 hover:bg-rose-50/50 sm:px-6">
                        <span class="flex items-center gap-2"><x-dashboard.icono nombre="alerta" clase="size-4" /> Rechazar convenio</span>
                        <svg class="size-4 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <form method="POST" action="{{ route('admin.convenios.rechazar', $convenio) }}" class="flex flex-col gap-2 border-t border-rose-100 px-5 py-4 sm:flex-row sm:items-end sm:px-6"
                        onsubmit="return confirm('¿Cerrar el trámite como rechazado? La empresa recibirá el motivo.')">
                        @csrf
                        <div class="flex-1"><label class="etiqueta" for="r-motivo">Motivo</label><input id="r-motivo" type="text" name="motivo" required maxlength="1000" class="campo h-11"></div>
                        <button class="boton boton-peligro h-11">Rechazar trámite</button>
                    </form>
                </details>
            @endunless
        </div>

        {{-- Columna lateral --}}
        <aside class="space-y-6" aria-label="Datos del convenio">
            <x-dashboard.panel titulo="Datos del convenio" icono="documento">
                <dl class="space-y-4 text-sm">
                    <div><dt class="text-xs font-medium text-gray-500">Objetivos</dt><dd class="mt-1 leading-relaxed text-gray-800">{{ $convenio->objetivos ?: 'Sin objetivos registrados' }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500">Actividades propuestas</dt><dd class="mt-1 leading-relaxed text-gray-800">{{ $convenio->actividades_propuestas ?: 'Sin actividades registradas' }}</dd></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100"><dt class="text-xs text-gray-500">Plazas estimadas</dt><dd class="mt-0.5 font-display text-xl font-bold tabular-nums text-gray-900">{{ $convenio->plazas_estimadas }}</dd></div>
                        <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100"><dt class="text-xs text-gray-500">Vigencia</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-gray-900">
                                @if ($convenio->fecha_fin_vigencia){{ $convenio->fecha_inicio_vigencia->format('d/m/Y') }} – {{ $convenio->fecha_fin_vigencia->format('d/m/Y') }}@else<span class="text-xs font-normal italic text-gray-400">Se define al formalizar</span>@endif
                            </dd></div>
                    </div>
                </dl>
                @if ($firmado)
                    <a href="{{ route('admin.convenios.archivos.descargar', [$convenio, $firmado]) }}" class="boton boton-secundario mt-4 w-full"><x-dashboard.icono nombre="descargar" clase="size-4" /> Convenio firmado</a>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel titulo="Documentos físicos" icono="firma" :enlace="route('admin.documentos-fisicos', ['q' => $empresa->razon_social, 'vista' => 'activos'])" enlace-texto="Controlar">
                @forelse ($convenio->documentosFisicos as $d)
                    <div class="flex items-center justify-between gap-3 py-1.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $d->nombre() }}</p>
                            <p @class(['text-xs', 'font-semibold text-rose-600' => $d->vencido(), 'text-gray-500' => !$d->vencido()])>
                                {{ $d->estado->fueraDeOficina() && $d->fecha_compromiso ? ($d->vencido() ? 'Debía regresar el ' : 'Regresa el ') . $d->fecha_compromiso->format('d/m/Y') : 'Lo tiene ' . $d->estado->custodio() }}
                            </p>
                        </div>
                        <x-ui.estatus :estado="$d->estado" class="shrink-0" />
                    </div>
                @empty
                    @if (in_array($estado, [E::EnValidacionAcademica, E::EnRevisionJuridica, E::EnFirmas, E::Formalizado], true))
                        <p class="text-sm text-gray-600">Este trámite aún no tiene control de sus papeles.</p>
                        <x-ui.boton-icono :accion="route('admin.convenios.fisicos.preparar', $convenio)" icono="mas" etiqueta="Abrir control físico" tono="primario" con-texto class="mt-3" />
                    @else
                        <p class="dato-vacio">El oficio de turno se registra al turnar a las carreras</p>
                    @endif
                @endforelse
            </x-dashboard.panel>

            <x-dashboard.panel titulo="Contacto de la empresa" icono="alumno" :enlace="route('admin.empresas.show', $empresa)" enlace-texto="Expediente">
                <p class="font-semibold text-gray-900">{{ $empresa->contacto_nombre ?: 'Contacto sin registrar' }}</p>
                <p class="text-sm text-gray-600">{{ $empresa->contacto_cargo ?: 'Cargo sin registrar' }}</p>
                <p class="mt-2 break-all text-sm text-gray-700">{{ $empresa->user?->email ?? 'Sin cuenta de acceso' }}</p>
                <p class="text-sm {{ $empresa->contacto_telefono ? 'text-gray-700' : 'italic text-gray-400' }}">{{ $empresa->contacto_telefono ?: 'Sin teléfono' }}</p>
                <p class="mt-2 text-xs text-gray-500">Representante legal: <span class="font-medium text-gray-700">{{ $empresa->representante_legal ?: 'sin registrar' }}</span></p>
            </x-dashboard.panel>

            <x-dashboard.panel titulo="Bitácora" icono="reloj" :enlace="auth()->user()->can('ver-auditoria') ? route('admin.auditoria', ['entidad_tipo' => 'Convenio', 'entidad_id' => $convenio->id]) : null" enlace-texto="Auditoría">
                @if ($convenio->seguimientos->isEmpty())
                    <p class="dato-vacio">Sin movimientos todavía.</p>
                @else
                    <ol class="relative space-y-4 before:absolute before:inset-y-1 before:left-[5px] before:w-px before:bg-gray-200">
                        @foreach ($convenio->seguimientos as $s)
                            <li class="relative pl-6">
                                <span class="absolute left-0 top-1.5 size-[11px] rounded-full ring-4 ring-white {{ $bitacora[$s->estado->value] ?? 'bg-gray-300' }}" aria-hidden="true"></span>
                                <p class="text-sm font-semibold text-gray-900">{{ $s->etapa->label() }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ ucfirst($s->estado->value) }} · {{ $s->fecha_registro->format('d/m/Y') }}{{ $s->fecha_resolucion ? ' → ' . $s->fecha_resolucion->format('d/m/Y') : '' }}
                                    · {{ $s->diasHabiles() }} {{ $s->diasHabiles() === 1 ? 'día hábil' : 'días hábiles' }}
                                </p>
                                @if ($s->registrador?->user)<p class="text-xs text-gray-500">{{ $s->registrador->user->name }}</p>@endif
                                @if ($s->comentario)<p class="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs leading-relaxed text-gray-600">{{ $s->comentario }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-dashboard.panel>
        </aside>
    </div>
</div>
@endsection
