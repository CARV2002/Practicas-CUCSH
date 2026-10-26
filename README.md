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
- **Gestión de Archivos:** Spatie Laravel-Medialibrary (archivos en el disco privado `local`)
- **Frontend:** Blade + Alpine.js + Tailwind (plantilla TailAdmin como base visual)

## Quién usa el sistema

| Rol | Quién es | Qué hace |
|---|---|---|
| `administrador` | Coordinación de Prácticas Profesionales | Lleva el proceso de convenio de principio a fin y **captura** lo que resuelven divisiones, Jurídica y Rectoría |
| `empresa` | Empresas e instituciones | Tramita su convenio, da seguimiento a su estado y, una vez formalizado, publica ofertas |
| `alumno` | Estudiantes | Consulta ofertas, se postula y gestiona su práctica |

Divisiones, Secretaría Académica, Jurídica, Rectoría y Control Escolar **no entran al sistema**: sus dictámenes y firmas se registran por la Coordinación.
Si en el futuro lo usan, se agrega un caso en `App\Enums\Rol`; las tablas ya contemplan el alcance por división/carrera (`personal`).

## Estructura

| Capa | Dónde | Qué contiene |
|---|---|---|
| Datos | `database/migrations`, `app/Models` | 16 modelos del diagrama ER v6 (nombres en español) |
| Estados | `app/Enums` | Estados y etapas como enums (sin strings sueltos) |
| Proceso de convenio | `app/Services/ConvenioWorkflow.php` | Máquina de estados: solicitud → validación académica → Jurídica → firmas → formalizado, con bitácora y avisos a la empresa |
| Proceso de práctica | `app/Actions` | Emitir carta de presentación, registrar carta de aceptación, activar práctica |
| Reglas que siempre aplican | `app/Observers` | Oferta, cupos, cierre de práctica y acreditación |
| Acceso | `app/Http/Controllers/Auth`, `routes/auth.php` | Login y registro público de alumno y empresa (siempre con rol + perfil) |

Las pantallas de Coordinación (`resources/views/admin`) que aún no se construyen son marcadores que indican qué mostrarán y de qué modelo saldrán los datos.

## Usuarios y datos de prueba (solo entorno local)

`php artisan db:seed` es idempotente. **En local** crea, con contraseña `password123`:
`admin@test.com` (Coordinación), `alumno@test.com` y `empresa@test.com`, más una división y una carrera ficticias (`DEMO`).
**En producción solo crea los roles**; nunca siembra cuentas con contraseña conocida.

La cuenta real de la Coordinación se crea con un comando que pide la contraseña:

```bash
php artisan practicas:crear-administrador correo@udg.mx --nombre="Nombre Apellido"
```

Alumnos y empresas se registran solos desde el sitio. Las divisiones y carreras reales se capturan desde la aplicación.

## Pruebas

```bash
php artisan test
```

Cubren el registro con roles, el acceso por rol a cada pantalla, el proceso completo de convenio
(solicitud → dictámenes → Jurídica → firmas → formalización) y el de la práctica (cupos, IMSS, horas, cierre y acreditación).

## Pendiente

- Controladores y pantallas: formulario de solicitud de convenio (empresa), bandeja de convenios y dictámenes (Coordinación), ofertas y postulaciones.
- CRUD de divisiones y carreras (sin carreras no se puede registrar un alumno).
- Página del aviso de privacidad (hoy solo se guarda la aceptación en `users.aviso_privacidad_at`).
- Rutas autorizadas para descargar archivos privados (Policies).
- Generar el borrador del convenio y el Oficio de Acreditación desde plantilla.
