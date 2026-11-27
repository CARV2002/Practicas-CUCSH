/**
 * Lógica de la tarjeta x-ui.carga-documento: arrastrar y soltar, validación de tipo y peso
 * antes de enviar (el servidor rechaza envíos mayores a post_max_size) y estado «Enviando…».
 */
const peso = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

function tipoPermitido(archivo, accept) {
    const reglas = (accept || '').split(',').map((r) => r.trim().toLowerCase()).filter(Boolean);
    if (!reglas.length) return true;

    const nombre = archivo.name.toLowerCase();
    const tipo = (archivo.type || '').toLowerCase();

    return reglas.some((r) => (r.startsWith('.')
        ? nombre.endsWith(r)
        : r.endsWith('/*') ? tipo.startsWith(r.slice(0, -1)) : r === tipo));
}

export default function cargaDocumento({ maxMb = 10, accept = '', formatos = 'PDF' } = {}) {
    return {
        archivo: null,
        error: '',
        arrastrando: false,
        enviando: false,

        elegir(f) {
            this.error = '';
            this.archivo = null;
            if (!f) {
                this.avisar();
                return;
            }

            if (!tipoPermitido(f, accept)) {
                this.error = `«${f.name}» no es un ${formatos}. Elige el archivo en ${formatos}.`;
                this.$refs.entrada.value = '';
                this.avisar();
                return;
            }
            if (f.size > maxMb * 1048576) {
                this.error = `El archivo pesa ${peso(f.size)} y el máximo es ${maxMb} MB. Comprímelo (por ejemplo en www.ejemplo.com) e inténtalo de nuevo.`;
                this.$refs.entrada.value = '';
                this.avisar();
                return;
            }
            this.archivo = { nombre: f.name, peso: peso(f.size) };
            this.avisar();
        },

        /** Avisa al formulario si ya hay un archivo válido (p. ej. para habilitar «Registrar»). */
        avisar() {
            this.$dispatch('archivo-cambio', { valido: this.archivo !== null, nombre: this.$refs.entrada?.name });
        },

        limpiar() {
            this.archivo = null;
            this.error = '';
            this.avisar();
        },

        soltar(e) {
            this.arrastrando = false;
            const f = e.dataTransfer?.files?.[0];
            if (!f) return;
            // Pasa el archivo soltado al input real para que se envíe con el formulario
            const dt = new DataTransfer();
            dt.items.add(f);
            this.$refs.entrada.files = dt.files;
            this.elegir(f);
        },

        enviar(e) {
            if (!this.archivo) {
                e.preventDefault();
                this.error = 'Primero elige el archivo que vas a enviar.';
                return;
            }
            // Revisa los demás campos requeridos (p. ej. la fecha) con los mensajes del navegador
            const form = e.target;
            const invalido = [...form.querySelectorAll('input:not([type=file]),select,textarea')].find((c) => !c.checkValidity());
            if (invalido) {
                e.preventDefault();
                invalido.reportValidity();
                return;
            }
            this.enviando = true;
        },
    };
}

/**
 * Las zonas de archivo usan un input oculto: el aviso nativo del navegador («completa este campo»)
 * apuntaría a un elemento invisible. Lo interceptamos y mostramos el mensaje en la zona misma.
 */
export function iniciarZonasArchivo() {
    document.addEventListener('invalid', (e) => {
        const zona = e.target.closest?.('[data-zona-archivo]');
        if (!zona || e.target.type !== 'file') return;

        e.preventDefault();
        const datos = window.Alpine?.$data(zona);
        if (datos) datos.error = 'Falta adjuntar este archivo.';

        // Solo desplaza hacia la primera zona inválida del formulario
        const primera = e.target.form?.querySelector('[data-zona-archivo] input[type=file]:invalid');
        if (primera === e.target) zona.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, true);
}
