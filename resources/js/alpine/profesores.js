import { conId } from './rutas';

/**
 * Coordinación › Profesores supervisores: ventana para dar de alta o editar un profesor.
 *
 * @param {object} opciones
 * @param {boolean} opciones.abierto    reabrir la ventana si el envío regresó con errores
 * @param {object}  opciones.inicial    lo capturado (old()) o un profesor vacío
 * @param {object}  opciones.vacio      profesor nuevo
 * @param {string}  opciones.urlStore
 * @param {string}  opciones.urlUpdate  edición con id 0
 */
export default ({ abierto = false, inicial = {}, vacio = {}, urlStore = '', urlUpdate = '' } = {}) => ({
    modal: abierto,
    p: { ...inicial },
    vacio,

    nuevo() {
        this.p = { ...this.vacio };
        this.modal = true;
    },

    editar(datos) {
        this.p = { ...datos };
        this.modal = true;
    },

    /** Cancelar limpia lo capturado. */
    cerrar() {
        this.p = { ...this.vacio };
        this.modal = false;
    },

    get accion() {
        return this.p.id ? conId(urlUpdate, this.p.id) : urlStore;
    },
});
