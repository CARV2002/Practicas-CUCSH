import { conId } from './rutas';

/**
 * Programas y vacantes (Coordinación y Empresa): ventana para crear o editar un programa y, en Coordinación,
 * la ficha de revisión de una vacante de empresa (aprobar o rechazar con motivo).
 *
 * @param {object} opciones
 * @param {object}   opciones.vacio      campos de un programa nuevo
 * @param {object[]} opciones.convenios  convenios vigentes: { id, carreras: [id de carrera…], … }
 * @param {string}   opciones.urlStore   alta
 * @param {string}   opciones.urlUpdate  edición con id 0
 * @param {boolean}  opciones.preseleccionarConvenio  la empresa con un solo convenio no tiene que elegirlo
 */
export default ({ vacio = {}, convenios = [], urlStore = '', urlUpdate = '', preseleccionarConvenio = false } = {}) => ({
    modal: false,
    p: { ...vacio },
    vacio,
    convenios,

    nuevo() {
        const convenio = preseleccionarConvenio && this.convenios.length === 1 ? this.convenios[0].id : '';
        this.p = preseleccionarConvenio ? { ...this.vacio, convenio_id: convenio } : { ...this.vacio };
        this.modal = true;
    },

    editar(datos) {
        this.p = { ...this.vacio, ...datos };
        this.modal = true;
    },

    get action() {
        return this.p.id ? conId(urlUpdate, this.p.id) : urlStore;
    },

    /** Coordinación: convenios que autorizan la carrera elegida. */
    get conveniosDeCarrera() {
        if (!this.p.carrera_id) return this.convenios;
        return this.convenios.filter((c) => c.carreras.includes(Number(this.p.carrera_id)));
    },

    /** Empresa: carreras que autoriza el convenio elegido. */
    get carreras() {
        const c = this.convenios.find((c) => c.id == this.p.convenio_id);
        return c ? c.carreras : [];
    },

    // Ficha de revisión (Coordinación): toda la información que capturó la empresa
    detalle: null,
    motivo: '',

    ver(d) {
        this.detalle = d;
        this.motivo = '';
    },

    cerrarDetalle() {
        this.detalle = null;
        this.motivo = '';
    },
});
