import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Estilos propios de algunas páginas: se cargan solo ahí con @push('estilos')
                'resources/css/paginas/mis-practicas.css',
                'resources/css/paginas/oferta-programas.css',
                'resources/css/paginas/contacto.css',
                'resources/css/paginas/empresas.css',
                'resources/css/paginas/estudiantes.css',
                'resources/css/paginas/legal.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
