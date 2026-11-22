@props(['title', 'subtitle' => null, 'eyebrow' => 'Prácticas Profesionales · CUCSH'])

{{--
    Pantalla de acceso (login, registro, recuperación): formulario a la izquierda, identidad del CUCSH a la derecha.
    En celular la identidad va arriba, en una banda azul marino con el logo.
    Animación de entrada escalonada con animate-entrada (respeta "reducir movimiento").
--}}
<div class="relative z-1 min-h-dvh bg-white lg:flex">

    {{-- Columna del formulario --}}
    <div class="relative flex w-full flex-1 flex-col lg:w-1/2">

        {{-- Identidad en celular y tableta --}}
        <div class="relative overflow-hidden bg-udg-900 px-6 pb-10 pt-8 text-center lg:hidden">
            <div class="pointer-events-none absolute -right-16 -top-16 size-48 rounded-full bg-oro-400/15 blur-2xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-10 size-48 rounded-full bg-real-500/20 blur-2xl" aria-hidden="true"></div>
            <a href="{{ route('inicio') }}" class="relative inline-block animate-entrada rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-oro-300 motion-reduce:animate-none">
                <img src="{{ asset('images/home/cucsh.svg') }}" alt="CUCSH · Ir al inicio" class="mx-auto h-12 w-auto brightness-0 invert" width="170" height="48" />
            </a>
            <p class="relative mt-3 animate-entrada text-[11px] font-semibold uppercase tracking-[0.2em] text-oro-300 [animation-delay:80ms] motion-reduce:animate-none">
                Prácticas Profesionales
            </p>
        </div>
        <div class="h-1 bg-oro-400 lg:hidden" aria-hidden="true"></div>

        <div class="relative flex flex-1 flex-col px-6 sm:px-10">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10 lg:py-12">
                <div class="mb-8">
                    @if ($eyebrow)
                        <p class="hidden animate-entrada text-xs font-semibold uppercase tracking-[0.18em] text-oro-700 motion-reduce:animate-none lg:block">{{ $eyebrow }}</p>
                    @endif
                    <h1 class="mt-2 animate-entrada font-display text-[2rem] font-extrabold leading-[1.1] tracking-[-0.02em] text-udg-900 [animation-delay:60ms] motion-reduce:animate-none sm:text-[2.5rem]">
                        {{ $title }}
                    </h1>
                    @if ($subtitle)
                        <p class="mt-3 animate-entrada text-[15px] leading-relaxed text-gray-600 [animation-delay:120ms] motion-reduce:animate-none">{{ $subtitle }}</p>
                    @endif
                </div>

                <div class="animate-entrada [animation-delay:180ms] motion-reduce:animate-none">
                    {{ $slot }}
                </div>
            </div>

            <p class="pb-6 text-center text-xs text-gray-400">© {{ now()->year }} Universidad de Guadalajara · CUCSH</p>
        </div>
    </div>

    {{-- Panel de identidad (escritorio) --}}
    {{-- Fijo al alto de la pantalla: en formularios largos (registro) el logo no se pierde al bajar --}}
    <aside class="relative hidden overflow-hidden bg-gradient-to-br from-udg-950 via-udg-900 to-udg-800 lg:sticky lg:top-0 lg:flex lg:h-dvh lg:w-1/2 lg:items-center lg:justify-center lg:self-start" aria-label="Prácticas Profesionales CUCSH">
        <x-common.common-grid-shape />
        <div class="pointer-events-none absolute -right-24 top-10 size-80 rounded-full bg-oro-400/10 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-16 size-96 rounded-full bg-real-500/15 blur-3xl" aria-hidden="true"></div>
        <div class="absolute inset-x-0 bottom-0 h-1.5 bg-oro-400" aria-hidden="true"></div>

        <div class="relative z-1 max-w-md px-12">
            <a href="{{ route('inicio') }}" class="inline-block animate-entrada rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-oro-300 motion-reduce:animate-none">
                <img src="{{ asset('images/home/cucsh.svg') }}" alt="CUCSH · Ir al inicio" class="h-16 w-auto brightness-0 invert" width="220" height="64" />
            </a>
            <p class="mt-10 animate-entrada font-display text-[2.25rem] font-bold leading-tight tracking-[-0.02em] text-white [animation-delay:100ms] motion-reduce:animate-none">
                Del aula al <span class="text-oro-300">escenario laboral</span>.
            </p>
            <ul class="mt-8 space-y-4 text-[15px] text-white/80">
                @foreach ([
                    'Postúlate a programas de organizaciones con convenio vigente.',
                    'Sube tus documentos y reportes, y sigue tus horas en tiempo real.',
                    'Recibe avisos en cada cambio de etapa de tu trámite.',
                ] as $i => $beneficio)
                    <li class="flex animate-entrada gap-3 motion-reduce:animate-none" style="animation-delay: {{ 180 + $i * 80 }}ms">
                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-oro-400/20 text-oro-300" aria-hidden="true">
                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg>
                        </span>
                        {{ $beneficio }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-12 text-sm text-white/50">Centro Universitario de Ciencias Sociales y Humanidades</p>
        </div>
    </aside>
</div>
