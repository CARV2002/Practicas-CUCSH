@extends('layouts.landing')

@section('title', 'Estudiantes | Prácticas Profesionales CUCSH')

@section('content')

@push('estilos')
    @vite('resources/css/paginas/estudiantes.css')
@endpush

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
                <p>La que te entrega la organización al aceptarte. Puedes subirla en cuanto tengas tu carta de presentación; con ella tu coordinación de carrera activa tu práctica.</p>
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

  {{-- Accesos: la lista completa de convenios y el directorio viven en sus propias páginas --}}
  <section class="bg-gray-50 !py-16 sm:!py-20" aria-labelledby="titulo-donde">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
      <div class="max-w-2xl">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-oro-700">Antes de postularte</p>
        <h2 id="titulo-donde" class="mt-2 font-display text-2xl font-bold text-udg-900 sm:text-3xl">¿Dónde hago mis prácticas y a quién acudo?</h2>
      </div>

      <div class="mt-8 grid gap-5 md:grid-cols-2">
        <a href="{{ route('convenios-vigentes') }}"
          class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs transition duration-200 hover:-translate-y-0.5 hover:border-udg-200 hover:shadow-theme-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-8">
          <span class="flex size-12 items-center justify-center rounded-xl bg-udg-50 text-udg-900 ring-1 ring-udg-100" aria-hidden="true">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-4h6v4M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01"/></svg>
          </span>
          <h3 class="mt-5 font-display text-xl font-bold text-gray-900">Organizaciones con convenio vigente</h3>
          <p class="mt-2 text-sm leading-relaxed text-gray-600">
            Las prácticas externas solo pueden hacerse en una organización con convenio <strong class="font-semibold text-gray-800">vigente para tu carrera</strong>.
            Consulta la lista completa, filtra por tu carrera y revisa hasta cuándo es válido cada convenio.
            @if ($organizaciones > 0)
              Hoy hay <strong class="font-semibold text-gray-800">{{ $organizaciones }} {{ $organizaciones === 1 ? 'organización' : 'organizaciones' }}</strong> con convenio vigente.
            @endif
          </p>
          <span class="mt-auto inline-flex items-center gap-2 pt-6 text-sm font-semibold text-real-600 group-hover:text-real-700">
            Ver organizaciones con convenio
            <svg class="size-4 transition-transform duration-200 group-hover:translate-x-1 motion-reduce:transition-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </span>
        </a>

        <a href="{{ route('contacto') }}#directorio"
          class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs transition duration-200 hover:-translate-y-0.5 hover:border-udg-200 hover:shadow-theme-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:transition-none motion-reduce:hover:translate-y-0 sm:p-8">
          <span class="flex size-12 items-center justify-center rounded-xl bg-oro-50 text-oro-700 ring-1 ring-oro-100" aria-hidden="true">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><circle cx="12" cy="10" r="3"/><path d="M7.5 17a5 5 0 0 1 9 0"/></svg>
          </span>
          <h3 class="mt-5 font-display text-xl font-bold text-gray-900">Directorio de coordinaciones de carrera</h3>
          <p class="mt-2 text-sm leading-relaxed text-gray-600">
            Tu postulación, carta de presentación, reportes, carta de término y liberación los atiende
            <strong class="font-semibold text-gray-800">la coordinación de tu carrera</strong>. Encuentra su correo y teléfono en la página de Contacto.
          </p>
          <span class="mt-auto inline-flex items-center gap-2 pt-6 text-sm font-semibold text-real-600 group-hover:text-real-700">
            Buscar mi coordinación
            <svg class="size-4 transition-transform duration-200 group-hover:translate-x-1 motion-reduce:transition-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </span>
        </a>
      </div>
    </div>
  </section>

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
                <p>Si tienes dudas con la carga de documentos, con el estado de tu postulación o con tu práctica, comunícate con la coordinación de tu carrera (directorio en la página de Contacto). Vinculación solo atiende convenios con organizaciones.</p>
                @if (auth()->user()?->tieneAyuda())
                <div class="quick-contact-meta">
                    <p>También puedes consultar la <a href="{{ route('ayuda') }}" style="color: inherit; text-decoration: underline;">sección de ayuda</a> dentro de la plataforma.</p>
                </div>
                @endif
            </div>
            <div class="contact-form-side">
                <div class="form-group">
                    <label>Vinculación (solo convenios con organizaciones)</label>
                    <a href="{{ App\Support\Correo::gmail(config('practicas.contacto.correo')) }}" target="_blank" rel="noopener" style="font-size: var(--text-theme-sm); color: var(--color-black);">{{ config('practicas.contacto.correo') }}</a>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <a href="tel:{{ config('practicas.contacto.telefono_enlace') }}" style="font-size: var(--text-theme-sm); color: var(--color-black);">{{ config('practicas.contacto.telefono') }}, ext. {{ implode(' o ', config('practicas.contacto.extensiones')) }}</a>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <span style="font-size: var(--text-theme-sm); color: var(--color-black);">{{ config('practicas.domicilio_cucsh') }}</span>
                </div>
                <a href="{{ route('contacto') }}#directorio" class="btn-primary-submit" style="text-decoration: none;">
                    Buscar mi coordinación
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </a>
            </div>
        </div>
    </div>
  </section>

@endsection
