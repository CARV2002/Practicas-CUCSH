# Revisión del sistema contra «Confirmación de sistema»

Fecha de revisión: 18/11/2026 · Rama `vistas` · 161 pruebas automáticas pasando.

Leyenda: ✅ cumple · 🟡 a medias · ❌ no cumple

---

## 0. Actores y roles (6, no 5)

El documento nombra 5 actores; con el administrador técnico son 6:

| Actor | ¿Tiene cuenta en el sistema? | Estado |
|---|---|---|
| Administrador técnico | Sí (`administrador`): cuentas, catálogo, auditoría; entra a todo | ✅ |
| Vinculación (Lorena) | Sí (`vinculacion`): convenios, empresas, dictámenes, documentos físicos, informes | ✅ |
| Coordinación de carrera | Sí (`coordinador`), con alcance por carrera o división | ✅ |
| **Profesor supervisor** | **No.** Es solo un catálogo (`profesores`: nombre, correo, división) que la coordinación asigna a la práctica. No inicia sesión ni aprueba reportes | ❌ |
| Empresa | Sí (`empresa`) | ✅ |
| Alumno | Sí (`alumno`), también con Google institucional | ✅ |

**Hallazgo principal:** el documento dice que el profesor supervisor «entra al panel, lee el PDF y aprueba el reporte sumando las horas». Hoy eso lo hace la Coordinación. Hay que decidir: o se crea el rol `profesor` (sexto rol con sesión) o se ajusta el documento para decir que la Coordinación valida y el profesor solo queda registrado.

---

## 1. Puntos críticos del proceso CUCSH

| # | Requisito | Estado | Qué hace hoy el sistema | Qué falta |
|---|---|---|---|---|
| 1.1 | IMSS obligatorio antes de la carta de presentación | ✅ | Sin constancia subida, validada **y vigente** el alumno no puede postularse; la coordinación no puede emitir la carta (`Requisitos::emitirCarta`). La vigencia se captura y debe cubrir la práctica. | — |
| 1.2 | Recordar la inscripción de la materia en SIIAU | ❌ | SIIAU solo aparece al final (la coordinación registra que Control Escolar capturó la acreditación). Nunca se le pide ni recuerda al alumno inscribir la materia. | Casilla «Ya inscribí la materia en SIIAU (NRC / ciclo)» en el perfil o al activar la práctica, recordatorio al inicio del ciclo y aviso a la coordinación si no está confirmada. |
| 1.3 | Bloquear postulaciones si el convenio está por vencer | 🟡 | La vacante deja de verse cuando el convenio vence y **la práctica no se activa** si el convenio no cubre hasta su fecha de fin (`ActivarPractica`). Pero el alumno sí puede postularse a un convenio al que le quedan, p. ej., 2 meses. | Bloquear (o advertir) la postulación cuando el convenio vence antes del fin estimado del programa o en menos de N días (sugerido: 6 meses, ver 3.1). |
| 1.4 | Baja temporal, abandono, cancelación o cambio de plaza | 🟡 | La coordinación puede **cancelar** una práctica activa y avisa al alumno y a la empresa. | No pide motivo, no distingue baja temporal / renuncia / despido, no existe «cambio de plaza» y no deja decidir si las horas se **conservan o se reinician**. Además el alumno queda bloqueado («consulta a tu coordinación») sin un flujo para reubicarlo. |

---

## 2. Lo que debe automatizar la propuesta

| # | Requisito | Estado | Detalle |
|---|---|---|---|
| 2.1 | Sustituir el Google Form | ✅ | Ver §5. Todos los campos del formulario existen en el registro de la empresa, su expediente y la solicitud de convenio. |
| 2.2 | Sustituir la plantilla manual: datos capturados una vez e inyectados en el contrato | 🟡 | El Formato de PP se genera completo y fiel (§6). Pero las **declaraciones de la entidad** (constitución, atribuciones, facultad del firmante) las teclea Lorena cada vez que genera el borrador; no las captura la empresa. Se genera en **Word**, no en PDF. |
| 2.3 | Decisiones cruzadas por carrera | ✅ / 🟡 | ✅ La solicitud se divide en un dictamen por carrera, cada coordinación vota en paralelo, el contrato solo incluye las aprobadas, la vacante solo se aprueba para carreras con dictamen aprobatorio, y si todas rechazan el trámite muere solo. 🟡 Cuando se rechaza, el aviso a la empresa da el motivo pero **no le dice que debe iniciar de nuevo todo el trámite** (el documento lo pide). |
| 2.4 | Reglas por licenciatura | ✅ | Horas, % de créditos mínimo y créditos otorgados (catálogo del administrador técnico) + periodicidad de reportes (cada coordinación en «Reglas de mi carrera»). Se congelan al activar la práctica. Hay 16 carreras en 6 divisiones (el documento dice 15: confirmar). |
| 2.5 | Bolsa de trabajo filtrada | ✅ | Solo vacantes publicadas, aprobadas por la coordinación, de convenio vigente, con cupo y de **su carrera**. El % de créditos se exige al postularse (el alumno sí ve la vacante aunque no cumpla; ve el motivo del bloqueo). |
| 2.6 | Repositorio documental privado | ✅ | Todo archivo va al disco `local` (no público), se descarga solo por rutas con permisos (policies) y queda en la bitácora de auditoría. |

---

## 3. Mejoras propuestas en el documento

| # | Mejora | Estado | Detalle |
|---|---|---|---|
| 3.1 | Semáforo de vigencia con 6 meses de anticipación, por correo a Lorena y a la empresa | 🟡 | Existen avisos automáticos a **60 y 30 días** (campana a Vinculación, correo a la empresa) y la renovación se habilita a 60 días. Falta el umbral de **6 meses**, el correo a Lorena (hoy solo campana) y los colores del semáforo (verde > 6 m, ámbar 6–2 m, rojo < 2 m). |
| 3.2 | Oficios con código QR de autenticidad | ❌ | No hay QR ni página pública de verificación. La carta de presentación se genera en Word; el oficio de conclusión lo **sube** la coordinación (no lo genera el sistema). |
| 3.3 | Seguimiento tipo paquetería (general para empresa y alumno, detallado para Lorena) | ✅ / 🟡 | ✅ Empresa: pasos generales; Lorena: estado detallado, responsable, días hábiles contra el máximo documentado y control de documentos físicos. Alumno: avance de su práctica. 🟡 Falta el **estimado de respuesta** que pide el documento («respuesta estimada: 5 a 15 días hábiles»); los plazos ya existen en `EtapaConvenio::diasHabilesDocumentados()`, solo no se muestran a la empresa. |
| 3.4 | Calculadora de horas y bloqueo hasta el 100 % | ✅ | Las horas solo suman con reportes **validados**; barra de avance para el alumno; la carta de término no se valida si faltan horas o hay reportes pendientes. |

---

## 4. Flujo híbrido

### Etapa A — Convenio nuevo (Vinculación)

| Paso | Estado | Comentario |
|---|---|---|
| Entrada digital (registro, datos legales, carreras, PDFs) | ✅ | |
| Validación de documentos y turno a coordinaciones | ✅ | Validar/rechazar por documento con motivo; turno con fechas de Secretaría y División; cada coordinación dictamina desde su panel. |
| Contrato limpio sin carreras rechazadas | 🟡 | Se genera en **.docx** (editable, útil para Jurídica), no en PDF; ver 2.2. |
| Salida al mundo físico (3 juegos, firma al calce, Jurídica, Rector) | ✅ | Etapas registradas + **control de documentos físicos** (dónde está cada juego, fecha de regreso, recordatorios). |
| Retorno y activación (escaneo único, vigencia, habilitar vacantes) | ✅ | Formalizar exige el PDF escaneado, folio y vigencia; al formalizar la empresa ya puede proponer vacantes. |

### Etapa B — Alumno y reportes (académico)

| Paso | Estado | Comentario |
|---|---|---|
| Comprobación académica (créditos, kardex, IMSS) | ✅ | El kardex y el % de créditos se entregan **por postulación**, no como «perfil autorizado» único. Funciona, pero difiere del texto. |
| Postulación y carta de presentación | 🟡 | El documento dice «cuando la **empresa** lo acepta, se genera la carta». En el sistema la **coordinación** emite la carta al validar IMSS/kardex/créditos, y la empresa no acepta ni rechaza postulantes dentro del sistema (solo los ve). Aclarar cuál es el proceso real; el sistema sigue el que se confirmó en la Fase 0. Modalidad interna UdeG: ✅ (oficio de aceptación interno). |
| Aceptación física (carta de aceptación) | ✅ | El alumno la sube; la coordinación activa la práctica y empieza el conteo. |
| Bitácoras híbridas | 🟡 | La periodicidad se respeta y el alumno sube el PDF firmado; **el sistema no genera el formato estandarizado** a partir de lo que el alumno redacta. |
| Auditoría y cierre | 🟡 | Validación de reportes (por la coordinación, ver §0), carta de término, oficio, envío a Control Escolar y captura en SIIAU ✅. El **Oficio de Conclusión** se sube escaneado; no lo genera el sistema ni lleva QR. |

---

## 5. Google Form «Propuestas de convenio para Prácticas Profesionales, CUCSH»

| Pregunta del formulario | Dónde vive en el sistema | Estado |
|---|---|---|
| Correo electrónico | Cuenta de la empresa (correo de acceso) | ✅ |
| Breve CV de la instancia | Expediente: «Currículum de la instancia» (PDF o Word) | ✅ |
| Fecha de recepción | Automática al enviar la solicitud | ✅ |
| Quién propone (nombre y filiación) | Contacto y cargo del registro | ✅ |
| Tipo de organismo | Registro (catálogo) | ✅ |
| Carreras solicitadas | Solicitud (chips; una o varias) | ✅ |
| Objetivos | Solicitud | ✅ |
| Actividades propuestas | Solicitud (se inyecta en la cláusula QUINTA-VIII) | ✅ |
| Plazas por semestre | Solicitud («plazas estimadas por ciclo») | ✅ |
| Cómo se enteraron | Solicitud (opcional) | ✅ |
| Practicantes recibidos y desde cuándo | Solicitud («experiencia previa», opcional) | ✅ |
| Datos generales de la institución | Perfil: descripción, domicilio fiscal, teléfono | ✅ |
| Principales proyectos / clientes | Perfil: `proyectos_principales` | ✅ |
| Identificación del representante legal | Expediente | ✅ |
| Acta constitutiva | Expediente | ✅ |
| Constancia de situación fiscal | Expediente | ✅ |
| *(nuevo)* Comprobante de domicilio, poder notarial (opcional), representante legal | Expediente / datos legales | ➕ |

**Conclusión:** el formulario está sustituido por completo y mejorado (validación por documento, motivo de rechazo, avisos, historial). Solo verificar con Lorena si el comprobante de domicilio y el poder notarial se piden realmente.

---

## 6. FORMATO DE PP (convenio específico)

Comparado cláusula por cláusula contra `FORMATO DE PP.doc` (`GeneradorBorradorConvenio`):

- ✅ Preámbulo, antecedentes, declaraciones del CUCSH (con domicilio) y de la ENTIDAD (I–V, con RFC), cláusulas PRIMERA a DUODÉCIMA, cierre «por triplicado» y bloque de firmas: **texto idéntico**.
- ✅ Sector (público/privado/social) según el tipo de organismo; plan de trabajo (QUINTA-VIII) con las actividades y **solo las carreras aprobadas**; estímulo económico opcional; rectora y domicilio desde configuración; versión v1, v2… por cada corrección de Jurídica.
- 🟡 Antecedentes sin numeración romana (I., II.) como en el original.
- 🟡 Declaraciones I–III (constitución, objeto/atribuciones, facultad) y cargo del firmante: **las captura Lorena** al generar, en vez de la empresa al registrarse.
- 🟡 Vigencia fija «tres años» en UNDÉCIMA (igual que el formato), pero el sistema permite formalizar 2–5 años solo con advertencia: si la vigencia real difiere, el texto no lo refleja.

---

## 7. Coherencia y procesos inicio → fin

| Proceso | Inicio → fin | ¿Bien cerrado? |
|---|---|---|
| Convenio | Solicitud → documentos → dictámenes → borrador → Jurídica (observado vuelve a borrador) → firmas → formalizado / rechazado → vence → renovación | ✅ Todos los estados tienen salida. 🟡 Tras «rechazado» falta decirle a la empresa que puede (y debe) empezar de nuevo. |
| Vacante | Propuesta → aprobación de la coordinación → publicada → cerrada / sin cupo / convenio vencido | ✅ |
| Alumno | Registro → IMSS → postulación → carta de presentación → carta de aceptación → práctica en curso → reportes → carta de término → oficio → SIIAU → acreditado | ✅ |
| Práctica cancelada | Cancelada → ¿? | ❌ Termina en un callejón sin salida: no hay reasignación ni decisión sobre las horas (1.4). |
| Reporte | Registrado → firmado y subido → validado / rechazado → (re-subir) | ✅ |
| Documentos físicos | Por imprimir → fuera de la oficina con fecha de regreso → firmado → entregado / archivado | ✅ con recordatorios automáticos. |

Coherencia de interfaz: todas las vistas usan los mismos componentes (encabezado, tabla, filtros por estado, insignias pastel, botones de ícono, textos para vacíos).

---

## 8. Software, paquetes y librerías

### Lo que se usa y para qué

| Paquete | Uso | Valoración |
|---|---|---|
| Laravel 12 (PHP 8.2) | Base: rutas, políticas de acceso, colas, notificaciones, tareas programadas | ✅ |
| spatie/laravel-permission | Los 5 roles con sesión y el middleware `role:` | ✅ |
| spatie/laravel-medialibrary | Expedientes, versiones de borrador, escaneos en disco privado, estado de validación por archivo | ✅ Ahorra mucho código. |
| phpoffice/phpword | Genera el Formato de PP y la carta de presentación en .docx | ✅ |
| laravel/socialite | Inicio de sesión con Google institucional | ✅ |
| laravel-lang/common | Mensajes de validación en español | ✅ |
| Pest 4 | 161 pruebas de flujo, permisos y transiciones | ✅ Muy buena cobertura de procesos. |
| Tailwind CSS 4 + Vite 7 | Sistema de diseño (`DESIGN_SYSTEM.md`) | ✅ |
| Alpine.js | Interacciones (modales, filtros, contadores) sin framework pesado | ✅ |
| flatpickr | Selector de fechas | ✅ |
| Inter / Plus Jakarta Sans (fontsource) | Tipografías servidas localmente (sin Google Fonts) | ✅ Bien por privacidad. |
| Notificaciones en cola (`ShouldQueue`) + `practicas:avisos-programados` (diario 7:30) | Campana, correos y recordatorios | ✅ pero ver requisitos de operación. |

### Sobrantes o a revisar

- `laravel/sail` (Docker): no se usa con XAMPP; se puede quitar.
- `axios`: viene de la plantilla; no hay llamadas en el código propio. Quitar si no se usa.
- `laravel/breeze`: solo desarrollo; ya cumplió su función.

### Requisitos de operación (importantes)

- `QUEUE_CONNECTION=database`: **sin `php artisan queue:work` corriendo no sale ningún aviso ni correo.** En el servidor, dejarlo como servicio (Supervisor o el Programador de tareas de Windows).
- `MAIL_MAILER=log`: hoy los correos solo se escriben en el log. En producción, configurar SMTP institucional.
- Programador: `php artisan schedule:run` cada minuto para los avisos diarios.

### Librerías sugeridas para cubrir lo que falta

| Necesidad | Opción |
|---|---|
| PDF del contrato, carta y oficios | LibreOffice sin interfaz para convertir el .docx a PDF (respeta el formato oficial), o `barryvdh/laravel-dompdf` para oficios en HTML |
| QR de autenticidad | `endroid/qr-code` (o `simplesoftwareio/simple-qrcode`) + ruta pública `/verificar/{codigo}` con firma (`URL::signedRoute`) |
| Respaldos | `spatie/laravel-backup` (base de datos + expedientes) |
| Exportes a Excel | `maatwebsite/excel` si los CSV actuales no bastan |

---

## 9. Propuestas de mejora (priorizadas)

**Alta (las pide el documento):**
1. **Flujo de baja / cambio de plaza:** motivo (baja temporal, renuncia, despido, cambio), decisión de la coordinación «conservar horas / reiniciar», y reubicación del alumno en otra vacante sin perder el expediente.
2. **SIIAU:** confirmación de la materia inscrita (NRC y ciclo) + recordatorio al alumno y alerta a la coordinación.
3. **Bloqueo por vigencia en la postulación** (no solo en la activación).
4. **Semáforo de 6 meses** con correo a Lorena y a la empresa, y colores verde / ámbar / rojo en la bandeja.
5. **QR de autenticidad** en carta de presentación, oficio de asignación y oficio de conclusión, con página de verificación.
6. **Rol de profesor supervisor** (o ajustar el documento): que valide reportes de sus practicantes.

**Media:**
7. Generar el **Oficio de Conclusión** y el **formato de reporte** desde el sistema (el alumno captura actividades → PDF estandarizado listo para firmar).
8. Que la **empresa capture sus declaraciones legales** (constitución, objeto, facultad del firmante, cargo) una sola vez en «Documentos», y que el generador las use sin que Lorena las teclee.
9. Mostrar a la empresa y al alumno un **estimado de respuesta** por etapa.
10. Aviso de rechazo de convenio con el texto «Para continuar debes iniciar una nueva solicitud» y botón directo.
11. Exportar el contrato también en **PDF**; ajustar la cláusula UNDÉCIMA a la vigencia real.

**Baja / operación:**
12. Quitar `sail` y `axios` si no se usan; agregar respaldos automáticos.
13. Documentar el arranque del worker de colas y del programador en el README.
14. Confirmar el número de carreras (15 en el documento, 16 en el sistema).

---

## Resumen

| | Cumple | A medias | No cumple |
|---|---|---|---|
| Puntos críticos (4) | 1 | 2 | 1 |
| Automatización (6) | 4 | 2 | 0 |
| Mejoras propuestas (4) | 1 | 2 | 1 |
| Flujo A (5 pasos) | 4 | 1 | 0 |
| Flujo B (5 pasos) | 2 | 3 | 0 |
| Actores (6) | 5 | 0 | 1 (profesor supervisor) |

El núcleo (convenios por carrera, expedientes, reglas por carrera, bolsa filtrada, horas, cierre, control físico) está sólido y probado. Lo pendiente se concentra en: **bajas/cambio de plaza, SIIAU, QR, documentos generados en PDF y el rol de profesor**.

---

## 10. Actualización tras las aclaraciones (18/11/2026)

Decisiones confirmadas en `aclaraciones.txt` y cambios aplicados:

| Punto | Decisión | Estado ahora |
|---|---|---|
| Profesor supervisor | Catálogo de cada coordinación, **sin sesión**. La coordinación valida los reportes y suma las horas. | ✅ Así funciona el sistema (5 roles con sesión + profesor como catálogo = 6 actores). |
| Alertas de vigencia | **60 y 30 días**, por correo a Vinculación y a la empresa. | ✅ Antes Vinculación solo recibía la campana; ahora también correo. |
| Documento generado | Solo el **Formato de PP**: Word llenado automáticamente y **exportado a PDF** listo para imprimir. Lo demás se sube escaneado. | ✅ Word + PDF con LibreOffice (`App\Services\ConvertidorPdf`). |
| Carta de presentación | La emite la coordinación antes de que la empresa acepte al alumno. | ✅ Es el flujo programado. |
| SIIAU | Aviso permanente en el panel del alumno + casilla obligatoria «bajo protesta» al postularse. | ✅ Se guarda la fecha de la declaración (`postulaciones.siiau_confirmado_at`). |
| Código QR | Descartado. | — |
| Declaraciones del Word | La empresa las captura al registrarse (y puede corregirlas en Documentos); Lorena solo las revisa contra el acta. | ✅ Representante, cargo, domicilio legal, constitución, objeto social y facultad del firmante van solos al convenio. |
| Cláusula UNDÉCIMA | Duración variable (1 a 5 años) elegida por Lorena; calcula el fin de vigencia. | ✅ Al generar el borrador y al formalizar; fin = inicio + N años − 1 día. |

Además se aplicaron las mejoras pendientes del §9:

- **Bloqueo por vigencia en la postulación:** un convenio con menos de 180 días de vigencia (≈ un semestre) ya no muestra sus vacantes al alumno ni admite postulaciones nuevas.
- **Baja o cambio de plaza:** la coordinación registra el motivo (baja temporal, renuncia, terminación por la organización, cambio de plaza u otro), lo que pasó y si las horas validadas **se conservan o se reinician**. Si se conservan, la siguiente práctica arranca con esas horas reconocidas. El alumno ya no queda bloqueado.
- **Rechazo de convenio:** el aviso y la página de la empresa indican que debe **iniciar de nuevo todo el trámite** (el expediente se conserva; las carreras vuelven a dictaminar).
- **Estimado de respuesta** para la empresa en cada fase, con los plazos documentados.
- Antecedentes del Formato de PP numerados (I., II.).
- Se quitaron `laravel/sail` y `axios` (no se usaban) y el README documenta la puesta en marcha (cola, programador, SMTP y LibreOffice).

### Proceso confirmado (inicio → fin)

**Convenio**
1. La empresa se registra con sus datos fiscales **y legales** (sustituye al Google Form) y sube su expediente en PDF.
2. Solicita el convenio: carreras, objetivos, actividades, plazas, cómo se enteró y experiencia previa.
3. Vinculación valida cada documento (con motivo si rechaza) y turna a las carreras; registra el paso por Secretaría y División.
4. Cada coordinación emite su dictamen. Si todas rechazan, el trámite se cierra y la empresa debe iniciar de nuevo.
5. Vinculación elige la **vigencia (años)** y genera el Formato de PP: el sistema inyecta las declaraciones de la empresa y solo las carreras aprobadas → **Word + PDF**.
6. Jurídica: aprobado → firmas; observado → nueva versión del borrador. Los 3 juegos se controlan en **Documentos físicos**.
7. Firma de la empresa y de Rectoría → Vinculación sube el escaneo, captura folio y fecha de inicio; el fin se calcula solo → **formalizado**.
8. Avisos por correo a Vinculación y a la empresa a los **60 y 30 días** del vencimiento; la renovación se habilita a 60 días.

**Alumno**
1. Ve el aviso de inscribir la materia en SIIAU.
2. Sube su IMSS; la coordinación lo valida.
3. Se postula (créditos + kardex + **casilla de SIIAU**) solo a vacantes de su carrera con convenio vigente por al menos 180 días.
4. La coordinación emite la carta de presentación → el alumno sube la carta de aceptación → la coordinación activa la práctica.
5. Reportes firmados y escaneados → la coordinación los valida y suman horas.
6. Si hay baja o cambio de plaza, la coordinación decide conservar o reiniciar las horas.
7. Al 100 %: carta de término → oficio → Control Escolar → captura en SIIAU → acreditado.

**Pruebas:** 167 pasando (incluye `tests/Feature/AclaracionesTest.php`). Ejemplo real del PDF generado: `docs/ejemplo-formato-pp-v2.pdf`.
