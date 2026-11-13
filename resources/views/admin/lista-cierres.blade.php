@extends('layouts.app')

@section('content')
@php
    use App\Enums\PracticaEstado as PrE;
    use App\Enums\ValidacionEstado as VE;

    $input = 'focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-3 focus:outline-hidden';
    $filtro = 'focus:border-brand-300 focus:ring-brand-500/10 h-10 rounded-lg border border-gray-300 px-3 text-sm focus:ring-3 focus:outline-hidden';
    $btn = 'bg-brand-500 hover:bg-brand-600 rounded-lg px-3 py-1.5 text-xs font-medium text-white';
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500';
@endphp

<x-common.page-breadcrumb pageTitle="Cierre de prácticas" />

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5">
        <div class="mb-5 flex flex-col gap-4 px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Carta de término y acreditación</h3>
                <p class="mt-0.5 text-sm text-gray-500">Valida la carta, registra el oficio y confirma la captura en SIIAU (Control Escolar no usa el sistema).</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre o código..." class="{{ $filtro }} w-44">
                <select name="ver" class="{{ $filtro }}">
                    <option value="pendientes" @selected($ver === 'pendientes')>Pendientes</option>
                    <option value="todas" @selected($ver === 'todas')>Incluir acreditadas</option>
                </select>
                <button class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">Filtrar</button>
            </form>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead class="border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Practicante</th>
                        <th class="{{ $th }}">Horas</th>
                        <th class="{{ $th }}">Carta de término</th>
                        <th class="{{ $th }}">Oficio</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($practicas as $pr)
                        @php
                            $al = $pr->postulacion->alumno;
                            $carta = $pr->cartaTermino;
                            $cartaPdf = $carta?->documentoVigente('carta_termino');
                            $oficio = $pr->oficioAcreditacion;
                            $oficioPdf = $oficio?->getFirstMedia('oficio_acreditacion');
                            $cartaColor = match ($carta?->estado_validacion) {
                                VE::Validada => 'bg-green-50 text-green-700',
                                VE::Rechazada => 'bg-red-50 text-red-700',
                                default => 'bg-yellow-50 text-yellow-700',
                            };
                        @endphp
                        <tr class="align-top transition-colors hover:bg-gray-50/40">
                            <td class="px-6 py-4">
                                <span class="block text-sm font-semibold text-gray-900">{{ $al->user->name }}</span>
                                <span class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }}</span>
                                <span class="block text-xs text-gray-400">Créditos a otorgar: {{ $al->carrera->creditos_otorgados }}</span>
                                <span class="block text-xs text-gray-400">{{ $pr->postulacion->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm">
                                <span class="font-bold {{ $pr->horasCompletas() ? 'text-green-700' : 'text-red-600' }}">{{ $pr->progresoTexto() }} h</span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $cartaColor }}">{{ ucfirst($carta->estado_validacion->value) }}</span>
                                @if ($cartaPdf)
                                    <a href="{{ route('admin.cierres.archivos', [$pr, $cartaPdf]) }}" class="text-brand-600 mt-1 block text-xs font-semibold">Descargar PDF</a>
                                    @if ($cartaPdf->getCustomProperty('motivo'))<span class="block max-w-[200px] text-xs text-red-600">{{ $cartaPdf->getCustomProperty('motivo') }}</span>@endif
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs text-gray-600">
                                @if ($oficio)
                                    @if ($oficioPdf)<a href="{{ route('admin.cierres.archivos', [$pr, $oficioPdf]) }}" class="text-brand-600 block font-semibold">Descargar PDF</a>@endif
                                    <span class="block">Enviado a Control Escolar: {{ $oficio->enviado_control_escolar_at?->format('d/m/Y') ?? '—' }}</span>
                                    <span class="block">Captura en SIIAU: {{ $oficio->capturado_siiau_at?->format('d/m/Y') ?? '—' }}</span>
                                @else
                                    <span class="text-gray-400">Sin oficio</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col items-end gap-2">
                                    @if ($pr->estado === PrE::Acreditada)
                                        <span class="rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700">Acreditada</span>

                                    @elseif ($pr->estado === PrE::EnCurso)
                                        @if ($carta->estado_validacion === VE::Validada)
                                            <span class="text-xs text-gray-400">—</span>
                                        @elseif (! $cartaPdf)
                                            <span class="text-xs text-gray-400">Esperando la carta del alumno</span>
                                        @else
                                            <form method="POST" action="{{ route('admin.cierres.carta', $pr) }}" class="flex flex-col items-end gap-2">
                                                @csrf
                                                <input type="text" name="motivo" placeholder="Motivo (obligatorio si rechaza)" maxlength="1000" class="{{ $input }} !w-56 text-xs">
                                                <div class="flex gap-2">
                                                    <button name="estado" value="validada" class="rounded-lg border border-green-500 px-3 py-1.5 text-xs font-medium text-green-700">Validar</button>
                                                    <button name="estado" value="rechazada" class="rounded-lg border border-red-500 px-3 py-1.5 text-xs font-medium text-red-700">Rechazar</button>
                                                </div>
                                            </form>
                                        @endif

                                    @elseif ($pr->estado === PrE::EnCierre)
                                        @if (! $oficio || $oficio->enviado_control_escolar_at === null)
                                            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.cierres.oficio', $pr) }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="file" name="archivo" accept="application/pdf" required class="{{ $input }} !w-44 !py-1 text-xs">
                                                <button class="{{ $btn }}">{{ $oficio ? 'Reemplazar oficio' : 'Registrar oficio' }}</button>
                                            </form>
                                        @endif

                                        @if ($oficio && $oficio->enviado_control_escolar_at === null)
                                            <form method="POST" action="{{ route('admin.cierres.enviar', $pr) }}">
                                                @csrf
                                                <button class="{{ $btn }}">Marcar enviado a Control Escolar</button>
                                            </form>
                                        @elseif ($oficio && $oficio->capturado_siiau_at === null)
                                            <form method="POST" action="{{ route('admin.cierres.siiau', $pr) }}" onsubmit="return confirm('¿Control Escolar confirmó la captura en SIIAU? La práctica quedará acreditada.')">
                                                @csrf
                                                <button class="{{ $btn }}">Registrar captura en SIIAU</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">No hay prácticas en proceso de cierre.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4">{{ $practicas->links() }}</div>
    </div>
</div>
@endsection