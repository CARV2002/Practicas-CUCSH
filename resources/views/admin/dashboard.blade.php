@extends('layouts.app')

@section('content')

<style>
/* ==========================================================================
   ESTILOS ADICIONALES: PANEL DE CONTROL ADMINISTRADOR
   ========================================================================== */

.admin-dashboard {
    font-family: var(--font-outfit);
}

/* Colores de marca dinámicos */
.text-brand-600 {
    color: var(--color-brand-600);
}
.bg-brand-50 {
    background-color: var(--color-brand-50);
}
.bg-brand-500 {
    background-color: var(--color-brand-500);
}
.bg-brand-600 {
    background-color: var(--color-brand-600);
}
.hover\:bg-brand-700:hover {
    background-color: var(--color-brand-700);
}

/* Alertas de color para estados y advertencias */
.bg-blue-light-50 {
    background-color: var(--color-blue-light-50);
}
.text-blue-light-600 {
    color: var(--color-blue-light-600);
}

.bg-error-50 {
    background-color: var(--color-error-50);
}
.text-error-600 {
    color: var(--color-error-600);
}

.bg-warning-50 {
    background-color: var(--color-warning-50);
}
.text-warning-600 {
    color: var(--color-warning-600);
}
.text-warning-700 {
    color: var(--color-warning-700);
}
.border-warning-100 {
    border-color: var(--color-warning-100);
}

.bg-success-50 {
    background-color: var(--color-success-50);
}
.text-success-700 {
    color: var(--color-success-700);
}
.border-success-100 {
    border-color: var(--color-success-100);
}


</style>
    <x-common.page-breadcrumb pageTitle="Panel de Control Central" />

    <div class="space-y-6 admin-dashboard">
        
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Alumnos Activos</p>
                    <h3 class="mt-1 text-2xl font-bold text-gray-900">1,240</h3>
                    <span class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-success-600">
                        ▲ 4.8% <span class="text-gray-400 font-normal">este mes</span>
                    </span>
                </div>
                <div class="h-12 w-12 bg-brand-50 rounded-xl flex items-center justify-center text-brand-600 text-xl font-bold">🎓</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Empresas con Convenio</p>
                    <h3 class="mt-1 text-2xl font-bold text-gray-900">184</h3>
                    <span class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-success-600">
                        ▲ 12 nuevas <span class="text-gray-400 font-normal">este ciclo</span>
                    </span>
                </div>
                <div class="h-12 w-12 bg-blue-light-50 rounded-xl flex items-center justify-center text-blue-light-600 text-xl font-bold">🏢</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Convenios por Vencer</p>
                    <h3 class="mt-1 text-2xl font-bold text-error-600">8</h3>
                    <span class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-error-600">
                        ⚠️ Requieren renovación
                    </span>
                </div>
                <div class="h-12 w-12 bg-error-50 rounded-xl flex items-center justify-center text-error-600 text-xl font-bold">📄</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ocupación de Plazas</p>
                    <h3 class="mt-1 text-2xl font-bold text-gray-900">76%</h3>
                    <div class="w-24 bg-gray-200 rounded-full h-1.5 mt-3">
                        <div class="bg-brand-500 h-1.5 rounded-full" style="width: 76%"></div>
                    </div>
                </div>
                <div class="h-12 w-12 bg-warning-50 rounded-xl flex items-center justify-center text-warning-600 text-xl font-bold">📊</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            
            <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="text-base font-bold text-gray-900">Estado de Programas y Ofertas</h4>
                        <p class="text-xs text-gray-500">Monitoreo de vacantes publicadas por las empresas vinculadas.</p>
                    </div>
                    <a href="#" class="text-xs font-semibold text-brand-600 hover:underline">Ver todas las ofertas</a>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:bg-gray-50/50 transition-all">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">⚖️</span>
                            <div>
                                <h5 class="text-sm font-semibold text-gray-900">Asistente Jurídico - Suprema Corte</h5>
                                <p class="text-xs text-gray-400">Derecho • 15 plazas disponibles</p>
                            </div>
                        </div>
                        <span class="bg-success-50 text-success-700 px-2.5 py-1 rounded-full text-xs font-bold border border-success-100">Activo</span>
                    </div>

                    <div class="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:bg-gray-50/50 transition-all">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">📊</span>
                            <div>
                                <h5 class="text-sm font-semibold text-gray-900">Analista de Políticas Públicas - Gob</h5>
                                <p class="text-xs text-gray-400">Estudios Políticos • 4 plazas disponibles</p>
                            </div>
                        </div>
                        <span class="bg-success-50 text-success-700 px-2.5 py-1 rounded-full text-xs font-bold border border-success-100">Activo</span>
                    </div>

                    <div class="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:bg-gray-50/50 transition-all">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">🌍</span>
                            <div>
                                <h5 class="text-sm font-semibold text-gray-900">Coordinador de Proyectos - ONU México</h5>
                                <p class="text-xs text-gray-400">Sociología • 0 plazas disponibles</p>
                            </div>
                        </div>
                        <span class="bg-warning-50 text-warning-700 px-2.5 py-1 rounded-full text-xs font-bold border border-warning-100">Cupo Lleno</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs">
                <div class="mb-4">
                    <h4 class="text-base font-bold text-gray-900">Dictámenes y Validaciones Pendientes</h4>
                    <p class="text-xs text-gray-500">Documentación que requiere autorización inmediata del Administrador.</p>
                </div>

                <div class="divide-y divide-gray-100">
                    <div class="py-3 flex items-start gap-3">
                        <div class="mt-0.5 text-lg">📝</div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-900">Convenio: IBM México</span>
                                <span class="text-[10px] text-gray-400 font-medium">Hace 2h</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Falta firma digital institucional del dictamen de validación.</p>
                            <div class="mt-2 flex gap-2">
                                <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-2.5 py-1 rounded text-[11px] transition-colors">Validar</button>
                                <button class="text-gray-500 hover:bg-gray-100 px-2.5 py-1 rounded text-[11px] font-medium transition-colors">Revisar</button>
                            </div>
                        </div>
                    </div>

                    <div class="py-3 flex items-start gap-3">
                        <div class="mt-0.5 text-lg">🏢</div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-900">Nueva Solicitud: Notaría 44</span>
                                <span class="text-[10px] text-gray-400 font-medium">Ayer</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Empresa solicita apertura de cuenta receptora externa.</p>
                            <div class="mt-2 flex gap-2">
                                <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-2.5 py-1 rounded text-[11px] transition-colors">Aprobar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
@endsection