@extends('layouts.app')

@section('content')

<style>
    /* ==========================================================================
   ESTILOS ADICIONALES: CONVENIO INSTITUCIONAL (VISTA EMPRESA)
   ========================================================================== */

.company-agreement-container {
    font-family: var(--font-outfit);
}

/* Identidad Visual de Marca */
.bg-brand-50 {
    background-color: var(--color-brand-50);
}
.text-brand-700 {
    color: var(--color-brand-700);
}
.bg-brand-600 {
    background-color: var(--color-brand-600);
}
.hover\:bg-brand-700:hover {
    background-color: var(--color-brand-700);
}

/* Modificadores Semánticos de Validación de la Universidad */
.bg-success-50 {
    background-color: var(--color-success-50);
}
.text-success-700 {
    color: var(--color-success-700);
}
.border-success-100 {
    border-color: var(--color-success-100);
}

/* Alertas de Vencimiento / Advertencias */
.bg-warning-50 {
    background-color: var(--color-warning-50);
}
.text-warning-700 {
    color: var(--color-warning-600);
}
.border-warning-100 {
    border-color: var(--color-warning-100);
}
.bg-amber-50\/40 {
    background-color: rgba(254, 243, 199, 0.4);
}
.border-amber-200 {
    border-color: #fde68a;
}

/* Error / PDF */
.text-error-600 {
    color: var(--color-error-600);
}


</style>
    <x-common.page-breadcrumb pageTitle="Convenio Institucional CUCSH" />

    <div x-data="{
        convenio: {
            clave: 'CUCSH-CONV-2024-042',
            estatus: 'Vigente', /* Puede ser: Vigente, Por Vencer, Vencido */
            fechaInicio: '15 de Enero, 2024',
            fechaTermino: '15 de Enero, 2028',
            diasRestantes: 560,
            firmanteEmpresa: 'Lic. Claudia Ruiz Massieu (Apoderada Legal)',
            firmanteUdg: 'Dr. Juan Manuel Durán Juárez (Rector de Centro)',
            tipoConvenio: 'General de Colaboración para Prácticas y Servicio Social',
            documentoUrl: '#',
            solicitudRenovacionEnCurso: false
        }
    }" class="company-agreement-container space-y-6">

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs">
            
            <div class="flex flex-col gap-4 border-b border-gray-100 pb-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="font-mono text-xs font-bold text-gray-500 bg-gray-100 border border-gray-200 px-2.5 py-1 rounded-md" x-text="convenio.clave"></span>
                    <h3 class="text-xl font-bold text-gray-900 tracking-tight mt-2" x-text="convenio.tipoConvenio"></h3>
                    <p class="text-xs text-gray-400 mt-1">Instrumento jurídico vinculante celebrado con la Universidad de Guadalajara (CUCSH).</p>
                </div>

                <div class="self-start sm:self-auto">
                    <template x-if="convenio.estatus === 'Vigente'">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 border border-success-100 px-3 py-1 text-xs font-bold text-success-700">
                            <span class="h-2 w-2 rounded-full bg-success-600"></span> Convenio Activo
                        </span>
                    </template>
                    <template x-if="convenio.estatus === 'Por Vencer'">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-50 border border-warning-100 px-3 py-1 text-xs font-bold text-warning-700 animate-pulse">
                            <span class="h-2 w-2 rounded-full bg-warning-600"></span> Próximo a Vencer
                        </span>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 py-6 md:grid-cols-2 lg:grid-cols-3 border-b border-gray-100">
                
                <div class="space-y-4">
                    <div>
                        <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Fecha de Inicio / Firma</h4>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5" x-text="convenio.fechaInicio"></p>
                    </div>
                    <div>
                        <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Fecha de Término</h4>
                        <p class="text-sm font-semibold text-gray-900 mt-0.5 flex items-center gap-2">
                            <span x-text="convenio.fechaTermino"></span>
                            <span class="text-xs bg-brand-50 text-brand-700 font-bold px-1.5 py-0.5 rounded" x-text="convenio.diasRestantes + ' días restantes'"></span>
                        </p>
                    </div>
                </div>

                <div class="space-y-4 lg:col-span-2">
                    <div>
                        <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Representante Institucional (Empresa)</h4>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5" x-text="convenio.firmanteEmpresa"></p>
                    </div>
                    <div>
                        <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Autoridad Universitaria Concurrente</h4>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5" x-text="convenio.firmanteUdg"></p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between pt-5 gap-4">
                <div class="flex items-center gap-2 text-xs text-gray-500 font-medium">
                    <svg class="text-gray-400 shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Validado digitalmente por la Oficina de Convenios del CUCSH
                </div>
                
                <a :href="convenio.documentoUrl" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-theme-xs hover:bg-gray-50 transition-colors">
                    <svg class="text-error-600" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Descargar Documento Original (PDF)
                </a>
            </div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-6 shadow-theme-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
            <div class="flex items-start gap-3.5">
                <div class="h-10 w-10 bg-warning-50 text-warning-700 border border-warning-100 rounded-xl flex items-center justify-center text-lg font-bold shrink-0">⏳</div>
                <div>
                    <h4 class="text-sm font-bold text-gray-900">¿Tu convenio está por cumplir su ciclo?</h4>
                    <p class="text-xs text-gray-500 mt-1 max-w-[580px]">
                        Para evitar la suspensión automática de tus programas activos y asegurar que los alumnos sigan computando horas, te sugerimos iniciar el trámite de renovación 60 días antes de la fecha límite.
                    </p>
                </div>
            </div>

            <div class="shrink-0 w-full md:w-auto flex justify-end">
                <button 
                    @click="convenio.solicitudRenovacionEnCurso = true; alert('Solicitud de pre-renovación enviada al Administrador General del CUCSH.')"
                    :disabled="convenio.solicitudRenovacionEnCurso"
                    :class="convenio.solicitudRenovacionEnCurso ? 'bg-gray-300 text-gray-600 border-gray-300 cursor-not-allowed' : 'bg-brand-600 text-white hover:bg-brand-700'"
                    class="w-full md:w-auto inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-semibold shadow-theme-xs transition-colors">
                    <span x-text="convenio.solicitudRenovacionEnCurso ? 'Solicitud en Revisión' : 'Iniciar Trámite de Renovación'"></span>
                </button>
            </div>
        </div>

    </div>
@endsection