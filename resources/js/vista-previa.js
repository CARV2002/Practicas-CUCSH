/**
 * Vista previa de documentos.
 * Todo enlace a un archivo del sistema (rutas …/archivos/{id}, …/documentos/{id}, …/convenio/{id}/archivo)
 * abre una ventana con el documento antes de descargarlo. El servidor responde a ?ver=1 con el PDF o la
 * imagen en línea, o con una ficha de descarga para Word. Ctrl/Cmd+clic o el atributo data-sin-vista descargan directo.
 */
const PATRON = /\/(archivos|documentos)\/\d+$|\/convenio\/\d+\/archivo$|\/reportes\/\d+\/formato$/;

const OJO = '<svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';

function esDocumento(enlace) {
    if (!(enlace instanceof HTMLAnchorElement) || !enlace.href) return false;
    let url;
    try { url = new URL(enlace.href, window.location.origin); } catch { return false; }
    return url.origin === window.location.origin && PATRON.test(url.pathname);
}

/**
 * Junto a cada enlace a un documento se agrega «Ver documento» (abre la vista previa) y el enlace original
 * queda como descarga directa: así se distingue ver de descargar en todo el sistema.
 */
function agregarBotonesVer(raiz = document) {
    raiz.querySelectorAll?.('a[href]:not([data-con-ver]):not([data-sin-boton-ver])').forEach((enlace) => {
        if (!esDocumento(enlace) || enlace.closest('[role="dialog"][aria-labelledby="vista-previa-titulo"]')) return;
        enlace.dataset.conVer = '1';
        enlace.dataset.sinVista = '';
        if (!enlace.title) enlace.title = 'Descargar';

        const ver = document.createElement('button');
        ver.type = 'button';
        ver.className = 'js-ver-documento ml-1.5 inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-udg-50 px-2 py-0.5 align-middle text-xs font-semibold text-udg-800 ring-1 ring-inset ring-udg-100 transition duration-150 hover:-translate-y-px hover:bg-udg-800 hover:text-white hover:ring-udg-800 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40 motion-reduce:transform-none';
        ver.innerHTML = `${OJO}<span>Ver documento</span>`;
        ver.setAttribute('aria-label', `Ver documento: ${enlace.textContent.trim().replace(/\s+/g, ' ') || 'archivo'}`);
        ver.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); abrir(enlace); });
        enlace.insertAdjacentElement('afterend', ver);
    });
}

let modal = null;
let ultimoFoco = null;

function construir() {
    modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-[999999] hidden items-center justify-center p-2 sm:p-6';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'vista-previa-titulo');
    modal.innerHTML = `
        <div data-cerrar class="absolute inset-0 bg-udg-950/60 backdrop-blur-sm"></div>
        <div class="relative flex h-full max-h-[92vh] w-full max-w-5xl animate-entrada flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl motion-reduce:animate-none">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 sm:px-5">
                <p id="vista-previa-titulo" class="min-w-0 flex-1 truncate font-display text-sm font-bold text-gray-900 sm:text-base"></p>
                <div class="flex items-center gap-2">
                    <a data-pestana target="_blank" rel="noopener" class="boton boton-chico boton-secundario">Abrir en pestaña</a>
                    <a data-descargar class="boton boton-chico boton-primario">Descargar</a>
                    <button type="button" data-cerrar class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800" aria-label="Cerrar vista previa">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
            </div>
            <div class="relative flex-1 bg-gray-100">
                <p data-cargando class="absolute inset-0 grid place-items-center text-sm text-gray-500">Cargando documento…</p>
                <iframe class="relative h-full w-full bg-white" title="Vista previa del documento"></iframe>
            </div>
        </div>`;
    document.body.appendChild(modal);

    modal.addEventListener('click', (e) => {
        if (e.target.closest('[data-cerrar]')) cerrar();
    });
    modal.querySelector('iframe').addEventListener('load', () => modal.querySelector('[data-cargando]').classList.add('hidden'));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) cerrar();
    });
}

function abrir(enlace) {
    if (!modal) construir();
    ultimoFoco = document.activeElement;

    const url = new URL(enlace.href, window.location.origin);
    const vista = new URL(url);
    vista.searchParams.set('ver', '1');

    const titulo = enlace.dataset.nombre || enlace.textContent.trim().replace(/\s+/g, ' ') || 'Documento';
    modal.querySelector('#vista-previa-titulo').textContent = titulo;
    modal.querySelector('[data-descargar]').href = url.toString();
    modal.querySelector('[data-pestana]').href = vista.toString();
    modal.querySelector('[data-cargando]').classList.remove('hidden');
    modal.querySelector('iframe').src = vista.toString();

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.documentElement.style.overflow = 'hidden';
    modal.querySelector('[data-cerrar][aria-label]').focus();
}

function cerrar() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.querySelector('iframe').src = 'about:blank';
    document.documentElement.style.overflow = '';
    ultimoFoco?.focus?.();
}

export function iniciarVistaPrevia() {
    document.addEventListener('click', (e) => {
        const enlace = e.target.closest('a[href]');
        if (!enlace || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (enlace.hasAttribute('download') || enlace.dataset.sinVista !== undefined || enlace.target === '_blank') return;
        if (enlace.closest('[role="dialog"]') === modal && modal) return;

        let url;
        try { url = new URL(enlace.href, window.location.origin); } catch { return; }
        if (url.origin !== window.location.origin || !PATRON.test(url.pathname)) return;

        e.preventDefault();
        abrir(enlace);
    });

    // Botones «Ver documento»: al cargar y cuando aparecen enlaces nuevos (pestañas, ventanas, x-if)
    agregarBotonesVer();
    let pendiente = false;
    new MutationObserver(() => {
        if (pendiente) return;
        pendiente = true;
        requestAnimationFrame(() => { pendiente = false; agregarBotonesVer(); });
    }).observe(document.body, { childList: true, subtree: true });
}
