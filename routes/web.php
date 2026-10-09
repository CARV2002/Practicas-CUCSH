<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home.inicio');
})->name('inicio');


Route::get('/estudiantes', function () {
    return view('pages.home.estudiantes');
})->name('estudiantes');

Route::get('/empresas', function () {
    return view('pages.home.empresas');
})->name('empresas');










Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');
    
Route::prefix('alumno')
    ->name('alumno.')
    ->middleware(['auth', 'role:alumno'])
    ->group(function () {

        Route::get('/dashboard', function () {
            return view('alumno.dashboard');
        })->name('dashboard');


        Route::get('/oferta-programas', function () {
            return view('alumno.oferta-programas');
            
        })->name('oferta-programas');

        Route::get('/mis-practicas', function () {
            return view('alumno.mis-practicas');
            
        })->name('mis-practicas');

        Route::get('/mis-documentos', function () {
            return view('alumno.mis-documentos');
            
        })->name('mis-documentos');


    });



Route::prefix('empresa')
    ->name('empresa.')
    ->middleware(['auth', 'role:empresa'])
    ->group(function () {

        Route::get('/dashboard', function () {
            return view('empresa.dashboard');
        })->name('dashboard');

        Route::get('/lista-practicantes', function () {
            return view('empresa.lista-practicantes');
        })->name('lista-practicantes');

        Route::get('/lista-programas', function () {
            return view('empresa.lista-programas');
        })->name('lista-programas');

        Route::get('/convenio', function () {
            return view('empresa.convenio');
        })->name('convenio');

        Route::get('/documentos', function () {
            return view('empresa.documentos');
        })->name('documentos');

    });

Route::prefix('coordinador')
    ->name('coordinador.')
    ->middleware(['auth', 'role:coordinador'])
    ->group(function () {

        Route::get('/dashboard', function () {
            return view('coordinador.dashboard');
        })->name('dashboard');

        Route::get('/lista-empresas', function () {
            return view('coordinador.lista-empresas');
        })->name('lista-empresas');

        Route::get('/lista-practicantes', function () {
            return view('coordinador.lista-practicantes');
        })->name('lista-practicantes');

        Route::get('/lista-programas', function () {
            return view('coordinador.lista-programas');
        })->name('lista-programas');

        Route::get('/lista-convenios', function () {
            return view('coordinador.lista-convenios');
        })->name('lista-convenios');

        Route::get('/lista-departamentos', function () {
            return view('coordinador.lista-departamentos');
        })->name('lista-departamentos');

        Route::get('/lista-dictamenes', function () {
            return view('coordinador.lista-dictamenes');
        })->name('lista-dictamenes');




        Route::get('/lista-usuarios', function () {
            return view('coordinador.lista-usuarios');
        })->name('lista-usuarios');

        Route::get('/lista-roles', function () {
            return view('coordinador.lista-roles');
        })->name('lista-roles');

        Route::get('/lista-permisos', function () {
            return view('coordinador.lista-permisos');
        })->name('lista-permisos');

         Route::get('/lista-reportes', function () {
            return view('coordinador.lista-reportes');
        })->name('lista-reportes');


    });


Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:administrador'])
    ->group(function () {

        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');

        Route::get('/lista-empresas', function () {
            return view('admin.lista-empresas');
        })->name('lista-empresas');

        Route::get('/lista-practicantes', function () {
            return view('admin.lista-practicantes');
        })->name('lista-practicantes');

        Route::get('/lista-programas', function () {
            return view('admin.lista-programas');
        })->name('lista-programas');

        Route::get('/lista-convenios', function () {
            return view('admin.lista-convenios');
        })->name('lista-convenios');

        Route::get('/lista-departamentos', function () {
            return view('admin.lista-departamentos');
        })->name('lista-departamentos');

        Route::get('/lista-dictamenes', function () {
            return view('admin.lista-dictamenes');
        })->name('lista-dictamenes');




        Route::get('/lista-usuarios', function () {
            return view('admin.lista-usuarios');
        })->name('lista-usuarios');

        Route::get('/lista-roles', function () {
            return view('admin.lista-roles');
        })->name('lista-roles');

        Route::get('/lista-permisos', function () {
            return view('admin.lista-permisos');
        })->name('lista-permisos');

         Route::get('/lista-reportes', function () {
            return view('admin.lista-reportes');
        })->name('lista-reportes');


    });


Route::redirect('/login', '/signin');

Route::get('/signin', function () {
    return view('pages.auth.signin');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::get("/home", function(){
    return view('pages.home.inicio');
});

require __DIR__.'/auth.php';
