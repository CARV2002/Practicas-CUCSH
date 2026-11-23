/**
 * Confirmación antes de acciones delicadas (eliminar, desactivar, rechazar, cancelar…), en todo el sistema.
 *
 * Uso en Blade:
 *   <form data-confirmar="¿Eliminar este programa? No se puede deshacer."
 *         data-confirmar-titulo="Eliminar programa"      (opcional)
 *         data-confirmar-boton="Sí, eliminar"           (opcional)
 *         data-confirmar-tono="peligro|aviso|normal">   (opcional; por defecto peligro)
 *   También funciona en el botón de envío (data-confirmar en el <button type="submit">) y en enlaces <a>.
 *
 * Sustituye al confirm() del navegador: un diálogo propio, en español, accesible (foco atrapado, Esc cancela)
 * y con el mismo estilo del sistema. Solo se pregunta cuando el formulario ya es válido.
 */

const TONOS = {
    peligro: { icono: 'bg-rose-50 text-rose-600 ring-rose-100', boton: 'bg-rose-600 hover:bg-rose-500 focus-visible:ring-rose-500/30', titulo: 'Confirma la acción', aceptar: 'Sí, continuar' },
    aviso: { icono: 'bg-amber-50 text-amber-600 ring-amber-100', boton: 'bg-amber-600 hover:bg-amber-500 focus-visible:ring-amber-500/30', titulo: 'Antes de continuar', aceptar: 'Sí, continuar' },
    normal: { icono: 'bg-udg-50 text-udg-800 ring-udg-100', boton: 'bg-udg-800 hover:bg-udg-700 focus-visible:ring-real-500/30', titulo: 'Confirma la acción', aceptar: 'Continuar' },
};

const ICONOS = {
    peligro: '<path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
    aviso: '<circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>',
    normal: '<circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/>',
};

/** Palabras que convierten la acción en «peligro» si no se indica el tono. */
const PALABRAS_PELIGRO = /eliminar|borrar|quitar|desactivar|rechazar|cancelar|baja|revocar|devolver|cerrar/i;

let dialogo = null;

function construir() {
    const el = document.createElement('div');
    el.className = 'js-confirmar fixed inset-0 z-[100100] hidden items-center justify-center p-4';
    el.setAttribute('role', 'alertdialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'confirmar-titulo');
    el.setAttribute('aria-describedby', 'confirmar-texto');
    el.innerHTML = `
        <div data-fondo class="absolute inset-0 bg-udg-950/50 opacity-0 backdrop-blur-sm transition-opacity duration-200"></div>
        <div data-panel class="relative w-full max-w-md translate-y-2 scale-95 rounded-2xl bg-white p-6 opacity-0 shadow-theme-xl transition duration-200 ease-out motion-reduce:transition-none">
            <div class="flex items-start gap-4">
                <span data-icono class="flex size-11 shrink-0 items-center justify-center rounded-full ring-8">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"></svg>
                </span>
                <div class="min-w-0 pt-0.5">
                    <h3 id="confirmar-titulo" class="font-display text-lg font-bold text-gray-900"></h3>
                    <p id="confirmar-texto" class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-gray-600"></p>
                </div>
            </div>
            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" data-cancelar class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 shadow-theme-xs transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25">Cancelar</button>
                <button type="button" data-aceptar class="inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white shadow-theme-sm transition active:scale-[.98] focus-visible:outline-none focus-visible:ring-4"></button>
            </div>
        </div>`;
    document.body.appendChild(el);
    return el;
}

/** Abre el diálogo; resuelve true si el usuario acepta. */
export function confirmar({ texto, titulo, boton, tono }) {
    dialogo ??= construir();
    tono = TONOS[tono] ? tono : (PALABRAS_PELIGRO.test(`${texto} ${boton ?? ''}`) ? 'peligro' : 'normal');
    const t = TONOS[tono];

    dialogo.querySelector('#confirmar-titulo').textContent = titulo || t.titulo;
    dialogo.querySelector('#confirmar-texto').textContent = texto;
    dialogo.querySelector('[data-icono]').className = `flex size-11 shrink-0 items-center justify-center rounded-full ring-8 ${t.icono}`;
    dialogo.querySelector('[data-icono] svg').innerHTML = ICONOS[tono];
    const aceptar = dialogo.querySelector('[data-aceptar]');
    aceptar.textContent = boton || t.aceptar;
    aceptar.className = `inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white shadow-theme-sm transition active:scale-[.98] focus-visible:outline-none focus-visible:ring-4 ${t.boton}`;

    const anterior = document.activeElement;
    dialogo.classList.remove('hidden');
    dialogo.classList.add('flex');
    requestAnimationFrame(() => {
        dialogo.querySelector('[data-fondo]').classList.replace('opacity-0', 'opacity-100');
        const panel = dialogo.querySelector('[data-panel]');
        panel.classList.remove('translate-y-2', 'scale-95', 'opacity-0');
        // En acciones de peligro el foco inicial va a «Cancelar» (lo seguro)
        (tono === 'peligro' ? dialogo.querySelector('[data-cancelar]') : aceptar).focus();
    });

    return new Promise((resolver) => {
        const cerrar = (respuesta) => {
            dialogo.querySelector('[data-fondo]').classList.replace('opacity-100', 'opacity-0');
            dialogo.querySelector('[data-panel]').classList.add('translate-y-2', 'scale-95', 'opacity-0');
            setTimeout(() => { dialogo.classList.add('hidden'); dialogo.classList.remove('flex'); }, 180);
            document.removeEventListener('keydown', teclado, true);
            dialogo.onclick = null;
            if (anterior instanceof HTMLElement && !respuesta) anterior.focus();
            resolver(respuesta);
        };
        const teclado = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); cerrar(false); }
            if (e.key === 'Tab') {
                // Foco atrapado entre los dos botones
                const botones = [dialogo.querySelector('[data-cancelar]'), aceptar];
                const i = botones.indexOf(document.activeElement);
                e.preventDefault();
                botones[(i + (e.shiftKey ? -1 : 1) + botones.length) % botones.length].focus();
            }
        };
        document.addEventListener('keydown', teclado, true);
        dialogo.onclick = (e) => {
            if (e.target.closest('[data-aceptar]')) cerrar(true);
            else if (e.target.closest('[data-cancelar]') || e.target.matches('[data-fondo]')) cerrar(false);
        };
    });
}

function opcionesDe(el) {
    return {
        texto: el.dataset.confirmar,
        titulo: el.dataset.confirmarTitulo,
        boton: el.dataset.confirmarBoton,
        tono: el.dataset.confirmarTono,
    };
}

function iniciar() {
    // Formularios: se pregunta antes de que corra cualquier otro manejador de envío
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const origen = e.submitter?.dataset.confirmar ? e.submitter : (form.dataset.confirmar ? form : null);
        if (!origen || form.dataset.confirmado === '1') {
            delete form.dataset.confirmado;
            return;
        }
        // Si el formulario aún tiene errores, primero se corrigen (lo maneja validacion.js)
        if (!form.noValidate && !form.checkValidity()) return;

        e.preventDefault();
        e.stopImmediatePropagation();
        const submitter = e.submitter;
        confirmar(opcionesDe(origen)).then((ok) => {
            if (!ok) return;
            form.dataset.confirmado = '1';
            form.requestSubmit(submitter && form.contains(submitter) ? submitter : undefined);
        });
    }, true);

    // Enlaces y botones sueltos (no de envío)
    document.addEventListener('click', (e) => {
        const el = e.target.closest('a[data-confirmar], button[type="button"][data-confirmar]');
        if (!el || el.dataset.confirmado === '1') { if (el) delete el.dataset.confirmado; return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        confirmar(opcionesDe(el)).then((ok) => {
            if (!ok) return;
            el.dataset.confirmado = '1';
            el.click();
        });
    }, true);
}

window.confirmarAccion = confirmar;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
} else {
    iniciar();
}
