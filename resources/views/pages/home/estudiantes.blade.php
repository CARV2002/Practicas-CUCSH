@extends('layouts.landing')

@section('title', 'Estudiantes | Prácticas Profesionales CUCSH')

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
    @auth
      <a href="{{ route('dashboard') }}" class="btn-demo">Ir a mi panel</a>
    @else
      <a href="{{ route('login') }}" class="btn-demo">Iniciar sesión</a>
    @endauth
  </div>

  <section class="section-timeline">
    <div class="container">
        <div class="section-header">
            <span class="badge-category">Flujo del Proceso</span>
            <h2>Realiza tus prácticas en 5 pasos</h2>
            <p>Estas son las etapas que sigue tu trámite dentro de la plataforma, desde tu registro hasta la acreditación.</p>
        </div>

        <div class="timeline-grid">
            <div class="timeline-card">
                <div class="step-number">01</div>
                <h3>Regístrate y sube tu IMSS</h3>
                <p>Crea tu cuenta con tu código de alumno y tu carrera, y sube la constancia de tu seguro facultativo (IMSS) con su fecha de vigencia: debe cubrir hasta el final de tus prácticas.</p>
                <span class="card-action-hint">Mis documentos <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>
            <div class="timeline-card">
                <div class="step-number">02</div>
                <h3>Postúlate con tu kardex</h3>
                <p>Consulta los programas de tu carrera con convenio vigente y cupo disponible. Te postulas adjuntando tu kardex y el porcentaje de créditos que llevas. Puedes tener una sola empresa o práctica en trámite a la vez.</p>
                <span class="card-action-hint">Oferta programas <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>
            <div class="timeline-card">
                <div class="step-number">03</div>
                <h3>Recibe tu carta de presentación</h3>
                <p>La Coordinación revisa tu postulación y emite tu carta de presentación, que reserva tu cupo. Te presentas con la organización y subes en PDF la carta de aceptación que te entregue.</p>
                <span class="card-action-hint">Mis documentos <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>
            <div class="timeline-card">
                <div class="step-number">04</div>
                <h3>Inicia y reporta tus horas</h3>
                <p>La Coordinación activa tu práctica con sus fechas de inicio y fin. Durante la práctica subes tus reportes (mensual, intermedio y final) en PDF; tus horas cuentan cuando se validan.</p>
                <span class="card-action-hint">Mis prácticas <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>
            <div class="timeline-card full-width-mobile">
                <div class="step-number">05</div>
                <h3>Concluye y acredita</h3>
                <p>Al completar tus horas subes tu carta de término. La Coordinación la valida y envía el oficio de acreditación a Control Escolar, que lo captura en SIIAU y deja tu práctica acreditada.</p>
                <span class="card-action-hint">Mis prácticas <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>
        </div>
    </div>
  </section>

  <section class="section-resources">
    <div class="container">
        <div class="section-header">
            <span class="badge-category">Documentos</span>
            <h2>Lo que necesitas para tus prácticas</h2>
            <p>Estos son los documentos que solicita la plataforma durante el proceso. Todos se cargan desde Mis documentos.</p>
        </div>

        <div class="resources-grid">
            <div class="resource-premium-card">
                <div class="resource-icon-wrapper red-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3>Constancia IMSS</h3>
                <p>Comprobante de tu seguro facultativo con su fecha de vigencia. Es indispensable para que se emita tu carta de presentación y para activar tu práctica.</p>
                <span class="resource-link">Debe estar vigente al emitir tu carta</span>
            </div>

            <div class="resource-premium-card">
                <div class="resource-icon-wrapper blue-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <h3>Kardex</h3>
                <p>Se adjunta en cada postulación junto con el porcentaje de créditos que llevas, que debe alcanzar el mínimo que exige tu carrera (consulta la tabla más abajo).</p>
                <span class="resource-link">PDF o imagen, hasta 10 MB</span>
            </div>

            <div class="resource-premium-card">
                <div class="resource-icon-wrapper orange-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><polyline points="9 11 12 14 22 4"/></svg>
                </div>
                <h3>Carta de aceptación</h3>
                <p>La que te entrega la organización al aceptarte. Puedes subirla en cuanto tengas tu carta de presentación; con ella la Coordinación activa tu práctica.</p>
                <span class="resource-link">Solo en PDF</span>
            </div>

            <div class="resource-premium-card">
                <div class="resource-icon-wrapper purple-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <h3>Reportes y carta de término</h3>
                <p>Reportes mensual, intermedio y final con las horas que realizaste, firmados y en PDF. Si uno es rechazado, lo corriges y lo subes de nuevo. Al cubrir tus horas subes la carta de término.</p>
                <span class="resource-link">Solo en PDF</span>
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
                <p>El puente entre tu formación universitaria y tu experiencia profesional, con respaldo institucional del CUCSH.</p>
            </div>
            <div class="benefits-list">
                <div class="benefit-item">
                    <h4>Experiencia profesional real</h4>
                    <p>Aplica lo aprendido en una organización con convenio vigente con el CUCSH, o en un proyecto universitario, según la modalidad de tu programa.</p>
                </div>
                <div class="benefit-item">
                    <h4>Seguimiento de tus horas</h4>
                    <p>Cada reporte que subes se valida y suma a tu avance. Ves en tu panel cuántas horas llevas, cuántas te faltan y el estado de cada reporte.</p>
                </div>
                <div class="benefit-item">
                    <h4>Convenios con respaldo</h4>
                    <p>Las organizaciones pasan por revisión de documentos, validación académica, revisión jurídica y firmas antes de poder publicar programas.</p>
                </div>
                <div class="benefit-item">
                    <h4>Acompañamiento institucional</h4>
                    <p>La Coordinación valida tu postulación, activa tu práctica, puede asignarte un profesor supervisor y da seguimiento hasta tu acreditación.</p>
                </div>
            </div>
        </div>
    </div>
  </section>

  @if ($divisiones->isNotEmpty())
  <section class="section-benefits">
    <div class="container">
        <div class="benefits-layout">
            <div class="benefits-sticky-header">
                <span class="badge-category">Requisitos</span>
                <h2>Horas y créditos por carrera</h2>
                <p>Cada carrera define las horas de práctica que debes cubrir y el porcentaje mínimo de créditos que necesitas para postularte.</p>
            </div>
            <div class="benefits-list">
                @foreach ($divisiones as $division)
                    <div class="benefit-item">
                        <h4>{{ $division->nombre }}</h4>
                        @foreach ($division->carreras as $carrera)
                            <p>
                                <strong>{{ $carrera->nombre }}</strong>:
                                {{ $carrera->horas_requeridas }} horas · mínimo
                                {{ rtrim(rtrim(number_format((float) $carrera->porcentaje_creditos_minimo, 2), '0'), '.') }}&nbsp;% de créditos
                            </p>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
  </section>
  @endif

  @if ($organizaciones + $programasAbiertos + $practicasEnCurso + $practicasAcreditadas > 0)
  <section class="section-stats">
    <div class="container">
        <div class="stats-glass-grid">
            <div class="stat-box">
                <span class="stat-number">{{ number_format($organizaciones) }}</span>
                <span class="stat-label">Organizaciones con convenio vigente</span>
            </div>
            <div class="stat-box">
                <span class="stat-number">{{ number_format($programasAbiertos) }}</span>
                <span class="stat-label">Programas abiertos</span>
            </div>
            <div class="stat-box">
                <span class="stat-number">{{ number_format($practicasEnCurso) }}</span>
                <span class="stat-label">Prácticas en curso</span>
            </div>
            <div class="stat-box">
                <span class="stat-number">{{ number_format($practicasAcreditadas) }}</span>
                <span class="stat-label">Prácticas acreditadas</span>
            </div>
        </div>
    </div>
  </section>
  @endif

  @guest
  <div class="cta-band">
    <h2>¿Listo para empezar tus prácticas?</h2>
    <a href="{{ route('registro.alumno') }}" class="btn-demo">Crear cuenta de alumno</a>
    <a href="{{ route('login') }}" class="btn-outline">Ya tengo cuenta</a>
  </div>
  @endguest

  <section class="section-contact">
    <div class="container">
        <div class="contact-card-wrapper">
            <div class="contact-info-side">
                <h2>¿Necesitas ayuda con tu proceso?</h2>
                <p>Si tienes dudas con la carga de documentos, con el estado de tu postulación o con tu práctica, comunícate con la Coordinación de Prácticas Profesionales del CUCSH (consulta también la <a href="{{ route('contacto') }}" style="color: inherit; text-decoration: underline;">página de contacto</a>).</p>
                @auth
                <div class="quick-contact-meta">
                    <p>También puedes consultar la <a href="{{ route('ayuda') }}" style="color: inherit; text-decoration: underline;">sección de ayuda</a> dentro de la plataforma.</p>
                </div>
                @endauth
            </div>
            <div class="contact-form-side">
                <div class="form-group">
                    <label>Correo electrónico</label>
                    <a href="mailto:{{ config('practicas.contacto.correo') }}" style="font-size: var(--text-theme-sm); color: var(--color-black);">{{ config('practicas.contacto.correo') }}</a>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <a href="tel:{{ config('practicas.contacto.telefono_enlace') }}" style="font-size: var(--text-theme-sm); color: var(--color-black);">{{ config('practicas.contacto.telefono') }}, ext. {{ implode(' o ', config('practicas.contacto.extensiones')) }}</a>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <span style="font-size: var(--text-theme-sm); color: var(--color-black);">{{ config('practicas.domicilio_cucsh') }}</span>
                </div>
                <a href="mailto:{{ config('practicas.contacto.correo') }}" class="btn-primary-submit" style="text-decoration: none;">
                    Escribir a la Coordinación
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </a>
            </div>
        </div>
    </div>
  </section>

@endsection
