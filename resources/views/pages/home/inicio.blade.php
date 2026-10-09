@extends('layouts.landing')

@section('content')


  <!-- HERO -->
  <div class="hero">
    <!--<div class="brand-tag">Symplicity™</div>-->
    <h1>Prácticas Profesionales</h1>
    <p>Habilidades y competencias para el escenario laboral</p>
    <a href="/signin" class="btn-demo">Iniciar sesión  </a>
  </div>


  <!-- VIDEO / INTRO SECTION -->
  <section class="video-section">
    <div class="container">
      <div class="two-col">
        <div class="video-thumb">
          <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=700&q=80" alt="Symplicity CSM video"/>
          <span class="video-label">Introducción - Prácticas Profesionales</span>
          <div class="play-btn">▶</div>
        </div>
        <div class="section-text">
          <h3>Conecta tu formación académica con oportunidades <span class="highlight">Profesionales</span>.</h3>
        </div>
      </div>
    </div>
  </section>

  <!-- FEATURE 1 -->
  <section class="feature-section">
    <div class="container">
      <div class="feature-row">
        <div class="feature-content">
          <h3>Gran convocatoria de acuerdo a tu perfil</h3>
          <div class="underline-accent"></div>
          <p>Consulta las convocatorias disponibles, te ayudamos a encontrar la institución ideal para desarrollar tu experiencia profesional de acuerdo con tu formación e intereses.</p>
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
          <h3>Diferentes modalidades de acreditación</h3>
          <div class="underline-accent"></div>
          <p>Acredita tus prácticas profesionales con la modalidad de tu preferencia: Presencial, experiencia laboral y Apoyo en proyectos de investigación.</p>
        </div>
        <div class="mock-screen">
          <div class="titlebar">
            <div class="dot dot-red"></div>
            <div class="dot dot-yellow"></div>
            <div class="dot dot-green"></div>
          </div>
          <div class="body" style="padding: 20px;">
            <div style="background: var(--navy); color:#fff; border-radius:6px; padding: 12px; margin-bottom: 10px; font-size: 10px; font-weight: 700; font-family: 'Montserrat', sans-serif;">
              Selecciona el tipo de modalidad que prefieras:
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
              <div style="background:#f0f4ff; border-radius:5px; padding:8px; font-size: 9px; color: var(--navy); font-weight: 600;">📊 Presencial</div>
              <div style="background:#fff5f5; border-radius:5px; padding:8px; font-size: 9px; color: var(--rust); font-weight: 600;">🎯 Experiencia laboral</div>
              <div style="background:#f5fff5; border-radius:5px; padding:8px; font-size: 9px; color: #2a7a2a; font-weight: 600;">✅ Apoyo en investigación</div>
              
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
          <p>Gestiona todo el proceso de tus prácticas profesionales de forma digital, con seguimiento y actualizaciones en tiempo real.</p>
        </div>
        <div style="display: flex; gap: 16px; justify-content: center; align-items: flex-end;">
          <!-- Phone mockup 1 -->
          <div style="width: 120px; background: #111; border-radius: 18px; padding: 10px 6px; box-shadow: 0 12px 40px rgba(0,0,0,0.25);">
            <div style="background: #fff; border-radius: 12px; overflow: hidden; height: 220px;">
              <div style="background: var(--navy); padding: 8px; text-align: center;">
                <div style="font-family:'Montserrat',sans-serif; font-size: 7px; color:#fff; font-weight:700; letter-spacing:1px;">Alumnos</div>
              </div>
              <div style="padding: 8px; font-size: 8px; color: #333;">
                <div style="font-weight:700; margin-bottom:6px; color: var(--navy);">Bienvenida, Sarah!</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">📋 Subir reporte de actividades</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">🏢 Ver progreso</div>
                <div style="background:#fff0ee; border-radius:4px; padding:6px; font-size:7px; color: var(--rust);">🔔 Ver eventos próximos</div>
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
                <div style="font-weight:700; margin-bottom:6px; color: var(--navy);">Explora prestadores</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">🏛️ Tus prestadores</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; margin-bottom:4px; font-size:7px;">💡 Solicitudes</div>
                <div style="background:#f5f5f5; border-radius:4px; padding:6px; font-size:7px;">🌐 Posicionamiento</div>
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
          
          <div class="play-overlay">▶</div>
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
        Nuestros alumnos colaboran activamente en los organismos gubernamentales, corporativos y del tercer sector más importantes de la región.
      </p>
      
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 35px;">
        
        <div style="background: #fff; border: 1px solid #e3e6e8; border-radius: 8px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
          <span style="font-size: 12px; font-weight: 700; color: #495057; text-align: center; letter-spacing: 0.5px;">H. CONGRESO DEL ESTADO DE JALISCO</span>
          <span style="font-size: 9px; color: var(--navy); font-weight: 600; margin-top: 6px; text-transform: uppercase; background: #eef2f7; padding: 2px 8px; border-radius: 4px;">Sector Público</span>
        </div>

        <div style="background: #fff; border: 1px solid #e3e6e8; border-radius: 8px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
          <span style="font-size: 12px; font-weight: 700; color: #495057; text-align: center; letter-spacing: 0.5px;">SUPREMO TRIBUNAL DE JUSTICIA</span>
          <span style="font-size: 9px; color: var(--navy); font-weight: 600; margin-top: 6px; text-transform: uppercase; background: #eef2f7; padding: 2px 8px; border-radius: 4px;">Sector Público</span>
        </div>

        <div style="background: #fff; border: 1px solid #e3e6e8; border-radius: 8px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
          <span style="font-size: 12px; font-weight: 700; color: #495057; text-align: center; letter-spacing: 0.5px;">COMISIÓN DE DERECHOS HUMANOS (CEDHJ)</span>
          <span style="font-size: 9px; color: #2a7a2a; font-weight: 600; margin-top: 6px; text-transform: uppercase; background: #edf7ed; padding: 2px 8px; border-radius: 4px;">Organismo Autónomo</span>
        </div>

        <div style="background: #fff; border: 1px solid #e3e6e8; border-radius: 8px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
          <span style="font-size: 12px; font-weight: 700; color: #495057; text-align: center; letter-spacing: 0.5px;">TELEVISA GUADALAJARA</span>
          <span style="font-size: 9px; color: var(--rust); font-weight: 600; margin-top: 6px; text-transform: uppercase; background: #fff3f2; padding: 2px 8px; border-radius: 4px;">Iniciativa Privada</span>
        </div>

        <div style="background: #fff; border: 1px solid #e3e6e8; border-radius: 8px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
          <span style="font-size: 12px; font-weight: 700; color: #495057; text-align: center; letter-spacing: 0.5px;">INSTITUTO ELECTORAL (IEPC JALISCO)</span>
          <span style="font-size: 9px; color: #2a7a2a; font-weight: 600; margin-top: 6px; text-transform: uppercase; background: #edf7ed; padding: 2px 8px; border-radius: 4px;">Organismo Autónomo</span>
        </div>

        <div style="background: #fff; border: 1px solid #e3e6e8; border-radius: 8px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
          <span style="font-size: 12px; font-weight: 700; color: #495057; text-align: center; letter-spacing: 0.5px;">CÁMARA DE COMERCIO DE GUADALAJARA</span>
          <span style="font-size: 9px; color: var(--rust); font-weight: 600; margin-top: 6px; text-transform: uppercase; background: #fff3f2; padding: 2px 8px; border-radius: 4px;">Sector Empresarial</span>
        </div>

      </div>

      <a href="/path-to-your-pdf/listado-vigente_1.pdf" target="_blank" style="font-family: 'Montserrat', sans-serif; font-size: 14px; color: #fff; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border-bottom: 2px solid var(--navy); padding-bottom: 2px;">
        Ver todos los convenios vigentes
      </a>
    </div>
  </section>

  <!-- CTA BAND -->
  <div class="cta-band">
    <h2>¿Quieres establecer un convenio con el CUCSH?</h2>
    <a href="#" class="btn-demo">Iniciar convenio</a>
    <a href="#" class="btn-outline">Solicitar información</a>
  </div>


@endsection