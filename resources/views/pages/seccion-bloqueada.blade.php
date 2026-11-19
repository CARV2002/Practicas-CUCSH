@extends('layouts.app')

@section('content')
    <x-ui.pantalla-bloqueada :titulo="$titulo" :motivo="$motivo" :enlace="$enlace" :enlace-texto="$enlaceTexto" />
@endsection
