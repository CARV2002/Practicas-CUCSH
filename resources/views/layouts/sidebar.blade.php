
@php
    use App\Helpers\MenuHelper;
    $menuGroups = MenuHelper::getMenuGroups();

    // Ruta actual y, por submenú («grupo-opción»), las rutas que lo abren al cargar la página
    $currentPath = request()->path();
    $submenus = [];
    foreach ($menuGroups as $g => $grupo) {
        foreach ($grupo['items'] as $i => $item) {
            if (isset($item['subItems'])) {
                $submenus["{$g}-{$i}"] = array_column($item['subItems'], 'path');
            }
        }
    }
@endphp

{{-- Colores del menú: utilidades menu-item-* en resources/css/app.css (DESIGN_SYSTEM.md §4) --}}

<aside id="sidebar" x-cloak
    class="fixed left-0 top-0 z-[100001] mt-0 flex h-dvh flex-col border-r border-gray-200 bg-white px-4 text-gray-900 shadow-theme-xl transition-all duration-300 ease-in-out motion-reduce:transition-none xl:shadow-none"
    aria-label="Menú principal"
    @keydown.escape.window="$store.sidebar.setMobileOpen(false)"
    x-data="menuLateral(@js(['actual' => $currentPath, 'submenus' => $submenus]))"
    :class="{
        'w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered,
        'w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="if (!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)"
    >
    {{-- Logotipo --}}
    <div class="relative flex shrink-0 justify-center border-b border-gray-100 pb-5 pt-6">
        <a href="{{ route('dashboard') }}" class="block rounded-lg px-2 py-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40">
            <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" class="flex flex-col items-center text-center">
                <img src="{{ asset('images/home/cucsh.svg') }}" alt="CUCSH · Universidad de Guadalajara" width="170" height="46" class="h-auto w-[170px]" />
                <span class="mt-2.5 flex items-center gap-2 whitespace-nowrap text-[11px] font-semibold uppercase tracking-[0.12em] text-oro-700">
                    <span class="h-px w-3 bg-oro-300" aria-hidden="true"></span>Prácticas Profesionales
                    <span class="h-px w-3 bg-oro-300" aria-hidden="true"></span>
                </span>
            </span>
            <img x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"
                src="{{ asset('images/home/cucsh.svg') }}" alt="CUCSH" width="40" height="32" class="h-8 w-auto" />
        </a>
        {{-- Cerrar (solo menú móvil) --}}
        <button type="button" x-show="$store.sidebar.isMobileOpen" @click="$store.sidebar.setMobileOpen(false)"
            class="absolute right-0 top-4 flex size-10 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40 xl:hidden"
            aria-label="Cerrar el menú">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    {{-- Menú de navegación --}}
    <div class="custom-scrollbar -mx-1 flex flex-1 flex-col overflow-y-auto px-1 pt-5">
        <nav class="mb-6">
            <div class="flex flex-col gap-4">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div>
                        {{-- Título del grupo de opciones --}}
                        <h2 class="mb-2 flex px-3 text-[11px] font-semibold uppercase leading-5 tracking-wider text-gray-500"
                            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                            'lg:justify-center' : 'justify-start'">
                            <template
                                x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                                <span>{{ $menuGroup['title'] }}</span>
                            </template>
                            <template x-if="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path fill-rule="evenodd" clip-rule="evenodd" d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z" fill="currentColor"/>
                                </svg>
                            </template>
                        </h2>

                        {{-- Opciones del grupo --}}
                        <ul class="flex flex-col gap-1">
                            @foreach ($menuGroup['items'] as $itemIndex => $item)
                                <li>
                                    @if (isset($item['subItems']))
                                        {{-- Opción con submenú --}}
                                        <button @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }})"
                                            class="menu-item group w-full"
                                            :class="[
                                                isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                'menu-item-active' : 'menu-item-inactive',
                                                !$store.sidebar.isExpanded && !$store.sidebar.isHovered ?
                                                'xl:justify-center' : 'xl:justify-start'
                                            ]">

                                            {{-- Ícono --}}
                                            <span :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                    'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            {{-- Texto --}}
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex items-center gap-2">
                                                {{ $item['name'] }}
                                                @if (!empty($item['new']))
                                                    <span class="absolute right-10"
                                                        :class="isActive('{{ $item['path'] ?? '' }}') ?
                                                            'menu-dropdown-badge menu-dropdown-badge-active' :
                                                            'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                        new
                                                    </span>
                                                @endif
                                            </span>

                                            {{-- Flecha del submenú --}}
                                            <svg x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="ml-auto w-5 h-5 transition-transform duration-200"
                                                :class="{
                                                    'rotate-180 text-udg-900': isSubmenuOpen({{ $groupIndex }},
                                                        {{ $itemIndex }})
                                                }"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        {{-- Submenú --}}
                                        <div x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && ($store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen)">
                                            <ul class="mt-2 space-y-1 ml-9">
                                                @foreach ($item['subItems'] as $subItem)
                                                    <li>
                                                        <a href="{{ $subItem['path'] }}" class="menu-dropdown-item"
                                                            :class="isActive('{{ $subItem['path'] }}') ?
                                                                'menu-dropdown-item-active' :
                                                                'menu-dropdown-item-inactive'">
                                                            {{ $subItem['name'] }}
                                                            <span class="flex items-center gap-1 ml-auto">
                                                                @if (!empty($subItem['new']))
                                                                    <span
                                                                        :class="isActive('{{ $subItem['path'] }}') ?
                                                                            'menu-dropdown-badge menu-dropdown-badge-active' :
                                                                            'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                                        new
                                                                    </span>
                                                                @endif
                                                                @if (!empty($subItem['pro']))
                                                                    <span
                                                                        :class="isActive('{{ $subItem['path'] }}') ?
                                                                            'menu-dropdown-badge-pro menu-dropdown-badge-pro-active' :
                                                                            'menu-dropdown-badge-pro menu-dropdown-badge-pro-inactive'">
                                                                        pro
                                                                    </span>
                                                                @endif
                                                            </span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @elseif (!empty($item['bloqueado']))
                                        {{-- Pestaña deshabilitada: una etiqueta corta y legible; el motivo completo va al pasar el cursor y para lectores de pantalla (la pantalla lo muestra como aviso) --}}
                                        <span role="link" aria-disabled="true" tabindex="0" title="{{ $item['bloqueado'] }}"
                                            class="menu-item group menu-item-inactive cursor-not-allowed"
                                            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'xl:justify-center' : 'justify-start'">
                                            <span class="menu-item-icon-inactive opacity-50">{!! MenuHelper::getIconSvg($item['icon']) !!}</span>
                                            <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex flex-col items-start">
                                                <span class="flex items-center gap-1.5 text-gray-500">
                                                    {{ $item['name'] }}
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                                </span>
                                                <span class="text-[11px] font-semibold leading-tight text-amber-700" aria-hidden="true">Aún no disponible</span><span class="sr-only">. {{ $item['bloqueado'] }}</span>
                                            </span>
                                        </span>
                                    @else
                                        {{-- Opción sin submenú --}}
                                        <a href="{{ $item['path'] }}" class="menu-item group"
                                            :class="[
                                                isActive('{{ $item['path'] }}') ? 'menu-item-active' :
                                                'menu-item-inactive',
                                                (!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                                                'xl:justify-center' :
                                                'justify-start'
                                            ]">

                                            {{-- Ícono --}}
                                            <span
                                                :class="isActive('{{ $item['path'] }}') ? 'menu-item-icon-active' :
                                                    'menu-item-icon-inactive'">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            {{-- Texto --}}
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="menu-item-text flex items-center gap-2">
                                                {{ $item['name'] }}
                                                @if (!empty($item['new']))
                                                    <span
                                                        class="ml-2 inline-flex items-center rounded-full bg-oro-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-oro-700">
                                                        new
                                                    </span>
                                                @endif
                                            </span>
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>


    </div>
</aside>

{{-- Fondo oscuro detrás del menú en móvil (al tocarlo, se cierra) --}}
<div x-show="$store.sidebar.isMobileOpen" x-cloak @click="$store.sidebar.setMobileOpen(false)"
    x-transition.opacity.duration.300ms aria-hidden="true"
    class="fixed inset-0 z-[100000] bg-udg-950/50 backdrop-blur-sm xl:hidden"></div>
