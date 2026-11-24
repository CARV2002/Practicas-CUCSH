import { conId } from './rutas';

/**
 * Administración › Catálogos: ventanas para crear o editar divisiones y carreras.
 * El mismo formulario sirve para ambas cosas: con id se actualiza, sin id se crea.
 *
 * @param {object} rutas
 * @param {string} rutas.guardarDivision     alta de división
 * @param {string} rutas.actualizarDivision  edición con id 0
 * @param {string} rutas.guardarCarrera
 * @param {string} rutas.actualizarCarrera
 */
export default ({ guardarDivision = '', actualizarDivision = '', guardarCarrera = '', actualizarCarrera = '' } = {}) => ({
    modalDivision: false,
    modalCarrera: false,
    division: { id: null, nombre: '' },
    carrera: { id: null, division_id: '', carrera_clave: '', nombre: '' },

    nuevaDivision() {
        this.division = { id: null, nombre: '' };
        this.modalDivision = true;
    },

    editarDivision(d) {
        this.division = { ...d };
        this.modalDivision = true;
    },

    nuevaCarrera(divisionId = '') {
        this.carrera = { id: null, division_id: divisionId, carrera_clave: '', nombre: '' };
        this.modalCarrera = true;
    },

    editarCarrera(c) {
        this.carrera = { ...c };
        this.modalCarrera = true;
    },

    get actionDivision() {
        return this.division.id ? conId(actualizarDivision, this.division.id) : guardarDivision;
    },

    get actionCarrera() {
        return this.carrera.id ? conId(actualizarCarrera, this.carrera.id) : guardarCarrera;
    },
});
