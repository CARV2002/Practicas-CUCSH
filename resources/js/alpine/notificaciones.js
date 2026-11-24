/**
 * Campana de notificaciones del encabezado: cuenta las no leídas y muestra las recientes.
 * Se actualiza al abrirla y cada minuto.
 *
 * @param {object} rutas
 * @param {string} rutas.resumen     JSON con { no_leidas, items }
 * @param {string} rutas.leerTodas   POST que las marca todas como leídas
 */
export default ({ resumen = '', leerTodas = '' } = {}) => ({
    abierto: false,
    cargando: false,
    noLeidas: 0,
    items: [],

    init() {
        this.cargar();
        setInterval(() => this.cargar(), 60000);
    },

    async cargar() {
        this.cargando = true;
        try {
            const r = await fetch(resumen, { headers: { Accept: 'application/json' } });
            const d = await r.json();
            this.noLeidas = d.no_leidas;
            this.items = d.items;
        } finally {
            this.cargando = false;
        }
    },

    async toggle() {
        this.abierto = !this.abierto;
        if (this.abierto) await this.cargar();
    },

    async leerTodas() {
        await fetch(leerTodas, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        });
        await this.cargar();
    },
});
