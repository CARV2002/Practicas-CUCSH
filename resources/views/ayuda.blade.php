@extends('layouts.app')

@section('content')
@php
    $categorias = collect($contenido['temas'])->pluck('categoria')->unique()->values();
    $correo = config('practicas.contacto.correo');
@endphp

<div class="space-y-6"
    x-data="{
        q: '',
        cat: '',
        abierta: null,
        temas: @js(collect($contenido['temas'])->values()),
        norm(t) { return (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); },
        visible(i) {
            const t = this.temas[i];
            const q = this.norm(this.q.trim());
            return (this.cat === '' || t.categoria === this.cat)
                && (q === '' || this.norm(t.pregunta + ' ' + t.respuesta + ' ' + t.categoria).includes(q));
        },
        get total() { return this.temas.filter((_, i) => this.visible(i)).length; },
        buscar() { this.abierta = null; if (this.q.trim() !== '') this.$nextTick(() => document.getElementById('preguntas')?.scrollIntoView({ behavior: 'smooth', block: 'start' })); },
    }">

    <x-dashboard.encabezado :eyebrow="$contenido['eyebrow']" :titulo="$contenido['titulo']" :subtitulo="$contenido['subtitulo']">
        <form class="mt-5 max-w-xl" role="search" @submit.prevent="buscar()">
            <label for="buscar-ayuda" class="sr-only">Buscar en la ayuda</label>
            <div class="relative">
                <x-dashboard.icono nombre="buscar" clase="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-gray-400" />
                <input id="buscar-ayuda" type="search" x-model.debounce.150ms="q" @keydown.enter.prevent="buscar()"
                    placeholder="Escribe tu duda: «IMSS», «vacante», «carta»…" autocomplete="off"
                    class="h-12 w-full rounded-xl border-0 bg-white pl-12 pr-4 text-[15px] text-gray-900 shadow-theme-lg placeholder:text-gray-400 focus:outline-none focus:ring-4 focus:ring-oro-300/50">
            </div>
        </form>
    </x-dashboard.encabezado>

    {{-- Tu proceso --}}
    <section aria-labelledby="titulo-proceso">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div>
                <h2 id="titulo-proceso" class="font-display text-lg font-bold text-gray-900">Tu proceso, paso a paso</h2>
                <p class="text-sm text-gray-500">Toca un paso para ir directo a la pantalla donde se hace.</p>
            </div>
        </div>
        <ol class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($contenido['pasos'] as $i => $p)
                <li class="animate-entrada motion-reduce:animate-none" style="animation-delay: {{ $i * 60 }}ms">
                    <a href="{{ $p['ruta'] ? route($p['ruta']) : '#' }}"
                        class="group flex h-full gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs transition duration-200 hover:-translate-y-1 hover:border-udg-200 hover:shadow-theme-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/25 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                        <span class="relative flex size-12 shrink-0 items-center justify-center rounded-xl bg-udg-50 text-udg-900 ring-1 ring-inset ring-udg-100 transition-colors group-hover:bg-udg-900 group-hover:text-white">
                            <x-dashboard.icono :nombre="$p['icono']" />
                            <span class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-oro-400 text-[11px] font-bold text-udg-950 ring-2 ring-white">{{ $i + 1 }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-gray-900">{{ $p['titulo'] }}</span>
                            <span class="mt-1 block text-sm leading-relaxed text-gray-600">{{ $p['texto'] }}</span>
                            <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-real-600 group-hover:text-real-700">
                                Ir ahora <x-dashboard.icono nombre="flecha" clase="size-3.5 transition-transform group-hover:translate-x-0.5" />
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- Preguntas frecuentes --}}
        <section id="preguntas" class="scroll-mt-24 xl:col-span-2" aria-labelledby="titulo-preguntas">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-xs">
                <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="titulo-preguntas" class="font-display text-base font-bold text-gray-900">Preguntas frecuentes</h2>
                        <p class="text-xs text-gray-500" aria-live="polite"><span x-text="total"></span> <span x-text="total === 1 ? 'resultado' : 'resultados'"></span></p>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Filtrar por tema">
                        <button type="button" @click="cat = ''; abierta = null" :aria-pressed="(cat === '').toString()"
                            class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset transition-colors"
                            :class="cat === '' ? 'bg-udg-900 text-white ring-udg-900' : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50'">Todos</button>
                        @foreach ($categorias as $c)
                            <button type="button" @click="cat = @js($c); abierta = null" :aria-pressed="(cat === @js($c)).toString()"
                                class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset transition-colors"
                                :class="cat === @js($c) ? 'bg-udg-900 text-white ring-udg-900' : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50'">{{ $c }}</button>
                        @endforeach
                    </div>
                </div>

                <ul class="divide-y divide-gray-100">
                    @foreach ($contenido['temas'] as $i => $t)
                        <li x-show="visible({{ $i }})">
                            <h3>
                                <button type="button" @click="abierta = abierta === {{ $i }} ? null : {{ $i }}"
                                    :aria-expanded="(abierta === {{ $i }}).toString()" aria-controls="respuesta-{{ $i }}"
                                    class="group flex w-full items-start gap-3 px-5 py-4 text-left transition-colors hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none sm:px-6">
                                    <span class="mt-0.5 shrink-0 rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $t['categoria'] }}</span>
                                    <span class="flex-1 text-sm font-semibold text-gray-900 group-hover:text-udg-900">{{ $t['pregunta'] }}</span>
                                    <svg class="mt-0.5 size-5 shrink-0 text-gray-400 transition-transform duration-200 motion-reduce:transition-none"
                                        :class="abierta === {{ $i }} && 'rotate-180 text-udg-900'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                </button>
                            </h3>
                            <div id="respuesta-{{ $i }}" x-show="abierta === {{ $i }}" x-cloak
                                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                class="px-5 pb-5 sm:px-6">
                                <p class="rounded-xl border-l-4 border-oro-400 bg-oro-50/50 px-4 py-3 text-sm leading-relaxed text-gray-700">{{ $t['respuesta'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div x-show="total === 0" x-cloak class="px-6 py-10 text-center">
                    <p class="text-sm font-semibold text-gray-900">No encontramos esa duda</p>
                    <p class="mt-1 text-sm text-gray-500">Prueba con otra palabra o escríbenos: con gusto te ayudamos.</p>
                    <button type="button" @click="q = ''; cat = ''" class="boton boton-secundario mt-4">Ver todas las preguntas</button>
                </div>
            </div>
        </section>

        {{-- ¿A quién escribo? --}}
        <aside class="space-y-6 xl:sticky xl:top-28 xl:self-start" aria-label="Contacto">
            @if ($rol === \App\Enums\Rol::Alumno && $usuario->alumno)
                <x-alumno.tu-coordinacion :carrera="$usuario->alumno->carrera" />
                <p class="px-1 text-xs leading-relaxed text-gray-500">
                    Vinculación solo atiende convenios con organizaciones. Si tu duda es sobre una organización, revisa los
                    <a href="{{ route('convenios-vigentes') }}" class="enlace">convenios vigentes</a>.
                </p>
            @else
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs"
                    x-data="{ copiado: false, copiar(t) { navigator.clipboard?.writeText(t).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000) }) } }">
                    <div class="h-1 bg-gradient-to-r from-udg-800 to-oro-400" aria-hidden="true"></div>
                    <div class="p-5">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.14em] text-oro-700">¿Sigues con dudas?</h2>
                        <p class="mt-1.5 font-display text-base font-bold text-gray-900">Vinculación del CUCSH</p>
                        <p class="mt-1 text-sm leading-relaxed text-gray-600">
                            @if ($rol === \App\Enums\Rol::Empresa)
                                Le acompañamos en su expediente, su convenio y su renovación.
                            @else
                                Convenios con organizaciones y cuentas de la plataforma.
                            @endif
                        </p>
                        <dl class="mt-4 space-y-2 text-sm text-gray-700">
                            <div class="flex items-center gap-2"><dt class="sr-only">Correo</dt>
                                <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                <dd class="min-w-0 break-all">{{ $correo }}</dd></div>
                            <div class="flex items-center gap-2"><dt class="sr-only">Teléfono</dt>
                                <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                                <dd><a href="tel:{{ config('practicas.contacto.telefono_enlace') }}" class="enlace font-normal">{{ config('practicas.contacto.telefono') }}</a>, ext. {{ implode(' y ', config('practicas.contacto.extensiones')) }}</dd></div>
                        </dl>
                        <div class="mt-4 grid grid-cols-1 gap-2">
                            <a href="{{ \App\Support\Correo::gmail($correo, $rol === \App\Enums\Rol::Empresa ? 'Convenio de prácticas profesionales' : 'Prácticas profesionales · Coordinación') }}" target="_blank" rel="noopener" class="boton boton-primario">Escribir por Gmail</a>
                            <button type="button" @click="copiar(@js($correo))" class="boton boton-secundario" :class="copiado && '!border-success-300 !bg-success-50 !text-success-700'">
                                <span x-show="!copiado">Copiar correo</span><span x-show="copiado" x-cloak>¡Copiado!</span>
                            </button>
                        </div>
                    </div>
                </section>
                @if ($rol === \App\Enums\Rol::Coordinador)
                    <a href="{{ route('coordinacion.reglas') }}" class="group flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs transition hover:border-udg-200 hover:shadow-theme-md">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-oro-50 text-oro-700 ring-1 ring-inset ring-oro-100"><x-dashboard.icono nombre="informe" /></span>
                        <span class="min-w-0 flex-1 text-sm"><span class="block font-semibold text-gray-900">Reglas de mi carrera</span><span class="text-xs text-gray-500">Horas, créditos, reportes y contacto público</span></span>
                        <x-dashboard.icono nombre="flecha" clase="size-4 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-udg-800" />
                    </a>
                @endif
            @endif
        </aside>
    </div>

    {{-- Glosario de estados --}}
    <section aria-labelledby="titulo-estados">
        <h2 id="titulo-estados" class="font-display text-lg font-bold text-gray-900">¿Qué significa cada estado?</h2>
        <p class="text-sm text-gray-500">Los mismos colores que verás en tus pantallas.</p>
        <div @class(['mt-3 grid grid-cols-1 gap-4', 'lg:grid-cols-2' => count($contenido['estados']) === 2, 'lg:grid-cols-3' => count($contenido['estados']) >= 3])>
            @foreach ($contenido['estados'] as $g)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-oro-700">{{ $g['grupo'] }}</h3>
                    <dl class="mt-3 space-y-3">
                        @foreach ($g['items'] as $it)
                            <div>
                                <dt><x-ui.estatus :estado="$it['estado']" /></dt>
                                <dd class="mt-1 text-sm leading-relaxed text-gray-600">{{ $it['texto'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
