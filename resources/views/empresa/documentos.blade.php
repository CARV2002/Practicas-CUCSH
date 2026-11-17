@extends('layouts.app')

@section('content')
    @php
        $input = 'focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-3 focus:outline-hidden';
        $nombres = [
            'cv_instancia' => 'Currículum de la instancia',
            'identificacion_representante' => 'Identificación oficial del representante',
            'acta_constitutiva' => 'Acta constitutiva o nombramiento legal',
            'constancia_situacion_fiscal' => 'Constancia de situación fiscal',
            'comprobante_domicilio' => 'Comprobante de domicilio',
            'poder_notarial' => 'Poder notarial (opcional)',
        ];
    @endphp

    <x-common.page-breadcrumb pageTitle="Documentos" />

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)
                <li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Datos legales: el representante firma el convenio --}}
        <form method="POST" action="{{ route('empresa.datos-legales') }}" class="rounded-2xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')
            <h3 class="text-base font-bold text-gray-900">Representante legal</h3>
            <p class="mb-3 text-xs text-gray-500">Es quien firmará el convenio. Debe coincidir con la identificación oficial y el acta o poder que subas abajo.</p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="text" name="representante_legal" required maxlength="255" aria-label="Nombre del representante legal"
                    value="{{ old('representante_legal', $empresa->representante_legal) }}" placeholder="Nombre completo"
                    @disabled($bloqueado) class="{{ $input }}">
                @unless ($bloqueado)
                    <button class="bg-brand-500 hover:bg-brand-600 rounded-lg px-4 py-2 text-sm font-medium text-white">Guardar</button>
                @endunless
            </div>
        </form>

        @if ($bloqueado)
            <div class="rounded-xl border border-blue-500 bg-blue-50 p-4 text-sm text-blue-800">
                Tu convenio está en revisión, por lo que los documentos están bloqueados. Se habilitarán de nuevo si la
                Vinculación rechaza un documento o la solicitud.
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-5">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Expediente de la empresa</h3>
                    <p class="mt-0.5 text-sm text-gray-500">Sube tus documentos en PDF (el currículum también acepta Word).
                        Vinculación los validará.</p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold {{ $expedienteEnOrden ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700' }}">
                    {{ $expedienteEnOrden ? ($expedienteCompleto ? 'Expediente completo y validado' : 'Expediente en orden (convenio vigente)') : 'Expediente incompleto' }}
                </span>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach ($documentos as $col => $d)
                    @php
                        $media = $d['media'];
                        $st = $media ? ($media->getCustomProperty('estado') ?? 'pendiente') : null;
                        $motivo = $media?->getCustomProperty('motivo');
                        $color = match ($st) {
                            'validada' => 'bg-green-50 text-green-700',
                            'rechazada' => 'bg-red-50 text-red-700',
                            'pendiente' => 'bg-yellow-50 text-yellow-700',
                            default => 'bg-gray-100 text-gray-500',
                        };
                        $accept = $col === 'cv_instancia' ? '.pdf,.doc,.docx' : 'application/pdf';
                    @endphp
                    <div class="flex flex-col gap-4 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ $nombres[$col] ?? \Illuminate\Support\Str::headline($col) }}
                                @if ($d['obligatorio'])<span class="text-red-500">*</span>@endif
                            </p>
                            @if ($media)
                                <p class="text-xs text-gray-500">
                                    <a href="{{ route('empresa.documentos.descargar', $media->id) }}"
                                        class="text-brand-600 font-semibold hover:underline">{{ $media->file_name }}</a>
                                    · subido {{ $media->created_at->format('d/m/Y') }}
                                </p>
                                @if ($st === 'rechazada' && $motivo)
                                    <p class="mt-1 text-xs text-red-600">Motivo del rechazo: {{ $motivo }}</p>
                                @endif
                            @else
                                <p class="text-xs text-gray-400">Sin archivo</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $color }}">
                                {{ $st ? ucfirst($st) : 'Pendiente de subir' }}
                            </span>

                            @if ($d['puedeSubir'])
                            <form method="POST" enctype="multipart/form-data" action="{{ route('empresa.documentos.subir') }}" class="flex items-center gap-2">
                               @csrf
                              <input type="hidden" name="coleccion" value="{{ $col }}">
                               <input type="file" name="archivo" accept="{{ $accept }}" required class="{{ $input }} !w-56">
                                   <button class="bg-brand-500 hover:bg-brand-600 rounded-lg px-3 py-2 text-xs font-medium text-white">
                               {{ $media ? 'Reemplazar' : 'Subir' }}
                                   </button>
                                 </form>
                            @elseif ($st !== 'validada')
                                 <span class="text-xs text-gray-400">Bloqueado durante la revisión</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 text-xs leading-relaxed text-gray-500">
            <strong>¿Necesitas cambiar un documento ya validado?</strong> (cambio de apoderado, domicilio fiscal, etc.)
            Contacta a Vinculación (convenios): {{ config('practicas.contacto.correo') }}
        </div>
    </div>
@endsection