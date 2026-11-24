{{--
    «Tu coordinación»: el alumno resuelve su proceso (postulación, cartas, reportes, liberación)
    con la Coordinación de su carrera. Vinculación solo atiende convenios con empresas.
    Datos: App\View\Components\Alumno\TuCoordinacion ($coordinador, $correo, $telefono).
--}}

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs']) }} aria-labelledby="tu-coordinacion-titulo"
    x-data="{ copiado: false, copiar(t) { navigator.clipboard?.writeText(t).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000) }) } }">
    <div class="h-1 bg-gradient-to-r from-udg-800 to-oro-400" aria-hidden="true"></div>
    <div class="p-5">
        <h2 id="tu-coordinacion-titulo" class="text-xs font-semibold uppercase tracking-[0.14em] text-oro-700">Tu coordinación</h2>
        <p class="mt-1.5 font-display text-base font-bold leading-snug text-gray-900">Coordinación de {{ $carrera->nombre }}</p>
        @if ($coordinador)
            <p class="mt-0.5 text-sm text-gray-600">{{ $coordinador->name }}</p>
        @endif
        <p class="mt-2 text-xs leading-relaxed text-gray-500">Para tu postulación, cartas, reportes y liberación acude a tu coordinación. Vinculación solo atiende convenios con empresas.</p>

        @if ($correo || $telefono)
            <dl class="mt-4 space-y-2 text-sm">
                @if ($correo)
                    <div class="flex items-center gap-2 text-gray-700">
                        <dt class="sr-only">Correo</dt>
                        <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <dd class="min-w-0 break-all">{{ $correo }}</dd>
                    </div>
                @endif
                @if ($telefono)
                    <div class="flex items-center gap-2 text-gray-700">
                        <dt class="sr-only">Teléfono</dt>
                        <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                        <dd>Teléfono: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $telefono) }}" class="enlace font-normal">{{ $telefono }}</a></dd>
                    </div>
                @endif
            </dl>
        @endif

        @if ($correo)
            <div class="mt-4 grid grid-cols-1 gap-2">
                <a href="{{ \App\Support\Correo::gmail($correo, 'Prácticas profesionales · ' . $carrera->carrera_clave) }}" target="_blank" rel="noopener"
                    class="boton boton-primario whitespace-nowrap">Escribir por Gmail</a>
                <button type="button" @click="copiar(@js($correo))" class="boton boton-secundario whitespace-nowrap"
                    :class="copiado && '!border-success-300 !bg-success-50 !text-success-700'">
                    <span x-show="!copiado">Copiar correo</span>
                    <span x-show="copiado" x-cloak>¡Copiado!</span>
                </button>
            </div>
        @else
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-200">Tu coordinación aún no registra su correo de contacto.</p>
        @endif
    </div>
</section>
