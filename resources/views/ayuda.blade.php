@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb pageTitle="Ayuda" />

<div class="max-w-3xl space-y-4 rounded-2xl border border-gray-200 bg-white p-6">
    <h3 class="text-lg font-bold text-gray-900">¿Necesitas ayuda?</h3>
    <p class="text-sm text-gray-600">Escribe a la Coordinación de Prácticas Profesionales del CUCSH:</p>
    <ul class="space-y-1 text-sm text-gray-700">
        <li>Correo: {{ config('practicas.contacto.correo') }}</li>
        <li>Teléfono: (33) 3819-3300, ext. 23549 y 23404</li>
    </ul>
</div>
@endsection