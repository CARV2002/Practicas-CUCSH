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

## Tecnologías utilizadas

| Paquete | Para qué |
|---|---|
| Laravel 12 (PHP 8.2) | Rutas, políticas de acceso, colas, notificaciones y tareas programadas |
| spatie/laravel-permission | Roles con sesión (`administrador`, `vinculacion`, `coordinador`, `empresa`, `alumno`) |
| spatie/laravel-medialibrary | Expedientes y escaneos en el disco privado `local`, con estado de validación por archivo |
| phpoffice/phpword | Llena el Formato de PP (convenio específico) y la carta de presentación en Word |
| LibreOffice (sin interfaz) | Convierte el Formato de PP a PDF limpio para imprimir los 3 juegos (`App\Services\ConvertidorPdf`) |
| laravel/socialite | Inicio de sesión con la cuenta institucional de Google |
| laravel-lang/common | Mensajes de validación en español |
| Pest 4 | Pruebas de procesos, permisos y transiciones |
| Tailwind CSS 4 + Vite 7 + Alpine.js + flatpickr | Interfaz (ver `DESIGN_SYSTEM.md`); tipografías servidas localmente |

## Quién usa el sistema (6 actores)

| Actor | Rol | Qué hace |
|---|---|---|
| Administrador técnico | `administrador` | Cuentas de personal, divisiones y carreras, auditoría; entra a todo |
| Vinculación (Lorena) | `vinculacion` | Trámite de convenios: expedientes, turno a carreras, borrador, Jurídica, firmas, documentos físicos y formalización |
| Coordinación de carrera | `coordinador` | Dictamen de pertinencia, vacantes, IMSS/kardex, carta de presentación, **validación de reportes**, bajas y cierre |
| Profesor supervisor | *(sin sesión)* | Catálogo que administra cada coordinación y se asigna a la práctica; la validación de reportes la hace la coordinación |
| Empresa | `empresa` | Registro con sus datos legales, solicitud de convenio, vacantes y seguimiento de practicantes |
| Alumno | `alumno` | Postulación (con confirmación de SIIAU), documentos, reportes y carta de término |

Secretaría Académica, Divisiones, Jurídica, Rectoría y Control Escolar no entran al sistema: Vinculación y la coordinación registran sus resoluciones.

## Puesta en marcha en el servidor

1. `php artisan migrate --force`
2. **Cola de avisos** (sin ella no sale ninguna notificación ni correo):
   `php artisan queue:work --tries=3` como servicio (Supervisor en Linux; Programador de tareas o NSSM en Windows).
3. **Tareas programadas** (avisos diarios a las 7:30: convenios a 60 y 30 días, esperas, documentos físicos, dictámenes y prácticas por terminar):
   ejecutar `php artisan schedule:run` cada minuto (cron o Programador de tareas).
4. **Correo**: en `.env` cambiar `MAIL_MAILER=log` por el SMTP institucional (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`).
5. **PDF del convenio**: instalar LibreOffice. Si `soffice` no está en una ruta estándar, indicar `PRACTICAS_SOFFICE="C:\Program Files\LibreOffice\program\soffice.exe"`.
   Sin LibreOffice el borrador se sigue generando en Word y se muestra un aviso.
6. `npm ci && npm run build` y `php artisan optimize`.

## Usuarios y datos de prueba (solo entorno local)

`php artisan db:seed` es idempotente. **En local** crea cuentas con contraseña `password123`
(`admin@test.com`, `vinculacion@test.com`, `coord.abog@test.com`, `empresa@test.com`, `alumno.elegible@test.com`, entre otras) y datos de demostración.
**En producción solo crea los roles**; nunca siembra cuentas con contraseña conocida.

La cuenta real del administrador técnico se crea con un comando que pide la contraseña:

```bash
php artisan practicas:crear-administrador correo@udg.mx --nombre="Nombre Apellido"
```

## Pruebas

```bash
php artisan test
```

Cubren registro y roles, acceso por pantalla, el proceso completo del convenio (solicitud → dictámenes → Formato de PP → Jurídica → firmas → formalización),
los documentos físicos y recordatorios, y el de la práctica (SIIAU, IMSS, cupos, horas, bajas o cambio de plaza, cierre y acreditación).

Revisión contra el documento de confirmación: `docs/revision-confirmacion-sistema.md`.
