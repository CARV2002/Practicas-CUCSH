@extends('layouts.app')

@section('content')
    <x-common.pantalla-pendiente
        titulo="Convenios"
        descripcion="Bandeja de convenios entre las empresas y la Universidad. Aquí la Coordinación de Prácticas lleva cada trámite de principio a fin."
        :datos="[
        'Todos los convenios con su estado y la etapa abierta: quién tiene el trámite y cuántos días hábiles lleva',
        'Alerta cuando una etapa rebasa el tiempo documentado (p. ej. Unidad Jurídica por más de 15 días hábiles)',
        'Una acción por paso: validar documentos, turnar a las carreras, capturar dictámenes, adjuntar el borrador, turnar a Jurídica, registrar firmas y formalizar',
        'Convenios por vencer (avisos a 60 y 30 días)'
    ]"
        fuente="Modelos: Convenio, ConvenioSeguimiento · Servicio: ConvenioWorkflow" />
@endsection
