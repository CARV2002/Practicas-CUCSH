@extends('layouts.app')

@section('content')
    <x-common.pantalla-pendiente
        titulo="Dictámenes por carrera"
        descripcion="Dictamen de Pertinencia Académica de cada carrera sobre cada convenio. Las divisiones no usan el sistema: la Coordinación captura el resultado cuando regresa el oficio firmado."
        :datos="[
        'Dictámenes pendientes, por carrera y convenio',
        'Captura del dictamen (aprobatorio o negativo) con observaciones y el PDF escaneado',
        'Convenios en los que todas las carreras dieron dictamen negativo (el trámite se cierra)'
    ]"
        fuente="Modelo: ConvenioCarrera · Servicio: ConvenioWorkflow::registrarDictamenCarrera" />
@endsection
