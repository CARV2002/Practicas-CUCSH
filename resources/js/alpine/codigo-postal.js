/**
 * Domicilio por código postal (catálogo local de SEPOMEX, GET /api/cp/{cp}). El orden es: primero el CP.
 *
 *  - Colonia, municipio y estado quedan bloqueados hasta que el CP tenga 5 números y termine la búsqueda:
 *    así nunca se capturan datos que luego el CP contradiga.
 *  - CP encontrado: estado y municipio salen del catálogo (fijos); la colonia se elige de la lista
 *    o, si no aparece, «Otra» permite escribirla.
 *  - CP no encontrado (o sin catálogo): los tres se escriben a mano.
 *  - Cambiar el CP limpia colonia, municipio y estado (dependían del anterior).
 *  - Sin congelarse: cada búsqueda cancela la anterior y se descarta la respuesta de un CP que ya cambió;
 *    el campo del CP nunca se bloquea.
 *
 * @param {object} opciones
 * @param {string} opciones.url        base del endpoint, p. ej. /api/cp
 * @param {string} opciones.cp         valores guardados (se respetan al abrir el formulario)
 * @param {string} opciones.colonia
 * @param {string} opciones.municipio
 * @param {string} opciones.entidad
 */
const AYUDA = 'Empieza aquí: escribe los 5 números y llenamos el resto.';

export default ({ url = '/api/cp', cp = '', colonia = '', municipio = '', entidad = '' } = {}) => ({
    cp: cp || '',
    colonia: colonia || '',
    municipio: municipio || '',
    entidad: entidad || '',
    colonias: [],
    cargando: false,
    encontrado: false,    // el CP está en el catálogo: estado y municipio vienen de ahí
    coloniaLibre: false,  // la colonia se escribe (no está en la lista o el CP no se encontró)
    buscado: null,        // CP con búsqueda terminada
    mensaje: AYUDA,
    error: false,
    controlador: null,

    /** Colonia, municipio y estado esperan al CP. */
    get bloqueado() {
        return this.buscado === null || this.buscado !== this.cp;
    },

    get aviso() {
        return this.bloqueado ? 'Primero escribe tu código postal.' : '';
    },

    init() {
        // Al abrir con un CP guardado se consulta para ofrecer la lista, conservando lo que ya escribió
        if (/^\d{5}$/.test(this.cp)) this.buscar(true);
    },

    /** En cada tecla del campo CP. */
    alCambiarCp() {
        if (this.cp === this.buscado) return;

        // Lo que se llenó para el CP anterior ya no sirve
        if (this.buscado !== null) {
            this.colonias = [];
            this.colonia = '';
            this.municipio = '';
            this.entidad = '';
            this.encontrado = false;
            this.coloniaLibre = false;
            this.buscado = null;
        }

        if (/^\d{5}$/.test(this.cp)) {
            this.buscar();
        } else {
            this.controlador?.abort();
            this.cargando = false;
            this.error = false;
            this.mensaje = AYUDA;
        }
    },

    async buscar(conservar = false) {
        this.controlador?.abort();
        const controlador = new AbortController();
        this.controlador = controlador;
        const cp = this.cp;
        this.cargando = true;
        this.error = false;
        this.mensaje = 'Buscando el código postal…';

        try {
            const r = await fetch(`${url}/${cp}`, { headers: { Accept: 'application/json' }, signal: controlador.signal });
            const d = await r.json().catch(() => ({}));
            if (cp !== this.cp) return;   // respuesta vieja: el usuario ya cambió el CP

            if (!r.ok) {
                this.colonias = [];
                this.encontrado = false;
                this.coloniaLibre = true;
                this.error = r.status !== 503;
                this.mensaje = r.status === 404
                    ? 'No encontramos ese código postal. Si es correcto, escribe tu colonia, municipio y estado.'
                    : (d.mensaje || 'Escribe tu colonia, municipio y estado.');
                this.buscado = cp;
                return;
            }

            // Estado y municipio siempre los del catálogo: así coinciden con el CP
            this.colonias = d.colonias || [];
            this.entidad = d.estado || '';
            this.municipio = d.municipio || '';
            this.encontrado = true;

            if (conservar && this.colonia !== '' && !this.colonias.includes(this.colonia)) {
                this.coloniaLibre = true;   // la escribió a mano antes: se respeta como «otra»
            } else {
                this.coloniaLibre = false;
                if (!this.colonias.includes(this.colonia)) this.colonia = this.colonias.length === 1 ? this.colonias[0] : '';
            }
            this.mensaje = this.colonias.length === 1 ? '✓ Encontramos tu colonia, municipio y estado.' : `✓ ${this.colonias.length} colonias: elige la tuya.`;
            this.buscado = cp;
        } catch (e) {
            if (e.name === 'AbortError') return;
            this.encontrado = false;
            this.coloniaLibre = true;
            this.mensaje = 'No pudimos buscar el código postal. Escribe tu colonia, municipio y estado.';
            this.buscado = cp;
        } finally {
            if (this.controlador === controlador) {
                this.cargando = false;
                this.controlador = null;
            }
        }
    },

    /** «Otra colonia»: la lista se cambia por un campo para escribirla. */
    elegirOtra() {
        this.coloniaLibre = true;
        this.colonia = '';
        this.$nextTick(() => document.getElementById('g-colonia')?.focus());
    },
});
