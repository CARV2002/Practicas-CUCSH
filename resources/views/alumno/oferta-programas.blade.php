    @extends('layouts.app')

    @php
        $images = [
            [
                'src' => asset('images/grid-image/image-04.png'),
                'alt' => 'Grid image 1',
            ],
            [
                'src' => asset('images/grid-image/image-05.png'),
                'alt' => 'Grid image 2',
            ],
            [
                'src' => asset('images/grid-image/image-06.png'),
                'alt' => 'Grid image 3',
            ],
        ];
    @endphp


    @section('content')

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

    </style>
        <x-common.page-breadcrumb pageTitle="Oferta programas" />
        <div class="space-y-5 sm:space-y-6">

<div class="saas-dashboard-container">
    
    <div class="saas-search-filter-bar">
        <form action="#" method="GET" class="saas-search-form">
            <div class="saas-input-wrapper">
                <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por empresa, puesto, palabras clave o ubicación...">
            </div>
            
            <div class="saas-filters-group">
                <div class="saas-dropdown-filter">
                    <select name="area" class="saas-select">
                        <option value="">Todas las áreas</option>
                        <option value="juridica">Estudios Jurídicos</option>
                        <option value="social">Políticos y Sociales</option>
                        <option value="comunicacion">Cultura y Comunicación</option>
                    </select>
                </div>

                <div class="saas-dropdown-filter">
                    <select name="modalidad" class="saas-select">
                        <option value="">Todas las modalidades</option>
                        <option value="remoto">Remoto</option>
                        <option value="hibrido">Híbrido</option>
                        <option value="presencial">Presencial</option>
                    </select>
                </div>

                <div class="saas-dropdown-filter">
                    <select name="cupo" class="saas-select">
                        <option value="disponibles">Con cupos disponibles</option>
                        <option value="todos">Todos los programas</option>
                    </select>
                </div>
            </div>
        </form>

        <div class="saas-quick-chips">
            <button class="chip active">Solo Nuevas</button>
            <button class="chip">Recomendadas para mí</button>
            <button class="chip">Próximas a cerrar</button>
        </div>
    </div>

    <div class="saas-split-layout">
        
        <aside class="saas-feed-column" aria-label="Lista de vacantes">
            <div class="saas-feed-header">
                <span>Resultados encontrados ({{ count($programas ?? [1,2,3]) }})</span>
            </div>

            <div class="saas-cards-feed-wrapper">
                {{-- Mock loop simulando la iteración nativa de Laravel --}}
                @empty($programas)
                    @foreach([1, 2, 3] as $index)
                    <div class="saas-compact-card {{ $index === 1 ? 'is-selected' : '' }}" role="button" tabindex="0">
                        <div class="card-meta-indicators">
                            @if($index === 1)
                                <span class="indicator-badge new">Nueva</span>
                            @endif
                            <span class="indicator-badge slots-available">Con cupos</span>
                        </div>
                        
                        <div class="card-main-row">
                            <div class="company-logo-avatar">
                                <span>{{ substr("Administradora Inicial C.A.", 0, 2) }}</span>
                            </div>
                            <div class="card-headline">
                                <h3 class="vacancy-title">Practicante de Desarrollo Web</h3>
                                <p class="company-name">Administradora Inicial C.A.</p>
                            </div>
                        </div>

                        <div class="card-tags-row">
                            <span class="meta-tag">Híbrido</span>
                            <span class="meta-tag">Guadalajara</span>
                        </div>

                        <div class="card-footer-metrics">
                            <div class="metric"><span class="num">20</span><span class="lbl">Vacantes</span></div>
                            <div class="metric"><span class="num">11</span><span class="lbl">Postulados</span></div>
                            <span class="post-date">Ref: 2 feb 2026</span>
                        </div>
                    </div>
                    @endforeach
                @endempty
            </div>
        </aside>

        <main class="saas-detail-column" aria-live="polite">
            <div class="saas-sticky-detail-card">
                
                <div class="detail-header-block">
                    <div class="detail-company-identity">
                        <div class="detail-logo-large">UDG</div>
                        <div>
                            <h2>Practicante de Desarrollo Web</h2>
                            <p class="company-link">Administradora Inicial C.A. <span class="verified-badge">✓ Verified</span></p>
                            <p class="detail-geo">Guadalajara, Jalisco (Zona Centro) • Publicado el 2 de febrero de 2026</p>
                        </div>
                    </div>
                    
                    <div class="detail-action-bar">
                        <button class="btn-action-primary">Postularme ahora</button>
                        <button class="btn-action-secondary" aria-label="Guardar vacante">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="detail-body-scroller custom-scrollbar">
                    <div class="detail-summary-cards">
                        <div class="summary-box">
                            <strong>Modalidad</strong>
                            <p>Híbrido (3 días presenciales, 2 remoto)</p>
                        </div>
                        <div class="summary-box">
                            <strong>Horario</strong>
                            <p>Lunes a Viernes • 09:00 a 14:00 hrs</p>
                        </div>
                        <div class="summary-box">
                            <strong>Duración</strong>
                            <p>480 horas obligatorias</p>
                        </div>
                    </div>

                    <div class="detail-section-text">
                        <h3>Descripción del puesto</h3>
                        <p>Buscamos un estudiante con iniciativa proactiva para integrarse al equipo de transformación digital. Participará directamente en el mantenimiento de arquitecturas de software internas, pruebas funcionales y documentación técnica bajo metodologías ágiles.</p>
                    </div>

                    <div class="detail-section-text">
                        <h3>Responsabilidades y actividades</h3>
                        <ul>
                            <li>Apoyo en el maquetado adaptivo de vistas utilizando HTML5, CSS3 y Bootstrap.</li>
                            <li>Colaboración en optimización de consultas en bases de datos relacionales.</li>
                            <li>Generación de reportes semanales de avance técnico con coordinadores del área.</li>
                        </ul>
                    </div>

                    <div class="detail-section-text">
                        <h3>Requisitos indispensables</h3>
                        <ul>
                            <li>Ser estudiante activo del CUCSH con estatus regular.</li>
                            <li>Conocimientos básicos de frameworks backend o desarrollo web estructurado.</li>
                            <li>Comprensión de lógica de programación y control de versiones (Git).</li>
                        </ul>
                    </div>
                </div>

            </div>
        </main>

    </div>
</div>























        
        </div>



        
    @endsection

