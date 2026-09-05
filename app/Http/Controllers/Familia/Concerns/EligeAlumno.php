<?php

namespace App\Http\Controllers\Familia\Concerns;

use App\Models\Alumno;
use App\Models\TutorLegal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Elegir de qué hijo se está hablando.
 *
 * Lo comparten todas las pantallas de la zona de familia, y es la comprobación
 * de seguridad más importante de esta zona: **el alumno se busca dentro de los
 * tutelados, nunca por su id a secas**. Si el parámetro `?alumno=` trae el id
 * del hijo de otra familia, `firstWhere()` no lo encuentra y se cae al primero
 * de los propios. Ninguna pantalla puede saltarse esto sin dejar de usar este
 * trait, que es exactamente lo que se quiere: que saltárselo cueste.
 */
trait EligeAlumno
{
    protected function tutor(): TutorLegal
    {
        $tutor = auth()->user()->tutorLegal;

        abort_unless($tutor, 403, 'Esta cuenta no tiene perfil de tutor legal.');

        return $tutor;
    }

    /** @return Collection<int, Alumno> */
    protected function hijos(TutorLegal $tutor): Collection
    {
        $hijos = $tutor->alumnos()->with('grupo.curso')->get();

        abort_if($hijos->isEmpty(), 404, 'Todavía no tienes ningún alumno vinculado.');

        return $hijos;
    }

    /**
     * El hijo del que va la pantalla, con todo lo que hace falta para calcular
     * su rendimiento.
     *
     * @param Collection<int, Alumno> $hijos
     */
    protected function alumnoElegido(Request $peticion, Collection $hijos): Alumno
    {
        $id       = (int) $peticion->query('alumno', $hijos->first()->id);
        $elegido  = $hijos->firstWhere('id', $id) ?? $hijos->first();

        return Alumno::with([
            'grupo.curso', 'centro', 'asignaturas',
            'resultados.evaluable.imparticion.asignatura',
            'resultados.evaluable.imparticion.profesor',
        ])->findOrFail($elegido->id);
    }
}
