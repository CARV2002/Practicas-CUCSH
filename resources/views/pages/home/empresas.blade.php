@extends('layouts.landing')

@section('title', 'Empresas | Prácticas Profesionales CUCSH')

@section('content')

@push('estilos')
    @vite('resources/css/paginas/empresas.css')
@endpush

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
                <p>Usted elige las carreras de su interés y propone vacantes dirigidas a ellas, que publica la coordinación de cada carrera. Solo se postulan alumnos de la carrera del programa que cumplen el porcentaje mínimo de créditos que exige su plan.</p>
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
                <p>Dé seguimiento a su convenio, proponga vacantes, consulte postulantes y practicantes y retroalimente sus reportes desde un mismo lugar, con notificaciones en cada cambio de etapa.</p>
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
                <h3>Proponga sus vacantes</h3>
                <p>Con el convenio formalizado, registre vacantes para las carreras aprobadas en él: ciclo, actividades, perfil requerido, horario, sede y cupos. La coordinación de cada carrera revisa horario y actividades y la publica para sus alumnos; usted puede cerrarla cuando se cubra.</p>
            </div>

            <div class="b2b-step-card">
                <div class="b2b-step-badge">Paso 03</div>
                <h3>Reciba practicantes y retroalimente</h3>
                <p>Consulte a los postulantes con carta de presentación y entregue a cada alumno su carta de aceptación. Durante la práctica puede retroalimentar los reportes; la coordinación de cada carrera valida las horas y da el cierre.</p>
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
                    <p>Mientras el convenio esté vigente puede proponer vacantes. La renovación se solicita dentro de los 60 días previos al vencimiento.</p>
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
                <p>Al solicitar su convenio elige las carreras de su interés. Cada una se valida académicamente y solo para las aprobadas podrá proponer vacantes.</p>
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
                <p>Cree la cuenta de su organización y solicite su convenio directamente en la plataforma. Vinculación del CUCSH dará seguimiento a su solicitud en cada fase.</p>
                <div class="b2b-support-notice">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span>Atención directa: <a href="{{ App\Support\Correo::gmail(config('practicas.contacto.correo')) }}" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline;">{{ config('practicas.contacto.correo') }}</a></span>
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
