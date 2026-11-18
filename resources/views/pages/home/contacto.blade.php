@extends('layouts.landing')

@section('title', 'Contacto | Prácticas Profesionales CUCSH')

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
    background-color: var(--color-oro-50);
    color: var(--color-oro-700);
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

/* --- SECCIÓN RECURSOS (CARDS PREMIUM) --- */
.section-resources {
    padding: 120px 0;
    background-color: var(--color-white);
}

.resources-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    max-width: 880px;
    margin: 0 auto;
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
    font-family: var(--font-sans);
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
    font-family: var(--font-sans);
    background: var(--color-udg-800);
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
    background: var(--color-udg-700);
    transform: translateY(-1px);
}

/* --- RESPONSIVE DESIGN TOTAL (BREAKPOINTS COMPATIBLES) --- */
@media (max-width: 1024px) {
    .resources-grid { grid-template-columns: repeat(2, 1fr); }
    .benefits-layout { grid-template-columns: 1fr; gap: 40px; }
    .benefits-sticky-header { position: relative; top: 0; }
    .contact-card-wrapper { grid-template-columns: 1fr; }
    .section-header h2, .benefits-sticky-header h2 { font-size: var(--text-title-md); }
}

@media (max-width: 640px) {
    .resources-grid { grid-template-columns: 1fr; }
    .form-row-twin { grid-template-columns: 1fr; gap: 0; }
    .contact-info-side, .contact-form-side { padding: 32px 24px; }
}
</style>

@php
    $correo = config('practicas.contacto.correo');
    $mapa = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Centro Universitario de Ciencias Sociales y Humanidades, Zapopan, Jalisco');
@endphp

  <div class="hero hero-estudiantes">
    <div class="brand-tag">Contacto</div>
    <h1>Prácticas Profesionales</h1>
    <p>Alumnos: tu coordinación de carrera · Organizaciones: Vinculación del CUCSH</p>
    @auth
      <a href="{{ route('dashboard') }}" class="btn-demo">Ir a mi panel</a>
    @else
      <a href="{{ route('login') }}" class="btn-demo">Iniciar sesión</a>
    @endauth
  </div>

  {{-- ¿A quién escribo? Dos caminos según el tipo de usuario --}}
  <section class="bg-gray-50 !pb-8 !pt-12 sm:!pt-16" aria-labelledby="titulo-a-quien">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
      <div class="max-w-2xl">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-oro-700">¿A quién escribo?</p>
        <h2 id="titulo-a-quien" class="mt-2 font-display text-2xl font-bold text-udg-900 sm:text-3xl">Elige según quién eres</h2>
        <p class="mt-3 text-base leading-relaxed text-gray-600">Así tu mensaje llega directo a quien puede resolverlo.</p>
      </div>

      <div class="mt-8 grid gap-5 lg:grid-cols-2">
        {{-- Alumnos --}}
        <article class="flex flex-col rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm sm:p-8">
          <span class="flex size-12 items-center justify-center rounded-xl bg-udg-50 text-udg-900 ring-1 ring-udg-100" aria-hidden="true">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
          </span>
          <h3 class="mt-5 font-display text-xl font-bold text-gray-900">Soy alumno</h3>
          <p class="mt-2 text-sm leading-relaxed text-gray-600">
            Postulación, documentos (IMSS, kardex y cartas), reportes, horas y liberación los atiende la
            <strong class="font-semibold text-gray-800">coordinación de tu carrera</strong>.
          </p>
          <div class="mt-auto pt-6">
            <a href="#directorio" class="boton boton-primario w-full sm:w-auto">
              Buscar mi coordinación
              <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0-6-6m6 6 6-6"/></svg>
            </a>
          </div>
        </article>

        {{-- Organizaciones --}}
        <article class="flex flex-col rounded-2xl border border-udg-900 border-b-4 border-b-oro-400 bg-udg-900 p-6 text-white shadow-theme-lg sm:p-8">
          <span class="flex size-12 items-center justify-center rounded-xl bg-white/10 text-oro-300 ring-1 ring-white/15" aria-hidden="true">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/></svg>
          </span>
          <h3 class="mt-5 font-display text-xl font-bold">Represento una organización</h3>
          <p class="mt-2 text-sm leading-relaxed text-white/80">
            <strong class="font-semibold text-white">Vinculación del CUCSH</strong> atiende el registro de organizaciones, la solicitud y el seguimiento de convenios.
          </p>

          <dl class="mt-5 space-y-3 text-sm">
            <div class="flex gap-3">
              <dt class="sr-only">Correo</dt>
              <svg class="mt-0.5 size-4 shrink-0 text-oro-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
              <dd class="break-all">{{ $correo }}</dd>
            </div>
            <div class="flex gap-3">
              <dt class="sr-only">Teléfono</dt>
              <svg class="mt-0.5 size-4 shrink-0 text-oro-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
              <dd>
                <a href="tel:{{ config('practicas.contacto.telefono_enlace') }}" class="underline-offset-4 hover:underline">{{ config('practicas.contacto.telefono') }}</a>,
                ext. {{ implode(' o ', config('practicas.contacto.extensiones')) }}
              </dd>
            </div>
            <div class="flex gap-3">
              <dt class="sr-only">Dirección</dt>
              <svg class="mt-0.5 size-4 shrink-0 text-oro-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
              <dd>
                {{ config('practicas.domicilio_cucsh') }}.
                <a href="{{ $mapa }}" target="_blank" rel="noopener" class="ml-1 font-semibold text-oro-300 underline-offset-4 hover:underline">Ver en el mapa →</a>
              </dd>
            </div>
          </dl>

          <div class="mt-auto flex flex-col gap-2 pt-6 sm:flex-row">
            <a href="{{ App\Support\Correo::gmail($correo, 'Convenio de prácticas profesionales') }}" target="_blank" rel="noopener"
              class="boton bg-oro-400 text-udg-950 hover:bg-oro-300 focus-visible:ring-oro-300/50">
              Escribir a Vinculación
              <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/></svg>
            </a>
            <a href="{{ route('empresas') }}" class="boton border border-white/25 text-white hover:bg-white/10">Ver el proceso de convenio</a>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section id="directorio" class="scroll-mt-24 bg-gray-50 !pb-16 !pt-8 sm:!pb-20">
    <x-directorio-coordinaciones titulo="Escribe a tu coordinación de carrera" id="contacto" />
  </section>


  <section class="section-resources" style="padding-top: 60px;">
    <div class="container">
        <div class="section-header">
            <span class="badge-category">Orientación</span>
            <h2>Otras consultas frecuentes</h2>
            <p>Estas son las consultas más comunes y dónde encontrar la información antes de escribirnos.</p>
        </div>

        <div class="resources-grid">
            <div class="resource-premium-card">
                <div class="resource-icon-wrapper red-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h3>Acceso a mi cuenta</h3>
                <p>Puedes restablecer tu contraseña desde el inicio de sesión. Si tu cuenta aparece desactivada o no recibes el correo de verificación, escríbenos.</p>
                <a href="{{ route('password.request') }}" class="resource-link">Recuperar contraseña <span>→</span></a>
            </div>

            <div class="resource-premium-card">
                <div class="resource-icon-wrapper purple-accent">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                @auth
                    @if (auth()->user()->tieneAyuda())
                        <h3>Ayuda en la plataforma</h3>
                        <p>Consulta la sección de ayuda dentro de tu panel antes de escribirnos.</p>
                        <a href="{{ route('ayuda') }}" class="resource-link">Ir a la ayuda <span>→</span></a>
                    @else
                        <h3>Tu panel</h3>
                        <p>Consulta el estado de los trámites y tus pendientes desde tu panel.</p>
                        <a href="{{ route('dashboard') }}" class="resource-link">Ir a mi panel <span>→</span></a>
                    @endif
                @else
                    <h3>Aún no tengo cuenta</h3>
                    <p>Alumnos y organizaciones se registran por separado. Elige tu tipo de cuenta para comenzar.</p>
                    <a href="{{ route('register') }}" class="resource-link">Crear una cuenta <span>→</span></a>
                @endauth
            </div>
        </div>
    </div>
  </section>

  <section class="section-benefits">
    <div class="container">
        <div class="benefits-layout">
            <div class="benefits-sticky-header">
                <span class="badge-category">Antes de escribir</span>
                <h2>Datos que ayudan a identificar tu caso</h2>
                <p>Incluye esta información en tu mensaje para que tu coordinación (o Vinculación, si eres organización) pueda ubicar tu trámite.</p>
            </div>
            <div class="benefits-list">
                <div class="benefit-item">
                    <h4>Si eres alumno</h4>
                    <p>Tu nombre completo, tu código de alumno y tu carrera, y la etapa en la que está tu trámite: postulación, documentos, reportes o cierre.</p>
                </div>
                <div class="benefit-item">
                    <h4>Si representas una organización</h4>
                    <p>Razón social y RFC y, si ya cuentas con convenio, su folio; si aún está en trámite, la fase en la que va tu solicitud.</p>
                </div>
                <div class="benefit-item">
                    <h4>Describe lo que ocurre</h4>
                    <p>Indica qué intentabas hacer y qué mensaje apareció en pantalla. Por seguridad, no envíes contraseñas por correo.</p>
                </div>
            </div>
        </div>
    </div>
  </section>

@endsection
