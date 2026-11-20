@extends('layouts.app')

@section('content')
    @php
        $input = 'campo';
        $nombres = [
            'cv_instancia' => 'Currículum de la instancia',
            'identificacion_representante' => 'Identificación oficial del representante',
            'acta_constitutiva' => 'Acta constitutiva o nombramiento legal',
            'constancia_situacion_fiscal' => 'Constancia de situación fiscal',
            'comprobante_domicilio' => 'Comprobante de domicilio',
            'poder_notarial' => 'Poder notarial (opcional)',
        ];
    @endphp

    @php
        $estadosDoc = ['validada' => ['menta', 'Validado'], 'rechazada' => ['rosa', 'Rechazado'], 'pendiente' => ['ambar', 'En revisión']];
        $obligatorios = collect($documentos)->filter(fn ($d) => $d['obligatorio']);
        $subidos = $obligatorios->filter(fn ($d) => $d['media'])->count();
        $validados = $obligatorios->filter(fn ($d) => $d['media']?->getCustomProperty('estado') === 'validada')->count();
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Documentos" descripcion="Tu expediente para el convenio. Vinculación revisa cada documento y te avisa si alguno necesita corrección." />

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
        @endif
        @if ($bloqueado)
            <x-ui.aviso tipo="info" titulo="Documentos bloqueados durante la revisión">Se habilitarán de nuevo si Vinculación rechaza un documento o la solicitud.</x-ui.aviso>
        @endif

        <div class="space-y-6">
            {{-- Expediente --}}
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs" aria-labelledby="titulo-expediente">
                <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 id="titulo-expediente" class="font-display text-base font-bold text-gray-900">Expediente de la organización</h2>
                            <p class="mt-0.5 text-xs text-gray-500">En PDF; el currículum también acepta Word.</p>
                        </div>
                        <x-ui.estatus :familia="$expedienteEnOrden ? 'menta' : 'ambar'" tamano="normal"
                            :texto="$expedienteEnOrden ? ($expedienteCompleto ? 'Completo y validado' : 'En orden (convenio vigente)') : 'Incompleto'" />
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-udg-800 to-oro-400" style="width: {{ $subidos / max(1, $obligatorios->count()) * 100 }}%"></div>
                        </div>
                        <span class="text-xs font-semibold tabular-nums text-gray-700">{{ $subidos }}/{{ $obligatorios->count() }} subidos · {{ $validados }} validados</span>
                    </div>
                </div>

                @php
                    $instrucciones = [
                        'cv_instancia' => 'Una o dos páginas: a qué se dedica la organización, sus principales proyectos y clientes. PDF o Word.',
                        'identificacion_representante' => 'INE o pasaporte vigente de quien firmará el convenio, por ambos lados en un solo PDF.',
                        'acta_constitutiva' => 'Acta constitutiva completa (sociedades y asociaciones) o el nombramiento o decreto (dependencias de gobierno).',
                        'constancia_situacion_fiscal' => 'Descárgala del portal del SAT; no mayor a tres meses.',
                        'comprobante_domicilio' => 'Luz, agua, teléfono o predial a nombre de la organización; no mayor a tres meses.',
                        'poder_notarial' => 'Solo si quien firma no aparece en el acta: poder notarial que lo faculta.',
                    ];
                @endphp
                @php
                    // Primero lo que pide acción (devuelto, sin subir, en revisión); los validados van en una lista compacta
                    $estadoDe = fn ($d) => $d['media'] ? ($d['media']->getCustomProperty('estado') ?? 'pendiente') : null;
                    $orden = ['rechazada' => 0, null => 1, 'pendiente' => 2];
                    $porAtender = collect($documentos)->filter(fn ($d) => $estadoDe($d) !== 'validada')
                        ->sortBy(fn ($d) => ($orden[$estadoDe($d)] ?? 3) * 10 + ($d['obligatorio'] ? 0 : 5));
                    $yaValidados = collect($documentos)->filter(fn ($d) => $estadoDe($d) === 'validada');
                @endphp

                @if ($porAtender->isNotEmpty())
                    <div class="px-5 pt-5 sm:px-6">
                        @php $soloOpcionales = $porAtender->every(fn ($d) => !$d['obligatorio']); @endphp
                        <h3 class="text-sm font-semibold text-gray-900">{{ $soloOpcionales ? 'Opcional' : 'Por atender' }} <span class="font-normal text-gray-500">· {{ $porAtender->count() }}</span></h3>
                        <p class="text-xs text-gray-500">{{ $soloOpcionales ? 'Tu expediente obligatorio está completo. Sube esto solo si aplica a tu organización.' : 'Primero los que Vinculación te devolvió; luego los que faltan por subir.' }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-5 sm:p-6 lg:grid-cols-2">
                        @foreach ($porAtender as $col => $d)
                            @php
                                $media = $d['media'];
                                $st = $estadoDe($d);
                            @endphp
                            <x-ui.carga-documento compacto
                                :action="$d['puedeSubir'] ? route('empresa.documentos.subir') : null"
                                :titulo="\App\Models\Empresa::nombreDocumento($col) . ($d['obligatorio'] ? ' *' : '')"
                                :instrucciones="$instrucciones[$col] ?? null"
                                :accept="$col === 'cv_instancia' ? '.pdf,.doc,.docx' : '.pdf'"
                                :formatos="$col === 'cv_instancia' ? 'PDF o Word' : 'PDF'"
                                :estado="$st" :motivo="$media?->getCustomProperty('motivo')"
                                :archivo="$media?->file_name" :url-archivo="$media ? route('empresa.documentos.descargar', $media->id) : null"
                                :fecha="$media?->created_at?->format('d/m/Y')"
                                :bloqueo="!$d['puedeSubir'] ? 'Vinculación está revisando tu solicitud: podrás cambiarlo si te pide una corrección.' : null"
                                :boton="$st === 'rechazada' ? 'Enviar documento corregido' : ($media ? 'Reemplazar documento' : 'Enviar documento')">
                                <x-slot:ocultos><input type="hidden" name="coleccion" value="{{ $col }}"></x-slot:ocultos>
                            </x-ui.carga-documento>
                        @endforeach
                    </div>
                @else
                    <div class="m-5 flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-100 sm:m-6">
                        <x-dashboard.icono nombre="check" clase="size-5 shrink-0" />
                        <p><span class="font-semibold">No tienes documentos por atender.</span> Vinculación ya validó todo tu expediente.</p>
                    </div>
                @endif

                @if ($yaValidados->isNotEmpty())
                    <div class="border-t border-gray-100 px-5 py-4 sm:px-6">
                        <h3 class="text-sm font-semibold text-gray-900">Validados <span class="font-normal text-gray-500">· {{ $yaValidados->count() }}</span></h3>
                        <ul class="mt-2 divide-y divide-gray-100">
                            @foreach ($yaValidados as $col => $d)
                                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2.5">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-100"><x-dashboard.icono nombre="check" clase="size-4" /></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900">{{ \App\Models\Empresa::nombreDocumento($col) }}</p>
                                            <a href="{{ route('empresa.documentos.descargar', $d['media']->id) }}" data-sin-vista class="inline-flex max-w-full items-center gap-1 text-xs font-medium text-real-600 hover:underline">
                                                <x-dashboard.icono nombre="descargar" clase="size-3.5 shrink-0" /><span class="truncate">{{ $d['media']->file_name }}</span>
                                            </a>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-500">Subido el {{ $d['media']->created_at->format('d/m/Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="px-6 pb-5 text-xs text-gray-500">* Obligatorio para solicitar el convenio.</p>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                {{-- Representante legal --}}
                @php
                    $legales = ['representante_legal', 'representante_cargo', 'domicilio_fiscal', 'constitucion', 'objeto_social', 'facultad_firmante'];
                    $faltanLegales = collect($legales)->filter(fn ($c) => blank($empresa->{$c}))->count()
                        + (\App\Support\DatosNotariales::usaEscritura($empresa->tipo_organismo) && blank($empresa->escritura_numero) ? 1 : 0);
                @endphp
                <x-dashboard.panel titulo="Datos legales del convenio" icono="firma" descripcion="Son tus declaraciones en el convenio; van tal cual al documento. Deben coincidir con tu acta y tu identificación." class="xl:col-span-2">
                    @if ($faltanLegales > 0)
                        <x-ui.aviso tipo="advertencia" class="mb-3">Faltan {{ $faltanLegales }} {{ $faltanLegales === 1 ? 'dato' : 'datos' }}: complétalos para que Vinculación no tenga que pedírtelos.</x-ui.aviso>
                    @endif
                    <form method="POST" action="{{ route('empresa.datos-legales') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2" x-data="{ tipo: @js($empresa->tipo_organismo->value) }">
                        @csrf
                        @method('PUT')
                        <div><label for="rep" class="etiqueta">Representante legal</label>
                            <input id="rep" type="text" name="representante_legal" required minlength="5" maxlength="255" data-solo="letras" pattern="{{ \App\Support\Formatos::NOMBRE_PERSONA_HTML }}" data-msg-formato="Escribe el nombre completo del representante (nombre y apellido), solo con letras." value="{{ old('representante_legal', $empresa->representante_legal) }}" placeholder="Nombre completo" @disabled($bloqueado) class="campo h-11"></div>
                        <div><label for="rep-cargo" class="etiqueta">Cargo</label>
                            <input id="rep-cargo" type="text" name="representante_cargo" required minlength="3" maxlength="255" data-solo="letras" value="{{ old('representante_cargo', $empresa->representante_cargo) }}" placeholder="Director General" @disabled($bloqueado) class="campo h-11"></div>
                        <div class="md:col-span-2"><label for="dom" class="etiqueta">Domicilio legal</label>
                            <input id="dom" type="text" name="domicilio_fiscal" required maxlength="500" value="{{ old('domicilio_fiscal', $empresa->domicilio_fiscal) }}" @disabled($bloqueado) class="campo h-11"></div>
                        <fieldset class="md:col-span-2">
                            <legend class="mb-2 text-sm font-semibold text-gray-900">Datos de creación de la organización</legend>
                            <x-empresa.datos-notariales :empresa="$empresa" :bloqueado="$bloqueado" />
                        </fieldset>
                        <div><label for="obj" class="etiqueta">Objeto social o atribuciones</label>
                            <textarea id="obj" name="objeto_social" rows="2" required maxlength="1500" @disabled($bloqueado) class="campo">{{ old('objeto_social', $empresa->objeto_social) }}</textarea></div>
                        <div><label for="fac" class="etiqueta">Facultad de quien firma</label>
                            <textarea id="fac" name="facultad_firmante" rows="2" required maxlength="1500" @disabled($bloqueado) class="campo">{{ old('facultad_firmante', $empresa->facultad_firmante) }}</textarea></div>
                        @if ($bloqueado)
                            <p class="flex items-center gap-1 text-xs text-gray-500 md:col-span-2"><x-dashboard.icono nombre="candado" clase="size-3.5" /> No se pueden cambiar mientras tu convenio está en revisión.</p>
                        @else
                            <div class="flex justify-end md:col-span-2"><button class="boton boton-primario">Guardar datos legales</button></div>
                        @endif
                    </form>
                </x-dashboard.panel>

                <section class="h-fit rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm leading-relaxed text-gray-600">
                    <p class="font-semibold text-gray-900">¿Necesitas cambiar un documento ya validado?</p>
                    <p class="mt-1">Por ejemplo, un cambio de apoderado o de domicilio fiscal. Escribe a Vinculación:</p>
                    <a href="{{ \App\Support\Correo::gmail(config('practicas.contacto.correo'), 'Cambio de documento del expediente') }}" target="_blank" rel="noopener" class="enlace mt-2 inline-block break-all">{{ config('practicas.contacto.correo') }}</a>
                </section>
            </div>
        </div>
    </div>
@endsection
