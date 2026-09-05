<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\SolicitudVinculacionController;
use App\Http\Controllers\Centro\AlumnoController;
use App\Http\Controllers\Centro\AsignaturaController;
use App\Http\Controllers\Centro\CuentaController;
use App\Http\Controllers\Centro\GrupoController;
use App\Http\Controllers\Centro\ImparticionController;
use App\Http\Controllers\Centro\MatriculaController;
use App\Http\Controllers\Centro\PanelController;
use App\Http\Controllers\Centro\ProfesorController;
use App\Http\Controllers\Centro\SolicitudController;
use App\Http\Controllers\Centro\TutorController;
use App\Http\Controllers\Familia\InicioController;
use App\Http\Controllers\Familia\RendimientoController as RendimientoFamiliaController;
use App\Http\Controllers\Familia\NotificacionController as AvisosFamiliaController;
use App\Http\Controllers\Familia\ResultadoController;
use App\Http\Controllers\Profesor\GrupoController as GrupoDocenteController;
use App\Http\Controllers\Profesor\NotificacionController as AvisosProfesorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de TUTOLAR
|--------------------------------------------------------------------------
| Tres zonas separadas por rol. Un usuario nunca alcanza una ruta que no le
| corresponde: el middleware `rol` lo corta antes de llegar al controlador.
*/

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/acceso', [AuthController::class, 'mostrarLogin'])->name('login');
    Route::post('/acceso', [AuthController::class, 'login'])->middleware('throttle:6,1');

    // Registro de familias, y solo de familias: el profesorado y la dirección
    // los da de alta el centro. Hace falta un código de vinculación, que es lo
    // que impide que un desconocido se ate a un menor cualquiera. El límite de
    // intentos es lo que impide probar códigos al azar.
    Route::get('/registro',  [RegistroController::class, 'create'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('registro.store');

    // Y si no tienes código, pedirlo. Esto no crea nada ni da acceso a nada:
    // deja una fila en la bandeja del centro para que una persona decida.
    Route::get('/solicitud',  [SolicitudVinculacionController::class, 'create'])->name('solicitud.create');
    Route::post('/solicitud', [SolicitudVinculacionController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('solicitud.store');
});

Route::post('/salir', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ---------- Mi perfil ----------
//
// Fuera de las tres zonas porque la tienen las tres. No recibe ningún id:
// trabaja siempre sobre la cuenta que ha iniciado sesión, así que nadie puede
// cambiarle la foto a otro. La del alumnado la pone el centro desde su ficha.
Route::middleware('auth')->group(function () {
    Route::get('/perfil',            [PerfilController::class, 'edit'])->name('perfil');
    Route::post('/perfil/foto',      [PerfilController::class, 'guardarFoto'])->name('perfil.foto');
    Route::delete('/perfil/foto',    [PerfilController::class, 'quitarFoto'])->name('perfil.foto.quitar');
});

// ---------- Centro educativo ----------
//
// La zona de dirección. Cuatro secciones, las mismas que la barra lateral:
// gestión, actividad académica, cobros y administración. Sesiones, asistencia
// y pagos todavía no tienen ni tabla, así que tampoco tienen ruta.
Route::middleware(['auth', 'rol:CENTRO'])->prefix('centro')->name('centro.')->group(function () {

    Route::get('/panel', [PanelController::class, 'index'])->name('panel');

    // ----- Gestión -----
    // Alumnos conserva sus nombres de ruta originales (`centro.alumnos` para el
    // listado y `centro.alumno` para la ficha) porque hay enlaces que los usan.
    Route::get('/alumnos',                   [AlumnoController::class, 'index'])->name('alumnos');
    Route::get('/alumnos/nuevo',             [AlumnoController::class, 'create'])->name('alumnos.create');
    Route::post('/alumnos',                  [AlumnoController::class, 'store'])->name('alumnos.store');
    Route::get('/alumnos/{alumno}',          [AlumnoController::class, 'show'])->name('alumno');
    Route::get('/alumnos/{alumno}/editar',   [AlumnoController::class, 'edit'])->name('alumnos.edit');
    Route::put('/alumnos/{alumno}',          [AlumnoController::class, 'update'])->name('alumnos.update');
    Route::patch('/alumnos/{alumno}/estado', [AlumnoController::class, 'alternarActivo'])->name('alumnos.estado');
    Route::delete('/alumnos/{alumno}',       [AlumnoController::class, 'destroy'])->name('alumnos.destroy');
    Route::post('/alumnos/{alumno}/codigo',  [AlumnoController::class, 'generarCodigo'])->name('alumnos.codigo');

    Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
    Route::post('/solicitudes/{solicitud}/aprobar',  [SolicitudController::class, 'aprobar'])->name('solicitudes.aprobar');
    Route::post('/solicitudes/{solicitud}/rechazar', [SolicitudController::class, 'rechazar'])->name('solicitudes.rechazar');

    Route::resource('tutores', TutorController::class)->parameters(['tutores' => 'tutor']);
    Route::post('/tutores/{tutor}/alumnos',            [TutorController::class, 'vincularAlumno'])->name('tutores.vincular');
    Route::delete('/tutores/{tutor}/alumnos/{alumno}', [TutorController::class, 'desvincularAlumno'])->name('tutores.desvincular');

    Route::resource('profesores', ProfesorController::class)->parameters(['profesores' => 'profesor']);
    Route::resource('asignaturas', AsignaturaController::class)->except(['show'])->parameters(['asignaturas' => 'asignatura']);

    // ----- Actividad académica -----
    Route::resource('grupos', GrupoController::class)->except(['show'])->parameters(['grupos' => 'grupo']);
    Route::post('/cursos',          [GrupoController::class, 'guardarCurso'])->name('cursos.store');
    Route::delete('/cursos/{curso}', [GrupoController::class, 'eliminarCurso'])->name('cursos.destroy');

    Route::resource('imparticiones', ImparticionController::class)
        ->except(['show'])
        ->parameters(['imparticiones' => 'imparticion']);

    // Cierra el hueco entre impartición y matrícula sin salir del listado.
    Route::post('/imparticiones/{imparticion}/matricular', [ImparticionController::class, 'matricularGrupo'])
        ->name('imparticiones.matricular');

    Route::get('/matriculas',        [MatriculaController::class, 'index'])->name('matriculas.index');
    Route::post('/matriculas',       [MatriculaController::class, 'store'])->name('matriculas.store');
    Route::post('/matriculas/grupo', [MatriculaController::class, 'matricularGrupo'])->name('matriculas.grupo');
    Route::delete('/matriculas/{alumno}/{asignatura}', [MatriculaController::class, 'destroy'])->name('matriculas.destroy');

    // ----- Administración -----
    Route::resource('cuentas', CuentaController::class)
        ->except(['show', 'destroy'])
        ->parameters(['cuentas' => 'cuenta']);
    Route::patch('/cuentas/{cuenta}/estado', [CuentaController::class, 'alternarActivo'])->name('cuentas.estado');
});

// ---------- Profesorado ----------
Route::middleware(['auth', 'rol:PROFESOR'])->prefix('docencia')->name('profesor.')->group(function () {
    Route::get('/grupos', [GrupoDocenteController::class, 'index'])->name('grupos');

    // Avisos a las familias. Cuatro tipos: examen programado, tarea asignada,
    // notas del examen entregadas y ejercicios corregidos. Siempre sobre una
    // impartición propia, es decir sobre un curso, un grupo y una materia.
    Route::get('/notificaciones',        [AvisosProfesorController::class, 'index'])->name('notificaciones.index');
    Route::get('/notificaciones/nueva',  [AvisosProfesorController::class, 'create'])->name('notificaciones.create');
    Route::post('/notificaciones',       [AvisosProfesorController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('notificaciones.store');
});

// ---------- Familias ----------
Route::middleware(['auth', 'rol:TUTOR_LEGAL'])->prefix('familia')->name('familia.')->group(function () {
    Route::get('/inicio', [InicioController::class, 'index'])->name('inicio');

    // El detalle de exámenes frente a ejercicios tiene pantalla propia: el
    // inicio dice cómo va, esto dice por qué.
    Route::get('/rendimiento', [RendimientoFamiliaController::class, 'index'])->name('rendimiento');

    // Registrar la nota de un examen o una tarea. El middleware solo garantiza
    // que quien envía es una familia; que sea LA familia de ese alumno lo
    // comprueba el controlador contra la relación de tutela.
    Route::post('/resultados', [ResultadoController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('resultados.store');

    // Bandeja de avisos del centro.
    Route::get('/avisos', [AvisosFamiliaController::class, 'index'])->name('avisos.index');
    Route::patch('/avisos/{aviso}/confirmar', [AvisosFamiliaController::class, 'confirmar'])->name('avisos.confirmar');
});
