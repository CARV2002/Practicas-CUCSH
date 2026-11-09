@php
    $usuario = auth()->user();
    $partes = preg_split('/\s+/', trim($usuario->name));
    $iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    $rolActual = $usuario->roles->first()?->name;
    $etiquetaRol = match ($rolActual) {
        'administrador' => 'Administrador',
        'empresa' => 'Empresa',
        'alumno' => 'Alumno',
        default => null,
    };
@endphp

<div class="relative" x-data="{ dropdownOpen: false }" @click.away="dropdownOpen = false">
    <button type="button" class="flex items-center text-gray-700" @click.prevent="dropdownOpen = !dropdownOpen">
        <span class="bg-brand-50 text-brand-600 mr-3 flex h-11 w-11 items-center justify-center overflow-hidden rounded-full text-sm font-bold">
            @if ($usuario->foto_url ?? false)
                <img src="{{ $usuario->foto_url }}" alt="{{ $usuario->name }}" class="h-full w-full object-cover" />
            @else
                {{ $iniciales }}
            @endif
        </span>

        <span class="mr-1 hidden text-left sm:block">
            <span class="block text-theme-sm font-medium text-white">{{ $usuario->name }}</span>
            @if ($etiquetaRol)
                <span class="block text-theme-xs text-gray-300">{{ $etiquetaRol }}</span>
            @endif
        </span>

        <svg class="h-5 w-5 text-white transition-transform duration-200" :class="{ 'rotate-180': dropdownOpen }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="dropdownOpen" x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 z-50 mt-[17px] flex w-[240px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg"
        style="display: none;">

        <div class="border-b border-gray-200 pb-3">
            <span class="block text-theme-sm font-medium text-gray-700">{{ $usuario->name }}</span>
            @if ($etiquetaRol)
                <span class="mt-0.5 block text-theme-xs text-gray-500">{{ $etiquetaRol }}</span>
            @endif
        </div>

        <ul class="flex flex-col gap-1 border-b border-gray-200 py-3">
            <li>
                <a href="{{ route('profile.edit') }}"
                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100">
                    <svg class="h-5 w-5 text-gray-500 group-hover:text-gray-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.5-1.632z" />
                    </svg>
                    Mi perfil
                </a>
            </li>
            <li>
                <a href="{{ route('ayuda') }}"
                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100">
                    <svg class="h-5 w-5 text-gray-500 group-hover:text-gray-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                    Ayuda
                </a>
            </li>
        </ul>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" @click="dropdownOpen = false"
                class="group mt-3 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100">
                <svg class="h-5 w-5 text-gray-500 group-hover:text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Cerrar sesión
            </button>
        </form>
    </div>
</div>