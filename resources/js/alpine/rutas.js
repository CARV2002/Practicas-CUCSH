/**
 * Las vistas mandan las rutas con id 0 (route('...', 0)) y aquí se cambia por el del registro elegido.
 * El 0 puede ir al final (/profesores/0) o a la mitad (/usuarios/0/password): se cambia ese segmento.
 */
export const conId = (ruta, id) => ruta.replace(/\/0(?=\/|$)/, `/${id}`);
