{{-- resources/views/alumno/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
<style>
/* ==========================================================================
   ESTILOS PREMIUM: STUDENT HOME DASHBOARD (CUCSH 2026)
   ========================================================================== */

.cucsh-dashboard-container {
    display: flex;
    flex-direction: column;
    gap: 28px;
    padding: 32px;
    max-width: 1600px;
    margin: 0 auto;
    font-family: var(--font-outfit);
}

/* --- HERO ENCABEZADO CON CONTROLADORES --- */
.dashboard-welcome-hero {
    display: flex;
    flex-direction: column;
    gap: 20px;
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    padding: 28px;
    border-radius: 16px;
    box-shadow: var(--shadow-theme-sm);
}

.welcome-profile-area {
    display: flex;
    align-items: center;
    gap: 16px;
}

.avatar-ring-wrapper {
    position: relative;
    width: 60px;
    height: 60px;
}

.student-hero-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--color-brand-200);
}

.online-status-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 14px;
    height: 14px;
    background-color: var(--color-success-500);
    border: 2px solid var(--color-white);
    border-radius: 50%;
}

.welcome-text-strings h1 {
    font-size: 24px;
    font-weight: 800;
    color: var(--color-black);
    letter-spacing: -0.02em;
}

.academic-subtext {
    font-size: 14px;
    color: var(--color-gray-500);
}

/* --- BANNER INTEGRADO INTERNO "SIGUIENTE PASO" --- */
.home-next-step-badge {
    background-color: var(--color-brand-25);
    border-left: 4px solid var(--color-brand-500);
    padding: 16px 20px;
    border-radius: 0 12px 12px 0;
}

.next-step-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--color-brand-800);
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}

.pulse-icon-ping {
    width: 6px;
    height: 6px;
    background-color: var(--color-brand-600);
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 3px var(--color-brand-200);
}

.home-next-step-badge p {
    font-size: 14px;
    color: var(--color-gray-700);
    line-height: 1.5;
}

/* --- GRID DE DISTRIBUCIÓN --- */
.dashboard-workspace-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 28px;
    align-items: start;
}

.dashboard-workspace-main {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

/* --- KPI SAAS CARDS --- */
.kpi-dashboard-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.kpi-saas-card {
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
    box-shadow: var(--shadow-theme-xs);
}

.kpi-icon-container {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.kpi-icon-container.bg-brand { background: var(--color-brand-50); color: var(--color-brand-600); }
.kpi-icon-container.bg-success { background: var(--color-success-50); color: var(--color-success-600); }
.kpi-icon-container.bg-warning { background: var(--color-warning-50); color: var(--color-warning-600); }

.kpi-data {
    flex: 1;
}

.kpi-title-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-gray-400);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.kpi-numeric-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--color-black);
    margin: 4px 0;
}

.kpi-numeric-value small {
    font-size: 13px;
    color: var(--color-gray-500);
    font-weight: 400;
}

.kpi-mini-progress {
    width: 100%;
    height: 6px;
    background: var(--color-gray-100);
    border-radius: 99px;
    overflow: hidden;
    margin-top: 8px;
}

.kpi-mini-progress .fill {
    height: 100%;
    background: var(--color-brand-500);
    border-radius: 99px;
}

.kpi-subtext-caption {
    font-size: 12px;
    font-weight: 500;
}

.text-success { color: var(--color-success-700); }
.text-warning { color: var(--color-warning-700); }

/* --- ACCIONES CON HOVER DE ALTA GAMA --- */
.dashboard-section-heading {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 16px;
}

.quick-actions-cards-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.action-hub-card {
    display: flex;
    align-items: center;
    gap: 20px;
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    padding: 20px 24px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: var(--shadow-theme-xs);
}

.action-hub-card:hover {
    border-color: var(--color-brand-300);
    background-color: var(--color-brand-25);
    transform: translateY(-2px);
    box-shadow: var(--shadow-theme-md);
}

.hub-icon-box {
    font-size: 24px;
    width: 48px;
    height: 48px;
    background: var(--color-gray-50);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
}

.action-hub-card:hover .hub-icon-box {
    background-color: var(--color-white);
}

.hub-content {
    flex: 1;
}

.hub-content h3 {
    font-size: 15px;
    font-weight: 700;
    color: var(--color-black);
}

.hub-content p {
    font-size: 13px;
    color: var(--color-gray-500);
    margin-top: 2px;
}

.hub-arrow-indicator {
    font-size: 18px;
    color: var(--color-gray-400);
    transition: transform 0.2s ease, color 0.2s ease;
}

.action-hub-card:hover .hub-arrow-indicator {
    transform: translateX(4px);
    color: var(--color-brand-600);
}

/* --- SIDEBAR DE ALERTAS --- */
.sidebar-saas-widget {
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}

.widget-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-gray-400);
    margin-bottom: 16px;
}

.widget-alert-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.saas-alert-pill {
    display: flex;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    background: var(--color-gray-25);
    border: 1px solid var(--color-gray-200);
}

.saas-alert-pill.border-danger { border-left: 3px solid var(--color-error-500); }
.saas-alert-pill.border-info { border-left: 3px solid var(--color-blue-400); }

.pill-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 4px;
}
.pill-dot.bg-danger { background-color: var(--color-error-500); }
.pill-dot.bg-info { background-color: var(--color-blue-500); }

.pill-info h4 {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-gray-800);
}

.pill-info p {
    font-size: 11px;
    color: var(--color-gray-500);
    margin-top: 1px;
}

/* --- GAMIFICACIÓN: SEGURIDAD Y LOGROS --- */
.motivation-widget-theme {
    background: linear-gradient(180deg, var(--color-gray-950) 0%, var(--color-gray-900) 100%);
    color: var(--color-white);
    border: none;
    text-align: center;
}

.motivation-icon-header {
    font-size: 28px;
    margin-bottom: 8px;
}

.motivation-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-brand-200);
}

.motivation-description {
    font-size: 13px;
    color: var(--color-gray-300);
    margin-top: 6px;
    line-height: 1.4;
}

.motivation-badge-inline {
    margin-top: 14px;
    display: inline-block;
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 12px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
    color: var(--color-warning-300);
}

/* --- CONTROL RESPONSIVE --- */
@media (max-width: 1024px) {
    .dashboard-workspace-layout {
        grid-template-columns: 1fr;
    }
}

 </style>

<x-common.page-breadcrumb pageTitle="Dashboard" />

@php
    $iniciales = mb_strtoupper(mb_substr($alumno->user->name, 0, 1));
    $partes = preg_split('/\s+/', trim($alumno->user->name));
    $iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
@endphp

<div class="min-h-screen rounded-2xl border border-gray-200 bg-white">
    <div class="cucsh-dashboard-container">

        <header class="dashboard-welcome-hero">
            <div class="welcome-profile-area">
                <div class="avatar-ring-wrapper">
                    <div class="student-hero-avatar" style="display:flex;align-items:center;justify-content:center;background:var(--color-brand-50);color:var(--color-brand-600);font-weight:800;font-size:20px;">{{ $iniciales }}</div>
                    <span class="online-status-dot"></span>
                </div>
                <div class="welcome-text-strings">
                    <h1>Hola de nuevo, {{ $saludo }} 👋</h1>
                    <p class="academic-subtext">{{ $alumno->carrera->nombre }} • Código: {{ $alumno->num_control }}</p>
                </div>
            </div>

            <div class="home-next-step-badge">
                <div class="next-step-header">
                    <span class="pulse-icon-ping"></span>
                    <strong>🎯 TU SIGUIENTE PASO HOY:</strong>
                </div>
                <p>{{ $paso }}</p>
            </div>
        </header>

        <div class="dashboard-workspace-layout">

            <main class="dashboard-workspace-main">

                @if ($kpis)
                    <section class="kpi-dashboard-row" aria-label="Métricas de progreso">
                        <div class="kpi-saas-card">
                            <div class="kpi-icon-container bg-brand">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <div class="kpi-data">
                                <span class="kpi-title-label">Horas validadas</span>
                                <div class="kpi-numeric-value">{{ $kpis['horas'] }} / <small>{{ $kpis['requeridas'] }} hrs</small></div>
                                <div class="kpi-mini-progress"><div class="fill" style="width: {{ $kpis['pct'] }}%"></div></div>
                            </div>
                        </div>

                        <div class="kpi-saas-card">
                            <div class="kpi-icon-container bg-success">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <div class="kpi-data">
                                <span class="kpi-title-label">Reportes validados</span>
                                <div class="kpi-numeric-value">{{ $kpis['validados'] }} <small>de {{ $kpis['reportes_total'] }}</small></div>
                                <p class="kpi-subtext-caption text-success">{{ $kpis['rechazados'] }} rechazado(s)</p>
                            </div>
                        </div>

                        <div class="kpi-saas-card">
                            <div class="kpi-icon-container bg-warning">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div class="kpi-data">
                                <span class="kpi-title-label">Pendientes</span>
                                <div class="kpi-numeric-value">{{ $kpis['pendientes'] }} <small>reporte(s)</small></div>
                                <p class="kpi-subtext-caption text-warning">{{ $kpis['dias_restantes'] }} días restantes</p>
                            </div>
                        </div>
                    </section>
                @endif

                <section class="dashboard-quick-actions-section">
                    <h2 class="dashboard-section-heading">¿Qué necesitas hacer hoy?</h2>
                    <div class="quick-actions-cards-grid">

                        @if ($practica)
                            <a href="{{ route('alumno.mis-documentos') }}" class="action-hub-card">
                                <div class="hub-icon-box">📁</div>
                                <div class="hub-content">
                                    <h3>Subir reportes y documentos</h3>
                                    <p>Reportes firmados, carta de término y constancias.</p>
                                </div>
                                <span class="hub-arrow-indicator">→</span>
                            </a>
                            <a href="{{ route('alumno.mis-practicas') }}" class="action-hub-card">
                                <div class="hub-icon-box">🏢</div>
                                <div class="hub-content">
                                    <h3>Ver mi práctica</h3>
                                    <p>{{ $empresa?->razon_social ?? 'Proyecto interno' }} · {{ $programa->titulo }}</p>
                                </div>
                                <span class="hub-arrow-indicator">→</span>
                            </a>
                        @else
                            <a href="{{ route('alumno.oferta-programas') }}" class="action-hub-card">
                                <div class="hub-icon-box">🔎</div>
                                <div class="hub-content">
                                    <h3>Explorar programas</h3>
                                    <p>Encuentra vacantes para {{ $alumno->carrera->nombre }}.</p>
                                </div>
                                <span class="hub-arrow-indicator">→</span>
                            </a>
                            <a href="{{ route('alumno.mis-documentos') }}" class="action-hub-card">
                                <div class="hub-icon-box">📁</div>
                                <div class="hub-content">
                                    <h3>Mis documentos</h3>
                                    <p>Constancia IMSS, kardex y cartas de tu postulación.</p>
                                </div>
                                <span class="hub-arrow-indicator">→</span>
                            </a>
                            @if ($enTramite)
                                <a href="{{ route('alumno.mis-practicas') }}" class="action-hub-card">
                                    <div class="hub-icon-box">⏳</div>
                                    <div class="hub-content">
                                        <h3>Seguimiento de mi postulación</h3>
                                        <p>{{ $enTramite->estado->label() }} · {{ $enTramite->programa->titulo }}</p>
                                    </div>
                                    <span class="hub-arrow-indicator">→</span>
                                </a>
                            @endif
                        @endif

                    </div>
                </section>
            </main>

            <aside class="dashboard-workspace-sidebar" aria-label="Panel de control diario">

                <div class="sidebar-saas-widget">
                    <h3 class="widget-title">Alertas académicas</h3>
                    <div class="widget-alert-list">
                        @forelse ($alertas as $a)
                            <div class="saas-alert-pill {{ $a['tipo'] === 'danger' ? 'border-danger' : 'border-info' }}">
                                <div class="pill-dot {{ $a['tipo'] === 'danger' ? 'bg-danger' : 'bg-info' }}"></div>
                                <div class="pill-info">
                                    <h4>{{ $a['titulo'] }}</h4>
                                    <p>{{ $a['texto'] }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="saas-alert-pill border-info">
                                <div class="pill-dot bg-info"></div>
                                <div class="pill-info">
                                    <h4>{{ $imssOk ? 'Seguro IMSS verificado' : 'Sube tu constancia IMSS' }}</h4>
                                    <p>{{ $imssOk ? 'Cumples el requisito de seguro facultativo.' : 'Es requisito para activar tu práctica.' }}</p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="sidebar-saas-widget motivation-widget-theme">
                    <div class="motivation-icon-header">{{ $motivacion['icono'] }}</div>
                    <h4 class="motivation-title">{{ $motivacion['titulo'] }}</h4>
                    <p class="motivation-description">{{ $motivacion['texto'] }}</p>
                    @if ($kpis)
                        <div class="motivation-badge-inline"><span>{{ $kpis['pct'] }}% completado</span></div>
                    @endif
                </div>

            </aside>
        </div>
    </div>
</div>
@endsection