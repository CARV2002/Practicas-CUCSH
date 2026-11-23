# Manual operativo y flujo completo (0 → 100) — versión revisada

Revisión del documento «proceso-completo» contra el código de la rama `vistas` (22/11/2026).
Cada paso quedó **confirmado (✅)**, **corregido (✏️: el texto original no describía lo que hace el sistema)** o **pendiente (❌: el sistema aún no lo hace)**.
216 pruebas automáticas pasan.

---

## 0. Resumen de correcciones

| # | Usuario | El documento decía | El sistema hace | Tipo |
|---|---|---|---|---|
| 1 | Administrador | Supervisión de **solo lectura** | **Resuelto:** consulta convenios, empresas y documentos físicos, pero toda acción del trámite responde 403 y sus botones no aparecen | ✅ (corregido) |
| 2 | Administrador | Enlace de contraseña vence en 24–48 h | **Confirmado en 72 h** (cubre fines de semana y días inhábiles) | ✏️ |
| 3 | Administrador / Coordinación | La coordinación define horas, % de créditos y créditos | **Resuelto:** solo la coordinación los edita; la carrera nace con 300 h, 50 % y 8 créditos | ✅ (corregido) |
| 4 | Empresa | Se registra con «nombre comercial» | Se registra con **razón social**, RFC, tipo de organismo y persona de contacto; los datos fiscales y notariales van después en «Mi convenio» | ✏️ |
| 5 | Empresa | Al inicio solo está habilitado «Mi convenio» | Habilitados: **Panel, Mi convenio y Documentos**; bloqueados: **Programas y Practicantes** | ✏️ |
| 6 | Empresa / Alumno | La empresa acepta al alumno | La empresa **no acepta ni rechaza en el sistema**: entrega su carta física; el alumno (o la coordinación) la sube | ✏️ |
| 7 | Vinculación | Lorena aprueba y «avanza» el trámite | Al validar el **último** documento, el sistema turna solo a las coordinaciones | ✏️ |
| 8 | Vinculación | Un botón genera el convenio | Un formulario **ya prellenado** (años de vigencia, lugar y fecha de firma, apoyo económico) genera **Word + PDF** por versión (v1, v2…) | ✏️ |
| 9 | Coordinación | Mantiene el catálogo de profesores supervisores | **No hay pantalla** para dar de alta profesores: solo se asignan los que ya existen | ❌ |
| 10 | Coordinación | Revisa kardex e IMSS **cuando el alumno se postula** | Los revisa **antes**: sin expediente validado el alumno no ve la oferta | ✏️ |
| 11 | Coordinación | Sube la carta de presentación firmada | El sistema **la genera** (Word) con los datos del alumno al emitirla | ✏️ |
| 12 | Coordinación | Emite el oficio de acreditación | **Sube** el oficio firmado en PDF; el sistema no lo genera | ✏️ |
| 13 | Alumno | Captura % de créditos y casilla SIIAU al registrarse | Registro: código, carrera, **grado y turno**. El % va con el kardex; la casilla SIIAU, **al postularse** | ✏️ |
| 14 | Alumno | Puede registrar una plaza interna | Los proyectos internos los **publica la coordinación**; el alumno se postula como a cualquier vacante | ✏️ |
| 15 | Alumno | «Máximo histórico de 2 cancelaciones» | El límite es de **2 bajas de práctica**; el alumno no puede cancelar postulaciones por su cuenta | ✏️ |
| 16 | Alumno | Redacta actividades y descarga el formato generado | Captura periodo y horas y **sube su propio PDF** firmado; el sistema **no genera** el formato | ❌ |
| 17 | Vinculación | Estado `rechazado_definitivo` | El estado se llama **`rechazado`** | ✏️ |

**Corregido en el código durante esta revisión:**

- **Vacantes por URL directa**: el servidor solo comprobaba que el convenio fuera de la empresa. Ahora también exige que esté **vigente** y que la carrera tenga **dictamen aprobatorio** (`app/Http/Controllers/Empresa/ProgramaController.php`). Antes, con una petición armada a mano, se podía crear una vacante para una carrera que rechazó el convenio y avisar a esa coordinación.
- **Enlace de activación vencido** (excepción 2): la pantalla mostraba el formulario y el error salía hasta después de escribir la contraseña. Ahora detecta el enlace vencido o ya usado **al abrirlo** y ofrece «Solicitar un enlace nuevo» (`ActivarCuentaController`, `activar-cuenta.blade.php`).

---

## 1. Usuarios del sistema

| Usuario | Sesión | Panel |
|---|---|---|
| Administrador general (Coordinación de Servicios Académicos) | Sí | `/admin` + Administración del sistema |
| Vinculación (Lorena Lozada) | Sí | `/admin` |
| Coordinación de carrera (15 carreras en 5 divisiones; alcance por **carrera o por división**) | Sí | `/coordinacion` |
| Empresa / entidad receptora | Sí (registro público) | `/empresa` |
| Estudiante | Sí (registro público o Google `@alumnos.udg.mx`) | `/alumno` |
| Profesor supervisor, Secretaría Académica, División, Unidad Jurídica, Rectoría, Control Escolar | **No**: sus pasos los registra Vinculación o la coordinación | — |

---

## 2. 🛡️ Administrador general

**Propósito:** estructura del CUCSH, cuentas del personal y supervisión.

| Paso | Qué hace | Estado |
|---|---|---|
| 1. Catálogo | Da de alta y edita las **5 divisiones** y las **15 carreras**, pero solo su **estructura** (división, clave y nombre). Una carrera nueva nace con **300 h, 50 % de créditos y 8 créditos**; desde ahí **solo su coordinación** ajusta esas reglas (el administrador las ve, pero no las edita). **No hay botón de borrar**, así que nunca se pierde historial. | ✅ (corregido) |
| 2. Cuentas de personal | Crea coordinaciones (con carrera **o** división), la cuenta de Vinculación y otros administradores. Solo acepta **correo institucional** (`@academicos.udg.mx`, `@csh.udg.mx`, `@udg.mx`); un correo repetido se frena con «Este correo ya se encuentra registrado en el sistema.». **Máximo 2 cuentas activas por coordinación** (titular y asistente o secretario administrativo): el formulario marca las coordinaciones completas como «2/2 activos · completa» y una tercera alta se bloquea con «Esta coordinación ya alcanzó el límite máximo de 2 encargados activos. Desactiva una cuenta anterior si deseas registrar a alguien nuevo.». Nadie escribe contraseñas: se envía un enlace de **72 h**; al usarlo, el correo queda verificado. Una coordinación nueva recibe un resumen de lo que ya esperaba en su carrera. | ✅ (corregido) |
| 3. Relevo y gestión de cuentas | Cuando cambia un coordinador, **no se borra** la cuenta: se **desactiva** (queda en la bitácora y conserva su historial) y el cupo se libera al instante. Reactivar una cuenta también respeta el límite de 2. Además reenvía el enlace de contraseña y puede marcar un correo como verificado. | ✅ (corregido) |
| 4. Supervisión | Panel general con métricas globales, **Informes** exportables (prácticas, postulaciones, convenios, programas, empresas, documentos físicos) y **Bitácora de auditoría** (quién, qué, cuándo, IP y navegador). | ✅ |
| 5. Alcance | **No entra** a las bandejas académicas (no valida kardex, vacantes, reportes ni cierres) y en convenios es de **solo lectura**: ve un aviso «Supervisión de solo lectura» y ningún control de operación; el servidor rechaza cualquier acción (403). | ✅ (corregido) |

---

## 3. 🏢 Empresa / entidad receptora

**Propósito:** formalizar el convenio, ofertar vacantes y dar seguimiento físico a sus practicantes.

| Paso | Qué hace | Estado |
|---|---|---|
| 1. Cuenta | Registro público: **razón social, RFC** (12 moral / 13 física, con fecha válida y sin genéricos), **tipo de organismo** (público, privado, sociedad civil u otro), nombre, cargo y teléfono del contacto, correo, contraseña y aviso de privacidad. Debe **verificar su correo**. Menú: Panel, Mi convenio y Documentos abiertos; **Programas y Practicantes con candado**. | ✅ ✏️ |
| 2. Solicitud | «Mi convenio» por **secciones con borrador** (datos generales, declaraciones legales y notariales, perfiles): domicilio fiscal (con código postal), representante legal y cargo, escritura o nombramiento (número, fecha, notario, folio mercantil), objeto social, facultad del firmante, **carreras solicitadas**, objetivos, actividades, plazas por ciclo, cómo se enteró y experiencia previa. En **Documentos** sube los PDF (máx. **5 MB** cada uno) que pide su tipo de organismo: CV de la instancia, INE del representante, acta constitutiva **o** documento de creación y nombramiento (sector público), constancia de situación fiscal, comprobante de domicilio y, si aplica, poder notarial. | ✅ |
| 3. Seguimiento | Barra de 5 pasos (documentos → revisión académica → jurídica → firmas → formalizado) con un texto de qué esperar en cada uno. Si Vinculación devuelve un documento, recibe **correo y campana con el motivo** y reemplaza **solo ese** PDF. Si todo se rechaza, se le indica que debe **iniciar una solicitud nueva** (su expediente se conserva). | ✅ |
| 4. Firma física | Vinculación le avisa cuando los **3 juegos** están listos. Su representante firma al calce y estampa firma y sello en la última hoja; los devuelve. Si tarda, el sistema le **recuerda** devolver los juegos. | ✅ |
| 5. Vacantes | Con convenio **formalizado y vigente** se desbloquea Programas. Cada vacante lleva convenio, **carrera aprobada en ese convenio**, ciclo (p. ej. 2027A), título, descripción, actividades, perfil, horario, sede y cupos. Nace **pendiente** hasta que la coordinación la aprueba. Puede editarla (vuelve a revisión), cerrarla o borrarla si no tiene postulaciones. Los alumnos solo la ven si al convenio le quedan **≥ 180 días** de vigencia. | ✅ (reforzado hoy) |
| 6. Practicantes | Ve a los alumnos **desde que la coordinación emite su carta de presentación**. No acepta ni rechaza en el sistema: entrevista, entrega su **carta de aceptación** física, firma y sella los reportes impresos, puede dejar **retroalimentación** en cada reporte y al final entrega la **carta de término**. | ✅ ✏️ |
| 7. Renovación | Recibe correo a los **60 y 30 días** del vencimiento; puede solicitar la renovación desde 60 días antes. | ✅ (no estaba en el documento) |

---

## 4. 📋 Vinculación (Lorena Lozada)

**Propósito:** el ciclo legal del convenio. No opera alumnos ni vacantes.

| Paso | Qué hace | Estado |
|---|---|---|
| 1. Revisión legal | Revisa los datos notariales contra el acta y valida o rechaza **cada PDF** (el rechazo **exige motivo**; lo validado ya no se puede rechazar). Al validar el **último** documento, el sistema **turna solo** la propuesta a las coordinaciones elegidas. Registra además las fechas de paso por **Secretaría Académica y División**. | ✅ ✏️ |
| 2. Dictámenes por carrera | Ve en tiempo real quién ya votó. Cada coordinación sube su dictamen en PDF; uno negativo exige observaciones. Con al menos una aprobatoria, el trámite sigue **solo con esas carreras**; si todas rechazan, pasa a **`rechazado`** sin intervención. | ✅ |
| 3. Borrador | Formulario prellenado con las declaraciones de la empresa (las puede corregir) + **años de vigencia (1–5)**, lugar y fecha de firma y apoyo económico opcional. Genera el Formato de PP en **Word + PDF**; la cláusula UNDÉCIMA usa los años elegidos, que **quedan congelados** para la formalización. Si adjunta un borrador redactado fuera del sistema, debe indicar sus años. Cada corrección crea una nueva versión (v1, v2…). | ✅ ✏️ |
| 4. Jurídica | Turna el borrador y registra el dictamen: **aprobado** → firmas; **observado** → regresa a redacción para corregir y regenerar; **rechazado** → se cierra el trámite. Imprime los 3 juegos y avisa a la empresa. | ✅ |
| 5. Firmas | Registra la firma de la empresa y luego la de Rectoría. En **Documentos físicos** controla dónde está cada juego, con fecha de regreso y recordatorios. | ✅ |
| 6. Formalización | Sube el **PDF escaneado** con todas las firmas y captura solo **folio** (único) y **fecha de inicio**. Los años **no se eligen**: se toman del último borrador (lo que dice la cláusula UNDÉCIMA firmada) y el fin se calcula como inicio + N años − 1 día. Se avisa a la empresa y se desbloquean sus vacantes. Alertas por **correo a Vinculación y a la empresa** a los **60 y 30 días**. | ✅ (corregido) |
| 7. Otros | Rechazar un trámite con motivo, editar datos o desactivar una empresa, consultar informes de convenios. | ✅ (no estaba en el documento) |

---

## 5. 🎓 Coordinación de carrera

**Propósito:** reglas de su carrera, dictámenes, vacantes, expediente del alumno, reportes y liberación.

| Paso | Qué hace | Estado |
|---|---|---|
| 1. Reglas | En «Reglas de mi carrera» (es la **única** que las edita): horas requeridas, % mínimo de créditos, créditos otorgados, **periodicidad de reportes** (mensual + final, bimestral + final, o parcial + final) y correo/teléfono de contacto. Las reglas se **congelan** en cada práctica al activarla. La coordinación puede tener hasta **2 cuentas activas** (titular y asistente). | ✅ |
| 1b. Profesores supervisores | Asigna un profesor de **la división de la carrera** al activar la práctica o después. **No puede dar de alta ni editar profesores**: no existe esa pantalla. | ❌ ver pendiente A |
| 2. Dictamen de convenio | Revisa la propuesta, vota aprobatorio o negativo, **sube su dictamen firmado en PDF (obligatorio en ambos casos)** y, si es negativo, explica el motivo. | ✅ |
| 3. Vacantes | Aprueba (se publica para los alumnos de su carrera) o rechaza con **motivo obligatorio**. Solo aprueba si el convenio está vigente y su carrera lo aprobó. También crea y publica **proyectos internos** del CUCSH. | ✅ |
| 4. Expediente del alumno | **Antes** de que el alumno vea la oferta, revisa juntos el **kardex** y la **constancia IMSS** (con su fecha de vigencia y el % de créditos declarado). Autoriza, o devuelve uno o ambos con motivo. | ✅ ✏️ (orden) |
| 4b. Carta de presentación | Cuando el alumno se postula, la emite con un botón: el sistema verifica expediente, créditos, cupo, convenio vigente, grado y turno, **reserva el cupo** y **genera la carta en Word**. Puede rechazar la postulación. | ✅ ✏️ |
| 5. Activación | Con la carta de aceptación subida (por el alumno o por ella), activa la práctica con fechas de inicio y fin y profesor. No se activa si el convenio o el IMSS no cubren el periodo. En proyectos internos exige el **oficio de aceptación interno**. | ✅ |
| 5b. Reportes | Valida o rechaza cada reporte (con evaluación del profesor y retroalimentación de la empresa); **solo los validados suman horas**. | ✅ |
| 5c. Bajas | Registra motivo y detalle; el tipo se resuelve solo: **sin reportes validados** → horas a 0; **baja justificada con reportes validados** → conserva las horas para su siguiente plaza; **sanción (despido, abandono, falta grave)** → horas anuladas y **bloqueo**. Avisa al alumno y a la empresa. Con **2 bajas** el alumno queda bloqueado; la coordinación puede hacer un **desbloqueo excepcional** con justificación. | ✅ |
| 6. Cierre | Valida la carta de término (solo con **100 % de horas** y sin reportes pendientes) → **sube** el oficio de acreditación firmado → marca **enviado a Control Escolar** → registra la **captura en SIIAU** → práctica **acreditada**. | ✅ ✏️ |

---

## 6. 🎒 Estudiante

**Propósito:** demostrar elegibilidad, postularse, entregar reportes y liberar.

| Paso | Qué hace | Estado |
|---|---|---|
| 1. Registro | Nombre, correo, contraseña, carrera, **código UdeG de 9 dígitos** (único), **grado y turno** y aviso de privacidad. También puede entrar con su cuenta Google `@alumnos.udg.mx`. Ve un aviso permanente de inscribir la materia en SIIAU. | ✅ ✏️ |
| 2. Expediente | En «Mis documentos» sube **kardex** y **constancia IMSS** (PDF, máx. 10 MB), la **fecha de vigencia** del IMSS y su **% de créditos**; si es menor al mínimo de su carrera, no puede enviarlo. Espera la validación de su coordinación (por correo). | ✅ ✏️ |
| 3. Postulación | Con el expediente validado se abre la oferta: solo vacantes **publicadas, de su carrera, con cupo** y de convenios con **≥ 180 días** de vigencia (incluye proyectos internos). Se postula a **una a la vez** y marca la casilla **«bajo protesta, ya inscribí la materia en SIIAU»**. | ✅ ✏️ |
| 4. Aceptación | Recibe aviso cuando su carta de presentación está lista; la descarga, la imprime y la lleva a la empresa. Sube en PDF la **carta de aceptación** que le entregan. | ✅ |
| 5. Reportes | Según la periodicidad de su carrera, registra tipo, periodo y horas y **sube su reporte en PDF** con firma y sello de la empresa. No puede reportar más horas de las requeridas ni periodos futuros. | ✅ ❌ (formato generado) |
| 6. Cierre | Al llegar al 100 % de horas validadas, sube la **carta de término**. Ve en su panel el avance hasta «acreditada». | ✅ |

---

## 7. Matriz de las 14 excepciones

| # | Excepción | Estado | Comportamiento real |
|---|---|---|---|
| 1 | Correo duplicado o mal escrito al dar de alta personal | ✅ | Se rechaza antes de crear la cuenta y enviar el enlace: formato, duplicado y **dominio institucional**. |
| 2 | Enlace de contraseña expirado | ✅ (corregido hoy) | Vence a las **72 h** o al usarse. Al abrirlo vencido, pantalla amigable con «Solicitar un enlace nuevo»; el administrador también puede reenviarlo. |
| 3 | Borrar división/carrera con historial | ✅ | No existe la acción de borrar: solo alta y edición. |
| 4 | RFC inválido, genérico o duplicado | ✅ | 12/13 caracteres, fecha AAMMDD válida, sin `XAXX010101000` ni `XEXX010101000`, único. |
| 5 | Archivo no PDF o mayor a 5 MB | ✅ | Se valida tipo real y tamaño por archivo; los textos notariales están en secciones con borrador y no se pierden. |
| 6 | Saltarse el menú bloqueado | ✅ (reforzado hoy) | La ruta real es `/empresa/lista-programas`: muestra el aviso de bloqueo sin formulario. Crear (`POST /empresa/programas`) se rechaza sin convenio vigente, y ahora también con una carrera sin dictamen aprobatorio o un convenio vencido. |
| 7 | Documento ilegible o vencido | ✅ | Motivo obligatorio, el trámite queda en revisión, la empresa recibe correo y reemplaza solo ese archivo. |
| 8 | Decisiones cruzadas | ✅ | El convenio sigue solo con las carreras aprobatorias; las rechazadas no aparecen en el PDF ni pueden recibir vacantes. |
| 9 | Rechazo total | ✅ ✏️ | El trámite pasa a **`rechazado`** solo; no se ofrece generar borrador. |
| 10 | Corrección pedida por Jurídica | ✅ | «Observado» regresa a redacción; Lorena edita declaraciones y años y regenera una versión nueva. |
| 11 | Kardex o IMSS inválido | ✅ ✏️ | Ocurre **antes** de postularse: la coordinación devuelve el documento con motivo y el alumno lo reemplaza. |
| 12 | Bajas a mitad de prácticas | ✅ | Los tres casos se resuelven exactamente como dice el documento, además del límite de 2 bajas y el desbloqueo excepcional. |
| 13 | Código, SIIAU o créditos | ✅ ✏️ | Código de 9 dígitos al registrarse; % de créditos al enviar el expediente; casilla SIIAU al postularse. |
| 14 | Concurrencia y límite | ✅ ✏️ | Una sola postulación o práctica en trámite (con bloqueo de filas). El límite de 2 es de **bajas**; se levanta con desbloqueo excepcional. |

---

## 8. Decisiones cerradas (22/11/2026) y pendientes

| # | Decisión | Cómo quedó en el sistema |
|---|---|---|
| 1 | El administrador es **solo lectura** en convenios | Las rutas de operación del trámite (documentos, turnos, borrador, Jurídica, firmas, formalización, rechazo, documentos físicos y edición de empresas) son solo de Vinculación; `ConvenioPolicy::gestionar` ya no incluye al administrador; las vistas le muestran el aviso «Supervisión de solo lectura» sin controles. |
| 2 | Horas y % de créditos: **solo la Coordinación** | El catálogo del administrador solo maneja división, clave y nombre; una carrera nueva nace con `Carrera::REGLAS_INICIALES` (300 h, 50 %, 8 créditos). |
| 3 | Años de vigencia **congelados en el borrador** | Al formalizar solo se capturan folio, fecha de inicio y escaneo; los años vienen del borrador (generado o adjuntado con sus años). Sin años registrados no se formaliza. |
| 4 | Enlace de contraseña: **72 h** | Sin cambios (ya era así). |
| 5 | **Máximo 2 cuentas activas por coordinación** (carrera o división) | Se valida en el alta y al reactivar; las coordinaciones completas aparecen deshabilitadas en el formulario; correo repetido con mensaje propio; el relevo se hace desactivando (queda en la bitácora). |

Pruebas: `tests/Feature/ReglasCerradasTest.php` (6 casos) y ajustes en auditoría, políticas, transiciones y el flujo completo.

**Pendientes de desarrollo:** los tres quedaron resueltos en la sección 9.

---

## 9. Ajustes de operación (23/11/2026)

### Administrador
| Ajuste | Cómo quedó |
|---|---|
| Cancelar el alta de un usuario | «Cancelar», la X, el fondo o Esc **borran lo capturado** (y los avisos de validación) para empezar limpio. |
| Sin duplicados | División y carrera: nombre único sin importar mayúsculas ni espacios (`App\Rules\UnicoNormalizado`); clave de carrera única. Empresa: RFC y **razón social** únicos (registro, solicitud y edición de Vinculación). Personal y alumnos: correo único; alumno: código UdeG único. |

### Vinculación (Lorena)
| Ajuste | Cómo quedó |
|---|---|
| Convenios por vencer | En el menú, **Convenios → Bandeja de convenios / Por vencer (N)**. La pantalla «Convenios por vencer» lista cada convenio de los próximos 60 días con días restantes, si ya pidió renovación, y accesos **Ir a la empresa** y **Ver trámite**. Las alertas del panel y el correo diario de 60/30 días llevan **directo al expediente de la empresa**. |
| Versiones del borrador | v1, v2… con insignia **Vigente / Obsoleta**. Solo la vigente tiene **PDF para imprimir**; el PDF de una versión obsoleta ya no se entrega (el Word queda como historial). Un borrador propio (.docx) también se convierte a PDF; si es PDF, ese mismo se imprime. |
| Vigencia al adjuntar v2 | El selector se **precarga con los años de la versión anterior** y lo indica («La versión anterior dice 2 años»). |
| Turnar a Jurídica | Congela la versión (no se adjunta otra hasta el dictamen), la bitácora anota **qué versión se turnó** y cómo. Si hay **correo de Jurídica configurado** (lo captura Vinculación en el mismo panel), se envía automáticamente con el borrador (Word + PDF) y los documentos constitutivos; Lorena puede desmarcar el envío y llevarlo impreso. Sin correo, solo se registra. |

### Empresa
| Ajuste | Cómo quedó |
|---|---|
| Rechazo por dictamen académico | Si **todas** las carreras dicen no: campana **y correo** con las observaciones de cada coordinación y la indicación de ajustar objetivos y actividades en «Mi convenio» y enviar una nueva solicitud. Si **algunas** dicen no: correo con las carreras excluidas y sus observaciones; el trámite sigue con las aprobadas. Cualquier otro rechazo (Vinculación o Jurídica) también llega por correo. |

### Coordinación
| Ajuste | Cómo quedó |
|---|---|
| Vista para dictaminar | Cada solicitud se despliega con pestañas **Objetivos y actividades** (con plazas, experiencia y carreras solicitadas), **La organización** y **Emitir dictamen**: tarjetas Aprobatorio / Negativo, observaciones (obligatorias si es negativo), dictamen firmado en PDF, confirmación y botón **Cancelar** que limpia y cierra sin registrar nada. |
| Profesores supervisores | Nueva pantalla **Profesores supervisores**: alta, edición (nombre, correo, teléfono, división) y activar/desactivar; solo divisiones de sus carreras; sin duplicados. Al activar una práctica o cambiar supervisor solo aparecen los **activos**. |
| Reportes | Ve lo que redactó el alumno; **no puede validar** un reporte hasta que tenga el escaneo firmado y sellado. |

### Alumno
| Ajuste | Cómo quedó |
|---|---|
| Reporte híbrido | En «Mis documentos» registra el **Reporte N** (número automático): tipo, periodo, horas, **actividades realizadas** y **logros y aprendizajes**. El sistema genera el **PDF institucional** (encabezado UdeG/CUCSH, datos del alumno y la organización, tabla de horas, actividades, logros y tres firmas: alumno, jefe directo con sello, Vo. Bo. coordinación/profesor). Lo descarga, recaba firmas, lo escanea y lo sube **en ese mismo reporte**. |
| Formato propio de la carrera | Marca la casilla y sube su formato ya firmado; los datos capturados siguen llevando el conteo de horas (la redacción es opcional en ese caso). |
| Carta de presentación | Al emitirla, el sistema genera el Word **y su PDF**; alumno y coordinación descargan el PDF (si LibreOffice no está disponible, el Word). |

Pruebas: `tests/Feature/AjustesOperacionTest.php` (9 casos) · total **216 pruebas pasando**.
