@extends('layouts.app')

@section('content')
    @php
        use App\Enums\PracticaEstado as PrE;
        use App\Enums\ValidacionEstado as VE;

        $input = 'campo';
        $filtro = 'campo-filtro';
        $btn = 'boton boton-chico boton-primario';
        $th = 'tabla-th';
    @endphp

    <div class="space-y-6">
        <x-pagina.encabezado titulo="Cierre de prácticas" descripcion="Valida la carta de término que emitió la organización (membretada, firmada y sellada), registra el oficio y confirma la captura en SIIAU. Control Escolar no usa el sistema: tú registras cada paso." />

        @if (session('status'))
            <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
        @endif
        @if ($errors->any())
            <x-ui.aviso tipo="error" titulo="Revisa lo siguiente"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
        @endif

        <x-tabla.marco titulo="Carta de término y acreditación" :total="$practicas->total()">
            <x-slot:herramientas>
                <x-tabla.filtros-estado parametro="ver" :actual="$ver" etiqueta="Qué mostrar" :opciones="[
                    ['valor' => 'pendientes', 'texto' => 'Por cerrar', 'familia' => 'ambar'],
                    ['valor' => 'todas', 'texto' => 'Incluir acreditadas', 'familia' => 'menta'],
                ]" />
                <form method="GET" class="flex flex-col gap-2 sm:flex-row" role="search">
                    <input type="hidden" name="ver" value="{{ $ver }}">
                    <div class="relative flex-1 sm:max-w-xs">
                        <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <label for="q" class="sr-only">Buscar por nombre o código</label>
                        <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o código" class="campo-filtro pl-9 sm:w-full">
                    </div>
                    <button class="boton boton-secundario">Buscar</button>
                </form>
            </x-slot:herramientas>

            <table class="tabla">
                <thead>
                    <tr>
                        <th class="tabla-th px-6">Practicante</th>
                        <th class="tabla-th">Avance del cierre</th>
                        <th class="tabla-th">Carta de término</th>
                        <th class="tabla-th">Oficio</th>
                        <th class="tabla-th px-6">Siguiente paso</th>
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
                            $pasos = [
                                ['Carta validada', $carta?->estado_validacion === VE::Validada],
                                ['Oficio enviado', (bool) $oficio?->enviado_control_escolar_at],
                                ['Captura en SIIAU', (bool) $oficio?->capturado_siiau_at],
                            ];
                        @endphp
                        <tr class="align-top tabla-fila">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <x-ui.user-avatar :user="$al->user" size="mt-0.5 size-9 shrink-0 text-xs ring-2 ring-white" />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $al->user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $al->num_control }} · {{ $al->carrera->carrera_clave }}</p>
                                        <p class="text-xs text-gray-500">{{ $pr->postulacion->programa->convenio?->empresa->razon_social ?? 'Proyecto interno' }}</p>
                                        <p class="mt-1 text-xs">
                                            <span class="font-semibold {{ $pr->horasCompletas() ? 'text-emerald-700' : 'text-rose-600' }}">{{ $pr->progresoTexto() }} h</span>
                                            <span class="text-gray-500">· {{ $al->carrera->creditos_otorgados }} créditos a otorgar</span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <ol class="space-y-1.5" aria-label="Avance del cierre">
                                    @foreach ($pasos as [$nombrePaso, $hecho])
                                        <li class="flex items-center gap-2 text-xs">
                                            <span @class(['flex size-4 items-center justify-center rounded-full', 'bg-emerald-500 text-white' => $hecho, 'bg-gray-100 ring-1 ring-inset ring-gray-300' => !$hecho])>
                                                @if ($hecho)<x-dashboard.icono nombre="check" clase="size-2.5" />@endif
                                            </span>
                                            <span class="{{ $hecho ? 'font-medium text-gray-800' : 'text-gray-400' }}">{{ $nombrePaso }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </td>
                            <td class="px-4 py-4">
                                <x-ui.estatus :estado="$carta->estado_validacion" />
                                @if ($cartaPdf)
                                    <a href="{{ route('coordinacion.cierres.archivos', [$pr, $cartaPdf]) }}" class="mt-1.5 flex items-center gap-1 text-xs font-semibold text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Carta de término</a>
                                    @if ($cartaPdf->getCustomProperty('motivo'))<p class="mt-1 max-w-[14rem] text-xs text-rose-600">Motivo: {{ $cartaPdf->getCustomProperty('motivo') }}</p>@endif
                                @else
                                    <p class="dato-vacio mt-1.5">El alumno aún no la sube</p>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs">
                                @if ($oficio)
                                    @if ($oficioPdf)<a href="{{ route('coordinacion.cierres.archivos', [$pr, $oficioPdf]) }}" class="flex items-center gap-1 font-semibold text-real-600 hover:text-real-700"><x-dashboard.icono nombre="descargar" clase="size-3.5" /> Oficio</a>@endif
                                    <p class="mt-1 text-gray-600">Control Escolar: <span class="font-medium {{ $oficio->enviado_control_escolar_at ? 'text-gray-900' : 'italic text-gray-400' }}">{{ $oficio->enviado_control_escolar_at?->format('d/m/Y') ?? 'sin enviar' }}</span></p>
                                    <p class="text-gray-600">SIIAU: <span class="font-medium {{ $oficio->capturado_siiau_at ? 'text-gray-900' : 'italic text-gray-400' }}">{{ $oficio->capturado_siiau_at?->format('d/m/Y') ?? 'sin capturar' }}</span></p>
                                @else
                                    <span class="dato-vacio">Sin oficio registrado</span>
                                @endif
                            </td>
                            <td class="acciones px-6 py-4">
                                <div class="flex min-w-56 flex-col gap-2">
                                    @if ($pr->estado === PrE::Acreditada)
                                        <x-ui.estatus familia="menta" texto="Acreditada" tamano="normal" />
                                    @elseif ($pr->estado === PrE::EnCurso)
                                        @if ($carta->estado_validacion === VE::Validada)
                                            <span class="dato-vacio">Sin acciones pendientes</span>
                                        @elseif (!$cartaPdf)
                                            <span class="dato-vacio">Esperando la carta del alumno</span>
                                        @else
                                            <form method="POST" action="{{ route('coordinacion.cierres.carta', $pr) }}" class="flex flex-col gap-2">
                                                @csrf
                                                <label class="sr-only" for="mot-{{ $pr->id }}">Motivo del rechazo</label>
                                                <input id="mot-{{ $pr->id }}" type="text" name="motivo" placeholder="Motivo (obligatorio si rechazas)" maxlength="1000" class="campo h-9 text-xs">
                                                @php $faltaValidar = $bloqueosValidar[$pr->id] ?? null; @endphp
                                                <div class="grid grid-cols-2 gap-2">
                                                    @if ($faltaValidar)
                                                        <x-ui.accion-bloqueada :motivo="$faltaValidar" tamano="chico">Validar</x-ui.accion-bloqueada>
                                                    @else
                                                        <button name="estado" value="validada" formnovalidate data-sin-bloqueo="1" class="inline-flex h-9 items-center justify-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95"><x-dashboard.icono nombre="check" clase="size-3.5" /> Validar</button>
                                                    @endif
                                                    <button name="estado" value="rechazada" data-sin-bloqueo="1" data-confirmar="¿Rechazar la carta de término? El alumno tendrá que subirla de nuevo con la corrección que indicas." data-confirmar-titulo="Rechazar carta" data-confirmar-boton="Sí, rechazar" class="inline-flex h-9 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-white text-xs font-semibold text-rose-600 transition hover:bg-rose-50 active:scale-95"><x-dashboard.icono nombre="equis" clase="size-3.5" /> Rechazar</button>
                                                </div>
                                            </form>
                                        @endif
                                    @elseif ($pr->estado === PrE::EnCierre)
                                        @if (!$oficio || $oficio->enviado_control_escolar_at === null)
                                            <form method="POST" enctype="multipart/form-data" action="{{ route('coordinacion.cierres.oficio', $pr) }}" class="space-y-1.5">
                                                @csrf
                                                <x-ui.zona-archivo name="archivo" etiqueta="Oficio de acreditación firmado" />
                                                <button class="boton boton-chico boton-secundario w-full"><x-dashboard.icono nombre="subir" clase="size-3.5" /> {{ $oficio ? 'Reemplazar oficio' : 'Registrar oficio' }}</button>
                                            </form>
                                        @endif
                                        @if ($oficio && $oficio->enviado_control_escolar_at === null)
                                            <form method="POST" action="{{ route('coordinacion.cierres.enviar', $pr) }}" data-confirmar="¿Marcar el oficio como enviado a Control Escolar? Ya no podrás reemplazarlo." data-confirmar-titulo="Enviar a Control Escolar" data-confirmar-boton="Sí, marcar enviado" data-confirmar-tono="normal">
                                                @csrf
                                                <button class="boton boton-chico boton-primario w-full"><x-dashboard.icono nombre="enviar" clase="size-3.5" /> Marcar enviado a Control Escolar</button>
                                            </form>
                                        @elseif ($oficio && $oficio->capturado_siiau_at === null)
                                            <form method="POST" action="{{ route('coordinacion.cierres.siiau', $pr) }}" data-confirmar="¿Control Escolar confirmó la captura en SIIAU? La práctica quedará acreditada y ya no se podrá modificar." data-confirmar-titulo="Acreditar práctica" data-confirmar-boton="Sí, acreditar" data-confirmar-tono="normal">
                                                @csrf
                                                <button class="boton boton-chico boton-exito w-full"><x-dashboard.icono nombre="cierre" clase="size-3.5" /> Registrar captura en SIIAU</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-4"><x-dashboard.vacio icono="cierre" titulo="No hay prácticas en proceso de cierre" texto="Cuando un practicante suba su carta de término aparecerá aquí." /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:pie>{{ $practicas->links() }}</x-slot:pie>
        </x-tabla.marco>
    </div>
@endsection
