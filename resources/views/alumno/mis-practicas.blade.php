{{-- resources/views/alumno/mis-practicas.blade.php --}}
@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Mi Programa" />

@push('estilos')
    @vite('resources/css/paginas/mis-practicas.css')
@endpush

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
                        <div class="timeline-item {{ $ok ? 'Validado' : ($rech ? 'Rechazado' : ($r->estado === \App\Enums\ReporteEstado::Anulado ? 'Anulado' : 'En revisión')) }}">
                            <div class="timeline-marker">
                                @if ($ok)
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                @else
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/></svg>
                                @endif
                            </div>
                            <div class="timeline-body">
                                <div class="timeline-meta">
                                    <h5>Reporte {{ mb_strtolower($r->tipo->label()) }}</h5>
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
                    @php $correoCoordinacion = auth()->user()->alumno->carrera->correoContacto() ?? config('practicas.contacto.correo'); @endphp
                    <a href="{{ App\Support\Correo::gmail($correoCoordinacion, 'Prácticas profesionales · ' . auth()->user()->alumno->num_control) }}" target="_blank" rel="noopener" class="btn-saas-minimal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        Contactar a mi coordinación
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