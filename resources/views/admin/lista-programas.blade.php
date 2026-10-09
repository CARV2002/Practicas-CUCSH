@extends('layouts.app')

@section('content')

<style>
/* ==========================================================================
   ESTILOS ADICIONALES: LISTA DE PROGRAMAS (ADMIN)
   ========================================================================== */

.programs-admin-container {
    font-family: var(--font-outfit);
}

/* Manejo de Tokens y colores corporativos */
.bg-brand-500 {
    background-color: var(--color-brand-500);
}
.bg-brand-600 {
    background-color: var(--color-brand-600);
}
.hover\:bg-brand-700:hover {
    background-color: var(--color-brand-700);
}
.border-brand-600 {
    border-color: var(--color-brand-600);
}
.bg-brand-600 {
    background-color: var(--color-brand-600);
}
.focus\:border-brand-500:focus {
    border-color: var(--color-brand-500);
}
.focus\:ring-brand-500:focus {
    --tw-ring-color: var(--color-brand-500);
}

/* Modificadores de Alertas y Estatus Operativos */
.bg-success-50 { background-color: var(--color-success-50); }
.text-success-700 { color: var(--color-success-700); }
.border-success-100 { border-color: var(--color-success-100); }

.bg-warning-50 { background-color: var(--color-warning-50); }
.text-warning-700 { color: var(--color-warning-700); }
.border-warning-100 { border-color: var(--color-warning-100); }

.text-error-600 { color: var(--color-error-600); }
.hover\:text-error-600:hover { color: var(--color-error-600); }

</style>
<div x-data="{
    // Datos reales adaptados del ecosistema del template y la vista del estudiante
    tableRowData: [
        {
            id: 'PROG-2026-001',
            checked: false,
            title: 'Asistencia y Consultoría Jurídica Gratuita',
            company: 'Bufete Jurídico Universitario',
            initials: 'BJ',
            avatarBg: 'bg-blue-50',
            avatarColor: 'text-blue-600',
            careers: ['Derecho'],
            slotsUsed: 12,
            slotsTotal: 15,
            modality: 'Presencial',
            status: 'Abierto',
        },
        {
            id: 'PROG-2026-002',
            checked: false,
            title: 'Apoyo Psicosocial a Comunidades Vulnerables',
            company: 'DIF Zapopan',
            initials: 'DZ',
            avatarBg: 'bg-purple-50',
            avatarColor: 'text-purple-600',
            careers: ['Psicología', 'Trabajo Social'],
            slotsUsed: 8,
            slotsTotal: 8,
            modality: 'Híbrido',
            status: 'Lleno',
        },
        {
            id: 'PROG-2026-003',
            checked: false,
            title: 'Análisis Legislativo y Parlamento Abierto',
            company: 'Congreso del Estado de Jalisco',
            initials: 'CE',
            avatarBg: 'bg-amber-50',
            avatarColor: 'text-amber-600',
            careers: ['Estudios Políticos', 'Derecho'],
            slotsUsed: 2,
            slotsTotal: 5,
            modality: 'Presencial',
            status: 'Abierto',
        },
        {
            id: 'PROG-2026-004',
            checked: false,
            title: 'Digitalización de Archivos Históricos',
            company: 'Archivo General del Estado',
            initials: 'AG',
            avatarBg: 'bg-green-50',
            avatarColor: 'text-green-600',
            careers: ['Historia'],
            slotsUsed: 0,
            slotsTotal: 4,
            modality: 'Virtual',
            status: 'Borrador',
        }
    ],
    selectedRows: [],
    selectAll: false,
    filterStatus: 'Todos',
    filterCareer: 'Todas',
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
            'Abierto': 'bg-success-50 text-success-700 border border-success-100',
            'Lleno': 'bg-warning-50 text-warning-700 border border-warning-100',
            'Borrador': 'bg-gray-100 text-gray-600 border border-gray-200',
        };
        return classes[status] || '';
    },
    deleteRow(id) {
        if (confirm('¿Está seguro de que desea eliminar este programa? Se perderán las vinculaciones asociadas.')) {
            this.tableRowData = this.tableRowData.filter(row => row.id !== id);
            this.selectedRows = this.selectedRows.filter(rowId => rowId !== id);
        }
    }
}" class="programs-admin-container">

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5 shadow-theme-xs">
        
        <div class="flex flex-col gap-4 px-6 mb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">Programas de Prácticas y Servicio</h3>
                <p class="text-xs text-gray-500 mt-0.5">Control centralizado de ofertas institucionales y asignación de plazas.</p>
            </div>
            
            <div class="flex items-center gap-2.5 self-end sm:self-auto">
                <button @click="showFilters = !showFilters" :class="showFilters ? 'bg-gray-100 border-gray-400 text-gray-900' : 'bg-white text-gray-700'" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-xs font-semibold shadow-theme-xs hover:bg-gray-50 transition-all">
                    <svg class="stroke-current" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    Filtros Avanzados
                </button>
                <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-theme-xs hover:bg-brand-700 transition-colors">
                    + Crear Programa
                </button>
            </div>
        </div>

        <div x-show="showFilters" x-transition class="px-6 py-4 border-t border-b border-gray-100 bg-gray-50/50 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Licenciatura / Carrera</label>
                <select x-model="filterCareer" class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value="Todas">Todas las carreras</option>
                    <option value="Derecho">Derecho</option>
                    <option value="Psicología">Psicología</option>
                    <option value="Trabajo Social">Trabajo Social</option>
                    <option value="Estudios Políticos">Estudios Políticos</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Estatus Operativo</label>
                <select x-model="filterStatus" class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value="Todos">Todos los estatus</option>
                    <option value="Abierto">Abierto</option>
                    <option value="Lleno">Lleno</option>
                    <option value="Borrador">Borrador</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Búsqueda rápida</label>
                <input type="text" placeholder="Buscar por título o empresa..." class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"/>
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
                                <span>Clave ID</span>
                            </div>
                        </th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Programa / Institución Receptora</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Licenciaturas Válidas</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Modalidad</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Ocupación Plazas</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Estado</th>
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
                            <td class="px-4 py-4 max-w-[280px]">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-9 h-9 rounded-lg font-bold text-xs shrink-0 shadow-sm border border-gray-100"
                                        :class="[row.avatarBg, row.avatarColor]">
                                        <span x-text="row.initials"></span>
                                    </div>
                                    <div class="truncate">
                                        <span class="block text-sm font-semibold text-gray-900 truncate" x-text="row.title"></span>
                                        <span class="text-xs text-gray-500 font-medium" x-text="row.company"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1 max-w-[200px]">
                                    <template x-for="career in row.careers">
                                        <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-1.5 py-0.5 rounded border border-blue-100" x-text="career"></span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-600" x-text="row.modality"></span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-800" x-text="row.slotsUsed + '/' + row.slotsTotal"></span>
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
                                    <button class="p-1 text-gray-400 hover:text-brand-600 rounded hover:bg-gray-50 transition-colors" title="Editar Programa">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    </button>
                                    <button @click="deleteRow(row.id)" class="p-1 text-gray-400 hover:text-error-600 rounded hover:bg-gray-50 transition-colors" title="Eliminar">
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