# Sistema de Gestión de Prácticas Profesionales

Este es un sistema web desarrollado en Laravel diseñado para la automatización, seguimiento y control de flujos de procesos administrativos, asignación de espacios y validación de expedientes documentales de forma segura.

## Requisitos Previos

Asegúrate de tener instalado en tu entorno de desarrollo:
- PHP >= 8.2
- Composer
- Node.js & NPM
- Servidor de Base de Datos (MySQL / PostgreSQL / SQLite)

## Instalación y Configuración Local

Sigue estos pasos para levantar el proyecto en tu máquina local:

1. **Clonar el repositorio:**
   ```bash
   git clone <URL_DE_TU_REPOSITORIO>
   cd <NOMBRE_DE_LA_CARPETA>
   ```

2. **Instalar dependencias de PHP:**
   ```bash
   composer install
   ```

3. **Instalar dependencias de Frontend:**
   ```bash
   npm install
   ```

4. **Configurar el entorno:**
   Crea una copia del archivo de configuración ambiental:
   ```bash
   cp .env.example .env
   ```
   *Nota: Abre el archivo `.env` recién creado y configura las credenciales de tu base de datos local (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).*

5. **Generar la clave de la aplicación:**
   ```bash
   php artisan key:generate
   ```

6. **Ejecutar migraciones y seeders:**
   Prepara la estructura de la base de datos junto con los roles básicos del sistema:
   ```bash
   php artisan migrate --seed
   ```

7. **Compilar assets del frontend:**
   Mantén corriendo el compilador en modo desarrollo:
   ```bash
   npm run dev
   ```

8. **Iniciar el servidor local:**
   En otra terminal, arranca el servidor integrado de Laravel:
   ```bash
   php artisan serve
   ```

Ya puedes acceder a la aplicación desde tu navegador en `http://127.0.0.1:8000`.

## Tecnologías Utilizadas

- **Backend:** Laravel 12
- **Manejador de Permisos:** Spatie Laravel-Permission
- **Gestión de Archivos:** Spatie Laravel-Medialibrary



