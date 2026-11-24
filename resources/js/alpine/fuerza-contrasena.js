/**
 * Medidor de la contraseña nueva: muestra qué reglas ya cumple (las mismas que valida el servidor).
 */
export default () => ({
    nueva: '',
    ver: false,

    get reglas() {
        const n = this.nueva;

        return [
            { t: 'Al menos 8 caracteres', ok: n.length >= 8 },
            { t: 'Una mayúscula y una minúscula', ok: /[A-ZÁÉÍÓÚÑ]/.test(n) && /[a-záéíóúñ]/.test(n) },
            { t: 'Un número', ok: /\d/.test(n) },
            { t: 'Un símbolo', ok: /[^A-Za-z0-9ÁÉÍÓÚÑáéíóúñ]/.test(n) },
        ];
    },

    get fuerza() {
        return this.reglas.filter((r) => r.ok).length;
    },
});
