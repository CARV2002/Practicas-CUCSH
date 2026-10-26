@extends('layouts.app')

@section('content')
    <x-common.pantalla-pendiente
        titulo="Usuarios"
        descripcion="Cuentas del sistema: alumnos y empresas se registran solos; el personal de la Coordinación lo da de alta el administrador."
        :datos="[
        'Alumnos, empresas y administradores con su rol',
        'Activar o desactivar cuentas'
    ]"
        fuente="Modelo: User (rol con spatie/laravel-permission)" />
@endsection
