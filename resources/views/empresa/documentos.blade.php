@extends('layouts.app')

@section('content')

<style>
/* ==========================================================================
   ESTILOS ADICIONALES: REPOSITORIO DIGITAL DE LA EMPRESA
   ========================================================================== */

.company-documents-container {
    font-family: var(--font-outfit);
}

/* Tokens de Identidad Institucional */
.text-brand-600 {
    color: var(--color-brand-600);
}
.bg-brand-50 {
    background-color: var(--color-brand-50);
}
.text-brand-700 {
    color: var(--color-brand-700);
}
.border-brand-100 {
    border-color: var(--color-brand-100);
}
.focus\:border-brand-500:focus {
    border-color: var(--color-brand-500);
}
.focus\:ring-brand-500:focus {
    --tw-ring-color: var(--color-brand-500);
}

/* Tokens de Éxito / Validación Jurídica */
.bg-success-50 {
    background-color: var(--color-success-50);
}
.text-success-700 {
    color: var(--color-success-700);
}
.border-success-100 {
    border-color: var(--color-success-100);
}

/* Tokens de Advertencia / Pendientes */
.bg-warning-50 {
    background-color: var(--color-warning-50);
}
.text-warning-700 {
    color: var(--color-warning-700);
}
.border-warning-100 {
    border-color: var(--color-warning-100);
}

/* Helper Utilitario de Líneas de Texto */
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

</style>
    <x-common.page-breadcrumb pageTitle="Repositorio Documental" />

    <div x-data="{
        activeTab: 'todos', /* Filtro rápido: todos, formatos, expedientes */
        searchQuery: '',
        
        // Catálogo unificado de archivos y documentos de la organización
        documentos: [
            {
                id: 'DOC-01',
                nombre: 'Convenio General de Colaboración Firmado',
                categoria: 'expedientes',
                tipoArchivo: 'PDF',
                peso: '2.4 MB',
                fecha: '15-Ene-2024',
                descripcion: 'Copia certificada del instrumento digital con firmas del CUCSH y Apoderado.',
                estatus: 'Validado',
                origen: 'Subido por nosotros'
            },
            {
                id: 'DOC-02',
                nombre: 'Plantilla Oficial: Reporte Mensual de Actividades',
                categoria: 'formatos',
                tipoArchivo: 'DOCX',
                peso: '420 KB',
                fecha: 'Actualizado recientemente',
                descripcion: 'Formato obligatorio institucional para que el alumno describa y compute sus horas.',
                estatus: 'Descargable',
                origen: 'Provisto por CUCSH'
            },
            {
                id: 'DOC-03',
                nombre: 'Acta Constitutiva o Nombramiento Legal',
                categoria: 'expedientes',
                tipoArchivo: 'PDF',
                peso: '5.1 MB',
                fecha: '10-Ene-2024',
                descripcion: 'Documento probatorio de la existencia jurídica de la institución receptora.',
                estatus: 'Validado',
                origen: 'Subido por nosotros'
            },
            {
                id: 'DOC-04',
                nombre: 'Guía de Evaluación de Desempeño Técnico',
                categoria: 'formatos',
                tipoArchivo: 'PDF',
                peso: '1.1 MB',
                fecha: 'Vigente',
                descripcion: 'Rúbrica que debe llenar el tutor de la empresa al término de las prácticas o servicio social.',
                estatus: 'Descargable',
                origen: 'Provisto por CUCSH'
            },
            {
                id: 'DOC-05',
                nombre: 'Formato de Renovación Anticipada de Convenio',
                categoria: 'formatos',
                tipoArchivo: 'PDF',
                peso: '850 KB',
                fecha: 'Vigente',
                descripcion: 'Solicitud formal para prórroga de plazas y vinculación institucional.',
                estatus: 'Descargable',
                origen: 'Provisto por CUCSH'
            }
        ],

        // Filtrado dinámico por pestaña y búsqueda
        get filteredDocs() {
            return this.documentos.filter(doc => {
                const matchesTab = this.activeTab === 'todos' || doc.categoria === this.activeTab;
                const matchesSearch = doc.nombre.toLowerCase().includes(this.searchQuery.toLowerCase()) || 
                                      doc.descripcion.toLowerCase().includes(this.searchQuery.toLowerCase());
                return matchesTab && matchesSearch;
            });
        },

        getStatusClass(estatus) {
            const classes = {
                'Validado': 'bg-success-50 text-success-700 border border-success-100',
                'Descargable': 'bg-brand-50 text-brand-700 border border-brand-100',
                'En Revisión': 'bg-warning-50 text-warning-700 border border-warning-100'
            };
            return classes[estatus] || 'bg-gray-100 text-gray-600';
        }
    }" class="company-documents-container space-y-6">

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs">
            
            <div class="flex flex-col gap-4 border-b border-gray-100 pb-5 lg:flex-row lg:items-center lg:justify-between">
                
                <div class="flex flex-wrap items-center gap-1.5 bg-gray-100/80 p-1 rounded-xl self-start">
                    <button @click="activeTab = 'todos'" 
                        :class="activeTab === 'todos' ? 'bg-white text-gray-900 shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-800'"
                        class="px-4 py-2 text-xs rounded-lg transition-all focus:outline-none">
                        Todos los archivos
                    </button>
                    <button @click="activeTab = 'formatos'" 
                        :class="activeTab === 'formatos' ? 'bg-white text-gray-900 shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-800'"
                        class="px-4 py-2 text-xs rounded-lg transition-all focus:outline-none">
                        Formatos y Plantillas CUCSH
                    </button>
                    <button @click="activeTab = 'expedientes'" 
                        :class="activeTab === 'expedientes' ? 'bg-white text-gray-900 shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-800'"
                        class="px-4 py-2 text-xs rounded-lg transition-all focus:outline-none">
                        Nuestro Expediente Legal
                    </button>
                </div>

                <div class="w-full lg:w-72">
                    <input x-model="searchQuery" type="text" placeholder="Buscar documento..." 
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-xs text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 pt-6">
                
                <template x-for="doc in filteredDocs" :key="doc.id">
                    <div class="group rounded-xl border border-gray-100 p-5 hover:border-gray-200 hover:bg-gray-50/40 hover:shadow-theme-xs transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3.5">
                                <span class="font-mono text-[10px] font-bold text-gray-400 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded" x-text="doc.tipoArchivo + ' • ' + doc.peso"></span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="getStatusClass(doc.estatus)" x-text="doc.estatus"></span>
                            </div>

                            <h4 class="text-sm font-bold text-gray-900 line-clamp-1 group-hover:text-brand-600 transition-colors" x-text="doc.nombre"></h4>
                            <p class="text-xs text-gray-500 mt-1.5 line-clamp-2 leading-relaxed" x-text="doc.descripcion"></p>
                        </div>

                        <div class="mt-5 pt-3.5 border-t border-gray-100 flex items-center justify-between">
                            <div class="text-[10px] font-medium text-gray-400">
                                <span class="block text-gray-500 font-semibold" x-text="doc.origen"></span>
                                <span x-text="'Ref: ' + doc.fecha"></span>
                            </div>

                            <div>
                                <a href="#" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-theme-xs hover:bg-gray-50 transition-colors">
                                    <template x-if="doc.categoria === 'formatos'">
                                        <span class="flex items-center gap-1">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4M7 10l5 5 5-5M12 15V3"/></svg>
                                            Descargar
                                        </span>
                                    </template>
                                    <template x-if="doc.categoria === 'expedientes'">
                                        <span class="flex items-center gap-1">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            Ver Original
                                        </span>
                                    </template>
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="filteredDocs.length === 0" class="col-span-full py-12 text-center">
                    <p class="text-sm text-gray-400 font-medium">No se encontraron documentos que coincidan con los criterios de búsqueda.</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 flex items-start gap-3">
            <div class="text-base">💡</div>
            <p class="text-xs text-gray-500 leading-relaxed">
                <strong>¿Necesitas actualizar tu expediente legal?</strong> Si vas a realizar un cambio de apoderado legal, domicilio fiscal o requieres anexar una nueva adenda para ampliación de cupos institucionales, por favor ponte en contacto directo con la Coordinación de Vinculación del CUCSH para habilitar la carga compartida de archivos especiales.
            </p>
        </div>

    </div>
@endsection