@extends('layouts.landing')

@section('title', 'Prácticas Profesionales | CUCSH')

@section('content')


  <!-- HERO -->
  <div class="hero">
    <h1>Prácticas Profesionales</h1>
    <p>Habilidades y competencias para el escenario laboral</p>
    @auth
      <a href="{{ route('dashboard') }}" class="btn-demo">Ir a mi panel</a>
    @else
      <a href="{{ route('login') }}" class="btn-demo">Iniciar sesión</a>
    @endauth
  </div>


  <!-- VIDEO / INTRO SECTION -->
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

  <!-- FEATURE 1 -->
  <section class="feature-section">
    <div class="container">
      <div class="feature-row">
        <div class="feature-content">
          <h3>Ofertas dirigidas a tu carrera</h3>
          <div class="underline-accent"></div>
          <p>Al iniciar sesión verás los programas publicados para tu carrera, con convenio vigente y cupo disponible. Te postulas con tu kardex y la Coordinación da seguimiento a tu postulación.</p>
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

  <!-- FEATURE 2 -->
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
            <div style="background: var(--navy); color:#fff; border-radius:6px; padding: 12px; margin-bottom: 10px; font-size: 10px; font-weight: 700; font-family: 'Montserrat', sans-serif;">
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

  <!-- FEATURE 3 -->
  <section class="feature-section">
    <div class="container">
      <div class="feature-row">
        <div class="feature-content">
          <h3>Gestiona desde una sola plataforma</h3>
          <div class="underline-accent"></div>
          <p>Gestiona todo el proceso de tus prácticas profesionales de forma digital, con seguimiento y notificaciones en cada cambio de etapa.</p>
        </div>
        <div style="display: flex; gap: 16px; justify-content: center; align-items: flex-end;">
          <!-- Phone mockup 1 -->
          <div style="width: 120px; background: #111; border-radius: 18px; padding: 10px 6px; box-shadow: 0 12px 40px rgba(0,0,0,0.25);">
            <div style="background: #fff; border-radius: 12px; overflow: hidden; height: 220px;">
              <div style="background: var(--navy); padding: 8px; text-align: center;">
                <div style="font-family:'Montserrat',sans-serif; font-size: 7px; color:#fff; font-weight:700; letter-spacing:1px;">Alumnos</div>
              </div>
              <div style="padding: 8px; font-size: 8px; color: #333;">
                <div style="font-weight:700; margin-bottom:6px; color: var(--navy);">Panel del alumno</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">📋 Subir reportes</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">⏱️ Ver progreso de horas</div>
                <div style="background:#fff0ee; border-radius:4px; padding:6px; font-size:7px; color: var(--rust);">🔔 Notificaciones</div>
              </div>
            </div>
          </div>
          <!-- Phone mockup 2 -->
          <div style="width: 120px; background: #111; border-radius: 18px; padding: 10px 6px; box-shadow: 0 12px 40px rgba(0,0,0,0.25);">
            <div style="background: #fff; border-radius: 12px; overflow: hidden; height: 220px;">
              <div style="background: var(--rust); padding: 8px; text-align: center;">
                <div style="font-family:'Montserrat',sans-serif; font-size: 7px; color:#fff; font-weight:700; letter-spacing:1px;">Empresas</div>
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

  <!-- PARTNER SECTION -->
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

<section class="partners-grid-section" style="background: #1b2a4a; padding: 80px 0; border-top: 1px solid #e9ecef;">
    <div class="container" style="max-width: 1100px; margin: 0 auto; padding: 0 20px; text-align: center;">
      <h3 style="font-family: 'Montserrat', sans-serif; font-size: 28px; color: #fff; margin-bottom: 12px; font-weight: 700;">
        ¿Dónde puedes realizar tus Prácticas Profesionales?
      </h3>
      <p style="font-size: 16px; color: #cdcdcd; max-width: 600px; margin: 0 auto 40px auto; line-height: 1.6;">
        @if ($organizaciones->isNotEmpty())
          Estas son algunas de las organizaciones con convenio vigente con el CUCSH.
        @else
          Aquí aparecerán las organizaciones con convenio vigente con el CUCSH.
        @endif
      </p>

      @if ($organizaciones->isNotEmpty())
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 35px;">
          @foreach ($organizaciones as $empresa)
            @php
              $estiloTipo = match ($empresa->tipo_organismo?->value) {
                'publico' => 'color: var(--navy); background: #eef2f7;',
                'privado' => 'color: var(--rust); background: #fff3f2;',
                'sociedad_civil' => 'color: #2a7a2a; background: #edf7ed;',
                default => 'color: #495057; background: #f1f3f5;',
              };
            @endphp
            <div style="background: #fff; border: 1px solid #e3e6e8; border-radius: 8px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
              <span style="font-size: 12px; font-weight: 700; color: #495057; text-align: center; letter-spacing: 0.5px; text-transform: uppercase;">{{ $empresa->razon_social }}</span>
              @if ($empresa->tipo_organismo)
                <span style="font-size: 9px; {{ $estiloTipo }} font-weight: 600; margin-top: 6px; text-transform: uppercase; padding: 2px 8px; border-radius: 4px;">{{ $empresa->tipo_organismo->label() }}</span>
              @endif
            </div>
          @endforeach
        </div>

        @if ($totalOrganizaciones > $organizaciones->count())
          <p style="font-family: 'Montserrat', sans-serif; font-size: 14px; color: #fff; font-weight: 600;">
            y {{ $totalOrganizaciones - $organizaciones->count() }} {{ ($totalOrganizaciones - $organizaciones->count()) === 1 ? 'organización más' : 'organizaciones más' }}
          </p>
        @endif
      @endif
    </div>
  </section>

  <!-- CTA BAND -->
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