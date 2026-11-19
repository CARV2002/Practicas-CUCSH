/**
 * Calendario mensual de regresos de documentos físicos (Vinculación).
 * Recibe [{ fecha: 'YYYY-MM-DD', papel, empresa, custodio, estado, tono, url }].
 */
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export default function calendarioRegresos(eventos = []) {
    const hoy = new Date();

    return {
        eventos,
        abierto: false,
        anio: hoy.getFullYear(),
        mes: hoy.getMonth(),
        hoyIso: iso(hoy),
        seleccion: null,

        abrir() {
            // Abre en el mes del regreso más urgente, si existe
            const primero = this.eventos[0]?.fecha;
            if (primero) {
                const [a, m] = primero.split('-').map(Number);
                this.anio = a;
                this.mes = m - 1;
                this.seleccion = primero;
            } else {
                this.seleccion = this.hoyIso;
            }
            this.abierto = true;
        },

        mover(delta) {
            const d = new Date(this.anio, this.mes + delta, 1);
            this.anio = d.getFullYear();
            this.mes = d.getMonth();
        },

        hoy() {
            this.anio = hoy.getFullYear();
            this.mes = hoy.getMonth();
            this.seleccion = this.hoyIso;
        },

        get tituloMes() {
            return new Date(this.anio, this.mes, 1).toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });
        },

        /** Celdas de lunes a domingo, con huecos al inicio del mes. */
        get celdas() {
            const primero = new Date(this.anio, this.mes, 1);
            const hueco = (primero.getDay() + 6) % 7;
            const dias = new Date(this.anio, this.mes + 1, 0).getDate();
            const celdas = [];

            for (let i = 0; i < hueco; i++) {
                celdas.push({ clave: `h${i}`, fecha: null, dia: '', eventos: [] });
            }
            for (let d = 1; d <= dias; d++) {
                const fecha = iso(new Date(this.anio, this.mes, d));
                celdas.push({ clave: fecha, fecha, dia: d, eventos: this.eventosDe(fecha) });
            }

            return celdas;
        },

        eventosDe(fecha) {
            return fecha ? this.eventos.filter((e) => e.fecha === fecha) : [];
        },

        fechaLarga(fecha) {
            const [a, m, d] = fecha.split('-').map(Number);
            return new Date(a, m - 1, d).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
        },
    };
}
