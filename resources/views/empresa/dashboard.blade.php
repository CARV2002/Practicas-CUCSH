@extends('layouts.app')

@section('content')

<style>
/* ==========================================================================
   ESTILOS ADICIONALES: PANEL DE CONTROL DE EMPRESA / RECEPTOR
   ========================================================================== */

.company-dashboard {
    font-family: var(--font-outfit);
}

/* Clases de utilidad corporativa */
.text-brand-600 {
    color: var(--color-brand-600);
}
.bg-brand-50 {
    background-color: var(--color-brand-50);
}
.bg-brand-600 {
    background-color: var(--color-brand-600);
}
.hover\:bg-brand-700:hover {
    background-color: var(--color-brand-700);
}

/* Adaptación de Alertas del Sistema */
.bg-warning-50 {
    background-color: var(--color-warning-50);
}
.text-warning-600 {
    color: var(--color-warning-600);
}

.bg-blue-light-50 {
    background-color: var(--color-blue-light-50);
}
.text-blue-light-600 {
    color: var(--color-blue-light-600);
}

.bg-success-50 {
    background-color: var(--color-success-50);
}
.text-success-600 {
    color: var(--color-success-600);
}
.text-success-700 {
    color: var(--color-success-700);
}

.text-error-600 {
    color: var(--color-error-600);
}
.bg-error-50 {
    background-color: var(--color-error-50);
}


</style>
    <x-common.page-breadcrumb pageTitle="Panel de Gestión Externa" />

    <div class="space-y-6 company-dashboard">
        
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Alumnos Asignados</p>
                    <h3 class="mt-1 text-2xl font-bold text-gray-900">14</h3>
                    <span class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-success-600">
                        ● 6 Prácticas • 8 Servicio
                    </span>
                </div>
                <div class="h-12 w-12 bg-brand-50 rounded-xl flex items-center justify-center text-brand-600 text-xl font-bold">🎓</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Postulaciones Nuevas</p>
                    <h3 class="mt-1 text-2xl font-bold text-gray-900">5</h3>
                    <span class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-amber-600 animate-pulse">
                        ⚠️ Requieren entrevista
                    </span>
                </div>
                <div class="h-12 w-12 bg-warning-50 rounded-xl flex items-center justify-center text-warning-600 text-xl font-bold">📩</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Programas Ofertados</p>
                    <h3 class="mt-1 text-2xl font-bold text-gray-900">3</h3>
                    <span class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-gray-500">
                        Plazas totales: 20
                    </span>
                </div>
                <div class="h-12 w-12 bg-blue-light-50 rounded-xl flex items-center justify-center text-blue-light-600 text-xl font-bold">📋</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Convenio General</p>
                    <h3 class="mt-1 text-lg font-bold text-success-700 flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-success-600"></span> Vigente
                    </h3>
                    <span class="mt-1.5 block text-[11px] text-gray-400 font-medium">Vence: 12-Dic-2027</span>
                </div>
                <div class="h-12 w-12 bg-success-50 rounded-xl flex items-center justify-center text-success-600 text-xl font-bold">📄</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            
            <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h4 class="text-base font-bold text-gray-900">Reportes Mensuales de Horas Pendientes</h4>
                        <p class="text-xs text-gray-500">Valida y firma digitalmente las horas ejecutadas por tus alumnos asignados.</p>
                    </div>
                    <a href="#" class="text-xs font-semibold text-brand-600 hover:underline">Ver bitácoras</a>
                </div>

                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 border border-gray-100 rounded-xl hover:bg-gray-50/40 transition-all gap-3">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center text-xs font-bold font-mono">AM</div>
                            <div>
                                <h5 class="text-sm font-semibold text-gray-900">Alejandro Martínez Solís</h5>
                                <p class="text-xs text-gray-400">Lic. Derecho • Reporte #2 (80 Horas)</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 self-end sm:self-auto">
                            <button class="text-gray-600 hover:bg-gray-100 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">Ver Detalles</button>
                            <button class="bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold shadow-theme-xs transition-colors">Firmar Reporte</button>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 border border-gray-100 rounded-xl hover:bg-gray-50/40 transition-all gap-3">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center text-xs font-bold font-mono">SV</div>
                            <div>
                                <h5 class="text-sm font-semibold text-gray-900">Sofía Villanueva Ramos</h5>
                                <p class="text-xs text-gray-400">Lic. Psicología • Reporte Inicial (40 Horas)</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 self-end sm:self-auto">
                            <button class="text-gray-600 hover:bg-gray-100 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">Ver Detalles</button>
                            <button class="bg-brand-600 hover:bg-brand-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold shadow-theme-xs transition-colors">Firmar Reporte</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs">
                <div class="mb-4">
                    <h4 class="text-base font-bold text-gray-900">Entrevistas y Candidatos</h4>
                    <p class="text-xs text-gray-500">Últimos estudiantes postulados a tus programas.</p>
                </div>

                <div class="divide-y divide-gray-100">
                    <div class="py-3.5 flex items-start gap-3">
                        <div class="mt-0.5 text-base">📩</div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-900">Carlos Eduardo Gómez</span>
                                <span class="text-[10px] bg-brand-50 text-brand-700 font-bold px-1.5 py-0.5 rounded">CV Adjunto</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Postulado a: Asistencia Jurídica Institucional</p>
                            <div class="mt-2.5 flex gap-1.5">
                                <button class="bg-brand-600 hover:bg-brand-700 text-white px-2.5 py-1 rounded text-[11px] font-semibold transition-colors">Agendar Cita</button>
                                <button class="text-error-600 hover:bg-error-50 px-2.5 py-1 rounded text-[11px] font-semibold transition-colors">Declinar</button>
                            </div>
                        </div>
                    </div>

                    <div class="py-3.5 flex items-start gap-3">
                        <div class="mt-0.5 text-base">📩</div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-900">Elena Rostova Ortiz</span>
                                <span class="text-[10px] bg-brand-50 text-brand-700 font-bold px-1.5 py-0.5 rounded">CV Adjunto</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Postulado a: Apoyo Psicosocial Comunitario</p>
                            <div class="mt-2.5 flex gap-1.5">
                                <button class="bg-brand-600 hover:bg-brand-700 text-white px-2.5 py-1 rounded text-[11px] font-semibold transition-colors">Agendar Cita</button>
                                <button class="text-error-600 hover:bg-error-50 px-2.5 py-1 rounded text-[11px] font-semibold transition-colors">Declinar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
@endsection