{{-- resources/views/notificaciones/index.blade.php --}}
@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notificaciones" />

    <div class="tarjeta overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
            {{-- Recientes: sin leer y leídas en la última semana · Archivadas: leídas hace más tiempo (se conservan) --}}
            <nav class="flex gap-1 rounded-xl bg-gray-100/80 p-1" aria-label="Notificaciones">
                @foreach ([false => ['Recientes', null], true => ['Archivadas', $totalArchivadas]] as $esArchivo => [$texto, $conteo])
                    <a href="{{ route('notificaciones.index', $esArchivo ? ['ver' => 'archivadas'] : []) }}" @if ($archivadas === (bool) $esArchivo) aria-current="page" @endif
                        @class(['flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                            'bg-white text-udg-900 shadow-theme-xs ring-1 ring-gray-200' => $archivadas === (bool) $esArchivo,
                            'text-gray-600 hover:bg-white/70' => $archivadas !== (bool) $esArchivo])>
                        {{ $texto }}
                        @if ($conteo)<span class="rounded-full bg-white px-1.5 text-xs text-gray-500 ring-1 ring-inset ring-gray-200">{{ $conteo }}</span>@endif
                    </a>
                @endforeach
            </nav>
            @unless ($archivadas)
                <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                    @csrf
                    <button class="text-sm font-semibold text-real-600 hover:underline">Marcar todas como leídas</button>
                </form>
            @endunless
        </div>

        @if ($archivadas)
            <p class="border-b border-gray-100 bg-gray-50 px-6 py-2.5 text-xs text-gray-500">Notificaciones que leíste hace más de {{ \App\Http\Controllers\NotificacionController::DIAS_ARCHIVO }} días. Se guardan como historial.</p>
        @endif

        <div class="divide-y divide-gray-100">
            @forelse ($notificaciones as $n)
                @php $partes = \App\Support\TextoAviso::partes($n->data['mensaje'] ?? null, $n->data['titulo'] ?? null); @endphp
                <a href="{{ route('notificaciones.abrir', $n->id) }}" @class(['group flex items-start gap-3 px-6 py-4 transition-colors hover:bg-gray-50', 'bg-udg-50/40' => !$n->read_at])>
                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-gray-200' : 'bg-udg-800' }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm leading-snug group-hover:text-udg-800 {{ $n->read_at ? 'text-gray-600' : 'font-semibold text-gray-900' }}">{{ $partes['titulo'] }}</p>
                        @if ($partes['detalle'])
                            <p class="mt-1 text-sm leading-relaxed text-gray-500">{{ $partes['detalle'] }}</p>
                        @endif
                        <p class="mt-1 text-xs text-gray-400">
                            {{ $n->data['detalle'] ?? $n->data['empresa'] ?? '' }}{{ isset($n->data['detalle']) || isset($n->data['empresa']) ? ' · ' : '' }}{{ $n->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                </a>
            @empty
                <p class="px-6 py-10 text-center text-sm text-gray-400">{{ $archivadas ? 'No tienes notificaciones archivadas.' : 'No tienes notificaciones recientes.' }}</p>
            @endforelse
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $notificaciones->links() }}</div>
    </div>
@endsection
