@extends('layouts.app')

@section('content')
<style>
    /* ==========================================================================
   ESTILOS ADICIONALES: LISTA DE PRACTICANTES POR EMPRESA
   ========================================================================== */

.company-students-container {
    font-family: var(--font-outfit);
}

/* Tokens de marca del Core */
.bg-brand-50 { background-color: var(--color-brand-50); }
.text-brand-600 { color: var(--color-brand-600); }
.bg-brand-500 { background-color: var(--color-brand-500); }
.bg-brand-600 { background-color: var(--color-brand-600); }
.border-brand-600 { border-color: var(--color-brand-600); }
.focus\:border-brand-500:focus { border-color: var(--color-brand-500); }
.focus\:ring-brand-500:focus { --tw-ring-color: var(--color-brand-500); }

/* Alertas y Modificadores Semánticos de Estado */
.bg-success-50 { background-color: var(--color-success-50); }
.text-success-700 { color: var(--color-success-700); }
.border-success-100 { border-color: var(--color-success-100); }

.bg-error-50 { background-color: var(--color-error-50); }
.text-error-700 { color: var(--color-error-700); }
.border-error-100 { border-color: var(--color-error-100); }

.bg-blue-50 { background-color: var(--color-blue-light-50); }
.text-blue-700 { color: var(--color-blue-light-600); }
.border-blue-100 { border-color: var(--color-blue-light-100); }

</style>
<div x-data="{
    // Datos de alumnos vinculados a los programas específicos de la empresa
    tableRowData: [
        {
            id: 'AL-2024091',
            checked: false,
            studentName: 'Alejandro Martínez Solís',
            studentEmail: 'alejandro.martinez@alumnos.udg.mx',
            initials: 'AM',
            avatarBg: 'bg-brand-50',
            avatarColor: 'text-brand-600',
            programName: 'Asistencia y Consultoría Jurídica Gratuita',
            type: 'Prácticas',
            hoursLogged: 240,
            hoursTotal: 480,
            status: 'Activo',
        },
        {
            id: 'AL-2024105',
            checked: false,
            studentName: 'Sofía Villanueva Ramos',
            studentEmail: 'sofia.villanueva@alumnos.udg.mx',
            initials: 'SV',
            avatarBg: 'bg-purple-50',
            avatarColor: 'text-purple-600',
            programName: 'Apoyo Psicosocial a Comunidades Vulnerables',
            type: 'Servicio Social',
            hoursLogged: 180,
            hoursTotal: 300,
            status: 'Activo',
        },
        {
            id: 'AL-2023542',
            checked: false,
            studentName: 'Carlos Eduardo Gómez',
            studentEmail: 'carlos.gomez@alumnos.udg.mx',
            initials: 'CG',
            avatarBg: 'bg-blue-50',
            avatarColor: 'text-blue-600',
            programName: 'Asistencia y Consultoría Jurídica Gratuita',
            type: 'Prácticas',
            hoursLogged: 480,
            hoursTotal: 480,
            status: 'Completado',
        },
        {
            id: 'AL-2025112',
            checked: false,
            studentName: 'Elena Rostova Ortiz',
            studentEmail: 'elena.rostova@alumnos.udg.mx',
            initials: 'ER',
            avatarBg: 'bg-amber-50',
            avatarColor: 'text-amber-600',
            programName: 'Análisis Legislativo y Parlamento Abierto',
            type: 'Servicio Social',
            hoursLogged: 12,
            hoursTotal: 300,
            status: 'Baja',
        }
    ],
    selectedRows: [],
    selectAll: false,
    filterProgram: 'Todos',
    filterType: 'Todos',
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
            'Activo': 'bg-success-50 text-success-700 border border-success-100',
            'Completado': 'bg-blue-50 text-blue-700 border border-blue-100',
            'Baja': 'bg-error-50 text-error-700 border border-error-100',
        };
        return classes[status] || '';
    },
    getTypeClass(type) {
        return type === 'Prácticas' 
            ? 'bg-gray-100 text-gray-700 font-semibold text-[11px] px-2 py-0.5 rounded border border-gray-200' 
            : 'bg-indigo-50 text-indigo-700 font-semibold text-[11px] px-2 py-0.5 rounded border border-indigo-100';
    }
}" class="company-students-container">

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-5 shadow-theme-xs">
        
        <div class="flex flex-col gap-4 px-6 mb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">Plantilla de Practicantes Asignados</h3>
                <p class="text-xs text-gray-500 mt-0.5">Monitoreo de asistencia, asignación de proyectos y validación de horas acumuladas.</p>
            </div>
            
            <div class="flex items-center gap-2.5 self-end sm:self-auto">
                <button @click="showFilters = !showFilters" :class="showFilters ? 'bg-gray-100 border-gray-400 text-gray-900' : 'bg-white text-gray-700'" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-xs font-semibold shadow-theme-xs hover:bg-gray-50 transition-all">
                    <svg class="stroke-current" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    Filtrar Lista
                </button>
            </div>
        </div>

        <div x-show="showFilters" x-transition class="px-6 py-4 border-t border-b border-gray-100 bg-gray-50/50 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Programa de la Empresa</label>
                <select x-model="filterProgram" class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value="Todos">Todos los programas</option>
                    <option value="Asistencia">Asistencia y Consultoría Jurídica</option>
                    <option value="Apoyo">Apoyo Psicosocial</option>
                    <option value="Análisis">Análisis Legislativo</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Tipo de Proceso</label>
                <select x-model="filterType" class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <option value="Todos">Todos los tipos</option>
                    <option value="Prácticas">Prácticas Profesionales</option>
                    <option value="Servicio Social">Servicio Social</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Buscar Alumno</label>
                <input type="text" placeholder="Escribe el nombre o correo..." class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"/>
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
                                <span>Código / ID</span>
                            </div>
                        </th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Estudiante</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Programa Perteneciente</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Modalidad</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Horas Cumplidas</th>
                        <th class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Estado</th>
                        <th class="px-6 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase text-right">Bitácora</th>
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
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-9 h-9 rounded-full font-bold text-xs shrink-0 shadow-sm border border-gray-100"
                                        :class="[row.avatarBg, row.avatarColor]">
                                        <span x-text="row.initials"></span>
                                    </div>
                                    <div>
                                        <span class="block text-sm font-semibold text-gray-900" x-text="row.studentName"></span>
                                        <span class="text-xs text-gray-400 font-medium" x-text="row.studentEmail"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 max-w-[240px]">
                                <div class="truncate">
                                    <span class="block text-sm font-semibold text-gray-800 truncate" x-text="row.programName"></span>
                                    <span :class="getTypeClass(row.type)" x-text="row.type" class="mt-0.5 inline-block"></span>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-600">Presencial</span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-800" x-text="row.hoursLogged + ' / ' + row.hoursTotal + ' hrs'"></span>
                                    <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                        <div class="bg-brand-500 h-1.5 rounded-full" :style="'width: ' + ((row.hoursLogged / row.hoursTotal) * 100) + '%'"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-[11px] inline-block rounded-full px-2.5 py-0.5 font-bold" 
                                    :class="getStatusClass(row.status)" 
                                    x-text="row.status"></span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap">
                                <button class="inline-flex items-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-theme-xs transition-colors">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Ver Horas
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection