import './bootstrap';
import './validacion';
import './tablas';
import Alpine from 'alpinejs';

// Fuentes institucionales (DESIGN_SYSTEM.md §2): se empaquetan con Vite, sin depender de Google Fonts
import '@fontsource-variable/inter';
import '@fontsource-variable/plus-jakarta-sans';

// flatpickr (selector de fechas de x-form.date-picker)
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

window.Alpine = Alpine;
window.flatpickr = flatpickr;

Alpine.start();
