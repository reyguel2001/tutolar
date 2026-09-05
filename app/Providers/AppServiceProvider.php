<?php

namespace App\Providers;

use App\Models\NotificacionDestinatario;
use App\Models\SolicitudVinculacion;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Los enlaces de paginación se pintan con Bootstrap 5, que es la
        // retícula del proyecto. Sin esto Laravel saca marcado de Tailwind.
        Paginator::useBootstrapFive();

        // Las URL están en español de punta a punta:
        // /centro/tutores/nuevo, no /centro/tutores/create.
        Route::resourceVerbs([
            'create' => 'nuevo',
            'edit'   => 'editar',
        ]);

        // Contador de avisos sin leer en la barra lateral de las familias.
        // Va en un composer y no en cada controlador porque lo pinta el layout,
        // que es común a todas las pantallas de esa zona.
        View::composer('layouts.app', function ($vista) {
            $usuario = auth()->user();

            $sinLeer = ($usuario && $usuario->rol === User::ROL_TUTOR_LEGAL && $usuario->tutorLegal)
                ? NotificacionDestinatario::where('tutor_legal_id', $usuario->tutorLegal->id)
                    ->whereNull('leida_en')
                    ->count()
                : 0;

            $vista->with('avisosSinLeer', $sinLeer);

            // Y el de solicitudes de familia sin resolver, para dirección.
            // Misma razón: lo pinta el layout, no una pantalla concreta.
            $pendientes = ($usuario && $usuario->rol === User::ROL_CENTRO && $usuario->centro_id)
                ? SolicitudVinculacion::where('centro_id', $usuario->centro_id)->pendientes()->count()
                : 0;

            $vista->with('solicitudesPendientes', $pendientes);
        });
    }
}
