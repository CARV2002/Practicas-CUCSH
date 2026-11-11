@extends('layouts.app')

@section('content')
@php
    use App\Enums\PostulacionEstado as PE;

    $iniciales = fn ($t) => mb_strtoupper(mb_substr(preg_replace('/[^\p{L}]/u', '', $t), 0, 2));
    $empresaDe = fn ($p) => $p->convenio?->empresa->razon_social ?? 'Proyecto interno CUCSH';
    $chipUrl = fn ($c) => request()->fullUrlWithQuery(['chip' => $chip === $c ? null : $c, 'page' => null, 'programa' => null]);
@endphp

<style>
/* ==========================================================================
   SaaS JOB FEED INTERFACE CSS (Estilo UI Moderno / Reducción de Fricción)
   ========================================================================== */

.saas-dashboard-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 24px;
    font-family: var(--font-outfit);
}

/* --- BARRA DE BÚSQUEDA Y FILTROS INTEGRADOS --- */
.saas-search-filter-bar {
    background: var(--color-white);
    padding: 20px;
    border-radius: var(--b2b-radius-md, 12px);
    border: 1px solid var(--color-gray-200);
    box-shadow: var(--shadow-theme-sm);
}

.saas-search-form {
    display: grid;
    grid-template-columns: 5fr 7fr;
    gap: 16px;
    align-items: center;
}

.saas-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.saas-input-wrapper .search-icon {
    position: absolute;
    left: 14px;
    color: var(--color-gray-400);
}

.saas-input-wrapper input {
    width: 100%;
    padding: 11px 16px 11px 44px;
    border: 1px solid var(--color-gray-300);
    border-radius: 8px;
    font-size: var(--text-theme-sm);
    color: var(--color-black);
    background: var(--color-gray-25);
    transition: all 0.2s ease;
}

.saas-input-wrapper input:focus {
    outline: none;
    border-color: var(--color-brand-500);
    background: var(--color-white);
    box-shadow: var(--shadow-focus-ring);
}

.saas-filters-group {
    display: flex;
    gap: 12px;
}

.saas-dropdown-filter {
    flex: 1;
}

.saas-select {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid var(--color-gray-300);
    border-radius: 8px;
    background: var(--color-white);
    font-size: var(--text-theme-sm);
    color: var(--color-gray-700);
    cursor: pointer;
}

.saas-quick-chips {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

.saas-quick-chips .chip {
    padding: 6px 14px;
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-200);
    border-radius: 99px;
    font-size: var(--text-theme-xs);
    font-weight: 500;
    color: var(--color-gray-600);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.saas-quick-chips .chip:hover {
    background: var(--color-brand-25);
    border-color: var(--color-brand-300);
    color: var(--color-brand-700);
}

.saas-quick-chips .chip.active {
    background: var(--color-brand-500);
    border-color: var(--color-brand-600);
    color: var(--color-white);
}

/* --- DISTRIBUCIÓN DE PANELES (SPLIT-VIEW) --- */
.saas-split-layout {
    display: grid;
    grid-template-columns: 410px 1fr;
    gap: 24px;
    height: calc(100vh - 210px);
    min-height: 580px;
}

/* --- COLUMNA DE CARDS FEED (IZQUIERDA) --- */
.saas-feed-column {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.saas-feed-header {
    font-size: var(--text-theme-xs);
    font-weight: 600;
    color: var(--color-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.saas-cards-feed-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow-y: auto;
    padding-right: 4px;
}

.saas-compact-card {
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.saas-compact-card:hover {
    border-color: var(--color-brand-300);
    transform: translateY(-1px);
    box-shadow: var(--shadow-theme-md);
}

.saas-compact-card.is-selected {
    border-color: var(--color-brand-500);
    background: var(--color-brand-25);
    box-shadow: 0 0 0 1px var(--color-brand-500);
}

.card-meta-indicators {
    display: flex;
    gap: 6px;
    margin-bottom: 12px;
}

.indicator-badge {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.indicator-badge.new {
    background: var(--color-brand-500);
    color: var(--color-white);
}

.indicator-badge.slots-available {
    background: var(--color-success-50);
    color: var(--color-success-700);
    border: 1px solid var(--color-success-200);
}

.card-main-row {
    display: flex;
    gap: 12px;
    align-items: center;
}

.company-logo-avatar {
    width: 40px;
    height: 40px;
    background: var(--color-brand-950);
    color: var(--color-white);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    flex-shrink: 0;
}

.card-headline .vacancy-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--color-black);
    line-height: 1.3;
}

.card-headline .company-name {
    font-size: var(--text-theme-sm);
    color: var(--color-gray-500);
    margin-top: 2px;
}

.card-tags-row {
    display: flex;
    gap: 8px;
    margin: 14px 0;
}

.meta-tag {
    background: var(--color-gray-100);
    color: var(--color-gray-600);
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
}

.card-footer-metrics {
    display: flex;
    align-items: center;
    gap: 16px;
    border-top: 1px solid var(--color-gray-100);
    padding-top: 12px;
}

.card-footer-metrics .metric {
    display: flex;
    gap: 4px;
    align-items: baseline;
}

.card-footer-metrics .metric .num {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-black);
}

.card-footer-metrics .metric .lbl {
    font-size: 11px;
    color: var(--color-gray-500);
}

.card-footer-metrics .post-date {
    margin-left: auto;
    font-size: 11px;
    color: var(--color-gray-400);
}

/* --- COLUMNA DE DETALLE EXTENDIDO (DERECHA) --- */
.saas-detail-column {
    background: var(--color-white);
    border: 1px solid var(--color-gray-200);
    border-radius: 12px;
    overflow: hidden;
}

.saas-sticky-detail-card {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.detail-header-block {
    padding: 32px;
    border-bottom: 1px solid var(--color-gray-200);
    background: var(--color-gray-25);
}

.detail-company-identity {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.detail-logo-large {
    width: 56px;
    height: 56px;
    background: var(--color-brand-900);
    color: var(--color-white);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 18px;
}

.detail-company-identity h2 {
    font-size: 24px;
    font-weight: 800;
    color: var(--color-black);
    letter-spacing: -0.01em;
}

.company-link {
    font-size: 15px;
    color: var(--color-brand-600);
    font-weight: 600;
    margin-top: 4px;
}

.verified-badge {
    font-size: 12px;
    color: var(--color-success-600);
    background: var(--color-success-50);
    padding: 1px 6px;
    border-radius: 4px;
    margin-left: 6px;
}

.detail-geo {
    font-size: var(--text-theme-sm);
    color: var(--color-gray-500);
    margin-top: 6px;
}

.detail-action-bar {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.btn-action-primary {
    background: var(--color-brand-600);
    color: var(--color-white);
    border: none;
    padding: 12px 32px;
    border-radius: 8px;
    font-weight: 600;
    font-size: var(--text-theme-sm);
    cursor: pointer;
    transition: background 0.15s ease;
}

.btn-action-primary:hover {
    background: var(--color-brand-700);
}

.btn-action-secondary {
    background: var(--color-white);
    border: 1px solid var(--color-gray-300);
    color: var(--color-gray-700);
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action-secondary:hover {
    background: var(--color-gray-50);
    border-color: var(--color-gray-400);
}

/* SCROLLER DEL CUERPO DEL DETALLE */
.detail-body-scroller {
    padding: 32px;
    overflow-y: auto;
    flex: 1;
}

.detail-summary-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}

.summary-box {
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    padding: 16px;
    border-radius: 8px;
}

.summary-box strong {
    display: block;
    font-size: 12px;
    color: var(--color-gray-400);
    text-transform: uppercase;
}

.summary-box p {
    font-size: var(--text-theme-sm);
    font-weight: 600;
    color: var(--color-gray-800);
    margin-top: 4px;
}

.detail-section-text {
    margin-bottom: 28px;
}

.detail-section-text h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 12px;
}

.detail-section-text p, 
.detail-section-text ul {
    font-size: 15px;
    color: var(--color-gray-600);
    line-height: 1.6;
}

.detail-section-text ul {
    padding-left: 20px;
    list-style-type: disc;
}

.detail-section-text li {
    margin-bottom: 8px;
}

/* --- RESPONSIVE ADAPTATIVO --- */
@media (max-width: 1024px) {
    .saas-split-layout { grid-template-columns: 1fr; height: auto; }
    .saas-detail-column { display: none; } /* En móvil se prioriza el feed, la vista extendida se activa por flujo alterno */
    .saas-search-form { grid-template-columns: 1fr; }
    .saas-filters-group { flex-wrap: wrap; }
}



.saas-compact-card { display:block; text-decoration:none; }
.saas-cards-feed-wrapper .pager-wrap { padding: 8px 0; }
.apply-panel { margin-top:16px; padding:16px; border:1px solid var(--color-gray-200); border-radius:10px; background:var(--color-gray-25); }
.apply-panel label { display:block; font-size:12px; font-weight:600; color:var(--color-gray-700); margin-bottom:6px; }
.apply-panel input { width:100%; padding:10px 12px; border:1px solid var(--color-gray-300); border-radius:8px; font-size:14px; background:#fff; margin-bottom:12px; }
.btn-action-primary:disabled { opacity:.5; cursor:not-allowed; }
.detail-state { padding:10px 14px; border-radius:8px; font-size:13px; margin-top:16px; background:var(--color-brand-25); color:var(--color-brand-800); }
</style>

<x-common.page-breadcrumb pageTitle="Oferta programas" />

<div class="saas-dashboard-container">

    @if (session('status'))
        <div class="rounded-xl border border-green-500 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-500 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($bloqueo)
        <div class="rounded-xl border border-yellow-500 bg-yellow-50 p-4 text-sm text-yellow-800">{{ $bloqueo }}</div>
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

    <div class="saas-split-layout">

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
                            <div class="company-logo-avatar"><span>{{ $iniciales($empresaDe($p)) }}</span></div>
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
        <main class="saas-detail-column" aria-live="polite">
            @if ($seleccionado)
                @php
                    $s = $seleccionado;
                    $postulado = $misPostulaciones->get($s->id);
                    $puede = ! $postulado && ! $bloqueo;
                @endphp
                <div class="saas-sticky-detail-card" x-data="{ abierto: {{ $errors->has('kardex') || $errors->has('porcentaje_creditos_declarado') ? 'true' : 'false' }} }">

                    <div class="detail-header-block">
                        <div class="detail-company-identity">
                            <div class="detail-logo-large">{{ $iniciales($empresaDe($s)) }}</div>
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
                            @else
                                <button type="button" class="btn-action-primary" disabled>{{ $postulado ? 'Ya te postulaste' : 'No disponible' }}</button>
                            @endif
                        </div>

                        @if ($postulado)
                            <div class="detail-state">Estado de tu postulación: <strong>{{ $postulado->label() }}</strong>. Da seguimiento en <a href="{{ route('alumno.mis-documentos') }}" style="text-decoration:underline;">Mis documentos</a>.</div>
                        @endif

                        @if ($puede)
                            <form method="POST" enctype="multipart/form-data" action="{{ route('alumno.oferta-programas.postular', $s) }}" x-show="abierto" x-cloak class="apply-panel">
                                @csrf
                                <label for="pct">Porcentaje de créditos cursados (mínimo {{ rtrim(rtrim((string) $alumno->carrera->porcentaje_creditos_minimo, '0'), '.') }}%)</label>
                                <input id="pct" type="number" step="0.01" min="{{ $alumno->carrera->porcentaje_creditos_minimo }}" max="100" name="porcentaje_creditos_declarado" value="{{ old('porcentaje_creditos_declarado') }}" required>

                                <label for="kardex">Kardex (PDF o imagen)</label>
                                <input id="kardex" type="file" name="kardex" accept=".pdf,.jpg,.jpeg,.png" required>

                                <button class="btn-action-primary">Enviar postulación</button>
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
                                <li>Contar con al menos {{ rtrim(rtrim((string) $alumno->carrera->porcentaje_creditos_minimo, '0'), '.') }}% de créditos.</li>
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

<style>[x-cloak]{display:none !important;}</style>
@endsection