<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#1b2a4a" />
  <title>@yield('title', 'Prácticas Profesionales | CUCSH')</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  {{-- home.css declara el orden de capas antes que Tailwind (ver comentario en el archivo) --}}
  <link rel="stylesheet" href="{{ asset('assets/css/home.css') }}">
  @include('partials.errores-servidor')

  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('estilos')
</head>

@php
  $enlaces = [
      ['ruta' => 'inicio', 'texto' => 'Inicio'],
      ['ruta' => 'estudiantes', 'texto' => 'Estudiantes', 'tambien' => ['convenios-vigentes']],
      ['ruta' => 'empresas', 'texto' => 'Empresas'],
      ['ruta' => 'contacto', 'texto' => 'Contacto'],
  ];
  $correo = config('practicas.contacto.correo');
@endphp

<body class="bg-white" x-data="{ menu: false }" @keydown.escape.window="menu = false">
  <a href="#contenido"
    class="sr-only z-[200] rounded-lg bg-udg-900 px-4 py-2 text-sm font-semibold text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
    Saltar al contenido
  </a>

  {{-- Navegación --}}
  <header class="sticky top-0 z-[100] border-b border-gray-200 bg-white/95 shadow-theme-xs backdrop-blur supports-[backdrop-filter]:bg-white/85">
    <nav class="mx-auto flex h-[4.5rem] max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:h-24 lg:px-8" aria-label="Principal">
      <a href="{{ route('inicio') }}" class="flex shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40">
        <img src="{{ asset('images/home/cucsh.svg') }}" alt="CUCSH · Prácticas Profesionales" class="h-11 w-auto sm:h-12 lg:h-[3.75rem]" width="200" height="60">
      </a>

      {{-- Escritorio --}}
      <div class="hidden items-center gap-1 md:flex lg:gap-2">
        @foreach ($enlaces as $e)
          @php $activo = request()->routeIs($e['ruta'], ...($e['tambien'] ?? [])); @endphp
          <a href="{{ route($e['ruta']) }}" @if ($activo) aria-current="page" @endif
            class="relative rounded-lg px-3 py-2 text-[15px] font-semibold tracking-[-0.01em] transition-colors lg:px-4 lg:text-base duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40
              {{ $activo ? 'text-udg-900 after:absolute after:inset-x-3 after:-bottom-1 lg:after:inset-x-4 after:h-0.5 after:rounded-full after:bg-oro-400' : 'text-gray-600 hover:bg-gray-50 hover:text-udg-900' }}">
            {{ $e['texto'] }}
          </a>
        @endforeach
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="boton boton-primario ml-3 min-h-11 px-5 text-[15px] lg:ml-4 lg:min-h-12 lg:px-6 lg:text-base">
          {{ auth()->check() ? 'Mi panel' : 'Iniciar sesión' }}
        </a>
      </div>

      {{-- Móvil --}}
      <button type="button" class="flex size-11 items-center justify-center rounded-lg text-udg-900 transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-real-500/40 md:hidden"
        @click="menu = !menu" :aria-expanded="menu.toString()" aria-controls="menu-publico" aria-label="Abrir el menú">
        <svg x-show="!menu" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        <svg x-show="menu" x-cloak class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </nav>

    <div id="menu-publico" x-show="menu" x-cloak @click.outside="menu = false"
      x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
      class="border-t border-gray-100 bg-white px-4 pb-5 pt-2 shadow-theme-lg md:hidden">
      <ul class="flex flex-col gap-1">
        @foreach ($enlaces as $e)
          @php $activo = request()->routeIs($e['ruta'], ...($e['tambien'] ?? [])); @endphp
          <li>
            <a href="{{ route($e['ruta']) }}" @if ($activo) aria-current="page" @endif
              class="flex items-center rounded-lg px-3 py-3 text-base font-semibold {{ $activo ? 'border-l-4 border-oro-400 bg-udg-50 text-udg-900' : 'text-gray-700 hover:bg-gray-50' }}">
              {{ $e['texto'] }}
            </a>
          </li>
        @endforeach
      </ul>
      <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="boton boton-primario mt-4 w-full">
        {{ auth()->check() ? 'Mi panel' : 'Iniciar sesión' }}
      </a>
    </div>
  </header>

  <main id="contenido">
    @yield('content')
  </main>

  {{-- Pie de página --}}
  <footer class="border-t-4 border-oro-400 bg-udg-950 text-white/80">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[2fr_1fr_1fr] lg:px-8 lg:py-16">
      <div>
        <img src="{{ asset('images/home/udg_w.svg') }}" alt="Universidad de Guadalajara" class="h-14 w-auto sm:h-16" width="170" height="56">
        <p class="mt-5 font-display text-base font-semibold text-white">Centro Universitario de Ciencias Sociales y Humanidades</p>
        <address class="mt-2 text-sm not-italic leading-relaxed text-white/70">
          Los Belenes. Av. José Parres Arias #150, San José del Bajío,<br>
          C.P. 45132. Zapopan, Jalisco, México.
        </address>
      </div>

      <div>
        <h2 class="text-xs font-semibold uppercase tracking-wider text-oro-300">Sitio</h2>
        <ul class="mt-4 space-y-2.5 text-sm">
          @foreach ($enlaces as $e)
            <li><a href="{{ route($e['ruta']) }}" class="transition-colors hover:text-white hover:underline underline-offset-4">{{ $e['texto'] }}</a></li>
          @endforeach
        </ul>
      </div>

      <div id="contacto-pie">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-oro-300">Contáctanos</h2>
        <ul class="mt-4 space-y-2.5 text-sm">
          <li>
            <a href="{{ App\Support\Correo::gmail($correo) }}" target="_blank" rel="noopener"
              class="break-all transition-colors hover:text-white hover:underline underline-offset-4">{{ $correo }}</a>
          </li>
          <li>
            <a href="tel:{{ config('practicas.contacto.telefono_enlace') }}" class="transition-colors hover:text-white hover:underline underline-offset-4">{{ config('practicas.contacto.telefono') }}</a>
            <span class="block text-xs text-white/60">Ext. {{ implode(', ', config('practicas.contacto.extensiones')) }}</span>
          </li>
          <li class="text-xs text-white/60">Vinculación atiende convenios con organizaciones. Si eres alumno, escribe a tu coordinación de carrera.</li>
        </ul>
      </div>
    </div>

    <div class="border-t border-white/10">
      <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs text-white/60 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <span>Derechos reservados © {{ now()->year }}. Universidad de Guadalajara.</span>
        <span class="flex gap-4">
          <a href="{{ route('terminos') }}" class="hover:text-white hover:underline underline-offset-4">Términos y condiciones</a>
          <a href="{{ route('privacidad') }}" class="hover:text-white hover:underline underline-offset-4">Políticas de privacidad</a>
        </span>
      </div>
    </div>
  </footer>

</body>

</html>
