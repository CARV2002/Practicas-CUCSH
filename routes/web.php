<?php

use App\Enums\Rol;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitio público
|--------------------------------------------------------------------------
*/
Route::view('/', 'pages.home.inicio')->name('inicio');
Route::view('/estudiantes', 'pages.home.estudiantes')->name('estudiantes');
Route::view('/empresas', 'pages.home.empresas')->name('empresas');

// /login es el nombre histórico; la pantalla real es /signin (ruta nombrada "login" en auth.php)
Route::get('/login', fn () => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| Cada usuario entra al panel de su rol
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Alumno: consulta ofertas y gestiona su práctica
|--------------------------------------------------------------------------
*/
Route::prefix('alumno')
    ->name('alumno.')
    ->middleware(['auth', 'role:'.Rol::Alumno->value])
    ->group(function () {
        Route::view('/dashboard', 'alumno.dashboard')->name('dashboard');
        Route::view('/oferta-programas', 'alumno.oferta-programas')->name('oferta-programas');
        Route::view('/mis-practicas', 'alumno.mis-practicas')->name('mis-practicas');
        Route::view('/mis-documentos', 'alumno.mis-documentos')->name('mis-documentos');
    });

/*
|--------------------------------------------------------------------------
| Empresa: tramita su convenio y publica sus ofertas
|--------------------------------------------------------------------------
*/
Route::prefix('empresa')
    ->name('empresa.')
    ->middleware(['auth', 'role:'.Rol::Empresa->value])
    ->group(function () {
        Route::view('/dashboard', 'empresa.dashboard')->name('dashboard');
        Route::view('/convenio', 'empresa.convenio')->name('convenio');
        Route::view('/documentos', 'empresa.documentos')->name('documentos');
        Route::view('/lista-programas', 'empresa.lista-programas')->name('lista-programas');
        Route::view('/lista-practicantes', 'empresa.lista-practicantes')->name('lista-practicantes');
    });

/*
|--------------------------------------------------------------------------
| Administrador (Coordinación de Prácticas): lleva el proceso de convenio
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:'.Rol::Administrador->value])
    ->group(function () {
        Route::view('/dashboard', 'admin.dashboard')->name('dashboard');
        Route::view('/lista-convenios', 'admin.lista-convenios')->name('lista-convenios');
        Route::view('/lista-dictamenes', 'admin.lista-dictamenes')->name('lista-dictamenes');
        Route::view('/lista-empresas', 'admin.lista-empresas')->name('lista-empresas');
        Route::view('/lista-programas', 'admin.lista-programas')->name('lista-programas');
        Route::view('/lista-practicantes', 'admin.lista-practicantes')->name('lista-practicantes');
        Route::view('/lista-reportes', 'admin.lista-reportes')->name('lista-reportes');
        Route::view('/catalogos', 'admin.catalogos')->name('catalogos');
        Route::view('/lista-usuarios', 'admin.lista-usuarios')->name('lista-usuarios');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
