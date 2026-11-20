<div class="relative" x-data="{
    abierto: false,
    cargando: false,
    noLeidas: 0,
    items: [],
    pendientes: [],
    // Cuántas cosas hay por atender (no cuántos avisos): lo mismo que dice el saludo del panel
    get porAtender() { return this.pendientes.reduce((t, p) => t + (p.n ?? 1), 0); },
    async cargar() {
        this.cargando = true;
        try {
            const r = await fetch('{{ route('notificaciones.resumen') }}', { headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            this.noLeidas = d.no_leidas;
            this.pendientes = d.pendientes || [];
            this.items = d.items;
        } finally { this.cargando = false; }
    },
    async toggle() {
        this.abierto = !this.abierto;
        if (this.abierto) await this.cargar();
    },
    async leerTodas() {
        await fetch('{{ route('notificaciones.leer-todas') }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        });
        await this.cargar();
    },
    init() { this.cargar(); setInterval(() => this.cargar(), 60000); }
}" @click.away="abierto = false">

    <button type="button" @click="toggle()"
        :aria-expanded="abierto.toString()"
        :aria-label="`Notificaciones: ${noLeidas} sin leer, ${porAtender} por atender`"
        class="relative flex size-10 items-center justify-center rounded-full border border-white/20 text-white/90 transition-colors duration-150 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-oro-300 lg:size-11"
        :class="{ 'bg-white/10': abierto }">
        <span x-show="noLeidas + porAtender > 0" x-cloak aria-hidden="true"
            class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-oro-400 px-1 text-[10px] font-bold text-udg-950 ring-2 ring-udg-900"
            x-text="noLeidas + porAtender > 9 ? '9+' : noLeidas + porAtender"></span>
        <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z" fill=""/>
        </svg>
    </button>

    <div x-show="abierto" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @keydown.escape.window="abierto = false"
        class="z-50 flex max-h-[min(480px,calc(100dvh-6rem))] origin-top-right flex-col tarjeta p-3 shadow-theme-lg max-xl:fixed max-xl:inset-x-3 max-xl:top-[4.5rem] sm:max-xl:left-auto sm:max-xl:right-4 sm:max-xl:w-[361px] xl:absolute xl:right-0 xl:mt-[17px] xl:w-[361px]">
        <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-3">
            <h5 class="text-lg font-semibold text-gray-800">Notificaciones</h5>
            <button type="button" @click="leerTodas()" x-show="noLeidas > 0" class="enlace text-xs">Marcar todas</button>
        </div>

        <div class="custom-scrollbar flex-1 overflow-y-auto">
        {{-- Por atender: sale de la situación actual del usuario, no de avisos previos --}}
        <template x-if="pendientes.length > 0">
            <div class="mb-3">
                <p class="px-1 pb-2 text-xs font-semibold text-gray-500">Por atender <span class="font-normal" x-text="'· ' + porAtender"></span></p>
                <ul class="space-y-1.5">
                    <template x-for="(p, i) in pendientes" :key="'p' + i">
                        <li>
                            <a :href="p.url" class="flex gap-2.5 rounded-lg px-3 py-2.5 text-sm ring-1 ring-inset transition hover:brightness-[0.98]"
                                :class="{ 'bg-rose-50 text-rose-900 ring-rose-200': p.tipo === 'error', 'bg-amber-50 text-amber-900 ring-amber-200': p.tipo === 'advertencia', 'bg-emerald-50 text-emerald-900 ring-emerald-200': p.tipo === 'exito', 'bg-sky-50 text-sky-900 ring-sky-200': p.tipo === 'info' }">
                                <span class="mt-1.5 size-2 shrink-0 rounded-full"
                                    :class="{ 'bg-rose-500': p.tipo === 'error', 'bg-amber-500': p.tipo === 'advertencia', 'bg-emerald-500': p.tipo === 'exito', 'bg-sky-500': p.tipo === 'info' }"></span>
                                <span x-text="p.mensaje"></span>
                            </a>
                        </li>
                    </template>
                </ul>
                <p class="mt-3 border-t border-gray-100 px-1 pt-3 text-xs font-semibold text-gray-500" x-show="items.length > 0">Avisos recientes</p>
            </div>
        </template>
        <ul>
            <template x-for="n in items" :key="n.id">
                <li>
                    <a :href="n.url" class="flex gap-3 rounded-lg border-b border-gray-100 px-3 py-3 hover:bg-gray-100">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.leida ? 'bg-transparent' : 'bg-udg-800'"></span>
                        <span class="block">
                            <span class="block text-sm" :class="n.leida ? 'text-gray-500' : 'font-medium text-gray-800'" x-text="n.mensaje"></span>
                            <span class="mt-1 block text-xs text-gray-400">
                                <template x-if="n.detalle"><span x-text="n.detalle + ' · '"></span></template><span x-text="n.hace"></span>
                            </span>
                        </span>
                    </a>
                </li>
            </template>
            <li x-show="!cargando && items.length === 0 && pendientes.length === 0" class="py-10 text-center text-sm text-gray-400">No tienes notificaciones ni pendientes.</li>
        </ul>
        </div>

        <a href="{{ route('notificaciones.index') }}" class="boton boton-secundario mt-3 w-full">
            Ver todas las notificaciones
        </a>
    </div>
</div>