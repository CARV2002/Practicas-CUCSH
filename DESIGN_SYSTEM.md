# Sistema de diseño · Prácticas Profesionales UDG (CUCSH)

Guía visual institucional de la plataforma. Aplica al sitio público (`layouts/landing`) y a los paneles de Vinculación, Coordinación, Empresa, Alumno y Administración.

> **Solo modo claro.** La plataforma no tiene modo oscuro. No se usan clases `dark:` en vistas nuevas; las que quedan de la plantilla original se irán retirando.

**Stack:** Laravel 12 · Tailwind CSS v4 (`@tailwindcss/vite`, configuración CSS-first con `@theme` en `resources/css/app.css`) · `@tailwindcss/forms` · Alpine.js 3 · Flatpickr.
**Regla general:** todo se resuelve con utilidades de Tailwind y, para interacción, Alpine.js. No se agregan hojas CSS sueltas ni estilos `style=""` en línea.

---

## 0. Punto de partida (lo que hay hoy)

| Aspecto | Estado actual | Qué cambia con esta guía |
|---|---|---|
| Tailwind | v4, tokens en `@theme` dentro de `app.css` (heredados de la plantilla TailAdmin) | Se agregan tokens institucionales (`udg`, `real`, `oro`, `estatus`) y se dejan de usar `brand`/`blue` de la plantilla en vistas nuevas |
| Fuentes | Antes: paneles en **Outfit** y sitio público en **Montserrat** | ✅ Hecho: **Plus Jakarta Sans** (títulos) e **Inter** (texto) en paneles y sitio público, empaquetadas con Vite |
| Colores | Azul `#465FFF` de la plantilla en los paneles; azul marino `#1B2A4A` en el encabezado y la landing; rojo óxido `#C0392B` como acento en la landing | El azul marino se vuelve el color institucional; el rojo queda solo para errores; el dorado es el nuevo acento |
| Estatus | Cada enum define colores a mano (`ProgramaEstado::color()`), `x-ui.badge` con variantes genéricas | Una sola tabla de colores pastel por estatus (sección 1.4) |
| Modo oscuro | `@custom-variant dark` y clases `dark:` sueltas en ~6 vistas y en `app.css` | Fuera de alcance; no se usa |

---

## 1. Color

### 1.1 Principios

1. **Fondo claro y limpio.** El lienzo es casi blanco y el contenido vive en tarjetas blancas.
2. **El azul marino manda.** Encabezado, navegación activa, títulos y acciones principales.
3. **El dorado es un detalle.** Filetes, subrayados, íconos destacados y el foco de la marca. Nunca se usa como fondo de bloques grandes ni como color de texto corrido.
4. **Los estatus van en pastel.** Fondo muy claro, texto oscuro del mismo tono y un borde sutil, siempre acompañados de su etiqueta en texto. **Nunca solo color.**
5. **El rojo es solo para errores y rechazos.**

### 1.2 Paleta institucional (tokens nuevos)

Se definen en `@theme` y generan utilidades como `bg-udg-900`, `text-real-600` o `border-oro-300`.

**`udg` — Azul marino institucional** (base `#1B2A4A`, el que ya usan el encabezado y la landing)

| Token | Hex | Uso |
|---|---|---|
| `udg-50` | `#F3F6FB` | Fondo de filas seleccionadas, hover sutil en menú |
| `udg-100` | `#E4EAF4` | Fondo de ítem de menú activo, chips neutros de marca |
| `udg-200` | `#C9D4E7` | Bordes de tarjetas destacadas |
| `udg-300` | `#9FB1D1` | Íconos deshabilitados sobre marino |
| `udg-400` | `#6C84B0` | Texto secundario sobre fondos claros de marca |
| `udg-500` | `#45608F` | Íconos de navegación |
| `udg-600` | `#324A75` | Hover de botón primario |
| `udg-700` | `#26395D` | Botón primario (estado activo/presionado) |
| `udg-800` | `#1F3052` | Botón primario |
| `udg-900` | `#1B2A4A` | **Color de marca:** encabezado, sidebar, títulos H1 |
| `udg-950` | `#111C33` | Texto de máximo contraste, pie de página |

**`real` — Azul real UDG** (acento interactivo: enlaces, foco, selección)

| Token | Hex | Uso |
|---|---|---|
| `real-50` | `#EEF4FF` | Fondo de aviso informativo |
| `real-100` | `#DCE7FE` | Fondo de selección (checkbox de tabla, día de calendario) |
| `real-200` | `#B9CFFC` | Borde de aviso informativo |
| `real-500` | `#2F5FD0` | Anillo de foco (`ring-real-500/30`) |
| `real-600` | `#2350B8` | **Enlaces** y botón secundario relleno |
| `real-700` | `#1C4196` | Hover de enlaces |

**`oro` — Dorado/ámbar institucional** (solo detalles)

| Token | Hex | Uso |
|---|---|---|
| `oro-50` | `#FBF7EC` | Fondo de banda destacada (máximo una por pantalla) |
| `oro-100` | `#F5EBCF` | Fondo de insignia "Nuevo" |
| `oro-300` | `#E2C27A` | Filete bajo títulos de sección, borde superior de tarjeta destacada |
| `oro-400` | `#D4A94F` | Indicador de pestaña activa, punto de la campanita |
| `oro-500` | `#C08F2E` | Íconos decorativos |
| `oro-700` | `#8A6416` | **Único tono dorado permitido como texto** (contraste AA sobre blanco) |

**Neutros:** se conservan los `gray-*` que ya están en `app.css` (escala fría de la plantilla), que combinan bien con el marino.

| Rol | Token |
|---|---|
| Lienzo de la página | `bg-gray-50` (`#F9FAFB`) |
| Superficie (tarjetas, tablas, modales) | `bg-white` |
| Superficie hundida (encabezado de tabla, filtros) | `bg-gray-25` / `bg-gray-50` |
| Borde por defecto | `border-gray-200` |
| Borde de campo de formulario | `border-gray-300` |
| Texto principal | `text-gray-900` |
| Texto secundario | `text-gray-600` |
| Texto de ayuda, metadatos | `text-gray-500` (mínimo permitido para texto) |
| Deshabilitado | `text-gray-400` (solo con otra señal: candado, motivo) |

### 1.3 Colores semánticos

Se reutilizan los tokens que ya existen en `app.css`:

| Semántica | Fondo | Borde | Texto/ícono | Uso |
|---|---|---|---|---|
| Éxito | `success-50` | `success-200` | `success-700` | Guardado, validado, formalizado |
| Advertencia | `warning-50` | `warning-200` | `warning-700` | Requisito pendiente, vigencia por vencer |
| Error | `error-50` | `error-200` | `error-700` | Validación de formulario, rechazos |
| Información | `real-50` | `real-200` | `real-700` | Avisos del proceso, ayudas |

El estado de bloqueo (`x-ui.accion-bloqueada`) usa los neutros: `bg-gray-100`, `text-gray-500`, borde `border-dashed border-gray-300` y candado.

### 1.4 Estatus de trámites (pastel)

**Receta única:** `inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset` más un punto de color (`size-1.5 rounded-full`).

| Familia | Clases | Punto |
|---|---|---|
| **Gris** · neutro / inicio | `bg-slate-50 text-slate-700 ring-slate-200` | `bg-slate-400` |
| **Cielo** · recibido, en revisión | `bg-sky-50 text-sky-800 ring-sky-200` | `bg-sky-400` |
| **Lavanda** · revisión académica | `bg-violet-50 text-violet-800 ring-violet-200` | `bg-violet-400` |
| **Índigo** · revisión jurídica | `bg-indigo-50 text-indigo-800 ring-indigo-200` | `bg-indigo-400` |
| **Ámbar** · espera acción de alguien | `bg-amber-50 text-amber-800 ring-amber-200` | `bg-amber-400` |
| **Menta** · aprobado, vigente | `bg-emerald-50 text-emerald-800 ring-emerald-200` | `bg-emerald-500` |
| **Turquesa** · en curso | `bg-teal-50 text-teal-800 ring-teal-200` | `bg-teal-500` |
| **Rosa** · rechazado | `bg-rose-50 text-rose-800 ring-rose-200` | `bg-rose-500` |
| **Piedra** · cerrado, cancelado | `bg-stone-100 text-stone-600 ring-stone-200` | `bg-stone-400` |

**Asignación por enum:**

| Enum | Valor | Familia |
|---|---|---|
| `ConvenioEstado` | `solicitado` | Cielo |
| | `en_validacion_academica` | Lavanda |
| | `en_revision_juridica` | Índigo |
| | `en_firmas` | Ámbar |
| | `formalizado` | Menta |
| | `rechazado` | Rosa |
| `ProgramaEstado` | `borrador` | Gris |
| | `pendiente_aprobacion` | Ámbar |
| | `publicado` | Menta |
| | `rechazado` | Rosa |
| | `cerrado` | Piedra |
| `PostulacionEstado` | `pendiente` | Ámbar |
| | `carta_presentacion_emitida` | Cielo |
| | `carta_aceptacion_recibida` | Lavanda |
| | `aceptada` | Menta |
| | `rechazada` | Rosa |
| | `cancelada` | Piedra |
| `PracticaEstado` | `en_curso` | Turquesa |
| | `en_cierre` | Ámbar |
| | `acreditada` | Menta |
| | `cancelada` | Piedra |
| `ValidacionEstado` (documentos) | `pendiente` | Ámbar |
| | `validada` | Menta |
| | `rechazada` | Rosa |
| Vigencia de convenio | vence en 60 días o menos | Ámbar, con texto "Vence el dd/mm/aaaa" |
| | vencido | Piedra |

**Implementado:**
- Cada enum de estado tiene `familia(): string` (por ejemplo `'menta'`).
- `App\Support\Estatus` traduce la familia a clases.
- El componente `<x-ui.estatus>` dibuja la insignia:

```blade
<x-ui.estatus :estado="$convenio->estado" />
<x-ui.estatus :estado="$c->estado" :texto="$c->estado->etiquetaEmpresa()" tamano="normal" />
<x-ui.estatus familia="menta" texto="Vigente" />
```

No escribas clases de color de estatus en las vistas. Si un estado nuevo necesita color, agrégalo en su enum (`familia()`).

### 1.5 Contraste (WCAG 2.1 AA)

- Texto normal ≥ 4.5:1, texto grande (≥ 18 px o ≥ 14 px en negritas) e íconos ≥ 3:1.
- `udg-900` sobre blanco ≈ 14:1 · `real-600` sobre blanco ≈ 6.5:1 · `gray-500` sobre blanco ≈ 4.8:1.
- Los textos `*-700/800` sobre fondos `*-50` de la tabla 1.4 pasan AA.
- `oro-300/400/500` **no** pasan como texto; solo se usan como decoración. Para texto dorado usa `oro-700`.
- Verifica cualquier combinación nueva antes de usarla.

---

## 2. Tipografía

### 2.1 Familias

| Rol | Familia | Token | Utilidad |
|---|---|---|---|
| Títulos, cifras de tarjetas, botones grandes del sitio público | **Plus Jakarta Sans** (variable, 400–800) | `--font-display` | `font-display` |
| Texto, formularios, tablas, menú, botones del panel | **Inter** (variable, 400–700) | `--font-sans` | `font-sans` (por defecto en `body`) |
| Folios, RFC, números de control | Inter con cifras tabulares | — | `font-sans tabular-nums` |

En tablas, montos, horas y porcentajes se usa siempre `tabular-nums` para que las columnas se alineen.

### 2.2 Escala

Base de 16 px en el sitio público y 14 px (`text-sm`) en los paneles, que tienen mucha densidad de datos.

| Nivel | Uso | Clases Tailwind |
|---|---|---|
| Display | Hero de la landing | `font-display text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-udg-900` |
| H1 | Título de página en el panel | `font-display text-2xl sm:text-3xl font-bold tracking-tight text-udg-900` |
| H2 | Sección dentro de la página | `font-display text-xl font-semibold text-gray-900` |
| H3 | Título de tarjeta o modal | `font-display text-base sm:text-lg font-semibold text-gray-900` |
| Eyebrow | Antetítulo de sección | `font-sans text-xs font-semibold uppercase tracking-wider text-oro-700` |
| Cifra KPI | Número en tarjeta de resumen | `font-display text-3xl font-bold tabular-nums text-udg-900` |
| Cuerpo | Párrafos | `text-sm sm:text-base leading-relaxed text-gray-700` |
| Cuerpo panel | Texto general del panel | `text-sm text-gray-700` |
| Etiqueta de campo | `<label>` | `text-sm font-medium text-gray-800` |
| Ayuda / metadato | Bajo campos, fechas | `text-xs text-gray-500` |
| Encabezado de tabla | `<th>` | `text-xs font-semibold uppercase tracking-wide text-gray-500` |
| Celda | `<td>` | `text-sm text-gray-700` (dato clave: `font-medium text-gray-900`) |
| Error de campo | Mensaje de validación | `text-xs font-medium text-error-700` |

**Reglas:**

- Un solo H1 por página.
- No uses más de 3 tamaños distintos en una tarjeta.
- `font-display` nunca se usa en párrafos, tablas ni campos.
- Medida máxima del texto corrido: `max-w-prose`.
- Las mayúsculas sostenidas solo se usan en eyebrows y encabezados de tabla, nunca en botones ni títulos.
- Con Plus Jakarta Sans usa pesos 600–800. En tamaños pequeños, el 400 se ve delgado.

---

## 3. Forma, profundidad y espacio

### 3.1 Bordes y radios

| Elemento | Radio | Borde |
|---|---|---|
| Insignia de estatus, avatar, chip | `rounded-full` | `ring-1 ring-inset` |
| Botón, campo, select, pestaña | `rounded-lg` (8 px) | `border border-gray-300` en campos |
| Tarjeta, tabla, alerta, dropdown | `rounded-xl` (12 px) | `border border-gray-200` |
| Modal, panel lateral, hero card | `rounded-2xl` (16 px) | `border border-gray-200` |
| Tarjeta destacada (convenio vigente, "Tu coordinación") | `rounded-xl` | `border border-gray-200 border-t-4 border-t-oro-300` |

- El grosor por defecto es 1 px. Usa 2 px (`border-2`) solo para la zona de arrastre de archivos (`border-dashed`). Usa 4 px solo para el acento dorado superior o el indicador lateral del menú activo.
- Los divisores internos de una tarjeta usan `divide-y divide-gray-100`.

### 3.2 Sombras (elevación)

Se usan los tokens que ya existen en `app.css`. **Un nivel por capa**, sin mezclar sombra fuerte y borde fuerte.

| Nivel | Token | Uso |
|---|---|---|
| 0 | sin sombra + `border-gray-200` | Tarjetas dentro del lienzo (valor por defecto) |
| 1 | `shadow-theme-xs` | Botones blancos (secundarios), campos |
| 2 | `shadow-theme-sm` | Tarjeta en hover cuando es clicable |
| 3 | `shadow-theme-lg` | Dropdowns: campanita, menú de usuario, selects personalizados |
| 4 | `shadow-theme-xl` | Modales, panel lateral móvil |
| Foco | `focus-visible:ring-4 focus-visible:ring-real-500/20 focus-visible:border-real-500` | Todos los controles |

El fondo detrás de un modal es `bg-udg-950/50 backdrop-blur-sm`, no negro puro.

### 3.3 Espaciado

Se usa la escala de Tailwind (múltiplos de 4 px). No se usan valores arbitrarios (`p-[13px]`).

| Contexto | Valor |
|---|---|
| Padding de página (panel) | `p-4 sm:p-6 2xl:p-8` |
| Separación entre secciones de una página | `space-y-6` |
| Padding de tarjeta | `p-5 sm:p-6` (tarjeta compacta: `p-4`) |
| Separación título → contenido en tarjeta | `mb-4` |
| Rejilla de tarjetas KPI | `grid gap-4 sm:grid-cols-2 xl:grid-cols-4` |
| Campos de formulario | `space-y-5` entre campos y `gap-x-6 gap-y-5` en rejillas de 2 columnas |
| Etiqueta → campo → ayuda | `mb-1.5` y `mt-1.5` |
| Celdas de tabla | `px-4 py-3` (densa: `py-2`) |
| Botones | `h-10 px-4` (chico: `h-8 px-3 text-xs`; grande: `h-12 px-6 text-base`) |
| Secciones de la landing | `py-16 sm:py-20 lg:py-24`, contenedor `mx-auto max-w-7xl px-4 sm:px-6 lg:px-8` |

**Área táctil mínima:** 40 × 40 px en el panel (`size-10`) y 44 × 44 px en móvil para botones de solo ícono (hamburguesa, campanita, "⋯").

---

## 4. Componentes base (recetas)

### 4.0 Utilidades disponibles (implementadas en `resources/css/app.css`)

Las recetas de esta sección ya existen como utilidades de Tailwind (`@utility`). Úsalas en lugar de repetir las clases largas:

| Utilidad | Para qué |
|---|---|
| `boton` + `boton-primario` / `boton-secundario` / `boton-exito` / `boton-peligro` / `boton-peligro-suave` | Botones. Agrega `boton-chico` para acciones dentro de tablas |
| `enlace` | Enlaces de acción ("Gestionar →", "Ver detalle") |
| `campo` | Input, select y textarea de formularios. La altura va aparte: `campo h-11` |
| `campo-filtro` | Campos de la barra de filtros: a lo ancho en móvil, tamaño natural desde `sm` |
| `etiqueta`, `ayuda` | `<label>` y texto de ayuda bajo el campo |
| `tarjeta`, `tarjeta-destacada` | Superficies blancas con borde (la destacada lleva filete dorado) |
| `tabla-scroll` | Contenedor de la tabla: hace scroll horizontal en pantallas chicas |
| `tabla`, `tabla-th`, `tabla-td`, `tabla-fila` | Tabla, encabezado, celda y fila con hover |
| `menu-item-*` | Menú lateral: activo en `udg-50` con barra dorada |

**Reglas responsivas:**
- **Barras de filtros:** `campo-filtro` y un botón con `w-full sm:w-auto`. En móvil quedan apilados a lo ancho; desde `sm`, en línea. Si quieres un ancho fijo, ponlo con prefijo (`sm:w-44`), nunca sin prefijo.
- **Tablas:** siempre dentro de `tabla-scroll`.
  - `tabla` tiene un ancho mínimo de 640 px. La primera columna mide al menos 15 rem y las columnas de texto 10 rem, así la tabla hace scroll horizontal en lugar de aplastar el texto.
  - Las celdas cortas (fechas, folios, estatus) llevan `whitespace-nowrap`.
- **Botones de acción principal de una tarjeta en móvil:** `w-full sm:w-auto`.

### Botones

| Variante | Clases | Uso |
|---|---|---|
| Primario | `bg-udg-800 text-white hover:bg-udg-700 active:bg-udg-900 shadow-theme-xs` | Una acción principal por vista: "Enviar solicitud", "Postularme" |
| Secundario | `bg-white text-gray-800 border border-gray-300 hover:bg-gray-50 shadow-theme-xs` | Cancelar, Descargar, Filtrar |
| Enlace | `text-real-600 hover:text-real-700 hover:underline underline-offset-4` | Acciones dentro de una tabla o texto |
| Peligro | `bg-error-600 text-white hover:bg-error-700` | Rechazar, Eliminar (siempre con confirmación) |
| Éxito | `bg-success-600 text-white hover:bg-success-700` | Validar, Aprobar, Formalizar |
| Bloqueado | `x-ui.accion-bloqueada` (gris, candado y motivo visible) | Acción no permitida todavía |

Base común: `inline-flex items-center justify-center gap-2 rounded-lg h-10 px-4 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/20 disabled:opacity-60 disabled:cursor-not-allowed`.

### Campos de formulario

- **Normal:** `block w-full h-11 rounded-lg border border-gray-300 bg-white px-3.5 text-sm text-gray-900 placeholder:text-gray-400 shadow-theme-xs focus:border-real-500 focus:ring-4 focus:ring-real-500/20`.
- **Error:** `border-error-400 focus:border-error-500 focus:ring-error-500/20`, con el mensaje debajo (`resources/js/validacion.js` ya lo pone).
- **Válido:** `border-success-400` (solo después de que el usuario interactuó).
- **Obligatorio:** asterisco `text-error-600` después de la etiqueta, con `aria-hidden="true"`; el `required` va en el campo.

### Tablas

- **Contenedor:** `overflow-hidden rounded-xl border border-gray-200 bg-white`. En móvil, `overflow-x-auto` con `min-w-[640px]` en la tabla.
- **Encabezado:** `bg-gray-50` con estilo de `<th>` (sección 2.2).
- **Filas:** `divide-y divide-gray-100`, `hover:bg-udg-50/60`.
- **Columna de acciones:** a la derecha, con enlaces o botones chicos.
- **Estado vacío:** ícono `text-gray-300`, título `text-sm font-semibold text-gray-900`, una línea de ayuda `text-sm text-gray-500` y, si aplica, un botón primario. Nunca una tabla vacía sin explicación.

### Pestañas

- **Activa:** `text-udg-900 font-semibold`, más un indicador inferior `h-0.5 bg-oro-400`.
- **Inactiva:** `text-gray-500 hover:text-gray-800`.
- **Bloqueada:** `text-gray-400 cursor-not-allowed`, con candado y `title` que da el motivo.

### Menú lateral

- **Ítem activo:** `bg-udg-50 text-udg-900 font-semibold`, más una barra izquierda `before:w-1 before:bg-oro-400 before:rounded-r`.
- **Ítem inactivo:** `text-gray-600 hover:bg-gray-50 hover:text-gray-900`.

### Encabezado del panel (implementado en `layouts/app-header.blade.php`)

- **Barra:** `bg-udg-900` con filete inferior `border-b-2 border-oro-400/80`.
- **Botones de ícono sobre el marino:** `text-white/85 hover:bg-white/10`, foco `ring-oro-300`, de 40–44 px.
- **Campana:** botón "fantasma" con borde `white/20`. El contador va en dorado (`bg-oro-400 text-udg-950`) con su número; el lector de pantalla anuncia "Notificaciones: N sin leer".
- **Usuario:** avatar, nombre en blanco y rol en `text-oro-300`. Menú blanco con "Cerrar sesión" en rojo.
- **Móvil:** hamburguesa · campana · ⋯ (cuenta). **Sin logo en la barra**: el logo del CUCSH vive solo en el menú lateral.
- **Comportamiento:** los menús se cierran con `Esc` o al tocar fuera.

### Menú lateral (implementado en `layouts/sidebar.blade.php`)

- **Cabecera:** fondo blanco, logo del CUCSH y debajo "Prácticas Profesionales" en `text-oro-700`.
- **Títulos de grupo:** `text-gray-500`, en mayúsculas pequeñas.
- **Ítem activo:** `bg-udg-50 text-udg-900` con barra dorada a la izquierda. Los ítems bloqueados muestran candado y motivo.
- **Móvil:** cajón de 290 px por encima del encabezado, con fondo `bg-udg-950/50 backdrop-blur-sm`. Se cierra con ×, con `Esc` o tocando fuera.
- **Carga:** `x-cloak` evita que se vea mientras carga la página.

### Sitio público (implementado en `layouts/landing.blade.php` y `public/assets/css/home.css`)

**`home.css`**
- Usa los mismos tokens que el panel: `--navy` = `udg-900`, detalles en `--oro` y Plus Jakarta Sans / Inter.
- Todo vive en la capa CSS `landing`, declarada entre `components` y `utilities`. Así home.css gana a los estilos base de Tailwind, pero cualquier utilidad en una vista (`py-16`, `text-white`, …) lo puede ajustar.
- `--rust` se conserva solo como alias del dorado legible (`oro-700`) para estilos antiguos.

**Navegación**
- Barra blanca fija.
- El enlace activo va en `udg-900` con subrayado dorado y `aria-current="page"`.
- En celular hay un menú hamburguesa que se cierra con `Esc` o tocando fuera.
- Incluye un enlace "Saltar al contenido".

**Hero**
- Degradado azul marino sobre la foto, con filete dorado inferior.
- La acción principal va en dorado con texto marino (única excepción al botón primario marino, para destacar sobre la foto).

**Pie de página:** `bg-udg-950` con filete dorado y tres columnas (CUCSH, sitio, contacto) que se apilan en celular.

**Organizaciones con convenio vigente**
- *Inicio:* tarjetas blancas sobre marino, con la inicial de la organización y su tipo como insignia pastel (público = cielo, privado = lavanda, sociedad civil = menta).
- *Estudiantes (`#convenios-vigentes`):*
  - Filtro por carrera (agrupado por división), búsqueda por nombre y contador accesible (`aria-live`).
  - Cada tarjeta muestra las claves de carrera con convenio (la del alumno se resalta en marino) y la vigencia con `<x-ui.estatus familia="menta">`.
  - Si los filtros no dan resultados, aparece un estado vacío con "Quitar filtros".

**Directorio de coordinaciones (`<x-directorio-coordinaciones>`)**
- Buscador que ignora acentos.
- Tarjetas por división en rejilla de 1 a 3 columnas, cada una con la clave de la carrera, el teléfono (enlace `tel:`), "Escribir por Gmail" y "Copiar correo".
- Si falta el correo, un aviso ámbar indica acudir a la división.

**Contacto:** bloque "¿A quién escribo?" con dos caminos:
- **Alumno:** lleva al directorio.
- **Organización:** tarjeta marina con los datos de Vinculación.

### Tablas y bandejas (lenguaje único en todo el sitio)

| Pieza | Uso |
|---|---|
| `<x-pagina.encabezado titulo descripcion volver>` + slot `acciones` | Encabezado de bandejas y detalles (sustituye al *breadcrumb* de la plantilla) |
| `<x-tabla.marco titulo descripcion total>` + slots `herramientas` y `pie` | Tarjeta que envuelve toda tabla: filtros arriba, paginación abajo |
| `<x-tabla.filtros-estado :opciones parametro actual>` | Pestañas de estado con conteo y punto de color; conservan la búsqueda |
| `<x-ui.boton-icono icono etiqueta href/accion tono con-texto confirmar>` | Acciones de fila: ícono con *tooltip*; la acción principal lleva texto |
| `<x-convenio.pista :estado grande>` | Pista de las 4 fases del convenio (fila de tabla o detalle) |
| `dato-vacio` | Texto en cursiva gris para celdas sin dato («Sin folio aún», «Sin PDF adjunto»): **nunca una raya «—»** |

**Reglas de las tablas**
- **Encabezados:** en oración (sin mayúsculas sostenidas), gris y sobre un fondo tenue. Las cifras van alineadas a la derecha y con números tabulares.
- **Filas:**
  - Al pasar el cursor aparece un filete marino a la izquierda y un fondo `udg-50`.
  - Al cargar, entran escalonadas (máx. 180 ms), respetando «reducir movimiento».
- **Celular (< 768 px):** cada fila se vuelve tarjeta.
  - La primera celda es el título.
  - Cada celda muestra su etiqueta, que `resources/js/tablas.js` toma del `<th>`.
  - La celda con clase `acciones` no lleva etiqueta.
- **Escritorio:** la primera columna mide al menos 16 rem para que los nombres no se partan palabra por palabra.

### Tableros por rol (componentes en `resources/views/components/dashboard/`)

| Componente | Uso |
|---|---|
| `<x-dashboard.encabezado eyebrow titulo subtitulo>` + slot `acciones` | Banda marina con filete dorado, saludo y botones (dorado = acción principal). En móvil los botones se apilan a lo ancho |
| `<x-dashboard.kpi href etiqueta valor icono tono detalle alerta orden>` | Tarjeta KPI clicable: se eleva al pasar el cursor, barra de color superior, flecha que aparece y número que cuenta hacia arriba. `alerta` muestra una píldora roja con pulso (p. ej. «2 fuera de plazo») |
| `<x-dashboard.panel titulo descripcion enlace icono>` | Panel con encabezado y enlace «Ver todo» |
| `<x-dashboard.vacio titulo texto>` | Estado vacío amable (nunca una lista en blanco) |
| `<x-dashboard.icono nombre>` | Íconos de línea: documento, academico, juridico, firma, vigente, alerta, reloj, vacante, alumno, usuarios, imss, reporte, cierre, empresa, calendario, flecha, check, buscar, subir, comentario, informe |
| `<x-ui.aviso tipo titulo descartable autocerrar>` | Avisos (info, éxito, advertencia, error) con entrada suave y botón para cerrar |

**Qué muestra cada tablero**

- **Vinculación:**
  - Encabezado y KPIs: un KPI por etapa del convenio (revisión, dictamen, jurídica, firmas, vigentes), con alerta de etapas fuera de plazo.
  - Atención prioritaria: ordenada por días hábiles contra el máximo documentado, con barra roja, ámbar o marina.
  - Paneles: por vencer y distribución por estado.
- **Coordinación:**
  - KPIs: vacantes por aprobar, postulaciones, IMSS y kardex por validar, reportes y cierres, cada uno con acceso directo.
  - Paneles: carrera en números y programas recientes.
- **Alumno:**
  - Siguiente paso dentro del encabezado.
  - Progreso de horas: barra marina → dorada con marcas al 25, 50 y 75 %.
  - Trámite en 6 pasos.
  - Accesos rápidos (incluye convenios vigentes) y «Tu coordinación» con contacto directo.
- **Empresa:**
  - Convenio con barra de vigencia, carreras cubiertas o pasos del trámite.
  - Vacantes activas y en revisión.
  - Practicantes y reportes por comentar.

**Tablas en celular**
- La primera columna queda fija al desplazar.
- Sombras en el borde indican que hay más contenido a los lados.
- Los avatares se alinean arriba, junto al nombre.

**Envío de formularios:** `validacion.js` pone el botón en estado de carga: spinner, «Enviando…» y `aria-busy`. Se restaura si el usuario vuelve con «Atrás».

### Alertas

`rounded-xl border p-4 flex gap-3` con el color semántico de la sección 1.3, ícono a la izquierda, título `font-semibold` y texto `text-sm`.

---

## 5. Micro-animaciones

**Principio:** el movimiento explica un cambio de estado. No decora. Debe ser corto, suave y respetar `prefers-reduced-motion`.

### 5.1 Duraciones y curvas

| Token Tailwind | Duración | Uso |
|---|---|---|
| `duration-150` | 150 ms | Hover de botones, color de enlaces, bordes de campos |
| `duration-200` | 200 ms | Dropdowns, tooltips, aparición de errores |
| `duration-300` | 300 ms | Modales, panel lateral, acordeones |
| `ease-out` | — | Entradas |
| `ease-in` | — | Salidas (con duración un poco menor) |

Nunca más de 300 ms en interacciones del panel. Anima solo `opacity`, `transform`, colores y sombras; no animes `width`, `height` ni `top`.

### 5.2 Recetas con Tailwind

| Interacción | Clases |
|---|---|
| Hover de botón | `transition-colors duration-150` |
| Botón al presionar | `active:scale-[0.98] transition-transform duration-150` |
| Tarjeta clicable | `transition duration-200 hover:-translate-y-0.5 hover:shadow-theme-sm` |
| Ícono de flecha en enlace "Ver más" | `group` + `transition-transform group-hover:translate-x-0.5` |
| Chevron de acordeón | `transition-transform duration-200` + `rotate-180` cuando abre |
| Esqueleto de carga | `animate-pulse bg-gray-100 rounded-md` |
| Spinner en botón al enviar | `animate-spin size-4` (ya lo usa "Enviando…" de `validacion.js`) |
| Punto de la campanita con avisos nuevos | `animate-ping` en un halo `bg-oro-400/60` detrás del punto, máximo 3 iteraciones |
| Accesibilidad | Siempre agrega `motion-reduce:transition-none motion-reduce:animate-none` |

### 5.3 Recetas con Alpine.js (instalado: `alpinejs ^3.14`)

**Dropdown** (campanita, menú de usuario):

```blade
<div x-show="abierto" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="origin-top-right rounded-xl border border-gray-200 bg-white shadow-theme-lg">
```

**Modal:** fondo con `x-transition.opacity.duration.300ms`. El panel usa `enter-start="opacity-0 translate-y-4 sm:scale-95"` → `enter-end="opacity-100 translate-y-0 sm:scale-100"`. Agrega `@keydown.escape.window="abierto = false"` y devuelve el foco al botón que lo abrió.

**Mensaje "Guardado" o toast:** `x-data="{ ver: true }" x-init="setTimeout(() => ver = false, 4000)" x-show="ver" x-transition`, con `role="status"`.

**Error de campo:** `x-transition:enter="transition ease-out duration-200"` desde `opacity-0 -translate-y-0.5`.

Para ocultar antes de que cargue Alpine, agrega `[x-cloak]{display:none !important}` en `app.css` si no existe.

---

## 6. Tokens para `resources/css/app.css`

Referencia de lo que se agregará al bloque `@theme` cuando se aplique esta guía (todavía no está aplicado):

```css
@theme {
  /* Tipografía */
  --font-sans: 'Inter Variable', Inter, ui-sans-serif, system-ui, sans-serif;
  --font-display: 'Plus Jakarta Sans Variable', 'Plus Jakarta Sans', var(--font-sans);

  /* Azul marino institucional */
  --color-udg-50:  #f3f6fb;  --color-udg-100: #e4eaf4;  --color-udg-200: #c9d4e7;
  --color-udg-300: #9fb1d1;  --color-udg-400: #6c84b0;  --color-udg-500: #45608f;
  --color-udg-600: #324a75;  --color-udg-700: #26395d;  --color-udg-800: #1f3052;
  --color-udg-900: #1b2a4a;  --color-udg-950: #111c33;

  /* Azul real (interacción) */
  --color-real-50:  #eef4ff; --color-real-100: #dce7fe; --color-real-200: #b9cffc;
  --color-real-500: #2f5fd0; --color-real-600: #2350b8; --color-real-700: #1c4196;

  /* Dorado (detalles) */
  --color-oro-50:  #fbf7ec;  --color-oro-100: #f5ebcf;  --color-oro-300: #e2c27a;
  --color-oro-400: #d4a94f;  --color-oro-500: #c08f2e;  --color-oro-700: #8a6416;

  /* Foco institucional (reemplaza el azul de la plantilla) */
  --shadow-focus-ring: 0 0 0 4px rgb(47 95 208 / 0.20);
}
```

Las familias pastel de estatus (`sky`, `violet`, `indigo`, `amber`, `emerald`, `teal`, `rose`, `slate`, `stone`) ya vienen en la paleta por defecto de Tailwind v4, porque `app.css` no reinicia `--color-*`. No hace falta declararlas.

> Ojo: `app.css` redefine `blue-*` con el azul `#465FFF` de la plantilla. Por eso esta guía usa `real-*` y no `blue-*` para el azul institucional.

---

## 7. Checklist para cada vista nueva o rediseñada

- [ ] Fondo `bg-gray-50`, contenido en tarjetas blancas con `border-gray-200 rounded-xl`.
- [ ] Un solo H1 con `font-display`; texto y tablas en Inter.
- [ ] Una sola acción primaria visible (`bg-udg-800`).
- [ ] El dorado aparece como detalle y no ocupa más de ~5 % de la pantalla.
- [ ] Estatus con `<x-ui.estatus>`: pastel, con etiqueta en texto.
- [ ] Acciones no permitidas con `x-ui.accion-bloqueada` y su motivo en español.
- [ ] Foco visible en todos los controles (`focus-visible:ring-4 ring-real-500/20`).
- [ ] Contraste AA verificado; sin texto dorado claro.
- [ ] Animaciones de 300 ms o menos, con `motion-reduce:`.
- [ ] Sin `dark:`, sin `style=""` y sin valores arbitrarios de color (`bg-[#...]`).
- [ ] Revisada a 375 px (móvil) y 1280 px (escritorio).
