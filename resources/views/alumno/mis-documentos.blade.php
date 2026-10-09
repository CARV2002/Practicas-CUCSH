@extends('layouts.app')

@section('content')

<x-common.page-breadcrumb pageTitle="Mis Documentos" />

<div class="space-y-6">

    <!-- Documentos del alumno -->
    <div class="bg-white border border-gray-200 rounded-2xl">

        <div class="px-6 py-5 border-b border-gray-100">
            <h2 class="text-lg font-semibold">
                Documentos por entregar
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Sube los documentos requeridos para tus prácticas profesionales.
            </p>
        </div>

        <div class="divide-y divide-gray-100">

            <!-- Documento -->
            <div class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">

                <div>
                    <h3 class="font-medium">
                        Carta Compromiso
                    </h3>

                    <p class="text-sm text-gray-500">
                        Última actualización: 10 Junio 2026
                    </p>
                </div>

                <div class="flex items-center gap-3">

                    <span class="px-3 py-1 text-sm font-medium text-green-700 bg-green-100 rounded-full">
                        Aprobado
                    </span>

                    <button class="px-4 py-2 text-white rounded-lg bg-brand-500 hover:bg-brand-600">
                        Reemplazar
                    </button>

                </div>

            </div>

            <!-- Documento -->
            <div class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">

                <div>
                    <h3 class="font-medium">
                        Reporte Mensual
                    </h3>

                    <p class="text-sm text-gray-500">
                        En revisión por el coordinador.
                    </p>
                </div>

                <div class="flex items-center gap-3">

                    <span class="px-3 py-1 text-sm font-medium text-yellow-700 bg-yellow-100 rounded-full">
                        Pendiente
                    </span>

                    <button class="px-4 py-2 text-white rounded-lg bg-brand-500 hover:bg-brand-600">
                        Ver
                    </button>

                </div>

            </div>

            <!-- Documento -->
            <div class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">

                <div>
                    <h3 class="font-medium">
                        Reporte Final
                    </h3>

                    <p class="text-sm text-gray-500">
                        Aún no ha sido enviado.
                    </p>
                </div>

                <div class="flex items-center gap-3">

                    <span class="px-3 py-1 text-sm font-medium text-red-700 bg-red-100 rounded-full">
                        No enviado
                    </span>

                    <button class="px-4 py-2 text-white rounded-lg bg-brand-500 hover:bg-brand-600">
                        Subir documento
                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- Recursos -->
    <div class="bg-white border border-gray-200 rounded-2xl">

        <div class="px-6 py-5 border-b border-gray-100">
            <h2 class="text-lg font-semibold">
                Formatos y recursos
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Descarga los documentos oficiales que necesitarás durante tus prácticas.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2 xl:grid-cols-4">

            <a href="#" class="p-5 transition border border-gray-200 rounded-xl hover:border-brand-500">
                <div class="mb-3 text-3xl">📄</div>
                <h3 class="font-medium">
                    Formato de Horas
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Registro oficial de actividades.
                </p>
            </a>

            <a href="#" class="p-5 transition border border-gray-200 rounded-xl hover:border-brand-500">
                <div class="mb-3 text-3xl">📘</div>
                <h3 class="font-medium">
                    Bitácora Oficial
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Seguimiento de actividades.
                </p>
            </a>

            <a href="#" class="p-5 transition border border-gray-200 rounded-xl hover:border-brand-500">
                <div class="mb-3 text-3xl">📝</div>
                <h3 class="font-medium">
                    Carta Compromiso
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Formato institucional.
                </p>
            </a>

            <a href="#" class="p-5 transition border border-gray-200 rounded-xl hover:border-brand-500">
                <div class="mb-3 text-3xl">🎓</div>
                <h3 class="font-medium">
                    Guía del Practicante
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Lineamientos generales.
                </p>
            </a>

        </div>

    </div>

</div>

@endsection