@extends('layouts.landing')

@section('content')

<style>

    /* ==========================================================================
   LANDING PAGE REDESIGN - PREMIUM COMPONENTS (2026)
   ========================================================================== */

/* Estilos de Contenedor Global y Estructura Basal */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
}

.badge-category {
    display: inline-block;
    padding: 6px 14px;
    background-color: var(--color-brand-50);
    color: var(--color-brand-600);
    border-radius: 99px;
    font-size: var(--text-theme-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 16px;
}

.section-header {
    text-align: center;
    max-width: 680px;
    margin: 0 auto 64px auto;
}

.section-header h2 {
    font-size: var(--text-title-lg);
    line-height: var(--text-title-lg--line-height);
    color: var(--color-black);
    font-weight: 800;
    letter-spacing: -0.02em;
}

.section-header p {
    font-size: 16px;
    color: var(--color-gray-500);
    margin-top: 16px;
    line-height: 1.6;
}

/* --- SECCIÓN PROCESO (TIMELINE) --- */
.section-timeline {
    padding: 120px 0;
    background-color: var(--color-gray-25);
}

.timeline-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

/* El último paso se expande horizontalmente en la fila inferior para dar dinamismo */
.timeline-grid .timeline-card:nth-child(5) {
    grid-column: span 4;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-align: left;
}

.timeline-grid .timeline-card:nth-child(5) .step-number {
    margin-bottom: 0;
    margin-right: 24px;
}

.timeline-card {
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: 16px;
    padding: 32px;
    position: relative;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
}

.timeline-card:hover {
    transform: translateY(-4px);
    border-color: var(--color-brand-300);
    box-shadow: var(--shadow-theme-lg);
}

.step-number {
    font-size: var(--text-title-md);
    font-weight: 800;
    color: var(--color-brand-100);
    transition: color 0.3s ease;
    margin-bottom: 16px;
}

.timeline-card:hover .step-number {
    color: var(--color-brand-500);
}

.timeline-card h3 {
    font-size: var(--text-theme-xl);
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 12px;
    letter-spacing: -0.01em;
}

.timeline-card p {
    font-size: var(--text-theme-sm);
    color: var(--color-gray-500);
    line-height: 1.6;
}

.card-action-hint {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 20px;
    font-size: var(--text-theme-xs);
    font-weight: 600;
    color: var(--color-brand-500);
    opacity: 0;
    transform: translateX(-4px);
    transition: all 0.3s ease;
}

.timeline-card:hover .card-action-hint {
    opacity: 1;
    transform: translateX(0);
}

/* --- SECCIÓN RECURSOS (CARDS PREMIUM) --- */
.section-resources {
    padding: 120px 0;
    background-color: var(--color-white);
}

.resources-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
}

.resource-premium-card {
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-100);
    border-radius: 20px;
    padding: 36px 28px;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: all 0.3s ease;
}

.resource-premium-card:hover {
    background: var(--color-white);
    border-color: var(--color-gray-200);
    box-shadow: var(--shadow-theme-xl);
}

.resource-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
}

.resource-icon-wrapper.red-accent { background: var(--color-error-50); color: var(--color-error-600); }
.resource-icon-wrapper.blue-accent { background: var(--color-blue-50); color: var(--color-blue-600); }
.resource-icon-wrapper.orange-accent { background: var(--color-orange-50); color: var(--color-orange-600); }
.resource-icon-wrapper.purple-accent { background: #f4efff; color: var(--color-theme-purple-500); }

.resource-premium-card h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 12px;
}

.resource-premium-card p {
    font-size: var(--text-theme-sm);
    color: var(--color-gray-500);
    line-height: 1.5;
    flex-grow: 1;
    margin-bottom: 24px;
}

.resource-link {
    font-size: var(--text-theme-sm);
    font-weight: 600;
    color: var(--color-black);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.resource-link span {
    transition: transform 0.2s ease;
}

.resource-premium-card:hover .resource-link span {
    transform: translateX(4px);
}

/* --- SECCIÓN BENEFICIOS (ASIMÉTRICA Y EDITORIAL) --- */
.section-benefits {
    padding: 120px 0;
    background-color: var(--color-gray-25);
    border-top: 1px solid var(--color-gray-200);
}

.benefits-layout {
    display: grid;
    grid-template-columns: 4fr 7fr;
    gap: 80px;
}

.benefits-sticky-header {
    position: sticky;
    top: 40px;
    height: fit-content;
}

.benefits-sticky-header h2 {
    font-size: var(--text-title-lg);
    line-height: var(--text-title-lg--line-height);
    font-weight: 800;
    color: var(--color-black);
    letter-spacing: -0.02em;
}

.benefits-sticky-header p {
    font-size: 16px;
    color: var(--color-gray-500);
    margin-top: 16px;
    line-height: 1.6;
}

.benefits-list {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.benefit-item {
    border-bottom: 1px solid var(--color-gray-200);
    padding-bottom: 32px;
}

.benefit-item h4 {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 12px;
}

.benefit-item p {
    font-size: 15px;
    color: var(--color-gray-600);
    line-height: 1.6;
}

/* --- SECCIÓN MÉTRICAS (METRIC DASHBOARD) --- */
.section-stats {
    padding: 60px 0;
    background-color: var(--color-gray-25);
}

.stats-glass-grid {
    background: #111827; /* Dark Obsidian premium background */
    border-radius: 24px;
    padding: 60px 40px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    text-align: center;
    box-shadow: var(--drop-shadow-4xl);
}

.stat-box {
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-box:last-child {
    border-right: none;
}

.stat-number {
    display: block;
    font-size: var(--text-title-xl);
    line-height: 1;
    font-weight: 900;
    color: var(--color-white);
    letter-spacing: -0.03em;
    margin-bottom: 8px;
}

.stat-label {
    font-size: var(--text-theme-sm);
    color: var(--color-gray-400);
    font-weight: 500;
}

/* --- SECCIÓN CONTACTO (DASHBOARD-STYLE TICKETING) --- */
.section-contact {
    padding: 140px 0;
    background-color: var(--color-white);
}

.contact-card-wrapper {
    display: grid;
    grid-template-columns: 5fr 7fr;
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow-theme-xl);
}

.contact-info-side {
    background: var(--color-brand-950);
    padding: 60px;
    color: var(--color-white);
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.contact-info-side h2 {
    font-size: var(--text-title-sm);
    line-height: 1.2;
    font-weight: 800;
    margin-bottom: 20px;
}

.contact-info-side p {
    color: var(--color-brand-200);
    font-size: 16px;
    line-height: 1.6;
}

.quick-contact-meta {
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    font-size: var(--text-theme-sm);
}

.contact-form-side {
    padding: 60px;
    background: var(--color-white);
}

.form-row-twin {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 24px;
}

.form-group label {
    font-size: var(--text-theme-xs);
    font-weight: 600;
    color: var(--color-gray-700);
}

.form-group input, 
.form-group textarea {
    font-family: var(--font-outfit);
    font-size: var(--text-theme-sm);
    padding: 12px 16px;
    border: 1px solid var(--color-gray-300);
    border-radius: 10px;
    background: var(--color-gray-25);
    color: var(--color-black);
    transition: all 0.2s ease;
}

.form-group input:focus, 
.form-group textarea:focus {
    outline: none;
    background: var(--color-white);
    border-color: var(--color-brand-500);
    box-shadow: var(--shadow-focus-ring);
}

.btn-primary-submit {
    font-family: var(--font-outfit);
    background: var(--color-brand-600);
    color: var(--color-white);
    font-size: var(--text-theme-sm);
    font-weight: 600;
    padding: 14px 28px;
    border-radius: 12px;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
    width: auto;
}

.btn-primary-submit:hover {
    background: var(--color-brand-700);
    transform: translateY(-1px);
}

/* --- RESPONSIVE DESIGN TOTAL (BREAKPOINTS COMPATIBLES) --- */
@media (max-width: 1024px) {
    .timeline-grid { grid-template-columns: repeat(2, 1fr); }
    .timeline-grid .timeline-card:nth-child(5) { grid-column: span 2; }
    .resources-grid { grid-template-columns: repeat(2, 1fr); }
    .benefits-layout { grid-template-columns: 1fr; gap: 40px; }
    .benefits-sticky-header { position: relative; top: 0; }
    .contact-card-wrapper { grid-template-columns: 1fr; }
    .section-header h2, .benefits-sticky-header h2 { font-size: var(--text-title-md); }
}

@media (max-width: 640px) {
    .timeline-grid, .resources-grid, .stats-glass-grid { grid-template-columns: 1fr; }
    .timeline-grid .timeline-card:nth-child(5) { grid-column: span 1; display: block; }
    .timeline-grid .timeline-card:nth-child(5) .step-number { margin-bottom: 16px; }
    .stats-glass-grid { padding: 40px 20px; }
    .stat-box { border-right: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 24px 0; }
    .stat-box:last-child { border-bottom: none; }
    .form-row-twin { grid-template-columns: 1fr; gap: 0; }
    .contact-info-side, .contact-form-side { padding: 32px 24px; }
}
</style>

  <div class="hero hero-estudiantes">
    <div class="brand-tag">Estudiantes</div>
    <h1>Prácticas Profesionales</h1>
    <p>Habilidades y competencias para el escenario laboral</p>
    <a href="/signin" class="btn-demo">Iniciar sesión</a>
  </div>

  <section class="section-timeline">
    <div class="container">
        <div class="section-header">
            <span class="badge-category">Flujo del Proceso</span>
            <h2>Realiza tus prácticas en 5 pasos</h2>
            <p>Gestiona todo tu camino profesional desde una plataforma intuitiva y centralizada.</p>
        </div>

        <div class="timeline-grid">
            <div class="timeline-card">
                <div class="step-number">01</div>
                <h3>Registra tu información</h3>
                <p>Sube tus documentos oficiales y espera su validación para habilitar tu participación en los programas.</p>
                <span class="card-action-hint">Ver más <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>

            <div class="timeline-card">
                <div class="step-number">02</div>
                <h3>Postúlate y elige empresa</h3>
                <p>Inscríbete en los programas disponibles del catálogo y selecciona la organización que acepte tu perfil.</p>
                <span class="card-action-hint">Ver más <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>

            <div class="timeline-card">
                <div class="step-number">03</div>
                <h3>Obtén tu asignación</h3>
                <p>Descarga tu Oficio de Asignación, gestiona las firmas requeridas y formaliza el inicio con la empresa.</p>
                <span class="card-action-hint">Ver más <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>

            <div class="timeline-card">
                <div class="step-number">04</div>
                <h3>Inicia tus prácticas</h3>
                <p>Adjunta el oficio firmado digitalmente a la plataforma y comienza tus actividades operativas curriculares.</p>
                <span class="card-action-hint">Ver más <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>

            <div class="timeline-card full-width-mobile">
                <div class="step-number">05</div>
                <h3>Concluye el proceso</h3>
                <p>Sube tu carta de término y recibe tu constancia oficial de liberación de prácticas profesionales.</p>
                <span class="card-action-hint">Ver más <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>
        </div>
    </div>
  </section>

  <section class="section-resources">
    <div class="container">
        <div class="section-header">
            <span class="badge-category">Centro de Soporte</span>
            <h2>Recursos para estudiantes</h2>
            <p>Formatos institucionales, infografías y guías diseñadas para agilizar tu trámite.</p>
        </div>

        <div class="resources-grid">
            <div class="resource-premium-card">
                <div class="resource-icon-wrapper red-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/></svg>
                </div>
                <h3>Guía de inicio</h3>
                <p>El manual definitivo con capturas de pantalla sobre cómo iniciar con éxito.</p>
                <a href="#" class="resource-link">Ver guía completa <span>→</span></a>
            </div>

            <div class="resource-premium-card">
                <div class="resource-icon-wrapper blue-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <h3>Formatos</h3>
                <p>Descarga directa de cartas de aceptación, reportes obligatorios y plantillas oficiales.</p>
                <a href="#" class="resource-link">Descargar ZIP <span>→</span></a>
            </div>

            <div class="resource-premium-card">
                <div class="resource-icon-wrapper orange-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="21" y1="9" x2="3" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                </div>
                <h3>Infografías</h3>
                <p>Esquemas visuales y mapas mentales rápidos para comprender los requisitos clave.</p>
                <a href="#" class="resource-link">Explorar multimedia <span>→</span></a>
            </div>

            <div class="resource-premium-card">
                <div class="resource-icon-wrapper purple-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h3>Preguntas frecuentes</h3>
                <p>Respuestas inmediatas a los problemas más comunes de registro y plazos académicos.</p>
                <a href="#" class="resource-link">Consultar Base de Conocimiento <span>→</span></a>
            </div>
        </div>
    </div>
  </section>

  <section class="section-benefits">
    <div class="container">
        <div class="benefits-layout">
            <div class="benefits-sticky-header">
                <span class="badge-category">Tu Futuro</span>
                <h2>Beneficios de tu trayectoria</h2>
                <p>El puente estratégico entre tu formación universitaria y tu inserción definitiva en los ecosistemas de alta innovación laboral.</p>
            </div>
            <div class="benefits-list">
                <div class="benefit-item">
                    <h4>Experiencia profesional real</h4>
                    <p>Deja la teoría atrás. Enfrenta retos reales de ingeniería, humanidades o gestión en empresas líderes del mercado regional.</p>
                </div>
                <div class="benefit-item">
                    <h4>Desarrollo de competencias críticas</h4>
                    <p>Potencia tus soft-skills y tus habilidades técnicas bajo la mentoría directa de profesionales experimentados del sector.</p>
                </div>
                <div class="benefit-item">
                    <h4>Vinculación de alto valor</h4>
                    <p>Construye un networking sólido y accede a ofertas de contratación directa antes de concluir tu carrera.</p>
                </div>
                <div class="benefit-item">
                    <h4>Acompañamiento Institucional</h4>
                    <p>Soporte continuo y validación académica por parte de asesores del CUCSH asignados exclusivamente a tu éxito.</p>
                </div>
            </div>
        </div>
    </div>
  </section>

  <section class="section-stats">
    <div class="container">
        <div class="stats-glass-grid">
            <div class="stat-box">
                <span class="stat-number">500+</span>
                <span class="stat-label">Estudiantes activos</span>
            </div>
            <div class="stat-box">
                <span class="stat-number">200+</span>
                <span class="stat-label">Instituciones receptoras</span>
            </div>
            <div class="stat-box">
                <span class="stat-number">1,000+</span>
                <span class="stat-label">Trámites gestionados</span>
            </div>
            <div class="stat-box">
                <span class="stat-number">95%</span>
                <span class="stat-label">Procesos concluidos</span>
            </div>
        </div>
    </div>
  </section>

  <section class="section-contact">
    <div class="container">
        <div class="contact-card-wrapper">
            <div class="contact-info-side">
                <h2>¿Necesitas ayuda con tu proceso?</h2>
                <p>Si tienes inconvenientes técnicos, dudas con la carga documental o requieres asistencia de un asesor, envíanos un ticket instantáneo.</p>
                <div class="quick-contact-meta">
                    <p><strong>Respuesta estimada:</strong> menos de 24 horas hábiles.</p>
                </div>
            </div>
            <div class="contact-form-side">
                <form>
                    <div class="form-row-twin">
                        <div class="form-group">
                            <label>Nombre completo</label>
                            <input type="text" placeholder="Ej. Juan Pérez">
                        </div>
                        <div class="form-group">
                            <label>Correo electrónico</label>
                            <input type="email" placeholder="juan.perez@alumnos.udg.mx">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Asunto</label>
                        <input type="text" placeholder="Ej. Problema con firma de asignación">
                    </div>
                    <div class="form-group">
                        <label>Mensaje o Descripción</label>
                        <textarea rows="4" placeholder="Describe detalladamente tu situación..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary-submit">
                        Enviar consulta
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
  </section>

@endsection