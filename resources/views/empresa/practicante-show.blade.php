@extends('layouts.app')

@section('content')
    @php
        use App\Enums\ReporteEstado as RE;

        $horas = (int) $practica->horas_acumuladas;
        $pct = $practica->horas_requeridas > 0 ? min(100, round($horas / $practica->horas_requeridas * 100)) : 0;
        $input = 'campo';
        $editable = in_array($practica->estado, \App\Enums\PracticaEstado::activas(), true);
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado :titulo="$alumno->user->name" :volver="route('empresa.lista-practicantes')" volver-texto="Practicantes"
            :descripcion="$alumno->num_control . ' · ' . $alumno->carrera->nombre">
            <x-slot:acciones><x-ui.estatus :estado="$practica->estado" tamano="normal" /></x-slot:acciones>
        </x-pagina.encabezado>

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
        @endif

        {{-- La carta de término la emite la organización: se pide en cuanto el practicante cubre sus horas --}}
        @if ($practica->estado === \App\Enums\PracticaEstado::EnCurso && $practica->horasCompletas() && ! $practica->cartaTermino?->getFirstMedia('carta_termino'))
            <x-ui.aviso tipo="advertencia" titulo="{{ $alumno->user->name }} completó sus horas: emite su carta de término">
                Elabórala en la hoja membretada de tu organización, fírmala, séllala y entrégasela. El practicante la sube escaneada y su coordinación la valida para acreditar la práctica.
                <x-ui.requisitos-carta tipo="termino" para="empresa" :horas="$practica->horas_requeridas" class="mt-3 bg-white/70" />
            </x-ui.aviso>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs sm:p-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center">
                <div class="flex min-w-0 items-center gap-4 lg:w-1/3">
                    <x-ui.user-avatar :user="$alumno->user" size="size-14 shrink-0 text-base" />
                    <dl class="min-w-0 space-y-1 text-sm">
                        <div><dt class="sr-only">Programa</dt><dd class="font-semibold text-gray-900">{{ $practica->postulacion->programa->titulo }}</dd></div>
                        <div><dt class="sr-only">Periodo</dt><dd class="text-gray-600">{{ $practica->fecha_inicio->format('d/m/Y') }} al {{ $practica->fecha_fin->format('d/m/Y') }}</dd></div>
                        <div><dt class="sr-only">Supervisor</dt><dd class="text-gray-600">Supervisor UdeG: <span class="{{ $practica->supervisor ? 'font-medium text-gray-800' : 'italic text-gray-400' }}">{{ $practica->supervisor?->nombre ?? 'por asignar' }}</span></dd></div>
                    </dl>
                </div>
                <div class="flex-1">
                    <div class="flex items-baseline justify-between">
                        <p class="text-sm text-gray-600"><span class="font-display text-3xl font-bold tabular-nums text-udg-900">{{ $horas }}</span> / {{ $practica->horas_requeridas }} h validadas</p>
                        <span class="font-display text-lg font-bold text-udg-900">{{ $pct }}%</span>
                    </div>
                    <div class="mt-2 h-3 overflow-hidden rounded-full bg-gray-100 ring-1 ring-inset ring-gray-200" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Horas validadas">
                        <div class="h-full rounded-full bg-gradient-to-r from-udg-900 via-udg-700 to-oro-400" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            </div>
        </section>

        <x-dashboard.panel titulo="Reportes de horas" icono="comentario" descripcion="La coordinación de la carrera valida las horas; tu retroalimentación sobre el desempeño queda en cada reporte.">
            @forelse ($reportes as $r)
                @php
                    $pdf = $r->media->firstWhere('collection_name', 'reporte_firmado');
                    $comentado = filled($r->retroalimentacion_empresa);
                @endphp
                <article class="-mx-2 rounded-xl px-2 py-4 [&+&]:border-t [&+&]:border-gray-100">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-udg-50 text-udg-900 ring-1 ring-inset ring-udg-100">
                                <span class="font-display text-sm font-bold leading-none tabular-nums">{{ $r->horas_reportadas }}</span>
                                <span class="text-[10px] font-medium">h</span>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Reporte {{ mb_strtolower($r->tipo->label()) }}</p>
                                <p class="text-xs text-gray-500">{{ $r->periodo_inicio->format('d/m/Y') }} – {{ $r->periodo_fin->format('d/m/Y') }}</p>
                                @if ($pdf)
                                    <a href="{{ route('empresa.reportes.archivos', [$r, $pdf]) }}" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Reporte firmado</a>
                                @else
                                    <p class="dato-vacio mt-1">El alumno aún no sube el PDF firmado</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.estatus :familia="$comentado ? 'menta' : 'ambar'" :texto="$comentado ? 'Ya comentaste' : 'Sin tu comentario'" />
                            <x-ui.estatus :estado="$r->estado" />
                        </div>
                    </div>

                    @if ($editable)
                        <form method="POST" action="{{ route('empresa.reportes.retroalimentar', $r) }}" class="mt-3 space-y-2" x-data="{ n: {{ mb_strlen(old('retroalimentacion_empresa', $r->retroalimentacion_empresa) ?? '') }} }">
                            @csrf
                            <label for="retro-{{ $r->id }}" class="sr-only">Retroalimentación del reporte</label>
                            <textarea id="retro-{{ $r->id }}" name="retroalimentacion_empresa" rows="2" required maxlength="2000" @input="n = $event.target.value.length"
                                placeholder="¿Cómo fue su desempeño en este periodo? Fortalezas, áreas de mejora…" class="campo">{{ old('retroalimentacion_empresa', $r->retroalimentacion_empresa) }}</textarea>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs text-gray-400"><span x-text="n"></span>/2000</span>
                                <button class="boton boton-chico boton-primario">{{ $comentado ? 'Actualizar comentario' : 'Guardar comentario' }}</button>
                            </div>
                        </form>
                    @elseif ($r->retroalimentacion_empresa)
                        <p class="mt-3 rounded-lg border-l-4 border-oro-400 bg-oro-50/50 px-3 py-2 text-sm text-gray-700">{{ $r->retroalimentacion_empresa }}</p>
                    @else
                        <p class="dato-vacio mt-3">No dejaste comentario en este reporte.</p>
                    @endif
                </article>
            @empty
                <x-dashboard.vacio icono="reporte" titulo="Este practicante aún no tiene reportes" texto="Cuando suba su primer reporte de horas podrás comentarlo aquí." />
            @endforelse
        </x-dashboard.panel>
    </div>
@endsection
