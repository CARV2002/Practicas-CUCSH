@extends('layouts.app')

@section('content')
@php
    use App\Enums\PostulacionEstado as PE;

    $empresaDe = fn ($p) => $p->convenio?->empresa->razon_social ?? 'Proyecto interno CUCSH';
    $chipUrl = fn ($c) => request()->fullUrlWithQuery(['chip' => $chip === $c ? null : $c, 'page' => null, 'programa' => null]);
@endphp

@push('estilos')
    @vite('resources/css/paginas/oferta-programas.css')
@endpush

<x-common.page-breadcrumb pageTitle="Oferta programas" />

<div class="saas-dashboard-container">

    @if (session('status'))
        <x-ui.aviso tipo="exito" descartable>{{ session('status') }}</x-ui.aviso>
    @endif
    @if ($errors->any())
        <x-ui.aviso tipo="error"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.aviso>
    @endif
    @if ($bloqueo)
        <x-ui.aviso tipo="advertencia">{{ $bloqueo }}</x-ui.aviso>
    @endif

    {{-- Búsqueda y filtros --}}
    <div class="saas-search-filter-bar">
        <form action="{{ route('alumno.oferta-programas') }}" method="GET" class="saas-search-form">
            <div class="saas-input-wrapper">
                <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por empresa, puesto, palabras clave o sede...">
            </div>

            <div class="saas-filters-group">
                <div class="saas-dropdown-filter">
                    <select name="modalidad" class="saas-select" onchange="this.form.submit()">
                        <option value="">Todas las modalidades</option>
                        @foreach ($modalidades as $m)
                            <option value="{{ $m->value }}" @selected(request('modalidad') === $m->value)>{{ $m->value === 'externa' ? 'Empresa / institución' : 'Proyecto interno' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="saas-dropdown-filter">
                    <select name="ciclo" class="saas-select" onchange="this.form.submit()">
                        <option value="">Todos los ciclos</option>
                        @foreach ($ciclos as $c)
                            <option value="{{ $c }}" @selected(request('ciclo') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="saas-dropdown-filter">
                    <button class="btn-action-primary" style="width:100%;">Buscar</button>
                </div>
            </div>
            @if ($chip)<input type="hidden" name="chip" value="{{ $chip }}">@endif
        </form>

        <div class="saas-quick-chips">
            <a href="{{ $chipUrl('nuevas') }}" class="chip {{ $chip === 'nuevas' ? 'active' : '' }}">Solo nuevas</a>
            <a href="{{ $chipUrl('cierre') }}" class="chip {{ $chip === 'cierre' ? 'active' : '' }}">Próximas a cerrar</a>
            <span class="chip" style="cursor:default;">Para {{ $alumno->carrera->nombre }}</span>
        </div>
    </div>

    <div class="saas-split-layout {{ request()->filled('programa') ? 'mostrando-detalle' : '' }}">

        {{-- Feed --}}
        <aside class="saas-feed-column" aria-label="Lista de vacantes">
            <div class="saas-feed-header">
                <span>Resultados encontrados ({{ $programas->total() }})</span>
            </div>

            <div class="saas-cards-feed-wrapper">
                @forelse ($programas as $p)
                    @php
                        $nuevo = $p->created_at->gt(now()->subDays(7));
                        $postulado = $misPostulaciones->get($p->id);
                    @endphp
                    <a href="{{ request()->fullUrlWithQuery(['programa' => $p->id]) }}"
                        class="saas-compact-card {{ $seleccionado && $seleccionado->id === $p->id ? 'is-selected' : '' }}">
                        <div class="card-meta-indicators">
                            @if ($nuevo)<span class="indicator-badge new">Nueva</span>@endif
                            <span class="indicator-badge slots-available">{{ $p->cupos_disponibles }} cupo(s)</span>
                            @if ($postulado)<span class="indicator-badge" style="background:#eef2ff;color:#3730a3;">{{ $postulado->label() }}</span>@endif
                        </div>

                        <div class="card-main-row">
                            <div class="card-headline">
                                <h3 class="vacancy-title">{{ $p->titulo }}</h3>
                                <p class="company-name">{{ $empresaDe($p) }}</p>
                            </div>
                        </div>

                        <div class="card-tags-row">
                            @if ($p->sede)<span class="meta-tag">{{ $p->sede }}</span>@endif
                            @if ($p->horario)<span class="meta-tag">{{ $p->horario }}</span>@endif
                            <span class="meta-tag">{{ $p->ciclo }}</span>
                        </div>

                        <div class="card-footer-metrics">
                            <div class="metric"><span class="num">{{ $p->cupos_totales }}</span><span class="lbl">Vacantes</span></div>
                            <div class="metric"><span class="num">{{ (int) $p->cupos_ocupados }}</span><span class="lbl">Ocupadas</span></div>
                            <span class="post-date">Publicada: {{ $p->created_at->translatedFormat('d M Y') }}</span>
                        </div>
                    </a>
                @empty
                    <div class="saas-compact-card" style="text-align:center; cursor:default;">
                        <p class="vacancy-title">No encontramos programas</p>
                        <p class="company-name">Prueba con otras palabras o quita los filtros.</p>
                    </div>
                @endforelse

                @if ($programas->hasPages())
                    <div class="pager-wrap">{{ $programas->links() }}</div>
                @endif
            </div>
        </aside>

        {{-- Detalle --}}
        <main class="saas-detail-column" aria-live="polite" >
            <a href="{{ request()->fullUrlWithQuery(['programa' => null]) }}" class="saas-volver-lista">← Volver a las ofertas</a>
            @if ($seleccionado)
                @php
                    $s = $seleccionado;
                    $postulado = $misPostulaciones->get($s->id);
                    $puede = ! $postulado && ! $bloqueo;
                @endphp
                <div class="saas-sticky-detail-card" x-data="{ abierto: {{ $errors->has('siiau') ? 'true' : 'false' }} }">

                    <div class="detail-header-block">
                        <div class="detail-company-identity">
                            <div>
                                <h2>{{ $s->titulo }}</h2>
                                <p class="company-link">{{ $empresaDe($s) }}
                                    @if ($s->convenio)<span class="verified-badge">✓ Convenio vigente</span>@endif
                                </p>
                                <p class="detail-geo">{{ $s->sede ?: 'Sede por confirmar' }} • Publicado el {{ $s->created_at->translatedFormat('d \d\e F \d\e Y') }}</p>
                            </div>
                        </div>

                        <div class="detail-action-bar">
                            @if ($puede)
                                <button type="button" class="btn-action-primary" @click="abierto = !abierto" x-text="abierto ? 'Cancelar' : 'Postularme ahora'"></button>
                            @elseif ($postulado)
                                <button type="button" class="btn-action-primary" disabled style="opacity:.55;cursor:not-allowed;">Ya te postulaste</button>
                            @else
                                <x-ui.accion-bloqueada :motivo="$bloqueo">Postularme</x-ui.accion-bloqueada>
                            @endif
                        </div>

                        @if ($postulado)
                            <div class="detail-state">Estado de tu postulación: <strong>{{ $postulado->label() }}</strong>. Da seguimiento en <a href="{{ route('alumno.mis-documentos') }}" style="text-decoration:underline;">Mis documentos</a>.</div>
                        @endif

                        @if ($puede)
                            <form method="POST" action="{{ route('alumno.oferta-programas.postular', $s) }}" x-show="abierto" x-cloak
                                data-confirmar="¿Enviar tu postulación a «{{ $s->titulo }}»? Solo puedes tener una postulación en trámite a la vez." data-confirmar-titulo="Confirmar postulación" data-confirmar-boton="Sí, postularme" data-confirmar-tono="normal"
                                x-data="{ enviando: false }" x-on:submit="if (!$el.checkValidity()) { $event.preventDefault(); $el.reportValidity(); } else { enviando = true }"
                                class="mt-4 space-y-3 rounded-2xl border border-udg-100 bg-udg-50/40 p-4 text-left sm:p-5">
                                @csrf
                                <p class="font-display text-base font-bold text-gray-900">Confirma tu postulación</p>
                                <p class="flex items-start gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                                    <x-dashboard.icono nombre="check" clase="mt-0.5 size-4 shrink-0" />
                                    <span>Tu kardex ({{ \App\Support\Formato::porcentaje($alumno->porcentaje_creditos) }}% de créditos) y tu IMSS ya están validados. Tu coordinación solo emitirá tu carta de presentación.</span>
                                </p>
                                <label class="flex items-start gap-2.5 rounded-xl bg-white p-3 text-sm leading-relaxed text-gray-700 ring-1 ring-inset ring-gray-200 has-[:checked]:ring-udg-800">
                                    <input type="checkbox" name="siiau" value="1" required @checked(old('siiau')) class="mt-1 size-4 shrink-0 rounded border-gray-300 text-udg-800">
                                    <span><span class="font-semibold text-gray-900">Declaro bajo protesta de decir verdad</span> que ya inscribí la materia de Prácticas Profesionales en SIIAU dentro del calendario escolar vigente.</span>
                                </label>
                                @error('siiau')<p class="text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                                <button class="boton boton-primario w-full justify-center" :disabled="enviando">
                                    <span x-show="!enviando">Enviar postulación</span>
                                    <span x-show="enviando" x-cloak>Enviando…</span>
                                </button>
                            </form>

                        @endif
                    </div>

                    <div class="detail-body-scroller custom-scrollbar">
                        <div class="detail-summary-cards">
                            <div class="summary-box"><strong>Modalidad</strong><p>{{ $s->esInterno() ? 'Proyecto interno' : 'Empresa / institución' }}</p></div>
                            <div class="summary-box"><strong>Horario</strong><p>{{ $s->horario ?: 'Por acordar' }}</p></div>
                            <div class="summary-box"><strong>Duración</strong><p>{{ $alumno->carrera->horas_requeridas }} horas requeridas</p></div>
                        </div>

                        <div class="detail-section-text">
                            <h3>Descripción del puesto</h3>
                            <p>{{ $s->descripcion }}</p>
                        </div>

                        <div class="detail-section-text">
                            <h3>Responsabilidades y actividades</h3>
                            <ul>@foreach ($s->actividades ?? [] as $a)<li>{{ $a }}</li>@endforeach</ul>
                        </div>

                        <div class="detail-section-text">
                            <h3>Requisitos indispensables</h3>
                            <ul>
                                <li>Ser alumno activo de {{ $alumno->carrera->nombre }}.</li>
                                <li>Contar con al menos {{ \App\Support\Formato::porcentaje($alumno->carrera->porcentaje_creditos_minimo) }}% de créditos.</li>
                                <li>Seguro facultativo (IMSS) vigente durante toda la práctica.</li>
                            </ul>
                            <p style="margin-top:12px;">{{ $s->perfil_requerido }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="saas-sticky-detail-card" style="align-items:center; justify-content:center; text-align:center; padding:48px;">
                    <div style="font-size:48px;">🔎</div>
                    <h3 class="vacancy-title" style="margin-top:12px;">Selecciona un programa para ver su detalle</h3>
                </div>
            @endif
        </main>
    </div>
</div>


@endsection