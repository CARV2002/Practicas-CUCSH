import { conId } from './rutas';

/**
 * Administración › Usuarios: alta de cuentas de personal y cambio de contraseña.
 *
 * @param {object} opciones
 * @param {boolean} opciones.abrirAlta   reabrir el alta si el envío regresó con errores
 * @param {string}  opciones.rol         rol elegido (old('rol') o el primero)
 * @param {string}  opciones.rolInicial  rol con el que empieza un alta limpia
 * @param {string}  opciones.alcance     'carrera' o 'division'
 * @param {string}  opciones.urlPass     ruta de cambio de contraseña con id 0 (/admin/usuarios/0/password)
 */
export default ({ abrirAlta = false, rol = '', rolInicial = '', alcance = 'carrera', urlPass = '' } = {}) => ({
    modalAlta: abrirAlta,
    rolAlta: rol,
    alcance,

    /** Cancelar o cerrar el alta borra lo capturado y los avisos de validación, para empezar limpio. */
    cerrarAlta() {
        const f = this.$refs.formAlta;
        f.querySelectorAll('input[type=text], input[type=email]').forEach((i) => { i.value = ''; i.removeAttribute('aria-invalid'); });
        f.querySelectorAll('select').forEach((s) => { s.value = ''; });
        f.querySelectorAll('.js-mensaje-campo, .js-aviso-formulario').forEach((p) => p.remove());
        this.rolAlta = rolInicial;
        this.alcance = 'carrera';
        this.modalAlta = false;
    },

    modalPass: false,
    pass: { id: null, nombre: '' },

    cambiar(id, nombre) {
        this.pass = { id, nombre };
        this.modalPass = true;
    },

    get actionPass() {
        return conId(urlPass, this.pass.id);
    },
});
