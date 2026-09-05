<?php

/*
|--------------------------------------------------------------------------
| Arranque de TUTOLAR
|--------------------------------------------------------------------------
| Un Laravel 12 con el árbol estándar. Lo único propio del proyecto es el
| alias del middleware `rol`, que es lo que separa las tres zonas de la
| aplicación: centro, profesorado y familias.
|
| Sin ese alias, cualquier ruta declarada con ->middleware('rol:...') deja de
| resolver y la web entera responde con error. Es una línea, pero es la que
| sostiene el aislamiento entre zonas.
*/

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => \App\Http\Middleware\EnsureRol::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
