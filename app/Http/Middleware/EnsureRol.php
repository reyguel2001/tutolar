<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a uno o varios roles.
 *
 * Uso: ->middleware('rol:CENTRO') o ->middleware('rol:CENTRO,PROFESOR')
 *
 * El control de acceso por recurso (¿este tutor tutela a este alumno?) NO se
 * resuelve aquí: eso vive en las consultas, que siempre parten de la relación
 * de tutela. Este middleware solo separa las tres zonas de la aplicación.
 */
class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->activo) {
            return redirect()->route('login');
        }

        if ($roles !== [] && ! in_array($user->rol, $roles, true)) {
            abort(403, 'Tu rol no tiene acceso a esta sección de TUTOLAR.');
        }

        return $next($request);
    }
}
