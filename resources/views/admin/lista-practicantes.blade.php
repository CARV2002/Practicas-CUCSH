

@extends('layouts.app')

@section('content')

<style>
/* ==========================================================================
   COMPONENTE: TABLA DE CONTROL DE PRACTICANTES CUCSH
   ========================================================================== */

.academic-table-container {
    font-family: var(--font-outfit);
    background-color: transparent;
    padding: 12px 0;
}

/* Modificadores de la barra de progreso dentro de celdas */
.academic-table-container .bg-brand-500 {
    background-color: var(--color-brand-500);
}

.academic-table-container .text-brand-600 {
    color: var(--color-brand-600);
}

/* Tratamiento de microinteracciones en las filas */
.academic-table-container table tbody tr {
    transition: background-color 0.15s ease;
}

.academic-table-container table tbody tr:hover {
    background-color: rgba(var(--color-brand-50), 0.2) !important;
}

/* Ajustes semánticos de alertas */
.bg-error-50 { background-color: var(--color-error-50); }
.text-error-600 { color: var(--color-error-600); }
.text-error-700 { color: var(--color-error-700); }
.border-error-200 { border-color: var(--color-error-200); }

.bg-success-50 { background-color: var(--color-success-50); }
.text-success-600 { color: var(--color-success-600); }
.text-success-700 { color: var(--color-success-700); }
.border-success-200 { border-color: var(--color-success-200); }

.bg-brand-50 { background-color: var(--color-brand-50); }
.text-brand-700 { color: var(--color-brand-700); }
.border-brand-200 { border-color: var(--color-brand-200); }


</style>
<div x-data="{
    tableRowData: [
        {
            id: '219485722',
            checked: false,
            studentName: 'Annete Black',
            studentEmail: 'annete.black@alumnos.udeg.mx',
            initials: 'AB',
            avatarBg: 'bg-brand-50',
            avatarColor: 'text-brand-600',
            company: 'Administradora Inicial C.A.',
            hoursApproved: 240,
            totalHours: 480,
            lastUpdate: 'Hace 10 min',
            status: 'Activo',
        },
        {
            id: '215394821',
            checked: false,
            studentName: 'Chance Philips',
            studentEmail: 'chance.philips@alumnos.udeg.mx',
            initials: 'CP',
            avatarBg: 'bg-error-50',
            avatarColor: 'text-error-600',
            company: 'Buffer Design Studio',
            hoursApproved: 110,
            totalHours: 480,
            lastUpdate: 'Hace 2 días',
            status: 'En Riesgo',
        },
        {
            id: '218304951',
            checked: false,
            studentName: 'Esther Howard',
            studentEmail: 'esther.h@alumnos.udeg.mx',
            initials: 'EH',
            avatarBg: 'bg-success-50',
            avatarColor: 'text-success-600',
            company: 'Hacienda Pública Estatal',
            hoursApproved: 480,
            totalHours: 480,
            lastUpdate: 'Ayer',
            status: 'Completado',
        },
        {
            id: '220493811',
            checked: false,
            studentName: 'Kristin Watson',
            studentEmail: 'kristin.w@alumnos.udeg.mx',
            initials: 'KW',
            avatarBg: 'bg-brand-50',
            avatarColor: 'text-brand-600',
            company: 'Bufete Jurídico CUCSH',
            hoursApproved: 315,
            totalHours: 480,
            lastUpdate: 'Hace 4 horas',
            status: 'Activo',
        }
    ],
    selectedRows: [],
    selectAll: false,
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
            'Activo': 'bg-brand-50 text-brand-700 border border-brand-200',
            'En Riesgo': 'bg-error-50 text-error-700 border border-error-200',
            'Completado': 'bg-success-50 text-success-700 border border-success-200',
        };
        return classes[status] || 'bg-gray-50 text-gray-700';
    },
    getProgressPercentage(approved, total) {
        return Math.min(Math.round((approved / total) * 100), 100);
    }
}" class="academic-table-container">

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs pt-5">
        
        <div class="flex flex-col gap-4 px-6 mb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">
                    Seguimiento Activo de Practicantes
                </h3>
                <p class="text-sm text-gray-500 mt-0.5">Control de horas acumuladas y estatus de reportes bimestrales.</p>
            </div>
            <div class="flex items-center gap-3">
                <button class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filtrar Estatus
                </button>
                <button class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 transition-colors">
                    Exportar Reporte
                </button>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-y border-gray-100 bg-gray-50/70">
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">
                            <div class="flex items-center gap-3">
                                <div @click="handleSelectAll()"
                                    class="flex h-4 w-4 cursor-pointer items-center justify-center rounded border transition-all"
                                    :class="selectAll ? 'border-brand-600 bg-brand-600' : 'bg-white border-gray-300'">
                                    <svg :class="selectAll ? 'block' : 'hidden'" width="10" height="10" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M11.6668 3.5L5.25016 9.91667L2.3335 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <span>Código UdeG</span>
                            </div>
                        </th>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Practicante</th>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Institución Receptora</th>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Progreso de Horas</th>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Última Actividad</th>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Estatus</th>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="row in tableRowData" :key="row.id">
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div @click="handleRowSelect(row.id)"
                                        class="flex h-4 w-4 cursor-pointer items-center justify-center rounded border transition-all"
                                        :class="selectedRows.includes(row.id) ? 'border-brand-600 bg-brand-600' : 'bg-white border-gray-300'">
                                        <svg :class="selectedRows.includes(row.id) ? 'block' : 'hidden'" width="10" height="10" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.6668 3.5L5.25016 9.91667L2.3335 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <span class="font-mono text-sm font-medium text-gray-600" x-text="row.id"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-9 h-9 rounded-full font-bold text-xs shadow-inner"
                                        :class="[row.avatarBg, row.avatarColor]">
                                        <span x-text="row.initials"></span>
                                    </div>
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-900" x-text="row.studentName"></span>
                                        <span class="text-xs text-gray-500" x-text="row.studentEmail"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-700" x-text="row.company"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-48">
                                    <div class="flex justify-between items-baseline mb-1">
                                        <span class="text-sm font-bold text-gray-900">
                                            <span x-text="row.hoursApproved"></span>
                                            <span class="text-xs text-gray-400 font-normal">/ <span x-text="row.totalHours"></span> h</span>
                                        </span>
                                        <span class="text-xs font-semibold text-brand-600" x-text="getProgressPercentage(row.hoursApproved, row.totalHours) + '%'"></span>
                                    </div>
                                    <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand-500 rounded-full transition-all duration-500" 
                                             :style="'width: ' + getProgressPercentage(row.hoursApproved, row.totalHours) + '%'"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-600 font-medium" x-text="row.lastUpdate"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-xs inline-flex items-center px-2.5 py-1 rounded-full font-semibold transition-all" 
                                    :class="getStatusClass(row.status)" 
                                    x-text="row.status"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="inline-flex gap-2">
                                    <button class="p-1.5 text-gray-500 hover:text-brand-600 hover:bg-brand-25 rounded-md transition-all" title="Validar Bitácora">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                    </button>
                                    <button class="p-1.5 text-gray-500 hover:text-error-600 hover:bg-error-25 rounded-md transition-all" title="Incidencia / Retraso">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
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
