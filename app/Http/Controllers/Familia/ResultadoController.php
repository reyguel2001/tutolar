<?php

namespace App\Http\Controllers\Familia;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\Evaluable;
use App\Models\Resultado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registro de resultados por la familia.
 *
 * Es el ciclo central de TUTOLAR: el profesor programa la prueba, la familia
 * apunta la nota que trae su hijo a casa y el motor recalcula el índice.
 *
 * Cuatro comprobaciones, en este orden, antes de escribir nada:
 *
 *   1. TUTELA      · el alumno tiene que estar entre los que tutela quien envía
 *                    el formulario. Es la barrera que impide que un tutor toque
 *                    la ficha del hijo de otro, y no se delega en el middleware:
 *                    `rol:TUTOR_LEGAL` solo dice «eres una familia», no «eres
 *                    la familia de este alumno».
 *   2. PERTENENCIA · el evaluable tiene que ser del grupo del alumno y de una
 *                    asignatura en la que esté matriculado.
 *   3. RANGO       · la puntuación no puede superar el máximo del evaluable.
 *   4. UNICIDAD    · una sola nota declarada por evaluable y alumno.
 *
 * Y una regla de negocio: si el centro ya había verificado un valor distinto,
 * la nota declarada entra como DISCREPANCIA, no como VÁLIDA (RN-01).
 */
class ResultadoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tutor = $request->user()->tutorLegal;
        abort_unless($tutor, 403, 'Esta cuenta no tiene perfil de tutor legal.');

        $datos = $request->validate([
            'alumno_id'    => ['required', 'integer'],
            'evaluable_id' => ['required', 'integer'],
            'puntuacion'   => ['required', 'numeric', 'min:0'],
        ], [
            'puntuacion.min' => 'La puntuación no puede ser negativa.',
        ], [
            'alumno_id'    => 'alumno',
            'evaluable_id' => 'examen o tarea',
            'puntuacion'   => 'puntuación',
        ]);

        // ---- 1. Tutela ----------------------------------------------------
        // La consulta parte de la relación de tutela, nunca de Alumno::find():
        // si el alumno no está tutelado, sencillamente no aparece.
        $alumno = $tutor->alumnos()->with('asignaturas')->find($datos['alumno_id']);
        abort_unless($alumno instanceof Alumno, 403, 'No tutelas a ese alumno.');

        // ---- 2. Pertenencia ------------------------------------------------
        $evaluable = Evaluable::with('imparticion')->find($datos['evaluable_id']);
        abort_unless($evaluable && $evaluable->imparticion, 404, 'Ese examen o tarea no existe.');

        $delGrupo     = $evaluable->imparticion->grupo_id === $alumno->grupo_id;
        $matriculado  = $alumno->asignaturas->contains('id', $evaluable->imparticion->asignatura_id);

        abort_unless(
            $delGrupo && $matriculado,
            403,
            'Ese examen o tarea no corresponde a este alumno.'
        );

        if ($evaluable->estado === 'ANULADO') {
            throw ValidationException::withMessages([
                'evaluable_id' => 'Esa prueba ha sido anulada por el centro.',
            ]);
        }

        // ---- 3. Rango ------------------------------------------------------
        $puntuacion = (float) $datos['puntuacion'];
        $maxima     = (float) $evaluable->puntuacion_maxima;

        if ($puntuacion > $maxima) {
            throw ValidationException::withMessages([
                'puntuacion' => "La puntuación no puede superar {$this->numero($maxima)}, "
                    . "que es el máximo de «{$evaluable->titulo}».",
            ]);
        }

        // ---- 4. Unicidad ---------------------------------------------------
        $yaDeclarado = Resultado::where('evaluable_id', $evaluable->id)
            ->where('alumno_id', $alumno->id)
            ->where('origen', 'DECLARADO')
            ->exists();

        if ($yaDeclarado) {
            throw ValidationException::withMessages([
                'evaluable_id' => "Ya habías registrado el resultado de «{$evaluable->titulo}».",
            ]);
        }

        // RN-01: si el centro ya verificó otro valor, esto es una discrepancia.
        // No se pisa el dato del centro ni se rechaza el de la familia: se
        // guardan los dos y se marca, que es lo que permite resolverlo después.
        $verificado = Resultado::where('evaluable_id', $evaluable->id)
            ->where('alumno_id', $alumno->id)
            ->where('origen', 'VERIFICADO')
            ->first();

        $estado = ($verificado && (float) $verificado->puntuacion_obtenida !== $puntuacion)
            ? 'DISCREPANCIA'
            : 'VALIDO';

        $resultado = DB::transaction(function () use ($request, $alumno, $evaluable, $puntuacion, $datos, $estado) {
            $resultado = Resultado::create([
                'evaluable_id'        => $evaluable->id,
                'alumno_id'           => $alumno->id,
                'puntuacion_obtenida' => $puntuacion,
                'origen'              => 'DECLARADO',
                'estado'              => $estado,
                // La familia ya no adjunta comentario: el campo se retiró de su
                // formulario y tampoco se acepta aunque llegue en la petición.
                // La columna sigue en el esquema porque el centro sí la usa al
                // verificar una nota.
                'comentario'          => null,
                'registrado_por'      => $request->user()->id,
            ]);

            Auditoria::registrar(
                entidad: 'resultado',
                entidadId: $resultado->id,
                accion: Auditoria::CREAR,
                autorId: $request->user()->id,
                nuevo: [
                    'evaluable_id'        => $evaluable->id,
                    'alumno_id'           => $alumno->id,
                    'puntuacion_obtenida' => $puntuacion,
                    'puntuacion_maxima'   => (float) $evaluable->puntuacion_maxima,
                    'origen'              => 'DECLARADO',
                    'estado'              => $estado,
                ],
                ip: $request->ip(),
            );

            return $resultado;
        });

        $aviso = $estado === 'DISCREPANCIA'
            ? "Guardado, pero no coincide con la nota que registró el centro. Lo revisarán."
            : "Guardado: {$evaluable->titulo}, {$this->numero($puntuacion)} de {$this->numero($maxima)}.";

        return redirect()
            ->route('familia.inicio', ['alumno' => $alumno->id])
            ->with($estado === 'DISCREPANCIA' ? 'aviso' : 'exito', $aviso)
            ->with('resultado_id', $resultado->id);
    }

    /** 7.5 → «7,5» y 10.0 → «10». Las notas se escriben con coma en español. */
    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
    }
}
