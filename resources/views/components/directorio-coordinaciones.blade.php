@props(['titulo' => 'Directorio de coordinaciones de carrera'])

{{--
    Directorio público: el alumno resuelve su trámite con la Coordinación de su carrera.
    Cada coordinación captura su correo y teléfono en «Reglas de mi carrera».
    Se usa en las páginas de Estudiantes y Contacto.
--}}
@php
    $divisiones = \App\Models\Division::with(['carreras' => fn ($q) => $q->orderBy('nombre')])
        ->whereHas('carreras')
        ->orderBy('nombre')
        ->get();
    $totalCarreras = $divisiones->sum(fn ($d) => $d->carreras->count());
    $quitarAcentos = fn (string $t) => mb_strtolower(\Illuminate\Support\Str::ascii($t));
    $textosCarreras = $divisiones->flatMap(fn ($d) => $d->carreras->map(
        fn ($c) => $quitarAcentos($c->nombre . ' ' . $c->carrera_clave . ' ' . $d->nombre)
    ))->values();
@endphp

<div {{ $attributes->merge(['class' => 'mx-auto w-full max-w-6xl px-4 sm:px-6']) }}
    x-data="{
        q: '',
        copiado: null,
        textos: @js($textosCarreras),
        get hayResultados() { return this.textos.some((t) => this.coincide(t)); },
        norm(t) { return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); },
        coincide(texto) { return this.q === '' || texto.includes(this.norm(this.q)); },
        copiar(t) { navigator.clipboard?.writeText(t).then(() => { this.copiado = t; setTimeout(() => this.copiado = null, 2000) }) },
    }">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-oro-700">¿Eres alumno?</p>
            <h2 class="mt-2 font-display text-2xl font-bold text-udg-900 sm:text-3xl">{{ $titulo }}</h2>
            <p class="mt-3 text-base leading-relaxed text-gray-600">
                Tu <strong class="font-semibold text-gray-800">postulación, carta de presentación, reportes, carta de término y liberación</strong>
                se atienden en la coordinación de tu carrera. Vinculación solo atiende convenios con organizaciones.
            </p>
        </div>
        <div class="w-full lg:w-80">
            <label for="buscar-coordinacion-{{ $attributes->get('id', 'dir') }}" class="etiqueta">Busca tu carrera</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input id="buscar-coordinacion-{{ $attributes->get('id', 'dir') }}" type="search" x-model.debounce.150ms="q"
                    placeholder="Ej. Abogado, Sociología…" class="campo h-11 pl-10" autocomplete="off">
            </div>
        </div>
    </div>

    <div class="mt-8 space-y-8">
        @foreach ($divisiones as $division)
            @php
                $textosDivision = $division->carreras
                    ->map(fn ($c) => $quitarAcentos($c->nombre . ' ' . $c->carrera_clave . ' ' . $division->nombre))
                    ->join(' | ');
            @endphp
            <section x-show="coincide(@js($textosDivision))" class="!p-0" aria-labelledby="division-{{ $division->id }}">
                <h3 id="division-{{ $division->id }}" class="mb-3 flex items-center gap-3 text-sm font-semibold text-gray-700">
                    <span class="h-4 w-1 rounded-full bg-oro-400" aria-hidden="true"></span>
                    {{ $division->nombre }}
                </h3>

                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($division->carreras as $c)
                        <li x-show="coincide(@js($quitarAcentos($c->nombre . ' ' . $c->carrera_clave . ' ' . $division->nombre)))"
                            class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-theme-xs transition-shadow duration-200 hover:shadow-theme-md">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-semibold leading-snug text-gray-900">{{ $c->nombre }}</p>
                                <span class="shrink-0 rounded-md bg-udg-50 px-2 py-0.5 text-xs font-semibold text-udg-900 ring-1 ring-inset ring-udg-100">{{ $c->carrera_clave }}</span>
                            </div>

                            @if ($c->coordinacion_correo || $c->coordinacion_telefono)
                                <dl class="mt-3 space-y-1.5 text-sm">
                                    @if ($c->coordinacion_correo)
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <dt class="sr-only">Correo</dt>
                                            <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                            <dd class="min-w-0 break-all">{{ $c->coordinacion_correo }}</dd>
                                        </div>
                                    @endif
                                    @if ($c->coordinacion_telefono)
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <dt class="sr-only">Teléfono</dt>
                                            <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                                            <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c->coordinacion_telefono) }}" class="enlace font-normal">{{ $c->coordinacion_telefono }}</a></dd>
                                        </div>
                                    @endif
                                </dl>
                            @endif

                            <div class="mt-auto pt-4">
                                @if ($c->coordinacion_correo)
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ \App\Support\Correo::gmail($c->coordinacion_correo, 'Prácticas profesionales · ' . $c->carrera_clave) }}"
                                            target="_blank" rel="noopener" class="boton boton-chico boton-primario flex-1 sm:flex-none"
                                            aria-label="Escribir por Gmail a la coordinación de {{ $c->nombre }}">
                                            Escribir por Gmail
                                        </a>
                                        <button type="button" @click="copiar(@js($c->coordinacion_correo))"
                                            class="boton boton-chico boton-secundario flex-1 sm:flex-none"
                                            aria-label="Copiar correo de la coordinación de {{ $c->nombre }}">
                                            <span x-text="copiado === @js($c->coordinacion_correo) ? '¡Copiado!' : 'Copiar correo'">Copiar correo</span>
                                        </button>
                                    </div>
                                @else
                                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-200">
                                        Correo por confirmar: acude a tu división.
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <div x-show="!hayResultados" x-cloak
            class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center">
            <p class="font-semibold text-gray-900">No encontramos esa carrera</p>
            <p class="mt-1 text-sm text-gray-500">Revisa cómo la escribiste o consulta la lista completa ({{ $totalCarreras }} carreras).</p>
            <button type="button" @click="q = ''" class="boton boton-secundario mt-4">Ver todas</button>
        </div>
    </div>
</div>
