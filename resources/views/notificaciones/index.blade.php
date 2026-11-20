{{-- resources/views/notificaciones/index.blade.php --}}
@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notificaciones" />

    @if (count($pendientes))
        {{-- Por atender: tu situación actual (no depende de que te hayan enviado un aviso) --}}
        <section class="tarjeta mb-6 overflow-hidden" aria-labelledby="titulo-pendientes">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 id="titulo-pendientes" class="text-lg font-bold text-gray-900">Por atender</h3>
                <p class="text-sm text-gray-500">Se actualiza solo según el estado de tu trámite.</p>
            </div>
            <div class="space-y-3 p-6">
                @foreach ($pendientes as $p)
                    <x-ui.aviso :tipo="$p['tipo']">
                        {{ $p['mensaje'] }}
                        <a href="{{ $p['url'] }}" class="enlace mt-1 block font-semibold">Ir ahora</a>
                    </x-ui.aviso>
                @endforeach
            </div>
        </section>
    @endif

    <div class="tarjeta overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h3 class="text-lg font-bold text-gray-900">Todas las notificaciones</h3>
            <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                @csrf
                <button class="text-real-600 text-sm font-semibold hover:underline">Marcar todas como leídas</button>
            </form>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($notificaciones as $n)
                <a href="{{ route('notificaciones.abrir', $n->id) }}" class="flex items-start gap-3 px-6 py-4 hover:bg-gray-50">
                    <span
                        class="mt-2 h-2 w-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-udg-800' }}"></span>
                    <div>
                        <p class="text-sm {{ $n->read_at ? 'text-gray-500' : 'font-medium text-gray-800' }}">
                            {{ $n->data['mensaje'] ?? 'Notificación' }}</p>
                        <p class="text-xs text-gray-400">{{ $n->data['detalle'] ?? $n->data['empresa'] ?? '' }}
                            {{ isset($n->data['detalle']) || isset($n->data['empresa']) ? '·' : '' }}
                            {{ $n->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </a>
            @empty
                <p class="px-6 py-10 text-center text-sm text-gray-400">No tienes notificaciones.</p>
            @endforelse
        </div>

        <div class="border-t border-gray-100 px-6 py-4">{{ $notificaciones->links() }}</div>
    </div>
@endsection