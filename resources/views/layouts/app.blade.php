<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Panel' }} | Prácticas Profesionales CUCSH</title>

    {{-- Estilos y scripts compilados por Vite --}}
    @include('partials.errores-servidor')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('estilos')

    {{-- Estado global del menú lateral (Alpine llega con Vite en resources/js/app.js) --}}
    <script>
        document.addEventListener('alpine:init', () => {
            // Solo modo claro (DESIGN_SYSTEM.md). El store se conserva vacío para no romper referencias antiguas.
            Alpine.store('theme', { theme: 'light', toggle() {}, updateTheme() {} });

            Alpine.store('sidebar', {
                // Abierto en escritorio (≥ 1280 px); en móvil y tableta empieza cerrado
                isExpanded: window.innerWidth >= 1280,
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    // Al contraer o expandir en escritorio, el menú móvil queda cerrado
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                    // El menú móvil no cambia el estado contraído/expandido de escritorio
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Al pasar el cursor se despliega solo en escritorio y con el menú contraído
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

    {{-- Solo modo claro: borra la preferencia oscura que haya guardado la plantilla anterior --}}
    <script>
        localStorage.removeItem('theme');
        document.documentElement.classList.remove('dark');
    </script>

</head>

<body x-data="{ 'loaded': true}" x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            $store.sidebar.isExpanded = true;
        }
    };
    window.addEventListener('resize', checkMobile);">

    {{-- preloader --}}
    <x-common.preloader />
    {{-- preloader end --}}

    <div class="min-h-screen xl:flex">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="min-w-0 flex-1 transition-all duration-300 ease-in-out" :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            {{-- Encabezado --}}
            @include('layouts.app-header')
            {{-- Fin del encabezado --}}
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @yield('content')
            </div>
        </div>

    </div>
    {{-- La validación de formularios en español vive en resources/js/validacion.js --}}
</body>

@stack('scripts')

</html>