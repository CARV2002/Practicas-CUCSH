import Alpine from 'alpinejs';

/**
 * Envío del expediente del alumno (kardex + IMSS): antes de mandar el formulario marca en su tarjeta
 * cada archivo obligatorio que falte y lleva la vista al primero. Mientras se envía, el botón queda ocupado.
 */
export default () => ({
    enviando: false,

    enviar(e) {
        let primero = null;

        this.$el.querySelectorAll('[data-zona-archivo]').forEach((zona) => {
            const input = zona.querySelector('input[type=file]');
            if (input.required && !input.files.length) {
                Alpine.$data(zona).error = 'Falta adjuntar este archivo.';
                primero ??= zona;
            }
        });

        if (primero) {
            e.preventDefault();
            primero.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        if (!this.$el.checkValidity()) {
            e.preventDefault();
            this.$el.reportValidity();
            return;
        }

        this.enviando = true;
    },
});
