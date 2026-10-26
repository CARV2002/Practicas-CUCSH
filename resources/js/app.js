import './bootstrap';
import Alpine from 'alpinejs';

// flatpickr (selector de fechas de x-form.date-picker)
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

window.Alpine = Alpine;
window.flatpickr = flatpickr;

Alpine.start();
