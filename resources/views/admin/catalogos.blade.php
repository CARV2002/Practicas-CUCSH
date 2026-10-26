@extends('layouts.app')

@section('content')
    <x-common.pantalla-pendiente
        titulo="Divisiones y carreras"
        descripcion="Catálogos del Centro Universitario. Las reglas de cada carrera se capturan una sola vez y de ahí salen las horas y los requisitos de cada práctica."
        :datos="[
        'Alta y edición de divisiones y carreras',
        'Por carrera: horas requeridas, porcentaje mínimo de créditos y créditos que se otorgan',
        'Catálogo de profesores supervisores (opcional por ahora: la práctica puede activarse sin supervisor)'
    ]"
        fuente="Modelos: Division, Carrera, Profesor" />
@endsection
