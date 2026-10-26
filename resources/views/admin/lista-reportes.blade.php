@extends('layouts.app')

@section('content')
    <x-common.pantalla-pendiente
        titulo="Reportes"
        descripcion="Seguimiento de las prácticas en curso y sus reportes de horas."
        :datos="[
        'Reportes por práctica, con horas y estado de validación',
        'Avance de cada practicante: horas acumuladas / horas requeridas',
        'Prácticas próximas a cerrar'
    ]"
        fuente="Modelos: Reporte, Practica" />
@endsection
