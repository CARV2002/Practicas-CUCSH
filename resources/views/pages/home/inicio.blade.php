@extends('layouts.landing')

@section('title', 'Prácticas Profesionales | CUCSH')

@section('content')


  {{-- Portada --}}
  <div class="hero">
    <h1>Prácticas Profesionales</h1>
    <p>Habilidades y competencias para el escenario laboral</p>
    @auth
      <a href="{{ route('dashboard') }}" class="btn-demo">Ir a mi panel</a>
    @else
      <a href="{{ route('login') }}" class="btn-demo">Iniciar sesión</a>
    @endauth
  </div>


  {{-- Presentación --}}
  <section class="video-section">
    <div class="container">
      <div class="two-col">
        <div class="video-thumb">
          <img src="{{ asset('images/home/estudiantes.jpg') }}" alt="Estudiantes del CUCSH"/>
          <span class="video-label">Introducción - Prácticas Profesionales</span>
        </div>
        <div class="section-text">
          <h3>Conecta tu formación académica con oportunidades <span class="highlight">Profesionales</span>.</h3>
          <p style="margin-top: 16px; font-size: 15px; color: var(--text-muted); line-height: 1.6;">
            @if ($programasAbiertos > 0)
              Hoy hay <strong>{{ $programasAbiertos }}</strong> {{ $programasAbiertos === 1 ? 'programa abierto' : 'programas abiertos' }}
              en {{ $totalOrganizaciones === 1 ? 'una organización' : $totalOrganizaciones . ' organizaciones' }} con convenio vigente, para estudiantes de {{ $totalCarreras }} carreras del CUCSH.
            @else
              Las convocatorias se publican cuando las organizaciones con convenio vigente abren sus programas. Inicia sesión para consultarlas.
            @endif
          </p>
        </div>
      </div>
    </div>
  </section>

  {{-- Característica 1 --}}
  <section class="feature-section">
    <div class="container">
      <div class="feature-row">
        <div class="feature-content">
          <h3>Ofertas dirigidas a tu carrera</h3>
          <div class="underline-accent"></div>
          <p>Al iniciar sesión verás los programas publicados para tu carrera, con convenio vigente y cupo disponible. Te postulas con tu kardex y tu coordinación de carrera da seguimiento a tu postulación.</p>
        </div>
        <div class="mock-screen">
          <div class="titlebar">
            <div class="dot dot-red"></div>
            <div class="dot dot-yellow"></div>
            <div class="dot dot-green"></div>
          </div>
          <div class="body">
            <div class="mock-row"><div class="mock-bar rust" style="width:60%"></div><div class="mock-bar" style="width:30%"></div></div>
            <div class="mock-row"><div class="mock-bar" style="width:80%"></div></div>
            <div class="mock-row"><div class="mock-bar navy" style="width:45%"></div><div class="mock-bar rust" style="width:20%"></div></div>
            <div class="mock-row"><div class="mock-bar" style="width:70%"></div></div>
            <div class="mock-row"><div class="mock-bar" style="width:55%"></div><div class="mock-bar navy" style="width:25%"></div></div>
            <div class="mock-row"><div class="mock-bar rust" style="width:35%"></div></div>
            <div class="mock-row"><div class="mock-bar" style="width:65%"></div></div>
            <div class="mock-row"><div class="mock-bar navy" style="width:50%"></div><div class="mock-bar" style="width:20%"></div></div>
            <div class="mock-row"><div class="mock-bar" style="width:40%"></div></div>
            <div class="mock-row"><div class="mock-bar rust" style="width:75%"></div></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Característica 2 --}}
  <section style="background:#fff; padding: 80px 0;">
    <div class="container">
      <div class="feature-row reverse">
        <div class="feature-content">
          <h3>Dos modalidades de prácticas</h3>
          <div class="underline-accent"></div>
          <p>Realiza tus prácticas en una organización con convenio vigente con el CUCSH (modalidad externa) o en un proyecto universitario, sin convenio (modalidad interna). Cada carrera define las horas que debes cubrir.</p>
        </div>
        <div class="mock-screen">
          <div class="titlebar">
            <div class="dot dot-red"></div>
            <div class="dot dot-yellow"></div>
            <div class="dot dot-green"></div>
          </div>
          <div class="body" style="padding: 20px;">
            <div style="background: var(--navy); color:#fff; border-radius:6px; padding: 12px; margin-bottom: 10px; font-size: 10px; font-weight: 700; font-family: var(--font-display);">
              Modalidades disponibles:
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 8px;">
              <div style="background:#f0f4ff; border-radius:5px; padding:8px; font-size: 9px; color: var(--navy); font-weight: 600;">🏢 Externa: organización con convenio</div>
              <div style="background:#fff5f5; border-radius:5px; padding:8px; font-size: 9px; color: var(--rust); font-weight: 600;">🎓 Interna: proyecto universitario</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Característica 3 --}}
  <section class="feature-section">
    <div class="container">
      <div class="feature-row">
        <div class="feature-content">
          <h3>Gestiona desde una sola plataforma</h3>
          <div class="underline-accent"></div>
          <p>Gestiona todo el proceso de tus prácticas profesionales de forma digital, con seguimiento y notificaciones en cada cambio de etapa.</p>
        </div>
        <div style="display: flex; gap: 16px; justify-content: center; align-items: flex-end;">
          {{-- Maqueta de teléfono 1 --}}
          <div style="width: 120px; background: #111; border-radius: 18px; padding: 10px 6px; box-shadow: 0 12px 40px rgba(0,0,0,0.25);">
            <div style="background: #fff; border-radius: 12px; overflow: hidden; height: 220px;">
              <div style="background: var(--navy); padding: 8px; text-align: center;">
                <div style="font-family:var(--font-display); font-size: 7px; color:#fff; font-weight:700; letter-spacing:1px;">Alumnos</div>
              </div>
              <div style="padding: 8px; font-size: 8px; color: #333;">
                <div style="font-weight:700; margin-bottom:6px; color: var(--navy);">Panel del alumno</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">📋 Subir reportes</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">⏱️ Ver progreso de horas</div>
                <div style="background:#fff0ee; border-radius:4px; padding:6px; font-size:7px; color: var(--rust);">🔔 Notificaciones</div>
              </div>
            </div>
          </div>
          {{-- Maqueta de teléfono 2 --}}
          <div style="width: 120px; background: #111; border-radius: 18px; padding: 10px 6px; box-shadow: 0 12px 40px rgba(0,0,0,0.25);">
            <div style="background: #fff; border-radius: 12px; overflow: hidden; height: 220px;">
              <div style="background: var(--rust); padding: 8px; text-align: center;">
                <div style="font-family:var(--font-display); font-size: 7px; color:#fff; font-weight:700; letter-spacing:1px;">Empresas</div>
              </div>
              <div style="padding: 8px; font-size: 8px; color: #333;">
                <div style="font-weight:700; margin-bottom:6px; color: var(--navy);">Panel de la empresa</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">📄 Mi convenio</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">💡 Mis programas</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; font-size:7px;">👥 Practicantes</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Organizaciones con convenio --}}
  <section class="partner-section">
    <div class="container">
      <div class="partner-inner">
        <div>
          <p>Más organizaciones confían en el talento CUCSH</p>
          <div class="accent-line"></div>
        </div>
        <div class="csm-card">
          <div class="brand-sm">Prácticas Profesionales</div>
          <h2>CUCSH</h2>
          @if ($totalOrganizaciones > 0)
            <p>{{ $totalOrganizaciones }} {{ $totalOrganizaciones === 1 ? 'organización con convenio vigente' : 'organizaciones con convenio vigente' }}</p>
          @endif
        </div>
      </div>
    </div>
  </section>

<section class="!py-16 sm:!py-20 bg-udg-900" aria-labelledby="titulo-organizaciones">
    <div class="mx-auto max-w-6xl px-4 text-center sm:px-6">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-oro-300">Convenios vigentes</p>
      <h2 id="titulo-organizaciones" class="mt-2 font-display text-2xl font-bold text-white sm:text-3xl">
        ¿Dónde puedes realizar tus Prácticas Profesionales?
      </h2>
      <p class="mx-auto mt-3 max-w-2xl text-base leading-relaxed text-white/75">
        @if ($organizaciones->isNotEmpty())
          Estas son algunas de las organizaciones con convenio vigente con el CUCSH.
        @else
          Aquí aparecerán las organizaciones con convenio vigente con el CUCSH.
        @endif
      </p>

      @if ($organizaciones->isNotEmpty())
        <ul class="mt-10 grid gap-4 text-left sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($organizaciones as $empresa)
            @php
              $familiaTipo = match ($empresa->tipo_organismo?->value) {
                'publico' => 'cielo',
                'privado' => 'lavanda',
                'sociedad_civil' => 'menta',
                default => 'gris',
              };
            @endphp
            <li class="group flex items-start gap-4 rounded-xl border border-white/10 bg-white p-5 shadow-theme-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-theme-lg motion-reduce:transition-none motion-reduce:hover:translate-y-0">
              <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-udg-50 font-display text-base font-bold text-udg-900 ring-1 ring-udg-100" aria-hidden="true">
                {{ mb_strtoupper(mb_substr($empresa->razon_social, 0, 1)) }}
              </span>
              <span class="min-w-0">
                <span class="block font-semibold leading-snug text-gray-900">{{ $empresa->razon_social }}</span>
                @if ($empresa->tipo_organismo)
                  <x-ui.estatus :familia="$familiaTipo" :texto="$empresa->tipo_organismo->label()" class="mt-2" />
                @endif
              </span>
            </li>
          @endforeach
        </ul>

        <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
          @if ($totalOrganizaciones > $organizaciones->count())
            <p class="text-sm font-semibold text-white">
              y {{ $totalOrganizaciones - $organizaciones->count() }} {{ ($totalOrganizaciones - $organizaciones->count()) === 1 ? 'organización más' : 'organizaciones más' }}
            </p>
          @endif
          <a href="{{ route('convenios-vigentes') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-oro-300 underline-offset-4 hover:text-oro-100 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-oro-300">
            Ver todas por carrera <span aria-hidden="true">→</span>
          </a>
        </div>
      @endif
    </div>
  </section>

  {{-- Franja de llamada a la acción --}}
  <div class="cta-band">
    <h2>¿Quieres establecer un convenio con el CUCSH?</h2>
    @guest
      <a href="{{ route('registro.empresa') }}" class="btn-demo">Iniciar convenio</a>
    @else
      <a href="{{ route('dashboard') }}" class="btn-demo">Ir a mi panel</a>
    @endguest
    <a href="{{ route('empresas') }}" class="btn-outline">Conoce el proceso</a>
  </div>


@endsection