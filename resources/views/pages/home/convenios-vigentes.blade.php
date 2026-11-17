@extends('layouts.landing')

@section('title', 'Organizaciones con convenio vigente | Prácticas Profesionales CUCSH')

@section('content')
  <div class="hero hero-estudiantes !min-h-0 !py-16 sm:!py-20">
    <div class="brand-tag">Estudiantes</div>
    <h1 class="!text-[clamp(32px,6vw,56px)]">Convenios vigentes</h1>
    <p class="!mb-0">Organizaciones donde puedes realizar tus prácticas externas, según tu carrera.</p>
  </div>

  <nav aria-label="Ruta de navegación" class="border-b border-gray-200 bg-white">
    <ol class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 text-sm text-gray-500 sm:px-6">
      <li><a href="{{ route('estudiantes') }}" class="enlace font-medium">Estudiantes</a></li>
      <li aria-hidden="true">›</li>
      <li class="font-medium text-gray-800" aria-current="page">Convenios vigentes</li>
    </ol>
  </nav>
  {{-- Convenios vigentes por carrera: reemplaza la lista que Vinculación enviaba por correo --}}
  @php
    $listaConvenios = $conveniosVigentes->map(fn ($cv) => [
        'nombre' => mb_strtolower($cv->empresa->razon_social),
        'claves' => $cv->dictamenes->map(fn ($d) => $d->carrera->carrera_clave)->values()->all(),
    ])->values();
  @endphp
  <section id="convenios-vigentes" class="scroll-mt-24 bg-gray-50 !py-16 sm:!py-20"
    aria-labelledby="titulo-convenios"
    x-data="{
      carrera: '',
      q: '',
      items: @js($listaConvenios),
      visible(i) {
        const it = this.items[i];
        return (this.carrera === '' || it.claves.includes(this.carrera))
          && (this.q.trim() === '' || it.nombre.includes(this.q.trim().toLowerCase()));
      },
      get total() { return this.items.filter((_, i) => this.visible(i)).length; },
    }">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
      <div class="max-w-2xl">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-oro-700">Dónde hacer tus prácticas</p>
        <h2 id="titulo-convenios" class="mt-2 font-display text-2xl font-bold text-udg-900 sm:text-3xl">Organizaciones con convenio vigente</h2>
        <p class="mt-3 text-base leading-relaxed text-gray-600">
          Solo puedes hacer prácticas externas en una organización con convenio vigente <strong class="font-semibold text-gray-800">para tu carrera</strong>.
          Elige tu carrera para ver solo las que te corresponden.
        </p>
      </div>

      @if ($conveniosVigentes->isNotEmpty())
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-end">
          <div class="sm:w-80">
            <label for="filtro-carrera-convenios" class="etiqueta">Mi carrera</label>
            <select id="filtro-carrera-convenios" x-model="carrera" class="campo h-11">
              <option value="">Todas las carreras</option>
              @foreach ($divisiones as $division)
                <optgroup label="{{ $division->nombre }}">
                  @foreach ($division->carreras as $c)
                    <option value="{{ $c->carrera_clave }}">{{ $c->nombre }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
          </div>
          <div class="sm:flex-1">
            <label for="buscar-organizacion" class="etiqueta">Buscar organización</label>
            <div class="relative">
              <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
              <input id="buscar-organizacion" type="search" x-model.debounce.150ms="q" placeholder="Nombre de la organización" class="campo h-11 pl-10" autocomplete="off">
            </div>
          </div>
        </div>

        <p class="mt-4 text-sm text-gray-500" aria-live="polite">
          <span class="font-semibold text-gray-800" x-text="total">{{ $conveniosVigentes->count() }}</span>
          <span x-text="total === 1 ? 'organización' : 'organizaciones'">organizaciones</span>
          <template x-if="carrera !== ''"><span> para tu carrera</span></template>
        </p>

        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($conveniosVigentes as $i => $cv)
            @php $carrerasCv = $cv->dictamenes->map(fn ($d) => $d->carrera)->filter(); @endphp
            <li x-show="visible({{ $i }})" x-transition.opacity.duration.200ms
              class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-theme-xs transition duration-200 hover:-translate-y-0.5 hover:shadow-theme-md motion-reduce:transition-none motion-reduce:hover:translate-y-0">
              <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-udg-50 font-display font-bold text-udg-900 ring-1 ring-udg-100" aria-hidden="true">
                  {{ mb_strtoupper(mb_substr($cv->empresa->razon_social, 0, 1)) }}
                </span>
                <div class="min-w-0">
                  <p class="font-semibold leading-snug text-gray-900">{{ $cv->empresa->razon_social }}</p>
                  @if ($cv->empresa->tipo_organismo)
                    <p class="mt-0.5 text-xs text-gray-500">{{ $cv->empresa->tipo_organismo->label() }}</p>
                  @endif
                </div>
              </div>

              <div class="mt-4 flex flex-wrap gap-1.5" aria-label="Carreras con convenio">
                @forelse ($carrerasCv as $carreraCv)
                  <span title="{{ $carreraCv->nombre }}"
                    class="rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset transition-colors"
                    :class="carrera === @js($carreraCv->carrera_clave) ? 'bg-udg-900 text-white ring-udg-900' : 'bg-gray-50 text-gray-700 ring-gray-200'">
                    {{ $carreraCv->carrera_clave }}
                  </span>
                @empty
                  <span class="text-xs text-gray-400">Carreras por confirmar</span>
                @endforelse
              </div>

              <div class="mt-auto pt-4">
                <x-ui.estatus familia="menta" :texto="'Vigente hasta ' . $cv->fecha_fin_vigencia->format('d/m/Y')" />
              </div>
            </li>
          @endforeach
        </ul>

        <div x-show="total === 0" x-cloak class="mt-4 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center">
          <p class="font-semibold text-gray-900">No hay organizaciones con esos filtros</p>
          <p class="mt-1 text-sm text-gray-500">
            <template x-if="carrera !== ''"><span>Por ahora no hay convenios vigentes para tu carrera. Pregunta a tu coordinación por proyectos internos (modalidad interna).</span></template>
            <template x-if="carrera === ''"><span>Revisa el nombre que escribiste.</span></template>
          </p>
          <button type="button" @click="carrera = ''; q = ''" class="boton boton-secundario mt-4">Quitar filtros</button>
        </div>
      @else
        <div class="mt-8 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center">
          <p class="font-semibold text-gray-900">Aún no hay convenios vigentes</p>
          <p class="mt-1 text-sm text-gray-500">Mientras tanto, pregunta a tu coordinación por proyectos internos.</p>
        </div>
      @endif
    </div>
  </section>


  <section class="bg-white !py-12 sm:!py-16">
    <div class="mx-auto flex max-w-6xl flex-col items-start gap-4 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <div>
        <h2 class="font-display text-xl font-bold text-udg-900">¿Tienes dudas sobre una organización o tu postulación?</h2>
        <p class="mt-1 text-sm text-gray-600">Escribe a la coordinación de tu carrera: ellos validan tu postulación y emiten tu carta de presentación.</p>
      </div>
      <a href="{{ route('contacto') }}#directorio" class="boton boton-primario w-full shrink-0 sm:w-auto">Buscar mi coordinación</a>
    </div>
  </section>
@endsection
