/**
 * Menú lateral: un solo submenú abierto a la vez y, al cargar, abierto el que contiene la página actual.
 *
 * @param {object} opciones
 * @param {string} opciones.actual   ruta actual sin la diagonal inicial (request()->path())
 * @param {Object<string, string[]>} opciones.submenus  "grupo-item" => rutas de sus opciones
 */
export default ({ actual = '', submenus = {} } = {}) => ({
    openSubmenus: {},

    init() {
        for (const [clave, rutas] of Object.entries(submenus)) {
            if (rutas.some((ruta) => actual === ruta.replace(/^\//, '') || window.location.pathname === ruta)) {
                this.openSubmenus[clave] = true;
            }
        }
    },

    toggleSubmenu(grupo, item) {
        const clave = `${grupo}-${item}`;
        const abrir = !this.openSubmenus[clave];

        // Al abrir uno se cierran los demás
        if (abrir) this.openSubmenus = {};
        this.openSubmenus[clave] = abrir;
    },

    isSubmenuOpen(grupo, item) {
        return this.openSubmenus[`${grupo}-${item}`] || false;
    },

    isActive(ruta) {
        return window.location.pathname === ruta || actual === ruta.replace(/^\//, '');
    },
});
