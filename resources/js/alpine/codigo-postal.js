/**
 * Domicilio por código postal: al escribir los 5 dígitos se consultan sus colonias y su estado
 * (servidor → servicio público de códigos postales, con caché). Si no hay respuesta, se captura a mano.
 */
export default (cpInicial = '', coloniaInicial = '', entidadInicial = '') => ({
    cp: cpInicial || '',
    colonia: coloniaInicial || '',
    entidad: entidadInicial || '',
    colonias: [],
    mensaje: 'Escríbelo y llenamos colonia y estado.',

    init() {
        if (/^\d{5}$/.test(this.cp)) this.buscar(true);
    },

    async buscar(silencioso = false) {
        if (!/^\d{5}$/.test(this.cp)) {
            this.colonias = [];
            this.mensaje = 'Escríbelo y llenamos colonia y estado.';
            return;
        }
        this.mensaje = 'Buscando el código postal…';
        try {
            const r = await fetch(`/empresa/codigo-postal/${this.cp}`, { headers: { Accept: 'application/json' } });
            const d = await r.json();
            this.colonias = d.colonias || [];
            if (d.estado && (!silencioso || !this.entidad)) this.entidad = d.estado;
            if (this.colonias.length === 1 && !this.colonia) this.colonia = this.colonias[0];
            this.mensaje = d.sin_servicio
                ? 'No pudimos consultar el código postal: escribe colonia y estado.'
                : (this.colonias.length
                    ? `${this.colonias.length} ${this.colonias.length === 1 ? 'colonia encontrada' : 'colonias: elige la tuya en la lista'}.`
                    : 'No encontramos ese código postal: revisa los 5 números o escribe tu colonia.');
        } catch {
            this.mensaje = 'No pudimos consultar el código postal: escribe colonia y estado.';
        }
    },
});
