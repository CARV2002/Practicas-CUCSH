import { conId } from './rutas';

/**
 * Ventana «Baja o cambio» de Coordinación › Practicantes. Antes de confirmar explica qué pasará con las horas
 * del alumno y si quedará bloqueado; la decisión final la toma el servidor (App\Actions\DarBajaPractica).
 *
 * Se abre con el evento «abrir-baja» y los datos de la práctica: { id, nombre, horas, bajas }.
 *
 * @param {object} opciones
 * @param {string[]} opciones.sanciones  motivos que cuentan como sanción (Practica::MOTIVOS_SANCION)
 * @param {number}   opciones.limite     bajas permitidas antes del bloqueo (Alumno::LIMITE_BAJAS)
 * @param {string}   opciones.url        ruta de baja con id 0 (se cambia por el de la práctica)
 */
export default ({ sanciones = [], limite = 2, url = '' } = {}) => ({
    abierto: false,
    p: { id: 0, nombre: '', horas: 0, bajas: 0 },
    motivo: 'cierre_empresa',
    sanciones,
    limite,
    url,

    /** Ruta de la baja de esta práctica (/coordinacion/practicas/{id}/cancelar). */
    get accion() {
        return conId(this.url, this.p.id);
    },

    /** sancion | parcial (conserva horas validadas) | sin_reportes */
    get tipo() {
        if (this.sanciones.includes(this.motivo)) return 'sancion';
        return this.p.horas > 0 ? 'parcial' : 'sin_reportes';
    },

    get bloquea() {
        return this.tipo === 'sancion' || this.p.bajas + 1 >= this.limite;
    },

    get explicacion() {
        return {
            parcial: `Conserva sus ${this.p.horas} h validadas: en su siguiente plaza solo cubrirá las horas restantes.`,
            sin_reportes: 'No tiene reportes validados: sus horas no se conservan y deberá iniciar un trámite nuevo.',
            sancion: `Se invalidan todas sus horas (${this.p.horas} h), aunque tenga reportes firmados.`,
        }[this.tipo];
    },

    get consecuencia() {
        if (this.tipo === 'sancion') return 'El alumno quedará bloqueado hasta que levantes la restricción.';

        return `Será su baja número ${this.p.bajas + 1} de ${this.limite}`
            + (this.bloquea ? ': alcanza el límite y quedará bloqueado para postularse.' : '. Podrá postularse de nuevo.');
    },
});
