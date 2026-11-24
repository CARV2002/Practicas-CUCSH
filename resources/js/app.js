import './confirmacion';
import './validacion';
import './tablas';
import { iniciarVistaPrevia } from './vista-previa';
import Alpine from 'alpinejs';

// Componentes de Alpine (x-data="nombre(...)"): la lógica vive aquí y no en los atributos de las vistas
import bajaPractica from './alpine/baja-practica';
import buscadorAyuda from './alpine/buscador-ayuda';
import calendarioRegresos from './alpine/calendario-regresos';
import cargaDocumento, { iniciarZonasArchivo } from './alpine/carga-documento';
import catalogos from './alpine/catalogos';
import codigoPostal from './alpine/codigo-postal';
import envioExpediente from './alpine/envio-expediente';
import fuerzaContrasena from './alpine/fuerza-contrasena';
import menuLateral from './alpine/menu-lateral';
import notificaciones from './alpine/notificaciones';
import profesores from './alpine/profesores';
import programaFormulario from './alpine/programa-formulario';
import usuarios from './alpine/usuarios';

// Fuentes institucionales (DESIGN_SYSTEM.md §2): se empaquetan con Vite, sin depender de Google Fonts
import '@fontsource-variable/inter';
import '@fontsource-variable/plus-jakarta-sans';

window.Alpine = Alpine;

Alpine.data('bajaPractica', bajaPractica);
Alpine.data('buscadorAyuda', buscadorAyuda);
Alpine.data('calendarioRegresos', calendarioRegresos);
Alpine.data('cargaDocumento', cargaDocumento);
Alpine.data('catalogos', catalogos);
Alpine.data('codigoPostal', codigoPostal);
Alpine.data('envioExpediente', envioExpediente);
Alpine.data('fuerzaContrasena', fuerzaContrasena);
Alpine.data('menuLateral', menuLateral);
Alpine.data('notificaciones', notificaciones);
Alpine.data('profesores', profesores);
Alpine.data('programaFormulario', programaFormulario);
Alpine.data('usuarios', usuarios);

Alpine.start();
iniciarVistaPrevia();
iniciarZonasArchivo();
