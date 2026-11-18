/**
 * Tablas del sitio (.tabla): en celular cada fila se muestra como tarjeta y cada celda necesita
 * su etiqueta. Aquí se toma del <th> de su columna (respetando colspan) y se guarda en data-label.
 * Si una celda ya trae data-label, se respeta.
 */
function etiquetar(tabla) {
    const encabezado = tabla.tHead?.rows[0];
    if (!encabezado) return;

    const titulos = [];
    for (const th of encabezado.cells) {
        const texto = th.textContent.replace(/\s+/g, ' ').trim();
        for (let i = 0; i < (th.colSpan || 1); i++) titulos.push(texto);
    }

    for (const cuerpo of tabla.tBodies) {
        for (const fila of cuerpo.rows) {
            let columna = 0;
            for (const celda of fila.cells) {
                if (!celda.hasAttribute('data-label') && celda.colSpan === 1 && titulos[columna]) {
                    celda.setAttribute('data-label', titulos[columna]);
                }
                columna += celda.colSpan || 1;
            }
        }
    }
}

function iniciar() {
    document.querySelectorAll('table.tabla').forEach(etiquetar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
} else {
    iniciar();
}
