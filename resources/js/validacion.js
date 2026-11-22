/**
 * Validación de formularios en español, en todas las pantallas.
 *
 *  - Reemplaza los globos del navegador (que salen en inglés u otro idioma) por mensajes propios
 *    bajo cada campo, con el nombre del campo y qué hacer para corregirlo.
 *  - Marca en rojo el campo con error y en verde el que ya quedó bien, mientras el usuario escribe.
 *  - Revisa tipo y tamaño de archivos, la confirmación de contraseña y el largo mínimo, en vivo.
 *  - Muestra junto a su campo los errores que regresa el servidor (window.__erroresServidor).
 *  - Evita el doble envío: deshabilita el botón y muestra «Enviando…».
 */

const MB_POR_DEFECTO = 10;

const clasesError = ['!border-error-500', 'ring-3', 'ring-error-500/10'];
const clasesOk = ['!border-success-500'];

/** Nombre legible del campo: su <label>, aria-label, placeholder o name. */
function nombreDe(campo) {
    let texto = '';
    if (campo.id) {
        const label = document.querySelector(`label[for="${CSS.escape(campo.id)}"]`);
        if (label) texto = label.textContent;
    }
    if (!texto) {
        const label = campo.closest('label');
        if (label) texto = label.textContent;
    }
    if (!texto) texto = campo.getAttribute('aria-label') || campo.getAttribute('title') || campo.placeholder || '';
    if (!texto) texto = (campo.name || 'este campo').replace(/_id$/, '').replace(/[_\[\].]+/g, ' ');

    return texto.replace(/\*/g, '').replace(/\s+/g, ' ').trim();
}

function extension(nombre) {
    const i = nombre.lastIndexOf('.');
    return i >= 0 ? nombre.slice(i).toLowerCase() : '';
}

/** Valida tipo y peso de archivos con base en `accept` y `data-max-mb`. */
function revisarArchivo(campo) {
    campo.setCustomValidity('');
    const archivo = campo.files && campo.files[0];
    if (!archivo) return;

    const maxMb = parseFloat(campo.dataset.maxMb || MB_POR_DEFECTO);
    if (archivo.size > maxMb * 1024 * 1024) {
        campo.setCustomValidity(`El archivo pesa ${(archivo.size / 1048576).toFixed(1)} MB; el máximo es ${maxMb} MB.`);
        return;
    }

    const accept = (campo.getAttribute('accept') || '').split(',').map((a) => a.trim().toLowerCase()).filter(Boolean);
    if (!accept.length) return;

    const ext = extension(archivo.name);
    const tipo = (archivo.type || '').toLowerCase();
    const permitido = accept.some((a) => a.startsWith('.')
        ? a === ext
        : a.endsWith('/*') ? tipo.startsWith(a.slice(0, -1)) : a === tipo);

    if (!permitido) {
        const legible = accept.map((a) => ({
            'application/pdf': 'PDF', '.pdf': 'PDF', '.docx': 'Word (.docx)', 'image/jpeg': 'JPG', 'image/png': 'PNG',
            'image/webp': 'WebP', '.jpg': 'JPG', '.jpeg': 'JPG', '.png': 'PNG', 'image/*': 'imagen',
        }[a] || a)).filter((v, i, arr) => arr.indexOf(v) === i).join(', ');
        campo.setCustomValidity(`Ese archivo no es válido: sube un archivo ${legible}.`);
    }
}

/** La confirmación debe coincidir con el campo `password` del mismo formulario. */
function revisarConfirmacion(campo) {
    if (!campo.name || !campo.name.endsWith('_confirmation') || !campo.form) return;
    const original = campo.form.querySelector(`[name="${campo.name.replace('_confirmation', '')}"]`);
    campo.setCustomValidity(original && campo.value && campo.value !== original.value
        ? 'Las contraseñas no coinciden. Escribe la misma en ambos campos.' : '');
}

/** Mensaje en español según el tipo de error del campo. */
function mensajeDe(campo) {
    const v = campo.validity;
    const nombre = nombreDe(campo);

    if (v.customError) return campo.validationMessage;
    if (v.valueMissing) {
        if (campo.type === 'file') return `Adjunta ${nombre.toLowerCase()}.`;
        if (campo.type === 'checkbox') return nombre.length <= 40 ? `Debes marcar «${nombre}» para continuar.` : 'Debes marcar esta casilla para continuar.';
        if (campo.tagName === 'SELECT' || campo.type === 'radio') return `Selecciona una opción en «${nombre}».`;
        return `Falta «${nombre}»: este campo es obligatorio.`;
    }
    if (v.typeMismatch) {
        if (campo.type === 'email') return 'Escribe un correo válido, por ejemplo nombre@dominio.com.';
        if (campo.type === 'url') return 'Escribe una dirección web válida, por ejemplo https://sitio.mx.';
        return `El formato de «${nombre}» no es válido.`;
    }
    if (v.tooShort) return `«${nombre}» debe tener al menos ${campo.minLength} caracteres (llevas ${campo.value.length}).`;
    if (v.tooLong) return `«${nombre}» no puede tener más de ${campo.maxLength} caracteres.`;
    if (v.rangeUnderflow) return campo.type === 'date'
        ? `La fecha de «${nombre}» no puede ser anterior al ${formatoFecha(campo.min)}.`
        : `«${nombre}» debe ser al menos ${campo.min}.`;
    if (v.rangeOverflow) return campo.type === 'date'
        ? `La fecha de «${nombre}» no puede ser posterior al ${formatoFecha(campo.max)}.`
        : `«${nombre}» no puede ser mayor que ${campo.max}.`;
    if (v.stepMismatch) return `«${nombre}» debe ser un número ${campo.step === '1' || !campo.step ? 'entero' : `con incrementos de ${campo.step}`}.`;
    if (v.badInput) return campo.type === 'number' ? `«${nombre}» debe ser un número.` : `Revisa el valor de «${nombre}».`;
    if (v.patternMismatch) return campo.dataset.msgFormato || campo.title || `El formato de «${nombre}» no es válido.`;

    return `Revisa «${nombre}».`;
}

function formatoFecha(iso) {
    const [a, m, d] = (iso || '').split('-');
    return a && m && d ? `${d}/${m}/${a}` : iso;
}

/** Dónde va el mensaje: tras el campo, o tras su <label> si el campo está dentro de él (checkbox/radio). */
function anclaDe(campo) {
    const label = campo.closest('label');
    return (campo.type === 'checkbox' || campo.type === 'radio') && label ? label : campo;
}

function mensajeEl(campo) {
    const ancla = anclaDe(campo);
    let p = ancla.nextElementSibling;
    if (!p || !p.classList.contains('js-mensaje-campo')) {
        p = document.createElement('p');
        p.className = 'js-mensaje-campo mt-1 flex items-start gap-1 text-sm font-medium text-error-600';
        p.setAttribute('role', 'alert');
        ancla.insertAdjacentElement('afterend', p);
    }
    return p;
}

function mostrarError(campo, texto) {
    campo.classList.remove(...clasesOk);
    campo.classList.add(...clasesError);
    campo.setAttribute('aria-invalid', 'true');
    const p = mensajeEl(campo);
    p.textContent = '⚠ ' + texto;
    p.hidden = false;
}

function limpiar(campo, marcarOk) {
    campo.classList.remove(...clasesError);
    campo.removeAttribute('aria-invalid');
    if (marcarOk && campo.type !== 'checkbox' && campo.type !== 'radio' && campo.type !== 'hidden' && campo.value !== '') {
        campo.classList.add(...clasesOk);
    } else {
        campo.classList.remove(...clasesOk);
    }
    const ancla = anclaDe(campo);
    const p = ancla.nextElementSibling;
    if (p && p.classList.contains('js-mensaje-campo')) p.hidden = true;
}

function esValidable(el) {
    // Las zonas de archivo (x-ui.zona-archivo y x-ui.carga-documento) muestran sus propios mensajes
    return el instanceof HTMLElement && el.matches('input, select, textarea') && el.type !== 'hidden' && !el.disabled
        && !(el.type === 'file' && el.closest('[data-zona-archivo], [x-data^="cargaDocumento"]'));
}

function revisar(campo, marcarOk = true) {
    campo.setCustomValidity('');
    if (campo.type === 'file') revisarArchivo(campo);
    revisarConfirmacion(campo);

    // Largo mínimo también cuando el valor no lo tecleó el usuario (autocompletado, pegado)
    if (!campo.validationMessage && campo.minLength > 0 && campo.value && campo.value.length < campo.minLength) {
        campo.setCustomValidity(`«${nombreDe(campo)}» debe tener al menos ${campo.minLength} caracteres (llevas ${campo.value.length}).`);
    }

    if (campo.checkValidity()) {
        limpiar(campo, marcarOk);
        return true;
    }
    mostrarError(campo, mensajeDe(campo));
    return false;
}

/** Contador en vivo para contraseñas nuevas. */
function medidorContrasena(campo) {
    const minimo = parseInt(campo.getAttribute('minlength') || campo.dataset.min || '8', 10);
    if (!campo.hasAttribute('minlength')) campo.setAttribute('minlength', String(minimo));

    const ayuda = document.createElement('p');
    ayuda.className = 'js-ayuda-contrasena mt-1 text-xs text-gray-500';
    campo.insertAdjacentElement('afterend', ayuda);

    const pintar = () => {
        const n = campo.value.length;
        ayuda.textContent = n >= minimo ? `✓ ${n} caracteres: cumple el mínimo de ${minimo}.` : `${n} de ${minimo} caracteres mínimos.`;
        ayuda.className = `js-ayuda-contrasena mt-1 text-xs ${n >= minimo ? 'text-success-600' : 'text-gray-500'}`;
    };
    campo.addEventListener('input', pintar);
    pintar();
}

/** Aviso general arriba del formulario. */
function avisoFormulario(form, mostrar) {
    let aviso = form.querySelector(':scope > .js-aviso-formulario');
    if (!mostrar) {
        if (aviso) aviso.hidden = true;
        return;
    }
    if (!aviso) {
        aviso = document.createElement('div');
        aviso.className = 'js-aviso-formulario mb-3 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm font-medium text-error-700';
        aviso.setAttribute('role', 'alert');
        form.prepend(aviso);
    }
    aviso.textContent = 'Revisa los campos marcados en rojo antes de continuar.';
    aviso.hidden = false;
}

/**
 * Filtros de captura (data-solo): el campo no acepta lo que no corresponde, ni al teclear ni al pegar.
 *  digitos → solo números (código de alumno, teléfono, número de escritura o notaría)
 *  letras  → letras, espacios, punto, guion y apóstrofo (nombres de personas, cargos, ciudades)
 *  rfc     → mayúsculas, sin espacios ni guiones
 *  clave   → mayúsculas, números y guiones (folio mercantil)
 */
const FILTROS = {
    digitos: (v) => v.replace(/\D+/g, ''),
    letras: (v) => v.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ' .,-]+/g, '').replace(/\s{2,}/g, ' '),
    rfc: (v) => v.toUpperCase().replace(/[^A-ZÑ&0-9]+/g, ''),
    clave: (v) => v.toUpperCase().replace(/[^A-Z0-9-]+/g, ''),
};

function filtrar(campo) {
    const filtro = FILTROS[campo.dataset?.solo];
    if (!filtro) return;
    const antes = campo.value;
    const despues = filtro(antes);
    if (antes === despues) return;
    // Conserva la posición del cursor aunque se quiten caracteres
    const pos = Math.max(0, (campo.selectionStart ?? despues.length) - (antes.length - despues.length));
    campo.value = despues;
    try { campo.setSelectionRange(pos, pos); } catch { /* type=email/number no admiten selección */ }
}

/** Contador «6 de 9 dígitos» bajo los campos con data-contador (largo exacto). */
function contador(campo) {
    const total = parseInt(campo.getAttribute('maxlength') || '0', 10);
    if (!total) return;
    const ayuda = document.createElement('p');
    ayuda.className = 'js-contador mt-1 text-xs text-gray-500';
    ayuda.setAttribute('aria-live', 'polite');
    (campo.parentElement?.classList.contains('relative') ? campo.parentElement : campo).insertAdjacentElement('afterend', ayuda);
    const unidad = campo.dataset.contador || 'caracteres';
    // Largo exacto (código de alumno, teléfono): ✓ al completarlo. Largo libre (nombres): solo avisa cerca del límite.
    const exacto = campo.minLength === total;
    const pintar = () => {
        const n = campo.value.length;
        const listo = exacto && n === total;
        ayuda.textContent = exacto ? `${listo ? '✓ ' : ''}${n} de ${total} ${unidad}` : `${n} / ${total} ${unidad}`;
        ayuda.className = `js-contador mt-1 text-xs ${listo ? 'text-success-600' : (!exacto && n >= total * 0.9 ? 'text-amber-700' : 'text-gray-500')}`;
    };
    campo.addEventListener('input', pintar);
    pintar();
}

function iniciar() {
    // 0. Filtros de captura: antes que la validación en vivo.
    //    Al teclear se detiene el carácter que no va (o se escribe ya transformado, p. ej. en mayúsculas)
    //    sin tocar el resto del valor, así el cursor no salta; lo pegado o autocompletado se limpia después.
    document.addEventListener('beforeinput', (e) => {
        const campo = e.target;
        const filtro = campo instanceof HTMLInputElement ? FILTROS[campo.dataset.solo] : null;
        if (!filtro || e.inputType !== 'insertText' || !e.data) return;
        const limpio = filtro(e.data);
        if (limpio === e.data) return;
        e.preventDefault();
        if (!limpio) return;
        const max = campo.maxLength > 0 ? campo.maxLength : Infinity;
        const inicio = campo.selectionStart ?? campo.value.length;
        const fin = campo.selectionEnd ?? inicio;
        const cabe = limpio.slice(0, Math.max(0, max - (campo.value.length - (fin - inicio))));
        if (!cabe) return;
        campo.setRangeText(cabe, inicio, fin, 'end');
        campo.dispatchEvent(new Event('input', { bubbles: true }));
    }, true);
    document.addEventListener('input', (e) => { if (e.target instanceof HTMLInputElement) filtrar(e.target); }, true);
    document.querySelectorAll('input[data-solo]').forEach(filtrar);
    document.querySelectorAll('input[data-contador]').forEach(contador);

    // 1. Errores del navegador: se cancelan los globos nativos y se muestra el mensaje propio
    document.addEventListener('invalid', (e) => {
        const campo = e.target;
        if (!esValidable(campo)) return;
        e.preventDefault();
        mostrarError(campo, mensajeDe(campo));
        if (campo.form) avisoFormulario(campo.form, true);
    }, true);

    // 2. En vivo: al salir del campo y mientras corrige
    document.addEventListener('focusout', (e) => {
        if (esValidable(e.target) && (e.target.value !== '' || e.target.hasAttribute('aria-invalid'))) revisar(e.target);
    });
    document.addEventListener('input', (e) => {
        if (!esValidable(e.target)) return;
        if (e.target.hasAttribute('aria-invalid') || e.target.name?.endsWith('_confirmation')) revisar(e.target);
        // Si cambia la contraseña, vuelve a revisar su confirmación
        if (e.target.form && e.target.type === 'password' && !e.target.name.endsWith('_confirmation')) {
            const conf = e.target.form.querySelector(`[name="${e.target.name}_confirmation"]`);
            if (conf && conf.value) revisar(conf);
        }
    });
    document.addEventListener('change', (e) => {
        if (esValidable(e.target) && (e.target.type === 'file' || e.target.tagName === 'SELECT' || e.target.type === 'checkbox')) revisar(e.target);
    });

    // 3. Al enviar: revisa todo, enfoca el primer error y evita el doble envío
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.noValidate) return;

        const campos = [...form.elements].filter(esValidable);
        campos.forEach((c) => { if (c.type === 'file' || c.minLength > 0 || c.name?.endsWith('_confirmation')) revisar(c, false); });
        const invalidos = campos.filter((c) => !c.validity.valid);

        if (invalidos.length) {
            e.preventDefault();
            invalidos.forEach((c) => mostrarError(c, mensajeDe(c)));
            avisoFormulario(form, true);
            invalidos[0].focus({ preventScroll: false });
            return;
        }

        avisoFormulario(form, false);
        if (e.defaultPrevented || form.method.toLowerCase() === 'get') return;

        const boton = e.submitter;
        if (boton && !boton.dataset.sinBloqueo) {
            // Se deshabilita después de que el navegador tome su name/value
            setTimeout(() => ponerCargando(boton), 0);
        }
    }, false);

    // 4. Contraseñas nuevas con contador
    document.querySelectorAll('input[type="password"][autocomplete="new-password"]:not([name$="_confirmation"])').forEach(medidorContrasena);

    // 5. Errores que regresó el servidor, junto a su campo
    const errores = window.__erroresServidor || {};
    Object.entries(errores).forEach(([nombre, mensajes]) => {
        const base = nombre.replace(/\.\d+$/, '');
        const candidatos = [
            nombre,
            nombre.replace(/\.(\w+)/g, '[$1]'),
            `${base}[]`,
        ];
        let campo = null;
        for (const n of candidatos) {
            campo = document.querySelector(`form [name="${CSS.escape(n)}"]`);
            if (campo) break;
        }
        if (campo && esValidable(campo)) {
            // Si la vista ya pinta el mensaje (@error) junto al campo, solo se marca el borde
            // (se revisa también el abuelo: el campo puede ir envuelto, p. ej. contraseña con botón de mostrar)
            const contenedor = anclaDe(campo).parentElement;
            const yaPintado = [contenedor, contenedor?.parentElement].some((c) => c && c.textContent.includes(mensajes[0]));
            if (yaPintado) {
                campo.classList.add(...clasesError);
                campo.setAttribute('aria-invalid', 'true');
            } else {
                mostrarError(campo, mensajes[0]);
            }
            // El servidor ya lo marcó: al corregir, el mensaje se quita
            campo.addEventListener('input', () => limpiar(campo, false), { once: true });
            campo.addEventListener('change', () => limpiar(campo, false), { once: true });
        }
    });
}

/**
 * Estado de carga del botón de envío: spinner + «Enviando…», mismo ancho para que el diseño no salte,
 * aria-busy para lectores de pantalla. Botones con mucho texto conservan su texto y solo suman el spinner.
 */
const SPINNER = '<svg class="js-spinner size-4 shrink-0 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
    + '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25"/>'
    + '<path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';

function ponerCargando(boton) {
    if (boton.dataset.cargando) return;
    boton.dataset.cargando = '1';
    boton.dataset.htmlOriginal = boton.innerHTML;
    boton.style.minWidth = `${boton.offsetWidth}px`;
    boton.disabled = true;
    boton.setAttribute('aria-busy', 'true');
    boton.classList.add('cursor-wait', 'opacity-80');
    if (!/\b(inline-)?flex\b/.test(boton.className)) boton.classList.add('inline-flex', 'items-center', 'justify-center', 'gap-2');
    const texto = boton.textContent.trim();
    boton.innerHTML = SPINNER + `<span>${texto.length < 40 ? 'Enviando…' : texto}</span>`;
}

function quitarCargando(boton) {
    if (!boton.dataset.cargando) return;
    boton.innerHTML = boton.dataset.htmlOriginal;
    boton.disabled = false;
    boton.removeAttribute('aria-busy');
    boton.classList.remove('cursor-wait', 'opacity-80');
    boton.style.minWidth = '';
    delete boton.dataset.cargando;
}

// Al volver con «Atrás», el navegador restaura la página desde caché: los botones deben quedar usables
window.addEventListener('pageshow', (e) => {
    if (e.persisted) document.querySelectorAll('[data-cargando]').forEach(quitarCargando);
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
} else {
    iniciar();
}
