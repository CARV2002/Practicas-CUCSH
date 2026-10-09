<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>{{ $title ?? 'Inicio' }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="{{ asset('assets/css/home.css') }}">
        
      @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body  x-data="{ openLoginModal: false }">
  <!-- NAVBAR -->
  <nav>
    <div class="logo-area">
      <span class="logo-name"><img src="{{ asset('images/home/cucsh.svg') }}" width="150"></span>
      
    </div>
    <div class="nav-links">
      <a href="{{ route('inicio') }}" class="hidden lg:block" >Inicio</a>
      <a href="{{ route('estudiantes') }}" >Estudiantes</a>
      <a href="{{ route('empresas') }}">Empresas</a>
      <a href="#" class="hidden lg:block">Contacto</a>
      <a
        
        class="btn-demo text-white" 
        style="padding-bottom: 16px;"
        href="/signin"
        >
        Iniciar sesión
        </a>
    </div>
  </nav>




@yield('content')




  <!-- FOOTER -->
  <footer>
    <div class="footer-grid">
        <div class="footer-logo">
            <div class="flex">
                <span class="logo-name flex-1"><img src="{{ asset('images/home/logo-udg.png') }}" width="150"></span>  
                <span class="logo-name flex-1 hidden"><img src="{{ asset('images/home/cucsh.svg') }}" width="150"></span>        
                  
                <span class="logo-name flex-1"></span> 
            </div>
            <br>
        <h4 class="font-bold">Centro Universitario de Ciencias Sociales y Humanidades</h4>
        <a href="#" class="text-sm">Los Belenes. Av. José Parres Arias #150, San Jose del Bajio,</a><br>
        <a href="#" class="text-sm">C.P. 45132. Zapopan, Jalisco, México.  </a><br>
        <a href="#" class="text-sm">Teléfono: +52 (33) 38193300</a>
        

        
        
      </div>
      <div class="footer-col">
        <a href="#" style="visibility: hidden;">f</a>
        <a href="#" style="visibility: hidden;">📷</a>
        <a href="#" style="visibility: hidden;">in</a>
        <h4>Contáctanos </h4>
        <a href="#">practicas.profesionales.cucsh@csh.udg.mx</a>
        <a href="#">(33) 3819-3300 </a>
        <a href="#">Ext. 23549, 23404</a>

      </div>


    </div>
    <div class="footer-bottom">
      <span>Derechos reservados ©2026. Universidad de Guadalajara.</span>
      <span>
        <a href="#">Términos y condiciones</a> &nbsp;|&nbsp;
        <a href="#">Políticas de privacidad</a>
      </span>
    </div>
  </footer>

  <div
    x-show="openLoginModal"
    class="fixed inset-0 z-[9998] bg-black/60"
></div>



</body>
</html>