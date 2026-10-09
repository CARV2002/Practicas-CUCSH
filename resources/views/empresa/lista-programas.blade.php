@extends('layouts.app')

@section('content')

<style>
/* ==========================================================================
   ESTILOS ADICIONALES: MIS PROGRAMAS (VISTA EMPRESA)
   ========================================================================== */

.company-programs-container {
    font-family: var(--font-outfit);
}

/* Clases de Identidad Core */
.bg-brand-500 { background-color: var(--color-brand-500); }
.bg-brand-600 { background-color: var(--color-brand-600); }
.hover\:bg-brand-700:hover { background-color: var(--color-brand-700); }
.border-brand-600 { border-color: var(--color-brand-600); }
.focus\:border-brand-500:focus { border-color: var(--color-brand-500); }
.focus\:ring-brand-500:focus { --tw-ring-color: var(--color-brand-500); }

/* Semántica y estados de la Universidad */
.bg-success-50 { background-color: var(--color-success-50); }
.text-success-700 { color: var(--color-success-700); }
.border-success-100 { border-color: var(--color-success-100); }

.bg-warning-50 { background-color: var(--color-warning-50); }
.text-warning-700 { color: var(--color-warning-700); }
.border-warning-100 { border-color: var(--color-warning-100); }

.bg-blue-50 { background-color: var(--color-blue-light-50); }
.text-blue-700 { color: var(--color-blue-light-600); }
.border-blue-100 { border-color: var(--color-blue-light-100); }

.text-error-600 { color: var(--color-error-600); }
.hover\:text-error-600:hover { color: var(--color-error-600); }

</style>

<div x-data="{
    // Listado de programas creados exclusivamente por esta organización
    tableRowData: [
        {
            id: 'PROG-081',
            checked: false,
            title: 'Asistencia y Consultoría Jurídica Gratuita',
            careers: ['Derecho'],
            slotsUsed: 12,
            slotsTotal: 15,
            modality: 'Presencial',
            dateCreated: '2026-01-10',
            status: 'Ofertado',
        },
        {
            id: 'PROG-094',
            checked: false,
            title: 'Apoyo Psicosocial a Comunidades Vulnerables',
            careers: ['Psicología', 'Trabajo Social'],
            slotsUsed: 8,
            slotsTotal: 8,
            modality: 'Híbrido',
            dateCreated: '2026-02-14',
            status: 'Lleno',
        },
        {
            id: 'PROG-102',
            checked: false,
            title: 'Mediación Penal y Solución de Conflictos',
            careers: ['Derecho'],
            slotsUsed: 0,
            slotsTotal: 5,
            modality: 'Presencial',
            dateCreated: '2026-06-20',
            status: 'Pendiente',
        },
        {
            id: 'PROG-115',
            checked: false,
            title: 'Taller de Desarrollo Organizacional y Clima Laboral',
            careers: ['Psicología'],
            slotsUsed: 0,
            slotsTotal: 4,
            modality: 'Virtual',
            dateCreated: '2026-07-02',
            status: 'Borrador',
        }
    ],
    selectedRows: [],
    selectAll: false,
    filterStatus: 'Todos',
    showFilters: false,

    handleSelectAll() {
        this.selectAll = !this.selectAll;
        if (this.selectAll) {
            this.selectedRows = this.tableRowData.map(row => row.id);
        } else {
            this.selectedRows = [];
        }
    },
    handleRowSelect(id) {
        if (this.selectedRows.includes(id)) {
            this.selectedRows = this.selectedRows.filter(rowId => rowId !== id);
        } else {
            this.selectedRows.push(id);
        }
    },
    getStatusClass(status) {
        const classes = {
            'Ofertado': 'bg-success-50 text-success-700 border border-success-100',
            'Lleno': 'bg-blue-50 text-blue-700 border border-blue-100',
            'Pendiente': 'bg-warning-50 text-warning-700 border border-warning-100',
            'Borrador': 'bg-gray-100 text-gray-600 border border-gray-200',
        };
        return classes[status] || '';
    },
    deleteRow(id) {
        if (confirm('¿Deseas eliminar este programa? Si está Ofertado o Pendiente, se notificará a la administración.')) {
            this.tableRowData = this.tableRowData.filter(row => row.id !== id);
            this.selectedRows = this.selectedRows.filter(rowId => rowId !== id);
        }
    }
}" class="company-programs-container">

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5 shadow-theme-xs">
        
        <div class="flex flex-col gap-4 px-6 mb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">Nuestros Programas Ofertados</h3>
                <p class="text-xs text-gray-500 mt-0.5">Administra las vacantes, consulta aprobaciones institucionales y añade nuevas propuestas de plazas.</p>
            </div>
            
            <div class="flex items-center gap-2.5 self-end sm:self-auto">
                <button @click="showFilters = !showFilters" :class="showFilters ? 'bg-gray-100 border-gray-400 text-gray-900' : 'bg-white text-gray-700'" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-xs font-semibold shadow-theme-xs hover:bg-gray-50 transition-all">
                    <svg class="stroke-current" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    Filtrar
                </button>
                <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-theme-xs hover:bg-brand-700 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Crear Nuevo Programa
                </button>
            </div>
        </div>

        <div x-show="showFilters" x-transition class="px-6 py-4 border-t border-b border-gray-100 bg-gray-50/50 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Estado Institucional</label>
                <select x-model="filterStatus" class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value="Todos">Todos los estados</option>
                    <option value="Ofertado">Ofertado (Activo)</option>
                    <option value="Lleno">Lleno (Cupo Completo)</option>
                    <option value="Pendiente">Pendiente de Aprobación</option>
                    <option value="Borrador">Borrador</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Buscar por Título</label>
                <input type="text" placeholder="Escribe el nombre del programa..." class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"/>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="border-t border-y border-gray-100 bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">
                            <div class="flex items-center gap-3">
                                <div @click="handleSelectAll()"
                                    class="flex h-4 w-4 cursor-pointer items-center justify-center rounded border"
                                    :class="selectAll ? 'border-brand-600 bg-brand-600' : 'bg-white border-gray-300'">
                                    <svg :class="selectAll ? 'block' : 'hidden'" width="10" height="10" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M11.6668 3.5L5.25016 9.91667L2.3335 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <span>ID Ref</span>
                            </div>
                        </th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Título del Programa</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Licenciaturas Perfiladas</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Modalidad</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Alta</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Cupos Asignados</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Estatus</th>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="row in tableRowData" :key="row.id">
                        <tr class="hover:bg-gray-50/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div @click="handleRowSelect(row.id)"
                                        class="flex h-4 w-4 cursor-pointer items-center justify-center rounded border"
                                        :class="selectedRows.includes(row.id) ? 'border-brand-600 bg-brand-600' : 'bg-white border-gray-300'">
                                        <svg :class="selectedRows.includes(row.id) ? 'block' : 'hidden'" width="10" height="10" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.6668 3.5L5.25016 9.91667L2.3335 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <span class="font-mono text-xs font-bold text-gray-600 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded" x-text="row.id"></span>
                                </div>
                            </td>
                            <td class="px-4 py-4 max-w-[260px]">
                                <span class="block text-sm font-semibold text-gray-900 truncate" :title="row.title" x-text="row.title"></span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1 max-w-[180px]">
                                    <template x-for="career in row.careers">
                                        <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-1.5 py-0.5 rounded border border-gray-200" x-text="career"></span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-600" x-text="row.modality"></span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-xs text-gray-500 font-medium" x-text="row.dateCreated"></span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-800" x-text="row.slotsUsed + ' / ' + row.slotsTotal"></span>
                                    <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                        <div class="bg-brand-500 h-1.5 rounded-full" :style="'width: ' + ((row.slotsUsed / row.slotsTotal) * 100) + '%'"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-[11px] inline-block rounded-full px-2.5 py-0.5 font-bold" 
                                    :class="getStatusClass(row.status)" 
                                    x-text="row.status"></span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <button class="p-1 text-gray-400 hover:text-brand-600 rounded hover:bg-gray-50 transition-colors" title="Editar">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    </button>
                                    <button @click="deleteRow(row.id)" class="p-1 text-gray-400 hover:text-error-600 rounded hover:bg-gray-50 transition-colors" title="Eliminar u Ocultar">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection