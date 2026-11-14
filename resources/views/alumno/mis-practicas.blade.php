{{-- resources/views/alumno/mis-practicas.blade.php --}}
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

@if (! $practica)
    {{-- Sin práctica activa --}}
    <div class="practicas-saas-layout">
        <section class="saas-hero-copilot">
            <div class="copilot-icon-wrapper"><span class="pulse-ring"></span>{{ $mensaje['icono'] }}</div>
            <div class="copilot-content">
                <h2 class="copilot-heading">{{ $mensaje['titulo'] }}</h2>
                <p class="copilot-text">{{ $mensaje['texto'] }}</p>
            </div>
        </section>

        <div class="saas-card" style="text-align:center; padding:48px 28px;">
            <div style="font-size:48px;">🎓</div>
            <h3 class="identity-title">Todavía no perteneces a ningún programa</h3>
            <p class="identity-subtitle" style="margin:8px 0 24px;">Explora las oportunidades para {{ $alumno->carrera->nombre }} y postúlate a una vacante.</p>
            <div class="actions-vertical-group" style="max-width:280px; margin:0 auto;">
                <a href="{{ route('alumno.oferta-programas') }}" class="btn-saas-primary">Ver catálogo de programas</a>
                @if ($enTramite)
                    <a href="{{ route('alumno.mis-documentos') }}" class="btn-saas-secondary">Ir a Mis documentos</a>
                @endif
            </div>
        </div>
    </div>
@else
<div class="practicas-saas-layout">

    {{-- Siguiente paso --}}
    <section class="saas-hero-copilot" aria-labelledby="copilot-title">
        <div class="copilot-icon-wrapper"><span class="pulse-ring"></span>🎯</div>
        <div class="copilot-content">
            <h2 id="copilot-title" class="copilot-heading">Tu siguiente paso</h2>
            <p class="copilot-text">{{ $mensaje }}</p>
        </div>
    </section>

    <div class="saas-grid-workspace">

        <div class="saas-main-panel">

            {{-- Identidad --}}
            <div class="saas-card system-identity-card">
                <div class="identity-header">
                    <div>
                        <span class="badge-status-active">{{ $practica->estado->label() }}</span>
                        <h3 class="identity-title">{{ $programa->titulo }}</h3>
                        <p class="identity-subtitle">{{ $empresa?->razon_social ?? 'Proyecto interno' }}</p>
                    </div>
                    <div class="identity-meta-right">
                        <span class="meta-label">Supervisor:</span>
                        <span class="meta-value">{{ $practica->supervisor?->nombre ?? 'Por asignar' }}</span>
                    </div>
                </div>

                <div class="identity-timeline-specs">
                    <div class="spec-item">
                        <span class="spec-lbl">Fecha de inicio</span>
                        <span class="spec-val">{{ $practica->fecha_inicio->translatedFormat('d M Y') }}</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-lbl">Fecha de término</span>
                        <span class="spec-val">{{ $practica->fecha_fin->translatedFormat('d M Y') }}</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-lbl">Total requerido</span>
                        <span class="spec-val">{{ $requeridas }} horas</span>
                    </div>
                </div>
            </div>

            {{-- Progreso --}}
            <div class="saas-card progression-analytics-card">
                <h4 class="section-card-title">Progreso del programa</h4>

                <div class="progression-master-wrapper">
                    <div class="progress-radial-surrogate">
                        <div class="giant-percentage">{{ $pct }}%</div>
                        <span class="radial-sub">Completado</span>
                    </div>

                    <div class="progress-linear-metrics">
                        <div class="metric-row-inline">
                            <span>Horas validadas</span>
                            <span class="metric-heavy">{{ $horas }} <small class="text-muted">/ {{ $requeridas }} hrs</small></span>
                        </div>
                        <div class="saas-progress-track">
                            <div class="saas-progress-fill" style="width: {{ $pct }}%;"></div>
                        </div>

                        <div class="metrics-micro-grid">
                            <div class="micro-box">
                                <span class="micro-lbl">Meta semanal</span>
                                <span class="micro-val">{{ $metricas['meta_semanal'] }} hrs</span>
                            </div>
                            <div class="micro-box">
                                <span class="micro-lbl">Tu ritmo semanal</span>
                                <span class="micro-val">{{ $metricas['ritmo_semanal'] }} hrs</span>
                            </div>
                            <div class="micro-box">
                                <span class="micro-lbl">Días restantes</span>
                                <span class="micro-val">{{ $metricas['dias_restantes'] }} días</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Reportes recientes (timeline) --}}
            <div class="saas-card activity-timeline-card">
                <div class="card-header-actions">
                    <h4 class="section-card-title">Reportes recientes</h4>
                    <a href="{{ route('alumno.mis-documentos') }}" class="saas-text-link">Ir a Mis documentos →</a>
                </div>

                <div class="saas-timeline">
                    @forelse ($reportes as $r)
                        @php
                            $ok = $r->estado === \App\Enums\ReporteEstado::Validado;
                            $rech = $r->estado === \App\Enums\ReporteEstado::Rechazado;
                            $comentario = $r->evaluacion_profesor ?: $r->retroalimentacion_empresa;
                        @endphp
                        <div class="timeline-item {{ $ok ? 'status-approved' : 'status-pending' }}">
                            <div class="timeline-marker">
                                @if ($ok)
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                @else
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/></svg>
                                @endif
                            </div>
                            <div class="timeline-body">
                                <div class="timeline-meta">
                                    <h5>Reporte {{ $r->tipo->value }}</h5>
                                    <span class="hours-badge {{ $ok ? '' : 'font-pending' }}">{{ $ok ? '+' : '' }}{{ $r->horas_reportadas }} horas</span>
                                </div>
                                <p class="timeline-date">
                                    {{ $r->periodo_inicio->translatedFormat('d M') }} – {{ $r->periodo_fin->translatedFormat('d M Y') }} •
                                    <span class="status-lbl {{ $ok ? 'text-success' : 'text-warning' }}">{{ $ok ? 'Validado' : ($rech ? 'Rechazado' : 'En revisión') }}</span>
                                </p>
                                @if ($comentario)
                                    <div class="supervisor-comment-bubble">
                                        <strong>Comentario:</strong> {{ $comentario }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="timeline-date">Aún no hay reportes registrados para tu práctica.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="saas-side-panel" aria-label="Acciones y alertas de seguimiento">

            {{-- Acciones --}}
            <div class="saas-card actions-box-card">
                <h4 class="side-panel-title">Acciones rápidas</h4>
                <div class="actions-vertical-group">
                    <a href="{{ route('alumno.mis-documentos') }}" class="btn-saas-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Subir reporte firmado
                    </a>
                    <a href="{{ route('alumno.mis-documentos') }}" class="btn-saas-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Mis documentos
                    </a>
                    <a href="mailto:{{ config('practicas.contacto.correo') }}" class="btn-saas-minimal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        Contactar a la Coordinación
                    </a>
                </div>
            </div>

            {{-- Alertas --}}
            <div class="saas-card alerts-box-card">
                <h4 class="side-panel-title">Atención requerida</h4>
                @foreach ($alertas as $a)
                    <div class="alert-item-container {{ $a['tipo'] === 'danger' ? 'warning' : 'info' }}">
                        <div class="alert-indicator-dot"></div>
                        <div class="alert-msg-body">
                            <strong>{{ $a['titulo'] }}</strong>
                            <p>{{ $a['texto'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Mensaje motivacional --}}
            <div class="saas-card motivation-widget-theme">
                <div class="motivation-icon-header">{{ $motivacion['icono'] }}</div>
                <h4 class="motivation-title">{{ $motivacion['titulo'] }}</h4>
                <p class="motivation-description">{{ $motivacion['texto'] }}</p>
                <div class="motivation-badge-inline"><span>{{ $pct }}% completado</span></div>
            </div>

            {{-- Hitos --}}
            <div class="saas-card gamification-badges-card">
                <h4 class="side-panel-title">Hitos del programa</h4>
                <div class="badges-inline-flex">
                    @foreach ($hitos as $h)
                        <div class="badge-item tooltipped {{ $h['logrado'] ? '' : 'locked' }}" title="{{ $h['detalle'] }}">
                            <span class="badge-icon">{{ $h['logrado'] ? $h['icono'] : '🔒' }}</span>
                            <span class="badge-lbl">{{ $h['titulo'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>
</div>
@endif
@endsection