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

                <ul class="divide-y divide-gray-100">
                    @foreach ($documentos as $col => $d)
                        @php
                            $media = $d['media'];
                            $st = $media ? ($media->getCustomProperty('estado') ?? 'pendiente') : null;
                            $motivo = $media?->getCustomProperty('motivo');
                            $accept = $col === 'cv_instancia' ? '.pdf,.doc,.docx' : 'application/pdf';
                        @endphp
                        <li class="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-gray-50/60 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span @class([
                                    'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset',
                                    'bg-emerald-50 text-emerald-600 ring-emerald-100' => $st === 'validada',
                                    'bg-rose-50 text-rose-600 ring-rose-100' => $st === 'rechazada',
                                    'bg-amber-50 text-amber-600 ring-amber-100' => $st === 'pendiente',
                                    'bg-gray-50 text-gray-400 ring-gray-200' => !$st,
                                ])><x-dashboard.icono :nombre="$st === 'validada' ? 'check' : ($st === 'rechazada' ? 'equis' : 'documento')" clase="size-[18px]" /></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ \App\Models\Empresa::nombreDocumento($col) }}
                                        @if ($d['obligatorio'])<span class="ml-0.5 text-rose-500" title="Obligatorio">*</span>@endif
                                    </p>
                                    @if ($media)
                                        <a href="{{ route('empresa.documentos.descargar', $media->id) }}" class="inline-flex max-w-full items-center gap-1 text-xs font-medium text-real-600 hover:text-real-700">
                                            <x-dashboard.icono nombre="descargar" clase="size-3.5 shrink-0" /><span class="truncate">{{ $media->file_name }}</span>
                                        </a>
                                        <p class="text-xs text-gray-500">Subido el {{ $media->created_at->format('d/m/Y') }}</p>
                                        @if ($st === 'rechazada' && $motivo)<p class="mt-1 text-xs font-medium text-rose-600">Motivo: {{ $motivo }}</p>@endif
                                    @else
                                        <p class="dato-vacio">Aún no subes este documento</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                @if ($st)
                                    <x-ui.estatus :familia="$estadosDoc[$st][0]" :texto="$estadosDoc[$st][1]" />
                                @endif
                                @if ($d['puedeSubir'])
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('empresa.documentos.subir') }}" class="flex items-center gap-2" x-data="{ nombre: '' }">
                                        @csrf
                                        <input type="hidden" name="coleccion" value="{{ $col }}">
                                        <label class="inline-flex h-9 max-w-[12rem] cursor-pointer items-center gap-1.5 truncate rounded-lg border border-dashed border-gray-300 px-3 text-xs font-medium text-gray-600 transition hover:border-udg-300 hover:bg-udg-50 hover:text-udg-900">
                                            <x-dashboard.icono nombre="subir" clase="size-3.5 shrink-0" />
                                            <span class="truncate" x-text="nombre || 'Elegir archivo'">Elegir archivo</span>
                                            <input type="file" name="archivo" accept="{{ $accept }}" required class="sr-only" @change="nombre = $event.target.files[0]?.name || ''">
                                        </label>
                                        <button class="boton boton-chico boton-primario" :disabled="!nombre">{{ $media ? 'Reemplazar' : 'Subir' }}</button>
                                    </form>
                                @elseif ($st !== 'validada')
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400"><x-dashboard.icono nombre="candado" clase="size-3.5" /> Bloqueado en revisión</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <aside class="space-y-6">
                {{-- Representante legal --}}
                @php
                    $legales = ['representante_legal', 'representante_cargo', 'domicilio_fiscal', 'constitucion', 'objeto_social', 'facultad_firmante'];
                    $faltanLegales = collect($legales)->filter(fn ($c) => blank($empresa->{$c}))->count();
                @endphp
                <x-dashboard.panel titulo="Datos legales del convenio" icono="firma" descripcion="Son tus declaraciones en el convenio; van tal cual al documento. Deben coincidir con tu acta y tu identificación.">
                    @if ($faltanLegales > 0)
                        <x-ui.aviso tipo="advertencia" class="mb-3">Faltan {{ $faltanLegales }} {{ $faltanLegales === 1 ? 'dato' : 'datos' }}: complétalos para que Vinculación no tenga que pedírtelos.</x-ui.aviso>
                    @endif
                    <form method="POST" action="{{ route('empresa.datos-legales') }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div><label for="rep" class="etiqueta">Representante legal</label>
                            <input id="rep" type="text" name="representante_legal" required maxlength="255" value="{{ old('representante_legal', $empresa->representante_legal) }}" placeholder="Nombre completo" @disabled($bloqueado) class="campo h-11"></div>
                        <div><label for="rep-cargo" class="etiqueta">Cargo</label>
                            <input id="rep-cargo" type="text" name="representante_cargo" required maxlength="255" value="{{ old('representante_cargo', $empresa->representante_cargo) }}" placeholder="Director General" @disabled($bloqueado) class="campo h-11"></div>
                        <div><label for="dom" class="etiqueta">Domicilio legal</label>
                            <input id="dom" type="text" name="domicilio_fiscal" required maxlength="500" value="{{ old('domicilio_fiscal', $empresa->domicilio_fiscal) }}" @disabled($bloqueado) class="campo h-11"></div>
                        <div><label for="const" class="etiqueta">Acta o decreto de creación</label>
                            <textarea id="const" name="constitucion" rows="2" required maxlength="1500" @disabled($bloqueado) class="campo">{{ old('constitucion', $empresa->constitucion) }}</textarea></div>
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
