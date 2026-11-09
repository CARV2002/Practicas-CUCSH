<div class="relative" x-data="{
    abierto: false,
    cargando: false,
    noLeidas: 0,
    items: [],
    async cargar() {
        this.cargando = true;
        try {
            const r = await fetch('{{ route('notificaciones.resumen') }}', { headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            this.noLeidas = d.no_leidas;
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
        class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700">
        <span x-show="noLeidas > 0" x-cloak
            class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-orange-400 px-1 text-[10px] font-bold text-white"
            x-text="noLeidas > 9 ? '9+' : noLeidas"></span>
        <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z" fill=""/>
        </svg>
    </button>

    <div x-show="abierto" x-cloak x-transition
        class="absolute -right-[240px] z-50 mt-[17px] flex max-h-[480px] w-[350px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg sm:w-[361px] lg:right-0">
        <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-3">
            <h5 class="text-lg font-semibold text-gray-800">Notificaciones</h5>
            <button type="button" @click="leerTodas()" x-show="noLeidas > 0" class="text-brand-600 text-xs font-semibold hover:underline">Marcar todas</button>
        </div>

        <ul class="custom-scrollbar flex-1 overflow-y-auto">
            <template x-for="n in items" :key="n.id">
                <li>
                    <a :href="n.url" class="flex gap-3 rounded-lg border-b border-gray-100 px-3 py-3 hover:bg-gray-100">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.leida ? 'bg-transparent' : 'bg-brand-500'"></span>
                        <span class="block">
                            <span class="block text-sm" :class="n.leida ? 'text-gray-500' : 'font-medium text-gray-800'" x-text="n.mensaje"></span>
                            <span class="mt-1 block text-xs text-gray-400">
                                <template x-if="n.detalle"><span x-text="n.detalle + ' · '"></span></template><span x-text="n.hace"></span>
                            </span>
                        </span>
                    </a>
                </li>
            </template>
            <li x-show="!cargando && items.length === 0" class="py-10 text-center text-sm text-gray-400">No tienes notificaciones.</li>
        </ul>

        <a href="{{ route('notificaciones.index') }}"
            class="mt-3 flex justify-center rounded-lg border border-gray-300 bg-white p-3 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Ver todas las notificaciones
        </a>
    </div>
</div>