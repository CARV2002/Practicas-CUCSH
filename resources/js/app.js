import './confirmacion';
import './validacion';
import './tablas';
import { iniciarVistaPrevia } from './vista-previa';
import calendarioRegresos from './calendario-regresos';
import cargaDocumento, { iniciarZonasArchivo } from './carga-documento';
import codigoPostal from './codigo-postal';
import Alpine from 'alpinejs';

// Fuentes institucionales (DESIGN_SYSTEM.md §2): se empaquetan con Vite, sin depender de Google Fonts
import '@fontsource-variable/inter';
import '@fontsource-variable/plus-jakarta-sans';

// flatpickr (selector de fechas de x-form.date-picker)
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

window.Alpine = Alpine;
window.flatpickr = flatpickr;

Alpine.data('calendarioRegresos', calendarioRegresos);
Alpine.data('cargaDocumento', cargaDocumento);
Alpine.data('codigoPostal', codigoPostal);
Alpine.start();
iniciarVistaPrevia();
iniciarZonasArchivo();
