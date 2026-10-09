@extends('layouts.landing')

@section('content')

<style>
    /* ==========================================================================
   E COCONOS DE VINCULACIÓN INSTITUCIONAL B2B PREMIUM (CUCSH 2026)
   ========================================================================== */

/* Variables de Soporte y Suavizado */
:root {
    --b2b-radius-lg: 20px;
    --b2b-radius-md: 12px;
    --b2b-transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

html {
    scroll-behavior: smooth;
}

/* Reutilización semántica del contenedor */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
}

/* Componentes Comunes de Encabezados B2B */
.b2b-badge {
    display: inline-block;
    padding: 6px 14px;
    background-color: var(--color-brand-50);
    color: var(--color-brand-700);
    border-radius: 99px;
    font-size: var(--text-theme-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 16px;
}

.b2b-section-header {
    text-align: center;
    max-width: 720px;
    margin: 0 auto 64px auto;
}

.b2b-section-header h2 {
    font-size: var(--text-title-lg);
    line-height: var(--text-title-lg--line-height);
    color: var(--color-black);
    font-weight: 800;
    letter-spacing: -0.02em;
}

.b2b-section-header p {
    font-size: 16px;
    color: var(--color-gray-500);
    margin-top: 16px;
    line-height: 1.6;
}

/* --- SECCIÓN 1: PROOF DE LOGOTIPOS (Estilo Stripe/IBM) --- */
.b2b-social-proof {
    padding: 60px 0;
    background: var(--color-white);
    border-bottom: 1px solid var(--color-gray-100);
}

.proof-title {
    text-align: center;
    font-size: var(--text-theme-xs);
    text-transform: uppercase;
    color: var(--color-gray-400);
    letter-spacing: 0.08em;
    font-weight: 600;
    margin-bottom: 32px;
}

.logo-cloud-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 32px;
    align-items: center;
    justify-content: center;
}

.logo-item {
    text-align: center;
    padding: 12px;
    background: var(--color-gray-50);
    border-radius: var(--b2b-radius-md);
    border: 1px solid var(--color-gray-100);
    transition: var(--b2b-transition);
}

.logo-item span {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-gray-400);
    letter-spacing: 0.03em;
}

.logo-item:hover {
    background: var(--color-white);
    border-color: var(--color-brand-200);
}

.logo-item:hover span {
    color: var(--color-brand-600);
}

/* --- SECCIÓN 2: BENEFICIOS CORPORATIVOS --- */
.b2b-section-benefits {
    padding: 120px 0;
    background-color: var(--color-gray-25);
}

.b2b-benefits-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 28px;
}

.b2b-benefit-card {
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: var(--b2b-radius-lg);
    padding: 40px;
    transition: var(--b2b-transition);
}

.b2b-benefit-card:hover {
    transform: translateY(-4px);
    border-color: var(--color-brand-300);
    box-shadow: var(--shadow-theme-xl);
}

.b2b-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
}

.b2b-icon-box.blue { background: var(--color-brand-50); color: var(--color-brand-600); }
.b2b-icon-box.purple { background: #f4efff; color: var(--color-theme-purple-500); }
.b2b-icon-box.green { background: var(--color-success-50); color: var(--color-success-600); }

.b2b-benefit-card h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 12px;
    letter-spacing: -0.01em;
}

.b2b-benefit-card p {
    font-size: var(--text-theme-sm);
    color: var(--color-gray-500);
    line-height: 1.6;
}

/* --- SECCIÓN 3: ÁREAS ACADÉMICAS (Diseño Editorial Apple) --- */
.b2b-section-fields {
    padding: 120px 0;
    background-color: var(--color-white);
    border-bottom: 1px solid var(--color-gray-100);
}

.b2b-fields-layout {
    display: grid;
    grid-template-columns: 5fr 7fr;
    gap: 64px;
}

.b2b-fields-sticky {
    position: sticky;
    top: 60px;
    height: fit-content;
}

.b2b-fields-sticky h2 {
    font-size: var(--text-title-lg);
    line-height: var(--text-title-lg--line-height);
    font-weight: 800;
    color: var(--color-black);
    letter-spacing: -0.02em;
}

.b2b-fields-sticky p {
    font-size: 16px;
    color: var(--color-gray-500);
    margin-top: 16px;
    line-height: 1.6;
}

.b2b-sticky-cta {
    margin-top: 32px;
}

.b2b-btn-secondary {
    display: inline-block;
    font-size: var(--text-theme-sm);
    font-weight: 600;
    color: var(--color-brand-600);
    text-decoration: none;
    transition: var(--b2b-transition);
}

.b2b-btn-secondary:hover {
    color: var(--color-brand-800);
    transform: translateX(4px);
}

.b2b-fields-list {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.b2b-field-item {
    border-left: 3px solid var(--color-gray-200);
    padding-left: 28px;
    transition: var(--b2b-transition);
}

.b2b-field-item:hover {
    border-left-color: var(--color-brand-500);
}

.field-tag {
    font-size: var(--text-theme-xs);
    font-weight: 600;
    color: var(--color-brand-600);
    display: block;
    margin-bottom: 8px;
}

.b2b-field-item h3 {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 12px;
}

.b2b-field-item p {
    font-size: 15px;
    color: var(--color-gray-500);
    line-height: 1.6;
}

/* --- SECCIÓN 4: PROCESO CORPORATIVO (TIMELINE) --- */
.b2b-section-process {
    padding: 120px 0;
    background-color: var(--color-gray-25);
}

.b2b-process-timeline {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.b2b-step-card {
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: var(--b2b-radius-md);
    padding: 36px;
    position: relative;
}

.b2b-step-badge {
    position: absolute;
    top: -14px;
    left: 36px;
    background: var(--color-brand-950);
    color: var(--color-white);
    padding: 4px 12px;
    font-size: var(--text-theme-xs);
    font-weight: 700;
    border-radius: 6px;
}

.b2b-step-card h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-black);
    margin-top: 12px;
    margin-bottom: 12px;
}

.b2b-step-card p {
    font-size: var(--text-theme-sm);
    color: var(--color-gray-500);
    line-height: 1.6;
}

/* --- SECCIÓN 5: MÉTRICAS DE IMPACTO --- */
.b2b-section-metrics {
    background: var(--color-gray-25);
    padding-bottom: 80px;
}

.b2b-metrics-card {
    background: var(--color-brand-950);
    border-radius: var(--b2b-radius-lg);
    padding: 48px 24px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    text-align: center;
    box-shadow: var(--drop-shadow-3xl);
}

.metric-item {
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    padding: 0 16px;
}

.metric-item:last-child {
    border-right: none;
}

.metric-number {
    display: block;
    font-size: var(--text-title-md);
    line-height: 1;
    font-weight: 800;
    color: var(--color-white);
    letter-spacing: -0.02em;
    margin-bottom: 12px;
}

.metric-label {
    font-size: var(--text-theme-xs);
    color: var(--color-brand-200);
    font-weight: 500;
    line-height: 1.4;
}

/* --- SECCIÓN 6: FORMULARIO DE CAPTURA B2B --- */
.b2b-section-lead {
    padding: 140px 0;
    background-color: var(--color-white);
}

.b2b-lead-wrapper {
    display: grid;
    grid-template-columns: 4fr 8fr;
    border: 1px solid var(--color-gray-200);
    border-radius: var(--b2b-radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-theme-xl);
}

.b2b-lead-info {
    background: var(--color-gray-50);
    border-right: 1px solid var(--color-gray-200);
    padding: 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.b2b-lead-info h2 {
    font-size: 32px;
    font-weight: 800;
    color: var(--color-black);
    line-height: 1.2;
    margin-bottom: 16px;
    letter-spacing: -0.02em;
}

.b2b-lead-info p {
    font-size: 15px;
    color: var(--color-gray-500);
    line-height: 1.6;
}

.b2b-support-notice {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--color-gray-200);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: var(--text-theme-xs);
    color: var(--color-gray-600);
    font-weight: 600;
}

.b2b-support-notice svg {
    color: var(--color-brand-600);
}

.b2b-lead-form {
    padding: 50px;
    background: var(--color-white);
}

.b2b-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.b2b-field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

.b2b-field-group label {
    font-size: var(--text-theme-xs);
    font-weight: 600;
    color: var(--color-gray-700);
}

.b2b-field-group input,
.b2b-field-group textarea,
.b2b-select-custom {
    font-family: var(--font-outfit);
    font-size: var(--text-theme-sm);
    padding: 12px 16px;
    border: 1px solid var(--color-gray-300);
    border-radius: 8px;
    background: var(--color-gray-25);
    color: var(--color-black);
    transition: var(--b2b-transition);
}

.b2b-field-group input:focus,
.b2b-field-group textarea:focus,
.b2b-select-custom:focus {
    outline: none;
    background: var(--color-white);
    border-color: var(--color-brand-500);
    box-shadow: var(--shadow-focus-ring);
}

.b2b-action-container {
    margin-top: 12px;
}

.b2b-btn-primary {
    font-family: var(--font-outfit);
    background: var(--color-brand-600);
    color: var(--color-white);
    font-size: var(--text-theme-sm);
    font-weight: 600;
    padding: 14px 28px;
    border-radius: 10px;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: var(--b2b-transition);
}

.b2b-btn-primary:hover {
    background: var(--color-brand-700);
    transform: translateY(-1px);
}

/* --- RESPONSIVE DESIGN BREAKPOINTS (NATIVO COMPATIBLE) --- */
@media (max-width: 1024px) {
    .b2b-benefits-grid, .b2b-process-timeline { grid-template-columns: 1fr; gap: 32px; }
    .b2b-fields-layout, .b2b-lead-wrapper { grid-template-columns: 1fr; gap: 40px; }
    .b2b-fields-sticky { position: relative; top: 0; }
    .b2b-metrics-card { grid-template-columns: repeat(2, 1fr); gap: 24px 0; }
    .metric-item:nth-child(2) { border-right: none; }
    .logo-cloud-grid { grid-template-columns: repeat(3, 1fr); gap: 20px; }
}

@media (max-width: 640px) {
    .logo-cloud-grid, .b2b-metrics-card { grid-template-columns: 1fr; }
    .metric-item { border-right: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 20px; }
    .metric-item:last-child { border-bottom: none; padding-bottom: 0; }
    .b2b-form-grid { grid-template-columns: 1fr; gap: 0; }
    .b2b-lead-info, .b2b-lead-form { padding: 32px 20px; }
    .b2b-section-header h2, .b2b-fields-sticky h2 { font-size: var(--text-title-md); }
}

</style>

  <div class="hero hero-estudiantes" >
    <div class="brand-tag">Vinculación Institucional</div>
    <h1>Prácticas Profesionales</h1>
    <p>Habilidades y competencias para el escenario laboral</p>
    <a href="/signin" class="btn-demo">Iniciar sesión  </a>
  </div>

  <section class="b2b-social-proof">
    <div class="container">
        <p class="proof-title">Organizaciones e instituciones que potencian su operación con talento CUCSH</p>
        <div class="logo-cloud-grid">
            <div class="logo-item"><span>GOBIERNO DE JALISCO</span></div>
            <div class="logo-item"><span>PODER JUDICIAL</span></div>
            <div class="logo-item"><span>NOTARÍAS PÚBLICAS</span></div>
            <div class="logo-item"><span>ONG'S INTERNACIONALES</span></div>
            <div class="logo-item"><span>CORPORATIVOS B2B</span></div>
        </div>
    </div>
  </section>

  <section class="b2b-section-benefits">
    <div class="container">
        <div class="b2b-section-header">
            <span class="b2b-badge">Ventaja Estratégica</span>
            <h2>Por qué incorporar talento de excelencia del CUCSH</h2>
            <p>Impulse los proyectos de su organización con estudiantes de una de las facultades de Ciencias Sociales y Humanidades más prestigiadas de México.</p>
        </div>

        <div class="b2b-benefits-grid">
            <div class="b2b-benefit-card">
                <div class="b2b-icon-box blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                </div>
                <h3>Atracción de Talento Calificado</h3>
                <p>Acceso directo a perfiles evaluados rigurosamente, listos para integrarse a áreas jurídicas, de comunicación, análisis social y gestión pública.</p>
            </div>

            <div class="b2b-benefit-card">
                <div class="b2b-icon-box purple">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h3>Respaldo Legal e Institucional</h3>
                <p>Todos los procesos operativos y de vinculación están protegidos por convenios macro de la Universidad de Guadalajara, garantizando total transparencia.</p>
            </div>

            <div class="b2b-benefit-card">
                <div class="b2b-icon-box green">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <h3>Eficiencia Tecnológica</h3>
                <p>Gestione vacantes, valide reportes, descargue oficios y evalúe a sus practicantes desde una sola plataforma digital, sin burocracia física.</p>
            </div>
        </div>
    </div>
  </section>

  <section class="b2b-section-fields">
    <div class="container">
        <div class="b2b-fields-layout">
            <div class="b2b-fields-sticky">
                <span class="b2b-badge">Perfiles Disponibles</span>
                <h2>Áreas de alta especialización académica</h2>
                <p>Seleccione el perfil profesional idóneo según los objetivos institucionales o comerciales de su organización.</p>
                <div class="b2b-sticky-cta">
                    <a href="#solicitud-convenio" class="b2b-btn-secondary">Consultar planes de estudio →</a>
                </div>
            </div>
            
            <div class="b2b-fields-list">
                <div class="b2b-field-item">
                    <span class="field-tag">División de Estudios Jurídicos</span>
                    <h3>Derecho y Consultoría Legal</h3>
                    <p>Estudiantes formados en juicios orales, derecho corporativo, civil, penal, administrativo y mecanismos alternativos de solución de controversias públicos o privados.</p>
                </div>

                <div class="b2b-field-item">
                    <span class="field-tag">División de Estudios Políticos y Sociales</span>
                    <h3>Estudios Políticos, Gobierno y Relaciones Internacionales</h3>
                    <p>Especialistas en análisis de políticas públicas, diplomacia, comercio exterior, gestión gubernamental, consultoría política y desarrollo social sustentable.</p>
                </div>

                <div class="b2b-field-item">
                    <span class="field-tag">División de Cultura y Comunicación</span>
                    <h3>Comunicación, Periodismo y Gestión Cultural</h3>
                    <p>Talento capacitado para la creación de estrategias multimedia, manejo de crisis institucionales, comunicación corporativa, relaciones públicas y desarrollo de contenidos digitales.</p>
                </div>

                <div class="b2b-field-item">
                    <span class="field-tag">División de Estudios Históricos y Humanos</span>
                    <h3>Idiomas, Docencia y Humanidades</h3>
                    <p>Perfiles avanzados en traducción e interpretación de lenguas extranjeras, investigación documental, preservación histórica y docencia a nivel medio superior y superior.</p>
                </div>
            </div>
        </div>
    </div>
  </section>

  <section class="b2b-section-process">
    <div class="container">
        <div class="b2b-section-header">
            <span class="b2b-badge">Flujo de Colaboración</span>
            <h2>Cómo establecer el convenio en 3 etapas</h2>
            <p>Diseñamos un flujo ágil e interconectado para reducir los tiempos de validación jurídica y comenzar la recepción de talento a la brevedad.</p>
        </div>

        <div class="b2b-process-timeline">
            <div class="b2b-step-card">
                <div class="b2b-step-badge">Paso 01</div>
                <h3>Solicitud de Registro Corporativo</h3>
                <p>Complete el formulario de vinculación inicial. Nuestro equipo jurídico validará los datos de su empresa o dependencia pública para emitir la propuesta de convenio macro.</p>
            </div>

            <div class="b2b-step-card">
                <div class="b2b-step-badge">Paso 02</div>
                <h3>Publicación de Convocatorias</h3>
                <p>Una vez aprobado el perfil institucional en el sistema, acceda a su consola privada para publicar los requerimientos, actividades, plazas disponibles y horarios de las vacantes.</p>
            </div>

            <div class="b2b-step-card">
                <div class="b2b-step-badge">Paso 03</div>
                <h3>Asignación y Validación Digital</h3>
                <p>Seleccione a los candidatos ideales. La plataforma gestionará de forma automatizada los oficios de asignación firmados electrónicamente para el arranque seguro de las prácticas.</p>
            </div>
        </div>
    </div>
  </section>

  <section class="b2b-section-metrics">
    <div class="container">
        <div class="b2b-metrics-card">
            <div class="metric-item">
                <span class="metric-number">200+</span>
                <span class="metric-label">Instituciones y empresas aliadas</span>
            </div>
            <div class="metric-item">
                <span class="metric-number">1,000+</span>
                <span class="metric-label">Trámites y convenios digitales anuales</span>
            </div>
            <div class="metric-item">
                <span class="metric-number">95%</span>
                <span class="metric-label">Índice de satisfacción en supervisiones</span>
            </div>
            <div class="metric-item">
                <span class="metric-number">500+</span>
                <span class="metric-label">Estudiantes incorporados por ciclo</span>
            </div>
        </div>
    </div>
  </section>

  <section id="solicitud-convenio" class="b2b-section-lead">
    <div class="container">
        <div class="b2b-lead-wrapper">
            <div class="b2b-lead-info">
                <h2>Inicie hoy la alianza con el CUCSH</h2>
                <p>Complete este registro preliminar de vinculación. Un Coordinador de Prácticas Profesionales corporativas se pondrá en contacto con su organización para coordinar el marco legal del convenio.</p>
                <div class="b2b-support-notice">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span>Atención Directa: vinculacion.cucsh@academicos.udg.mx</span>
                </div>
            </div>
            
            <div class="b2b-lead-form">
                <form action="#" method="POST">
                    @csrf
                    <div class="b2b-form-grid">
                        <div class="b2b-field-group">
                            <label for="org_name">Nombre de la Empresa o Institución</label>
                            <input type="text" id="org_name" name="org_name" placeholder="Ej. Notaría Pública No. 4 o Sector Privado S.A." required>
                        </div>
                        <div class="b2b-field-group">
                            <label for="contact_name">Nombre del Representante o Reclutador</label>
                            <input type="text" id="contact_name" name="contact_name" placeholder="Ej. Lic. Elena Rostova" required>
                        </div>
                    </div>

                    <div class="b2b-form-grid">
                        <div class="b2b-field-group">
                            <label for="contact_email">Correo Electrónico Institucional</label>
                            <input type="email" id="contact_email" name="contact_email" placeholder="ejemplo@organizacion.com" required>
                        </div>
                        <div class="b2b-field-group">
                            <label for="org_type">Tipo de Organización</label>
                            <select id="org_type" name="org_type" class="b2b-select-custom">
                                <option value="privada">Empresa Privada / Corporativo</option>
                                <option value="publica">Dependencia Pública / Gobierno</option>
                                <option value="civil">Asociación Civil / ONG</option>
                                <option value="autonoma">Organismo Autónomo</option>
                            </select>
                        </div>
                    </div>

                    <div class="b2b-field-group">
                        <label for="asunto_vinculacion">Área de Interés Principal o Vacantes Previstas</label>
                        <input type="text" id="asunto_vinculacion" name="asunto_vinculacion" placeholder="Ej. Incorporación de estudiantes de Derecho y Ciencias Políticas">
                    </div>

                    <div class="b2b-field-group">
                        <label for="mensaje_vinculacion">Especificaciones o Comentarios adicionales</label>
                        <textarea id="mensaje_vinculacion" name="mensaje_vinculacion" rows="4" placeholder="Describa brevemente los requerimientos de perfiles o dudas sobre el proceso legal del convenio..."></textarea>
                    </div>

                    <div class="b2b-action-container">
                        <button type="submit" class="b2b-btn-primary">
                            <span>Solicitar Vinculación Institucional</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </section>

@endsection