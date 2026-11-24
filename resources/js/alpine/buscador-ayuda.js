/**
 * Centro de ayuda: filtra las preguntas por categoría y por texto (sin importar acentos ni mayúsculas).
 *
 * @param {object} opciones
 * @param {{pregunta: string, respuesta: string, categoria: string}[]} opciones.temas
 */
export default ({ temas = [] } = {}) => ({
    q: '',
    cat: '',
    abierta: null,
    temas,

    /** «Constancia» y «constáncia» se comparan igual. */
    norm(t) {
        return (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    },

    visible(i) {
        const t = this.temas[i];
        const q = this.norm(this.q.trim());

        return (this.cat === '' || t.categoria === this.cat)
            && (q === '' || this.norm(`${t.pregunta} ${t.respuesta} ${t.categoria}`).includes(q));
    },

    get total() {
        return this.temas.filter((_, i) => this.visible(i)).length;
    },

    buscar() {
        this.abierta = null;
        if (this.q.trim() !== '') {
            this.$nextTick(() => document.getElementById('preguntas')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        }
    },
});
