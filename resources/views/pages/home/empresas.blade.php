@extends('layouts.landing')

@section('title', 'Empresas | Prácticas Profesionales CUCSH')

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

@php
    $plazo = fn (?array $p) => $p ? "{$p[0]} a {$p[1]} días hábiles" : 'sin plazo fijo';
    $solicitarUrl = route('registro.empresa');
@endphp

  <div class="hero hero-estudiantes" >
    <div class="brand-tag">Vinculación Institucional</div>
    <h1>Prácticas Profesionales</h1>
    <p>Habilidades y competencias para el escenario laboral</p>
    @auth
      <a href="{{ route('dashboard') }}" class="btn-demo">Ir a mi panel</a>
    @else
      <a href="{{ route('login') }}" class="btn-demo">Iniciar sesión</a>
    @endauth
  </div>

  @if ($organizaciones->isNotEmpty())
  <section class="b2b-social-proof">
    <div class="container">
        <p class="proof-title">
            {{ $totalOrganizaciones === 1 ? 'Organización con convenio vigente' : 'Organizaciones con convenio vigente' }} con el CUCSH
        </p>
        <div class="logo-cloud-grid">
            @foreach ($organizaciones as $empresa)
                <div class="logo-item"><span>{{ $empresa->razon_social }}</span></div>
            @endforeach
        </div>
    </div>
  </section>
  @endif

  <section class="b2b-section-benefits">
    <div class="container">
        <div class="b2b-section-header">
            <span class="b2b-badge">Ventaja Estratégica</span>
            <h2>Por qué incorporar talento del CUCSH</h2>
            <p>Impulse los proyectos de su organización con estudiantes del Centro Universitario de Ciencias Sociales y Humanidades de la Universidad de Guadalajara.</p>
        </div>

        <div class="b2b-benefits-grid">
            <div class="b2b-benefit-card">
                <div class="b2b-icon-box blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                </div>
                <h3>Talento por carrera</h3>
                <p>Usted elige las carreras de su interés y publica programas dirigidos a ellas. Solo se postulan alumnos de la carrera del programa que cumplen el porcentaje mínimo de créditos que exige su plan.</p>
            </div>

            <div class="b2b-benefit-card">
                <div class="b2b-icon-box purple">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h3>Respaldo Legal e Institucional</h3>
                <p>Cada convenio pasa por revisión de documentos, validación académica por carrera, dictamen de viabilidad jurídica y firma de Rectoría antes de formalizarse y poder publicar programas.</p>
            </div>

            <div class="b2b-benefit-card">
                <div class="b2b-icon-box green">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <h3>Gestión en una sola plataforma</h3>
                <p>Dé seguimiento a su convenio, publique programas, consulte postulantes y practicantes y retroalimente sus reportes desde un mismo lugar, con notificaciones en cada cambio de etapa.</p>
            </div>
        </div>
    </div>
  </section>

  <section class="b2b-section-process">
    <div class="container">
        <div class="b2b-section-header">
            <span class="b2b-badge">Flujo de Colaboración</span>
            <h2>Su participación en 3 pasos</h2>
            <p>Desde el registro de su organización hasta el seguimiento de sus practicantes.</p>
        </div>

        <div class="b2b-process-timeline">
            <div class="b2b-step-card">
                <div class="b2b-step-badge">Paso 01</div>
                <h3>Registre su organización y solicite el convenio</h3>
                <p>Cree su cuenta con razón social, RFC y tipo de organismo, suba los documentos de su expediente y envíe la solicitud indicando objetivos, actividades, plazas estimadas y las carreras de su interés.</p>
            </div>

            <div class="b2b-step-card">
                <div class="b2b-step-badge">Paso 02</div>
                <h3>Publique sus programas</h3>
                <p>Con el convenio formalizado, publique programas para las carreras aprobadas en él: ciclo, actividades, perfil requerido, horario, sede y cupos. Puede guardarlos como borrador, publicarlos o cerrarlos.</p>
            </div>

            <div class="b2b-step-card">
                <div class="b2b-step-badge">Paso 03</div>
                <h3>Reciba practicantes y retroalimente</h3>
                <p>Consulte a los postulantes con carta de presentación y entregue a cada alumno su carta de aceptación. Durante la práctica puede retroalimentar los reportes; la Coordinación valida las horas y da el cierre.</p>
            </div>
        </div>
    </div>
  </section>

  <section class="b2b-section-fields">
    <div class="container">
        <div class="b2b-fields-layout">
            <div class="b2b-fields-sticky">
                <span class="b2b-badge">Formalización del convenio</span>
                <h2>Cuatro fases hasta la firma</h2>
                <p>Estos son los plazos documentados de cada etapa, en días hábiles. Son estimaciones: dependen de la revisión de cada área y de la entrega oportuna de documentos.</p>
                <div class="b2b-sticky-cta">
                    <a href="#solicitud-convenio" class="b2b-btn-secondary">Solicitar convenio →</a>
                </div>
            </div>

            <div class="b2b-fields-list">
                <div class="b2b-field-item">
                    <span class="field-tag">Fase 1 · {{ $plazo($plazos['revision_documentos'] ?? null) }}</span>
                    <h3>Revisión de documentos</h3>
                    <p>Vinculación revisa los documentos de su expediente. Mientras alguno no esté validado, la solicitud no avanza a las carreras.</p>
                </div>

                <div class="b2b-field-item">
                    <span class="field-tag">Fase 2 · {{ $plazo($plazos['turno_academico'] ?? null) }}</span>
                    <h3>Validación académica por carrera</h3>
                    <p>La solicitud se turna a la Secretaría, las Divisiones y las Coordinaciones de las carreras que usted eligió. Cada carrera emite su dictamen y solo las aprobadas quedan en el convenio.</p>
                </div>

                <div class="b2b-field-item">
                    <span class="field-tag">Fase 3 · {{ $plazo($plazos['dictamen_juridico'] ?? null) }}</span>
                    <h3>Borrador y revisión jurídica</h3>
                    <p>Vinculación redacta el borrador del convenio y la Unidad Jurídica emite su dictamen de viabilidad. El plazo indicado corresponde al dictamen.</p>
                </div>

                <div class="b2b-field-item">
                    <span class="field-tag">Fase 4 · Empresa {{ $plazo($plazos['firma_empresa'] ?? null) }} · Rectoría {{ $plazo($plazos['firma_rectoria'] ?? null) }}</span>
                    <h3>Firmas y formalización</h3>
                    <p>Su organización firma tres juegos del convenio y después los firma Rectoría; la plataforma registra el avance de cada firma. Al formalizarse queda registrada la vigencia y se entregan los juegos.</p>
                </div>

                <div class="b2b-field-item">
                    <span class="field-tag">Renovación</span>
                    <h3>Convenio vigente</h3>
                    <p>Mientras el convenio esté vigente puede publicar programas. La renovación se solicita dentro de los 60 días previos al vencimiento.</p>
                </div>
            </div>
        </div>
    </div>
  </section>

  @if ($divisiones->isNotEmpty())
  <section class="b2b-section-fields">
    <div class="container">
        <div class="b2b-fields-layout">
            <div class="b2b-fields-sticky">
                <span class="b2b-badge">Perfiles Disponibles</span>
                <h2>Carreras con las que puede colaborar</h2>
                <p>Al solicitar su convenio elige las carreras de su interés. Cada una se valida académicamente y solo para las aprobadas podrá publicar programas.</p>
            </div>

            <div class="b2b-fields-list">
                @foreach ($divisiones as $division)
                    <div class="b2b-field-item">
                        <span class="field-tag">División</span>
                        <h3>{{ $division->nombre }}</h3>
                        <p>{{ $division->carreras->map(fn ($c) => preg_replace('/^Licenciatura (en|de) /u', '', $c->nombre))->implode(', ') }}.</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
  </section>
  @endif

  @if ($totalOrganizaciones + $programasAbiertos + $practicasEnCurso + $practicasAcreditadas > 0)
  <section class="b2b-section-metrics">
    <div class="container">
        <div class="b2b-metrics-card">
            <div class="metric-item">
                <span class="metric-number">{{ number_format($totalOrganizaciones) }}</span>
                <span class="metric-label">Organizaciones con convenio vigente</span>
            </div>
            <div class="metric-item">
                <span class="metric-number">{{ number_format($programasAbiertos) }}</span>
                <span class="metric-label">Programas abiertos</span>
            </div>
            <div class="metric-item">
                <span class="metric-number">{{ number_format($practicasEnCurso) }}</span>
                <span class="metric-label">Prácticas en curso</span>
            </div>
            <div class="metric-item">
                <span class="metric-number">{{ number_format($practicasAcreditadas) }}</span>
                <span class="metric-label">Prácticas acreditadas</span>
            </div>
        </div>
    </div>
  </section>
  @endif

  <section id="solicitud-convenio" class="b2b-section-lead">
    <div class="container">
        <div class="b2b-lead-wrapper">
            <div class="b2b-lead-info">
                <h2>Inicie hoy la alianza con el CUCSH</h2>
                <p>Cree la cuenta de su organización y solicite su convenio directamente en la plataforma. La Coordinación de Prácticas Profesionales dará seguimiento a su solicitud en cada fase.</p>
                <div class="b2b-support-notice">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span>Atención directa: <a href="mailto:practicas.profesionales.cucsh@csh.udg.mx" style="color: inherit; text-decoration: underline;">practicas.profesionales.cucsh@csh.udg.mx</a></span>
                </div>
            </div>

            <div class="b2b-lead-form">
                <div class="b2b-field-group">
                    <label>Para registrar su organización necesita</label>
                    <p style="margin: 6px 0 0; line-height: 1.6;">
                        Razón social, RFC y tipo de organismo ({{ implode(', ', $tiposOrganismo) }}), además de los datos de la persona de contacto y un correo para su cuenta.
                    </p>
                </div>

                <div class="b2b-field-group">
                    <label>Documentos del expediente (PDF, hasta 10 MB)</label>
                    <ul style="margin: 6px 0 0; padding-left: 18px; line-height: 1.8; list-style: disc;">
                        <li>Currículum de la instancia (también acepta Word)</li>
                        <li>Identificación oficial del representante</li>
                        <li>Acta constitutiva o nombramiento legal</li>
                        <li>Constancia de situación fiscal</li>
                        <li>Comprobante de domicilio</li>
                        <li>Poder notarial (opcional)</li>
                    </ul>
                </div>

                <div class="b2b-action-container">
                    @auth
                        <a href="{{ route('dashboard') }}" class="b2b-btn-primary" style="text-decoration: none;">
                            <span>Ir a mi panel</span>
                        </a>
                    @else
                        <a href="{{ $solicitarUrl }}" class="b2b-btn-primary" style="text-decoration: none;">
                            <span>Registrar mi organización</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </a>
                        <a href="{{ route('login') }}" class="b2b-btn-secondary" style="margin-left: 12px;">Ya tengo cuenta</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
  </section>

@endsection
