@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Mi Programa" />


<style>
/* ==========================================================================
   MÓDULO SAAZ: MIS PRÁCTICAS (CUCSH INTERNO)
   ========================================================================== */

.practicas-saas-layout {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 24px;
    font-family: var(--font-outfit);
    background-color: var(--color-gray-50);
}

/* --- COPILOT PANEL (HERO PROACTIVO) --- */
.saas-hero-copilot {
    display: flex;
    align-items: center;
    gap: 20px;
    background: linear-gradient(135deg, var(--color-brand-950) 0%, var(--color-brand-900) 100%);
    padding: 24px;
    border-radius: 14px;
    border: 1px solid var(--color-brand-800);
    color: var(--color-white);
    box-shadow: var(--shadow-theme-md);
}

.copilot-icon-wrapper {
    position: relative;
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.pulse-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border: 2px solid var(--color-brand-400);
    border-radius: 10px;
    animation: saasPulse 2s infinite ease-in-out;
}

.copilot-content .copilot-heading {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-brand-200);
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.copilot-content .copilot-text {
    font-size: 15px;
    line-height: 1.5;
    color: var(--color-brand-50);
    margin-top: 4px;
}

.copilot-content .copilot-text strong {
    color: var(--color-warning-300);
    font-weight: 600;
}

/* --- DISTRIBUCIÓN DEL ESPACIO DE TRABAJO --- */
.saas-grid-workspace {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}

.saas-main-panel {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.saas-side-panel {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* --- TARJETAS BASE CON ESPACIOS EN BLANCO --- */
.saas-card {
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: 12px;
    padding: 28px;
    box-shadow: var(--shadow-theme-sm);
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

.saas-card:hover {
    box-shadow: var(--shadow-theme-md);
    border-color: var(--color-gray-300);
}

.section-card-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 20px;
}

/* --- SECCIÓN IDENTIDAD --- */
.identity-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid var(--color-gray-100);
    padding-bottom: 18px;
}

.badge-status-active {
    background: var(--color-success-50);
    color: var(--color-success-700);
    border: 1px solid var(--color-success-200);
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 99px;
    text-transform: uppercase;
}

.identity-title {
    font-size: 20px;
    font-weight: 800;
    color: var(--color-black);
    margin-top: 8px;
}

.identity-subtitle {
    color: var(--color-gray-500);
    font-size: 14px;
}

.identity-meta-right {
    text-align: right;
}

.identity-meta-right .meta-label {
    display: block;
    font-size: 11px;
    color: var(--color-gray-400);
    text-transform: uppercase;
}

.identity-meta-right .meta-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-gray-800);
}

.identity-timeline-specs {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-top: 18px;
}

.spec-item .spec-lbl {
    display: block;
    font-size: 12px;
    color: var(--color-gray-400);
}

.spec-item .spec-val {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-gray-700);
}

/* --- ANALÍTICAS DE PROGRESO DE HORAS --- */
.progression-master-wrapper {
    display: flex;
    gap: 32px;
    align-items: center;
}

.progress-radial-surrogate {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    border: 6px solid var(--color-brand-100);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--color-brand-25);
    flex-shrink: 0;
}

.giant-percentage {
    font-size: 22px;
    font-weight: 800;
    color: var(--color-brand-700);
}

.radial-sub {
    font-size: 9px;
    text-transform: uppercase;
    color: var(--color-brand-500);
}

.progress-linear-metrics {
    flex: 1;
}

.metric-row-inline {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: var(--color-gray-600);
    margin-bottom: 8px;
}

.metric-heavy {
    font-size: 18px;
    font-weight: 800;
    color: var(--color-black);
}

.saas-progress-track {
    width: 100%;
    height: 10px;
    background-color: var(--color-gray-100);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 20px;
}

.saas-progress-fill {
    height: 100%;
    background-color: var(--color-brand-500);
    border-radius: 99px;
    transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.metrics-micro-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.micro-box {
    background: var(--color-gray-25);
    border: 1px solid var(--color-gray-100);
    padding: 10px;
    border-radius: 8px;
}

.micro-lbl {
    display: block;
    font-size: 11px;
    color: var(--color-gray-400);
}

.micro-val {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-gray-800);
}

/* --- LINEA DE TIEMPO (TIMELINE) --- */
.card-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.saas-text-link {
    font-size: 13px;
    color: var(--color-brand-600);
    text-decoration: none;
    font-weight: 600;
}

.saas-timeline {
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: relative;
    padding-left: 20px;
}

.saas-timeline::before {
    content: '';
    position: absolute;
    left: 4px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: var(--color-gray-200);
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -20px;
    top: 4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--color-white);
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-approved .timeline-marker {
    background: var(--color-success-500);
    box-shadow: 0 0 0 4px var(--color-success-100);
}

.status-pending .timeline-marker {
    background: var(--color-warning-500);
    box-shadow: 0 0 0 4px var(--color-warning-100);
}

.timeline-body {
    padding-left: 12px;
}

.timeline-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.timeline-meta h5 {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-gray-800);
}

.hours-badge {
    font-size: 12px;
    font-weight: 700;
    color: var(--color-success-700);
    background: var(--color-success-50);
    padding: 2px 8px;
    border-radius: 4px;
}

.hours-badge.font-pending {
    color: var(--color-warning-700);
    background: var(--color-warning-50);
}

.timeline-date {
    font-size: 12px;
    color: var(--color-gray-400);
    margin-top: 2px;
}

.supervisor-comment-bubble {
    margin-top: 10px;
    background: var(--color-warning-25);
    border-left: 3px solid var(--color-warning-400);
    padding: 10px 14px;
    border-radius: 0 8px 8px 0;
    font-size: 13px;
    color: var(--color-warning-800);
}

/* --- ACCIONES EN PANEL LATERAL --- */
.side-panel-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--color-black);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 16px;
}

.actions-vertical-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-saas-primary {
    background: var(--color-brand-600);
    color: var(--color-white);
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background 0.2s ease;
}

.btn-saas-primary:hover {
    background: var(--color-brand-700);
}

.btn-saas-secondary {
    background: var(--color-white);
    border: 1px solid var(--color-gray-300);
    color: var(--color-gray-700);
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-saas-secondary:hover {
    background: var(--color-gray-50);
    border-color: var(--color-gray-400);
}

.btn-saas-minimal {
    background: transparent;
    border: none;
    color: var(--color-gray-500);
    font-size: 13px;
    font-weight: 500;
    padding: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-saas-minimal:hover {
    color: var(--color-brand-600);
}

/* --- ALERTAS NOTIFICACIONES --- */
.alert-item-container {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
}

.alert-item-container.warning {
    background: var(--color-error-25);
    border: 1px solid var(--color-error-100);
}

.alert-item-container.info {
    background: var(--color-blue-25);
    border: 1px solid var(--color-blue-100);
}

.alert-indicator-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 5px;
    flex-shrink: 0;
}

.warning .alert-indicator-dot { background: var(--color-error-500); }
.info .alert-indicator-dot { background: var(--color-blue-500); }

.alert-msg-body strong {
    display: block;
    font-size: 13px;
    color: var(--color-gray-800);
}

.alert-msg-body p {
    font-size: 11px;
    color: var(--color-gray-500);
}

/* --- HITOS Y PREMIOS (GAMIFICACIÓN) --- */
.badges-inline-flex {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.badge-item {
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    padding: 8px 12px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-gray-700);
}

.badge-item.locked {
    opacity: 0.5;
    background: var(--color-gray-100);
}

/* --- ANIMACIONES --- */
@keyframes saasPulse {
    0% { transform: scale(0.95); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.5; }
}

/* --- RESPONSIVE COMPATIBLE --- */
@media (max-width: 991px) {
    .saas-grid-workspace {
        grid-template-columns: 1fr;
    }
}


</style>


<div class="practicas-saas-layout">
    
    <section class="saas-hero-copilot" aria-labelledby="copilot-title">
        <div class="copilot-icon-wrapper">
            <span class="pulse-ring"></span>
            🎯
        </div>
        <div class="copilot-content">
            <h2 id="copilot-title" class="copilot-heading">Tu siguiente paso</h2>
            <p class="copilot-text">
                Solo te faltan <strong>16 horas</strong> para alcanzar el 60% de tu programa de prácticas. Antes del 15 de junio debes registrar al menos 2 actividades y subir 1 evidencia pendiente. Tu supervisor aún no revisa tu último registro de horas.
            </p>
        </div>
    </section>

    <div class="saas-grid-workspace">
        
        <div class="saas-main-panel">
            
            <div class="saas-card system-identity-card">
                <div class="identity-header">
                    <div>
                        <span class="badge-status-active">Prácticas Activas</span>
                        <h3 class="identity-title">Practicante de Desarrollo Web</h3>
                        <p class="identity-subtitle">Administradora Inicial C.A.</p>
                    </div>
                    <div class="identity-meta-right">
                        <span class="meta-label">Supervisor:</span>
                        <span class="meta-value">Dr. Juan Pérez</span>
                    </div>
                </div>
                
                <div class="identity-timeline-specs">
                    <div class="spec-item">
                        <span class="spec-lbl">Fecha de Inicio</span>
                        <span class="spec-val">02 Feb 2026</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-lbl">Progreso Temporal</span>
                        <span class="spec-val">4 meses restantes</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-lbl">Total Requerido</span>
                        <span class="spec-val">480 Horas</span>
                    </div>
                </div>
            </div>

            <div class="saas-card progression-analytics-card">
                <h4 class="section-card-title">Progreso del Programa</h4>
                
                <div class="progression-master-wrapper">
                    <div class="progress-radial-surrogate">
                        <div class="giant-percentage">50%</div>
                        <span class="radial-sub">Completado</span>
                    </div>
                    
                    <div class="progress-linear-metrics">
                        <div class="metric-row-inline">
                            <span>Horas Acumuladas</span>
                            <span class="metric-heavy">240 <small class="text-muted">/ 480 hrs</small></span>
                        </div>
                        <div class="saas-progress-track">
                            <div class="saas-progress-fill" style="width: 50%;"></div>
                        </div>
                        
                        <div class="metrics-micro-grid">
                            <div class="micro-box">
                                <span class="micro-lbl">Meta Semanal</span>
                                <span class="micro-val">20 hrs</span>
                            </div>
                            <div class="micro-box">
                                <span class="micro-lbl">Promedio Diario</span>
                                <span class="micro-val">4.2 hrs</span>
                            </div>
                            <div class="micro-box">
                                <span class="micro-lbl">Días Restantes</span>
                                <span class="micro-val">76 Días</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="saas-card activity-timeline-card">
                <div class="card-header-actions">
                    <h4 class="section-card-title">Bitácora de Actividades Recientes</h4>
                    <a href="#" class="saas-text-link">Ver toda la bitácora →</a>
                </div>

                <div class="saas-timeline">
                    <div class="timeline-item status-approved">
                        <div class="timeline-marker">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div class="timeline-body">
                            <div class="timeline-meta">
                                <h5>Desarrollo de módulo de usuarios</h5>
                                <span class="hours-badge">+8 horas</span>
                            </div>
                            <p class="timeline-date">10 Junio 2026 • <span class="status-lbl text-success">Aprobado</span></p>
                        </div>
                    </div>

                    <div class="timeline-item status-pending">
                        <div class="timeline-marker">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/></svg>
                        </div>
                        <div class="timeline-body">
                            <div class="timeline-meta">
                                <h5>Corrección de incidencias en maquetado</h5>
                                <span class="hours-badge font-pending">+6 horas</span>
                            </div>
                            <p class="timeline-date">08 Junio 2026 • <span class="status-lbl text-warning">En revisión</span></p>
                            <div class="supervisor-comment-bubble">
                                <strong>Comentario del Supervisor:</strong> Pendiente de verificar el archivo de evidencias CSS adjunto.
                            </div>
                        </div>
                    </div>

                    <div class="timeline-item status-approved">
                        <div class="timeline-marker">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div class="timeline-body">
                            <div class="timeline-meta">
                                <h5>Documentación técnica de la arquitectura</h5>
                                <span class="hours-badge">+4 horas</span>
                            </div>
                            <p class="timeline-date">05 Junio 2026 • <span class="status-lbl text-success">Aprobado</span></p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <aside class="saas-side-panel" aria-label="Acciones y alertas de seguimiento">
            
            <div class="saas-card actions-box-card">
                <h4 class="side-panel-title">Acciones rápidas</h4>
                <div class="actions-vertical-group">
                    <button class="btn-saas-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Registrar actividad de hoy
                    </button>
                    <button class="btn-saas-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Subir archivo de evidencia
                    </button>
                    <button class="btn-saas-minimal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        Contactar con Supervisor
                    </button>
                </div>
            </div>

            <div class="saas-card alerts-box-card">
                <h4 class="side-panel-title">Atención requerida</h4>
                <div class="alert-item-container warning">
                    <div class="alert-indicator-dot"></div>
                    <div class="alert-msg-body">
                        <strong>Reporte Bimestral faltante</strong>
                        <p>Fecha límite: 15 de Junio</p>
                    </div>
                </div>
                <div class="alert-item-container info">
                    <div class="alert-indicator-dot"></div>
                    <div class="alert-msg-body">
                        <strong>Perfil validado</strong>
                        <p>Tu seguro facultativo ha sido verificado.</p>
                    </div>
                </div>
            </div>

            <div class="saas-card gamification-badges-card">
                <h4 class="side-panel-title">Hitos del Programa</h4>
                <div class="badges-inline-flex">
                    <div class="badge-item tooltipped" title="Completaste tus primeras 100 horas operativas">
                        <span class="badge-icon">🎉</span>
                        <span class="badge-lbl">100 Hrs</span>
                    </div>
                    <div class="badge-item tooltipped" title="Mitad del camino obligatorio cubierto">
                        <span class="badge-icon">⚡</span>
                        <span class="badge-lbl">50% Club</span>
                    </div>
                    <div class="badge-item locked tooltipped" title="Registrar actividades sin incidencias por 4 semanas consecutivas">
                        <span class="badge-icon">🔒</span>
                        <span class="badge-lbl">Constancia</span>
                    </div>
                </div>
            </div>

        </aside>
    </div>
</div>
































@php
    // Simulación
    $programa = true;
@endphp

@if($programa)

    <!-- Programa activo -->
    <div class="space-y-6">

        <!-- Hero -->
        <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">

            <img
                src="{{ asset('images/grid-image/image-01.png') }}"
                alt="Empresa"
                class="object-cover w-full h-64"
            >

            <div class="p-6">

                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">

                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-500">
                            Administradora Inicial C.A.
                        </p>

                        <h1 class="text-2xl font-bold text-gray-900">
                            Practicante de Desarrollo Web
                        </h1>
                    </div>

                    <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-green-700 bg-green-100 rounded-full">
                        Activo
                    </span>

                </div>

            </div>

        </div>

        <!-- Resumen -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">

            <div class="p-5 bg-white border border-gray-200 rounded-2xl">
                <p class="text-sm text-gray-500">Empresa</p>
                <h3 class="mt-2 text-lg font-semibold">
                    Administradora Inicial C.A.
                </h3>
            </div>

            <div class="p-5 bg-white border border-gray-200 rounded-2xl">
                <p class="text-sm text-gray-500">Supervisor</p>
                <h3 class="mt-2 text-lg font-semibold">
                    Dr. Juan Pérez
                </h3>
            </div>

            <div class="p-5 bg-white border border-gray-200 rounded-2xl">
                <p class="text-sm text-gray-500">Horas acumuladas</p>
                <h3 class="mt-2 text-lg font-semibold">
                    240 / 480
                </h3>
            </div>

            <div class="p-5 bg-white border border-gray-200 rounded-2xl">
                <p class="text-sm text-gray-500">Fecha de inicio</p>
                <h3 class="mt-2 text-lg font-semibold">
                    02 Feb 2026
                </h3>
            </div>

        </div>

        <!-- Progreso -->
        <div class="p-6 bg-white border border-gray-200 rounded-2xl">

            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">
                    Avance de Horas
                </h2>

                <span class="font-semibold text-brand-500">
                    50%
                </span>
            </div>

            <div class="w-full h-4 overflow-hidden bg-gray-200 rounded-full">
                <div
                    class="h-full rounded-full bg-brand-500"
                    style="width:50%"
                ></div>
            </div>

            <p class="mt-3 text-sm text-gray-500">
                Has completado 240 de las 480 horas requeridas.
            </p>

        </div>

        <!-- Actividades recientes -->
        <div class="p-6 bg-white border border-gray-200 rounded-2xl">

            <h2 class="mb-4 text-lg font-semibold">
                Actividades recientes
            </h2>

            <div class="space-y-4">

                <div class="flex items-center justify-between pb-4 border-b">
                    <div>
                        <p class="font-medium">
                            Desarrollo de módulo de usuarios
                        </p>
                        <p class="text-sm text-gray-500">
                            10 Junio 2026
                        </p>
                    </div>

                    <span class="text-green-600 font-medium">
                        8 horas
                    </span>
                </div>

                <div class="flex items-center justify-between pb-4 border-b">
                    <div>
                        <p class="font-medium">
                            Corrección de incidencias
                        </p>
                        <p class="text-sm text-gray-500">
                            08 Junio 2026
                        </p>
                    </div>

                    <span class="text-green-600 font-medium">
                        6 horas
                    </span>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">
                            Documentación técnica
                        </p>
                        <p class="text-sm text-gray-500">
                            05 Junio 2026
                        </p>
                    </div>

                    <span class="text-green-600 font-medium">
                        4 horas
                    </span>
                </div>

            </div>

        </div>

        <!-- Acciones -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">

            <a href="#"
               class="p-6 text-center transition bg-white border border-gray-200 rounded-2xl hover:border-brand-500">
                <div class="mb-2 text-3xl">📖</div>
                <h3 class="font-semibold">Bitácora</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Consultar actividades registradas
                </p>
            </a>

            <a href="#"
               class="p-6 text-center transition bg-white border border-gray-200 rounded-2xl hover:border-brand-500">
                <div class="mb-2 text-3xl">📎</div>
                <h3 class="font-semibold">Evidencias</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Subir archivos y documentos
                </p>
            </a>

            <a href="#"
               class="p-6 text-center transition bg-white border border-gray-200 rounded-2xl hover:border-brand-500">
                <div class="mb-2 text-3xl">📝</div>
                <h3 class="font-semibold">Registrar actividad</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Capturar horas realizadas
                </p>
            </a>

        </div>

    </div>

@else

    <!-- Sin programa -->
    <div class="flex items-center justify-center min-h-[60vh]">

        <div class="max-w-lg p-10 text-center bg-white border border-gray-200 rounded-2xl">

            <div class="mb-6 text-6xl">
                🎓
            </div>

            <h2 class="mb-3 text-2xl font-bold text-gray-900">
                Todavía no perteneces a algún programa
            </h2>

            <p class="mb-8 text-gray-500">
                Explora las oportunidades disponibles y postúlate a una vacante para comenzar tus prácticas profesionales.
            </p>

            <a
                href="{{ route('oferta-programas') }}"
                class="inline-flex items-center px-6 py-3 font-medium text-white rounded-xl bg-brand-500 hover:bg-brand-600"
            >
                Ver catálogo de programas
            </a>

        </div>

    </div>

@endif

@endsection