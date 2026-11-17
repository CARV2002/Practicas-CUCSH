<?php

use App\Enums\Rol;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\ConvenioController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\DictamenController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Coordinacion\CierreController;
use App\Http\Controllers\Coordinacion\DictamenController as CoordinacionDictamenController;
use App\Http\Controllers\Coordinacion\ExpedienteController;
use App\Http\Controllers\Alumno\DatosEscolaresController;
use App\Http\Controllers\Coordinacion\PracticanteController;
use App\Http\Controllers\Coordinacion\ProgramaController;
use App\Http\Controllers\Coordinacion\ReglaController;
use App\Http\Controllers\Coordinacion\ReporteController;
use App\Http\Controllers\Coordinacion\DashboardController as CoordinacionDashboardController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\Empresa\ProgramaController as EmpresaProgramaController;
use App\Http\Controllers\Empresa\ConvenioController as EmpresaConvenioController;
use App\Http\Controllers\Empresa\DocumentoController;
use App\Http\Controllers\Empresa\PracticanteController as EmpresaPracticanteController;
use App\Http\Controllers\Empresa\DashboardController as EmpresaDashboardController;
use App\Http\Controllers\Alumno\DocumentoController as AlumnoDocumentoController;
use App\Http\Controllers\Alumno\MisPracticasController;
use App\Http\Controllers\Alumno\OfertaController;
use App\Http\Controllers\Alumno\DashboardController as AlumnoDashboardController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\InformeController;
use App\Http\Controllers\FotoUsuarioController;

/*
|--------------------------------------------------------------------------
| Sitio público
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'inicio'])->name('inicio');
Route::get('/estudiantes', [HomeController::class, 'estudiantes'])->name('estudiantes');
Route::get('/convenios-vigentes', [HomeController::class, 'conveniosVigentesPublico'])->name('convenios-vigentes');
Route::get('/empresas', [HomeController::class, 'empresas'])->name('empresas');
Route::view('/contacto', 'pages.home.contacto')->name('contacto');
Route::view('/terminos-y-condiciones', 'pages.home.terminos')->name('terminos');
Route::view('/aviso-de-privacidad', 'pages.home.privacidad')->name('privacidad');

// /login es el nombre histórico; la pantalla real es /signin (ruta nombrada "login" en auth.php)
Route::get('/login', fn() => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| Cada usuario entra al panel de su rol
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth', 'verified')
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Alumno: consulta ofertas y gestiona su práctica
|--------------------------------------------------------------------------
*/
Route::prefix('alumno')
    ->name('alumno.')
    ->middleware(['auth', 'verified', 'role:' . Rol::Alumno->value])
    ->group(function () {
        //Route::view('/dashboard', 'alumno.dashboard')->name('dashboard');
        Route::get('/dashboard', [AlumnoDashboardController::class, 'index'])->name('dashboard');

        //Route::view('/oferta-programas', 'alumno.oferta-programas')->name('oferta-programas');
        Route::get('/oferta-programas', [OfertaController::class, 'index'])->name('oferta-programas');
        Route::post('/oferta-programas/{programa}/postular', [OfertaController::class, 'postular'])->name('oferta-programas.postular');


        //Route::view('/mis-practicas', 'alumno.mis-practicas')->name('mis-practicas');
        Route::get('/mis-practicas', [MisPracticasController::class, 'index'])->name('mis-practicas');

        //Route::view('/mis-documentos', 'alumno.mis-documentos')->name('mis-documentos');
        Route::get('/mis-documentos', [AlumnoDocumentoController::class, 'index'])->name('mis-documentos');
        Route::post('/documentos/imss', [AlumnoDocumentoController::class, 'subirImss'])->name('documentos.imss');
        Route::post('/postulaciones/{postulacion}/kardex', [AlumnoDocumentoController::class, 'subirKardex'])->name('documentos.kardex');
        Route::post('/postulaciones/{postulacion}/carta-aceptacion', [AlumnoDocumentoController::class, 'subirCartaAceptacion'])->name('documentos.aceptacion');
        Route::post('/reportes/{reporte}/archivo', [AlumnoDocumentoController::class, 'subirReporte'])->name('documentos.reporte');
        Route::post('/practicas/{practica}/carta-termino', [AlumnoDocumentoController::class, 'subirCartaTermino'])->name('documentos.termino');
        Route::post('/practicas/{practica}/reportes', [AlumnoDocumentoController::class, 'crearReporte'])->name('documentos.reporte.crear');
        Route::get('/archivos/{media}', [AlumnoDocumentoController::class, 'descargar'])->name('documentos.descargar');

        // Grado y turno (se imprimen en la Carta de Presentación)
        Route::put('/datos-escolares', [DatosEscolaresController::class, 'update'])->name('datos-escolares');
    });

/*
|--------------------------------------------------------------------------
| Empresa: tramita su convenio y publica sus ofertas
|--------------------------------------------------------------------------
*/
Route::prefix('empresa')
    ->name('empresa.')
    ->middleware(['auth', 'verified', 'role:' . Rol::Empresa->value])
    ->group(function () {
        //Route::view('/dashboard', 'empresa.dashboard')->name('dashboard');
        Route::get('/dashboard', [EmpresaDashboardController::class, 'index'])->name('dashboard');

        // Route::view('/convenio', 'empresa.convenio')->name('convenio');
        Route::get('/convenio', [EmpresaConvenioController::class, 'show'])->name('convenio');
        Route::post('/convenio', [EmpresaConvenioController::class, 'solicitar'])->name('convenio.solicitar');
        Route::get('/convenio/{convenio}/archivo', [EmpresaConvenioController::class, 'descargar'])->name('convenio.archivo');

        //Route::view('/documentos', 'empresa.documentos')->name('documentos');
        Route::get('/documentos', [DocumentoController::class, 'index'])->name('documentos');
        Route::post('/documentos', [DocumentoController::class, 'subir'])->name('documentos.subir');
        Route::put('/datos-legales', [DocumentoController::class, 'datosLegales'])->name('datos-legales');
        Route::get('/documentos/{media}', [DocumentoController::class, 'descargar'])->name('documentos.descargar');

        //Route::view('/lista-programas', 'empresa.lista-programas')->name('lista-programas');
        Route::get('/lista-programas', [EmpresaProgramaController::class, 'index'])->name('lista-programas');
        Route::post('/programas', [EmpresaProgramaController::class, 'store'])->name('programas.store');
        Route::put('/programas/{programa}', [EmpresaProgramaController::class, 'update'])->name('programas.update');
        Route::post('/programas/{programa}/estado', [EmpresaProgramaController::class, 'cambiarEstado'])->name('programas.estado');
        Route::delete('/programas/{programa}', [EmpresaProgramaController::class, 'destroy'])->name('programas.destroy');
        Route::get('/programas/{programa}/postulantes', [EmpresaProgramaController::class, 'postulantes'])->name('programas.postulantes');

        //Route::view('/lista-practicantes', 'empresa.lista-practicantes')->name('lista-practicantes');
        Route::get('/lista-practicantes', [EmpresaPracticanteController::class, 'index'])->name('lista-practicantes');
        Route::get('/practicantes/{practica}', [EmpresaPracticanteController::class, 'show'])->name('practicantes.show');
        Route::post('/reportes/{reporte}/retroalimentacion', [EmpresaPracticanteController::class, 'retroalimentar'])->name('reportes.retroalimentar');
        Route::get('/reportes/{reporte}/archivos/{media}', [EmpresaPracticanteController::class, 'descargarReporte'])->name('reportes.archivos');
    });

/*
|--------------------------------------------------------------------------
| Vinculación (Lorena) + administrador técnico: SOLO el trámite de convenios
|--------------------------------------------------------------------------
| Convenios, empresas y sus expedientes, dictámenes capturados, turnos a Jurídica,
| firmas, formalización y alta de coordinadores. Lo académico vive en coordinacion/.
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'role:' . implode(',', Rol::deVinculacion())])
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/lista-convenios', [ConvenioController::class, 'index'])->name('lista-convenios');
        Route::prefix('convenios/{convenio}')->name('convenios.')->group(function () {
            Route::get('/', [ConvenioController::class, 'show'])->name('show');
            Route::post('/documentos/{media}', [ConvenioController::class, 'resolverDocumento'])->name('documentos.resolver');
            Route::get('/archivos/{media}', [ConvenioController::class, 'descargar'])->name('archivos.descargar');
            Route::post('/validacion-academica', [ConvenioController::class, 'enviarAValidacionAcademica'])->name('validacion-academica');
            Route::post('/borrador', [ConvenioController::class, 'subirBorrador'])->name('borrador');
            Route::post('/juridica/turnar', [ConvenioController::class, 'turnarAJuridica'])->name('juridica.turnar');
            Route::post('/juridica/dictamen', [ConvenioController::class, 'registrarDictamenJuridico'])->name('juridica.dictamen');
            Route::post('/firmas', [ConvenioController::class, 'registrarFirma'])->name('firmas');
            Route::post('/formalizar', [ConvenioController::class, 'formalizar'])->name('formalizar');
            Route::post('/rechazar', [ConvenioController::class, 'rechazar'])->name('rechazar');
            Route::post('/borrador/generar', [ConvenioController::class, 'generarBorrador'])->name('borrador.generar');
        });

        // Fechas de paso del oficio por Secretaría Académica y la División
        Route::post('/convenios/{convenio}/turnos', [ConvenioController::class, 'registrarTurnos'])->name('convenios.turnos');

        // Dictámenes por carrera: los emite cada Coordinación; Vinculación da seguimiento
        Route::get('/lista-dictamenes', [DictamenController::class, 'index'])->name('lista-dictamenes');
        Route::get('/dictamenes/{dictamen}/archivos/{media}', [DictamenController::class, 'descargar'])->name('dictamenes.archivos.descargar');

        // Empresas y su expediente
        Route::get('/lista-empresas', [EmpresaController::class, 'index'])->name('lista-empresas');
        Route::get('/empresas/{empresa}', [EmpresaController::class, 'show'])->name('empresas.show');
        Route::put('/empresas/{empresa}', [EmpresaController::class, 'update'])->name('empresas.update');
        Route::post('/empresas/{empresa}/activo', [EmpresaController::class, 'toggleActivo'])->name('empresas.activo');

        // Informes de consulta: Vinculación ve convenios; el administrador técnico, todo
        Route::get('/informes', [InformeController::class, 'index'])->name('informes');
        Route::get('/informes/{tipo}/exportar', [InformeController::class, 'exportar'])
            ->whereIn('tipo', ['practicas', 'postulaciones', 'convenios', 'programas'])
            ->name('informes.exportar');

        // Administración del sistema: solo el administrador técnico
        // (cuentas de personal y alta de coordinadores, auditoría y estructura del catálogo)
        Route::middleware('role:' . Rol::Administrador->value)->group(function () {
            Route::get('/lista-usuarios', [UsuarioController::class, 'index'])->name('lista-usuarios');
            Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
            Route::post('/usuarios/{usuario}/activo', [UsuarioController::class, 'toggleActivo'])->name('usuarios.activo');
            Route::post('/usuarios/{usuario}/password', [UsuarioController::class, 'restablecerPassword'])->name('usuarios.password');
            Route::post('/usuarios/{usuario}/verificar', [UsuarioController::class, 'verificarCorreo'])->name('usuarios.verificar');

            Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria');

            Route::get('/catalogos', [CatalogoController::class, 'index'])->name('catalogos');
            Route::post('/catalogos/divisiones', [CatalogoController::class, 'storeDivision'])->name('catalogos.divisiones.store');
            Route::put('/catalogos/divisiones/{division}', [CatalogoController::class, 'updateDivision'])->name('catalogos.divisiones.update');
            Route::post('/catalogos/carreras', [CatalogoController::class, 'storeCarrera'])->name('catalogos.carreras.store');
            Route::put('/catalogos/carreras/{carrera}', [CatalogoController::class, 'updateCarrera'])->name('catalogos.carreras.update');
        });
    });

/*
|--------------------------------------------------------------------------
| Coordinación de carrera (+ administrador técnico): el proceso académico del alumno
|--------------------------------------------------------------------------
| Todo se filtra por el alcance de carrera/división del perfil de Personal.
*/
Route::prefix('coordinacion')
    ->name('coordinacion.')
    ->middleware(['auth', 'verified', 'role:' . implode(',', Rol::deCoordinacion())])
    ->group(function () {
        Route::get('/dashboard', [CoordinacionDashboardController::class, 'index'])->name('dashboard');

        // Programas / vacantes de su carrera
        Route::get('/lista-programas', [ProgramaController::class, 'index'])->name('lista-programas');
        Route::post('/programas', [ProgramaController::class, 'store'])->name('programas.store');
        Route::put('/programas/{programa}', [ProgramaController::class, 'update'])->name('programas.update');
        Route::post('/programas/{programa}/estado', [ProgramaController::class, 'cambiarEstado'])->name('programas.estado');
        Route::delete('/programas/{programa}', [ProgramaController::class, 'destroy'])->name('programas.destroy');
        Route::post('/programas/{programa}/aprobar', [ProgramaController::class, 'aprobar'])->name('programas.aprobar');
        Route::post('/programas/{programa}/rechazar', [ProgramaController::class, 'rechazar'])->name('programas.rechazar');

        // Dictamen de pertinencia de las solicitudes de convenio de su carrera
        Route::get('/lista-dictamenes', [CoordinacionDictamenController::class, 'index'])->name('lista-dictamenes');
        Route::post('/dictamenes/{dictamen}', [CoordinacionDictamenController::class, 'registrar'])->name('dictamenes.registrar');
        Route::get('/dictamenes/{dictamen}/archivos/{media}', [CoordinacionDictamenController::class, 'descargar'])->name('dictamenes.archivos');

        // Expediente del alumno: constancia IMSS y kardex
        Route::post('/documentos/{media}/resolver', [ExpedienteController::class, 'resolver'])->name('documentos.resolver');
        Route::get('/alumnos/{alumno}/archivos/{media}', [ExpedienteController::class, 'descargarAlumno'])->name('alumnos.archivos');

        // Practicantes y postulaciones
        Route::get('/lista-practicantes', [PracticanteController::class, 'index'])->name('lista-practicantes');
        Route::prefix('postulaciones/{postulacion}')->name('postulaciones.')->group(function () {
            Route::post('/carta-presentacion', [PracticanteController::class, 'emitirCarta'])->name('carta');
            Route::post('/rechazar', [PracticanteController::class, 'rechazarPostulacion'])->name('rechazar');
            Route::post('/carta-aceptacion', [PracticanteController::class, 'subirCartaAceptacion'])->name('aceptacion');
            Route::post('/oficio-interno', [PracticanteController::class, 'subirOficioInterno'])->name('oficio-interno');
            Route::post('/activar', [PracticanteController::class, 'activar'])->name('activar');
            Route::get('/archivos/{media}', [PracticanteController::class, 'descargar'])->name('archivos');
        });
        Route::post('/practicas/{practica}/supervisor', [PracticanteController::class, 'asignarSupervisor'])->name('practicas.supervisor');
        Route::post('/practicas/{practica}/cancelar', [PracticanteController::class, 'cancelar'])->name('practicas.cancelar');

        // Reportes de horas
        Route::get('/lista-reportes', [ReporteController::class, 'index'])->name('lista-reportes');
        Route::post('/reportes/{reporte}', [ReporteController::class, 'resolver'])->name('reportes.resolver');
        Route::get('/reportes/{reporte}/archivos/{media}', [ReporteController::class, 'descargar'])->name('reportes.archivos');

        // Cierre: carta de término -> oficio -> Control Escolar -> SIIAU
        Route::get('/lista-cierres', [CierreController::class, 'index'])->name('lista-cierres');
        Route::prefix('cierres/{practica}')->name('cierres.')->group(function () {
            Route::post('/carta', [CierreController::class, 'resolverCarta'])->name('carta');
            Route::post('/oficio', [CierreController::class, 'emitirOficio'])->name('oficio');
            Route::post('/enviar', [CierreController::class, 'enviarControlEscolar'])->name('enviar');
            Route::post('/siiau', [CierreController::class, 'capturarSiiau'])->name('siiau');
            Route::get('/archivos/{media}', [CierreController::class, 'descargar'])->name('archivos');
        });

        // Reglas académicas de su carrera (horas y créditos)
        Route::get('/reglas', [ReglaController::class, 'index'])->name('reglas');
        Route::put('/reglas/{carrera}', [ReglaController::class, 'update'])->name('reglas.update');
    });

/*
|--------------------------------------------------------------------------
| Gestión de Perfil de Usuario
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('/notificaciones/resumen', [NotificacionController::class, 'resumen'])->name('notificaciones.resumen');
    Route::get('/notificaciones/{id}/abrir', [NotificacionController::class, 'abrir'])->name('notificaciones.abrir');
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodas'])->name('notificaciones.leer-todas');

    Route::view('/ayuda', 'ayuda')->name('ayuda');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/foto', [ProfileController::class, 'actualizarFoto'])->name('profile.foto');
    Route::delete('/profile/foto', [ProfileController::class, 'eliminarFoto'])->name('profile.foto.eliminar');
    Route::get('/usuarios/{user}/foto', FotoUsuarioController::class)->name('usuarios.foto');
});

require __DIR__ . '/auth.php';