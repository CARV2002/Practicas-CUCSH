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

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Expediente --}}
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs xl:col-span-2" aria-labelledby="titulo-expediente">
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
                <div class="grid grid-cols-1 gap-4 p-5 sm:p-6 lg:grid-cols-2">
                    @foreach ($documentos as $col => $d)
                        @php
                            $media = $d['media'];
                            $st = $media ? ($media->getCustomProperty('estado') ?? 'pendiente') : null;
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
                            :bloqueo="!$d['puedeSubir'] && $st !== 'validada' ? 'Vinculación está revisando tu solicitud: podrás cambiarlo si te pide una corrección.' : null"
                            :boton="$st === 'rechazada' ? 'Enviar documento corregido' : ($media ? 'Reemplazar documento' : 'Enviar documento')">
                            <x-slot:ocultos><input type="hidden" name="coleccion" value="{{ $col }}"></x-slot:ocultos>
                        </x-ui.carga-documento>
                    @endforeach
                </div>
                <p class="px-6 pb-5 text-xs text-gray-500">* Obligatorio para solicitar el convenio.</p>
            </section>

            <aside class="space-y-6">
                {{-- Representante legal --}}
                @php
                    $legales = ['representante_legal', 'representante_cargo', 'domicilio_fiscal', 'constitucion', 'objeto_social', 'facultad_firmante'];
                    $faltanLegales = collect($legales)->filter(fn ($c) => blank($empresa->{$c}))->count()
                        + (\App\Support\DatosNotariales::usaEscritura($empresa->tipo_organismo) && blank($empresa->escritura_numero) ? 1 : 0);
                @endphp
                <x-dashboard.panel titulo="Datos legales del convenio" icono="firma" descripcion="Son tus declaraciones en el convenio; van tal cual al documento. Deben coincidir con tu acta y tu identificación.">
                    @if ($faltanLegales > 0)
                        <x-ui.aviso tipo="advertencia" class="mb-3">Faltan {{ $faltanLegales }} {{ $faltanLegales === 1 ? 'dato' : 'datos' }}: complétalos para que Vinculación no tenga que pedírtelos.</x-ui.aviso>
                    @endif
                    <form method="POST" action="{{ route('empresa.datos-legales') }}" class="space-y-3" x-data="{ tipo: @js($empresa->tipo_organismo->value) }">
                        @csrf
                        @method('PUT')
                        <div><label for="rep" class="etiqueta">Representante legal</label>
                            <input id="rep" type="text" name="representante_legal" required minlength="5" maxlength="255" data-solo="letras" pattern="{{ \App\Support\Formatos::NOMBRE_PERSONA_HTML }}" data-msg-formato="Escribe el nombre completo del representante (nombre y apellido), solo con letras." value="{{ old('representante_legal', $empresa->representante_legal) }}" placeholder="Nombre completo" @disabled($bloqueado) class="campo h-11"></div>
                        <div><label for="rep-cargo" class="etiqueta">Cargo</label>
                            <input id="rep-cargo" type="text" name="representante_cargo" required minlength="3" maxlength="255" data-solo="letras" value="{{ old('representante_cargo', $empresa->representante_cargo) }}" placeholder="Director General" @disabled($bloqueado) class="campo h-11"></div>
                        <div><label for="dom" class="etiqueta">Domicilio legal</label>
                            <input id="dom" type="text" name="domicilio_fiscal" required maxlength="500" value="{{ old('domicilio_fiscal', $empresa->domicilio_fiscal) }}" @disabled($bloqueado) class="campo h-11"></div>
                        <x-empresa.datos-notariales :empresa="$empresa" :bloqueado="$bloqueado" />
                        <div><label for="obj" class="etiqueta">Objeto social o atribuciones</label>
                            <textarea id="obj" name="objeto_social" rows="2" required maxlength="1500" @disabled($bloqueado) class="campo">{{ old('objeto_social', $empresa->objeto_social) }}</textarea></div>
                        <div><label for="fac" class="etiqueta">Facultad de quien firma</label>
                            <textarea id="fac" name="facultad_firmante" rows="2" required maxlength="1500" @disabled($bloqueado) class="campo">{{ old('facultad_firmante', $empresa->facultad_firmante) }}</textarea></div>
                        @if ($bloqueado)
                            <p class="flex items-center gap-1 text-xs text-gray-500"><x-dashboard.icono nombre="candado" clase="size-3.5" /> No se pueden cambiar mientras tu convenio está en revisión.</p>
                        @else
                            <button class="boton boton-primario w-full">Guardar datos legales</button>
                        @endif
                    </form>
                </x-dashboard.panel>

                <section class="rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm leading-relaxed text-gray-600">
                    <p class="font-semibold text-gray-900">¿Necesitas cambiar un documento ya validado?</p>
                    <p class="mt-1">Por ejemplo, un cambio de apoderado o de domicilio fiscal. Escribe a Vinculación:</p>
                    <a href="{{ \App\Support\Correo::gmail(config('practicas.contacto.correo'), 'Cambio de documento del expediente') }}" target="_blank" rel="noopener" class="enlace mt-2 inline-block break-all">{{ config('practicas.contacto.correo') }}</a>
                </section>
            </aside>
        </div>
    </div>
@endsection
