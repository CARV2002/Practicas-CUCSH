@extends('layouts.app')

@section('content')

<style>
    /* ==========================================================================
    ESTILOS COMPLEMENTARIOS: CATÁLOGO DE CONVENIOS (ADMIN)
    ========================================================================== */

    .admin-workspace-container {
        font-family: var(--font-outfit);
    }

    /* Color primario del botón y paginación activa */
    .bg-brand-600 {
        background-color: var(--color-brand-600);
    }
    .hover:bg-brand-700:hover {
        background-color: var(--color-brand-700);
    }
    .text-brand-700 {
        color: var(--color-brand-700);
    }
    .hover:text-brand-700:hover {
        color: var(--color-brand-700);
    }
    .hover:bg-brand-50:hover {
        background-color: var(--color-brand-50);
    }
    .focus\:border-brand-500:focus {
        border-color: var(--color-brand-500);
    }

    /* Clases de apoyo para estatus legales */
    .bg-warning-50 { background-color: var(--color-warning-50); }
    .text-warning-700 { color: var(--color-warning-700); }
    .border-warning-200 { border-color: var(--color-warning-200); }

    .bg-error-50 { background-color: var(--color-error-50); }
    .text-error-700 { color: var(--color-error-700); }
    .border-error-200 { border-color: var(--color-error-200); }
    .text-error-600 { color: var(--color-error-600); }
    .hover:bg-error-25:hover { background-color: var(--color-error-25); }

</style>

<div x-data="{
    companies: [
        {
            id: 1,
            name: 'Poder Judicial del Estado de Jalisco',
            image: '🏢',
            signDate: 'Ene 15, 2024',
            documentId: 'CUCSH-CONV-2024-001',
            sector: 'Público',
            slotsUsed: 8,
            slotsTotal: 15,
            status: 'Vigente',
        },
        {
            id: 2,
            name: 'Bufete Jurídico Integral Asociados',
            image: '⚖️',
            signDate: 'Mar 22, 2024',
            documentId: 'CUCSH-CONV-2024-042',
            sector: 'Privado',
            slotsUsed: 3,
            slotsTotal: 5,
            status: 'Vigente',
        },
        {
            id: 3,
            name: 'Secretaría de Cultura - Gob. Estado',
            image: '🎨',
            signDate: 'Jun 10, 2023',
            documentId: 'CUCSH-CONV-2023-118',
            sector: 'Público',
            slotsUsed: 5,
            slotsTotal: 5,
            status: 'Por Expirar',
        },
        {
            id: 4,
            name: 'Notaría Pública No. 45 de Guadalajara',
            image: '✒️',
            signDate: 'Ago 05, 2024',
            documentId: 'CUCSH-CONV-2024-089',
            sector: 'Privado',
            slotsUsed: 1,
            slotsTotal: 3,
            status: 'Vigente',
        },
        {
            id: 5,
            name: 'Asociación Civil Migrantes de Occidente',
            image: '🌍',
            signDate: 'Oct 12, 2022',
            documentId: 'CUCSH-CONV-2022-014',
            sector: 'ONG',
            slotsUsed: 0,
            slotsTotal: 4,
            status: 'Inactivo',
        },
        {
            id: 6,
            name: 'IBM de México S. de R.L.',
            image: '💻',
            signDate: 'Nov 02, 2024',
            documentId: 'CUCSH-CONV-2024-102',
            sector: 'Privado',
            slotsUsed: 2,
            slotsTotal: 10,
            status: 'Vigente',
        },
        {
            id: 7,
            name: 'Instituto Jalisco de Ciencias Forenses',
            image: '🔬',
            signDate: 'Dic 18, 2023',
            documentId: 'CUCSH-CONV-2023-144',
            sector: 'Público',
            slotsUsed: 12,
            slotsTotal: 12,
            status: 'Vigente',
        }
    ],
    itemsPerPage: 5,
    currentPage: 1,
    get totalPages() {
        return Math.ceil(this.companies.length / this.itemsPerPage);
    },
    get paginatedCompanies() {
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        return this.companies.slice(start, end);
    },
    get displayedPages() {
        const range = [];
        for (let i = 1; i <= this.totalPages; i++) {
            if (i === 1 || i === this.totalPages || (i >= this.currentPage - 1 && i <= this.currentPage + 1)) {
                range.push(i);
            } else if (range[range.length - 1] !== '...') {
                range.push('...');
            }
        }
        return range;
    },
    prevPage() { if (this.currentPage > 1) this.currentPage--; },
    nextPage() { if (this.currentPage < this.totalPages) this.currentPage++; },
    goToPage(page) { if (typeof page === 'number' && page >= 1 && page <= this.totalPages) this.currentPage = page; },
    
    getStatusClass(status) {
        const classes = {
            'Vigente': 'bg-success-50 text-success-700 border border-success-200',
            'Por Expirar': 'bg-warning-50 text-warning-700 border border-warning-200',
            'Inactivo': 'bg-error-50 text-error-700 border border-error-200',
        };
        return classes[status] || 'bg-gray-50 text-gray-600';
    }
}" class="admin-workspace-container">

    <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-xs pt-5">
        
        <div class="flex flex-col gap-4 px-6 mb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">Catálogo Institucional de Convenios</h3>
                <p class="text-sm text-gray-500 mt-0.5">Control regulatorio de empresas validadas y cupos para vinculación de alumnos.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <span class="absolute -translate-y-1/2 left-4 top-1/2 text-gray-400">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                    <input type="text" placeholder="Buscar convenio o empresa..." class="h-[42px] w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pl-11 pr-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/10 xl:w-[320px] transition-all"/>
                </div>
                <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs hover:bg-brand-700 transition-colors">
                    <span>+</span> Nuevo Convenio
                </button>
            </div>
        </div>

        <div class="overflow-hidden">
            <div class="max-w-full px-6 overflow-x-auto">
                <table class="min-w-full border-collapse text-left">
                    <thead>
                        <tr class="border-gray-100 border-y bg-gray-50/70">
                            <th scope="col" class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Institución / Empresa</th>
                            <th scope="col" class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Clave de Convenio</th>
                            <th scope="col" class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Sector</th>
                            <th scope="col" class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Alta del Convenio</th>
                            <th scope="col" class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Ocupación de Plazas</th>
                            <th scope="col" class="px-4 py-3.5 font-semibold text-gray-500 text-xs tracking-wider uppercase">Estatus Legal</th>
                            <th scope="col" class="relative px-4 py-3.5 text-right font-semibold text-gray-500 text-xs tracking-wider uppercase">Gestión</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="company in paginatedCompanies" :key="company.id">
                            <tr class="hover:bg-gray-50/40 transition-colors">
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="shrink-0 w-9 h-9 bg-gray-50 border border-gray-100 rounded-lg flex items-center justify-center text-lg shadow-sm" x-text="company.image"></div>
                                        <div class="ml-3.5">
                                            <div class="text-sm font-semibold text-gray-900" x-text="company.name"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="font-mono text-xs font-semibold text-gray-600 bg-gray-50 border border-gray-200 px-2 py-1 rounded" x-text="company.documentId"></span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-600" x-text="company.sector"></span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500 font-medium" x-text="company.signDate"></div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-gray-900" x-text="company.slotsUsed + ' / ' + company.slotsTotal"></span>
                                        <span class="text-xs text-gray-400 font-normal">plazas</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full inline-block" :class="getStatusClass(company.status)" x-text="company.status"></span>
                                </td>
                                <td class="px-4 py-4 text-sm font-medium text-right whitespace-nowrap">
                                    <div class="flex justify-end relative">
                                        <x-common.table-dropdown>
                                            <x-slot name="button">
                                                <button type="button" class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-50 transition-colors">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                                                </button>
                                            </x-slot>
        
                                            <x-slot name="content">
                                                <a href="#" class="flex w-full px-3 py-2 font-medium text-left text-gray-700 rounded-lg text-xs hover:bg-gray-50 transition-colors">
                                                    👁️ Ver Expediente Legal
                                                </a>
                                                <a href="#" class="flex w-full px-3 py-2 font-medium text-left text-gray-700 rounded-lg text-xs hover:bg-gray-50 transition-colors">
                                                    👥 Ver Alumnos Asignados
                                                </a>
                                                <div class="h-px bg-gray-100 my-1"></div>
                                                <a href="#" class="flex w-full px-3 py-2 font-medium text-left text-error-600 rounded-lg text-xs hover:bg-error-25 transition-colors">
                                                    🚫 Suspender Convenio
                                                </a>
                                            </x-slot>
                                        </x-common.table-dropdown>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/30">
            <div class="flex items-center justify-between">
                <button @click="prevPage" :disabled="currentPage === 1" :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    <span>Anterior</span>
                </button>

                <ul class="hidden items-center gap-1 sm:flex">
                    <template x-for="page in displayedPages" :key="page">
                        <li>
                            <button x-show="page !== '...'" @click="goToPage(page)" :class="currentPage === page ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-600 hover:bg-brand-50 hover:text-brand-700'" class="flex h-9 w-9 items-center justify-center rounded-lg text-sm font-semibold transition-all" x-text="page"></button>
                            <span x-show="page === '...'" class="flex h-9 w-9 items-center justify-center text-gray-400 font-medium">...</span>
                        </li>
                    </template>
                </ul>

                <button @click="nextPage" :disabled="currentPage === totalPages" :class="currentPage === totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-50'" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors">
                    <span>Siguiente</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </div>
        </div>

    </div>
</div>

@endsection