<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\Auditoria;
use App\Models\Centro;
use App\Models\Curso;
use App\Models\Evaluable;
use App\Models\Grupo;
use App\Models\Imparticion;
use App\Models\Profesor;
use App\Models\Resultado;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Registro de notas por la familia.
 *
 * Estas pruebas cubren lo que las 18 del motor no pueden cubrir: que nadie
 * escriba donde no le corresponde. La regla más importante del sistema —un
 * tutor solo toca la ficha de su hijo— se comprueba aquí, no en el navegador.
 */
class RegistroResultadoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un instituto mínimo pero completo: centro, curso, grupo, asignatura,
     * profesor, impartición, examen, alumno matriculado y familia tutora.
     *
     * @return array<string, mixed>
     */
    private function escenario(string $sufijo = 'a'): array
    {
        $centro = Centro::create([
            'denominacion' => "IES Prueba {$sufijo}",
            'cif'          => "Q000000{$sufijo}",
            'email'        => "centro.{$sufijo}@prueba.test",
        ]);

        $curso = Curso::create([
            'centro_id'      => $centro->id,
            'denominacion'   => '3º ESO',
            'anio_academico' => '2026/2027',
        ]);

        $grupo = Grupo::create(['curso_id' => $curso->id, 'denominacion' => 'A']);

        $asignatura = Asignatura::create([
            'centro_id'       => $centro->id,
            'denominacion'    => 'Matematicas',
            'horas_semanales' => 4,
        ]);

        $userProfesor = User::create([
            'name'      => "Docente {$sufijo}",
            'email'     => "docente.{$sufijo}@prueba.test",
            'password'  => 'secreto-de-prueba',
            'rol'       => User::ROL_PROFESOR,
            'centro_id' => $centro->id,
            'activo'    => true,
        ]);

        $profesor = Profesor::create([
            'user_id'   => $userProfesor->id,
            'centro_id' => $centro->id,
            'nombre'    => 'Ana',
            'apellidos' => 'Ruiz Vela',
        ]);

        $imparticion = Imparticion::create([
            'profesor_id'   => $profesor->id,
            'asignatura_id' => $asignatura->id,
            'grupo_id'      => $grupo->id,
        ]);

        $evaluable = Evaluable::create([
            'imparticion_id'    => $imparticion->id,
            'tipo'              => 'EXAMEN',
            'titulo'            => "Examen tema 4 {$sufijo}",
            'fecha_prevista'    => now()->toDateString(),
            'puntuacion_maxima' => 10,
            'creado_por'        => $profesor->id,
        ]);

        $alumno = Alumno::create([
            'centro_id'        => $centro->id,
            'grupo_id'         => $grupo->id,
            'nombre'           => 'Hugo',
            'apellidos'        => "Vega Blanco {$sufijo}",
            'fecha_nacimiento' => now()->subYears(14)->toDateString(),
        ]);

        $alumno->asignaturas()->attach($asignatura->id);

        $userTutor = User::create([
            'name'     => "Familia {$sufijo}",
            'email'    => "familia.{$sufijo}@prueba.test",
            'password' => 'secreto-de-prueba',
            'rol'      => User::ROL_TUTOR_LEGAL,
            'telefono' => '600000000',
            'activo'   => true,
        ]);

        $tutor = TutorLegal::create([
            'user_id'   => $userTutor->id,
            'nombre'    => 'Marta',
            'apellidos' => "Vega Blanco {$sufijo}",
            'telefono'  => '600000000',
        ]);

        DB::table('tutelas')->insert([
            'tutor_legal_id' => $tutor->id,
            'alumno_id'      => $alumno->id,
            'parentesco'     => 'MADRE',
            'vinculado_en'   => now(),
            'activa'         => true,
        ]);

        return compact(
            'centro', 'curso', 'grupo', 'asignatura', 'profesor',
            'imparticion', 'evaluable', 'alumno', 'userTutor', 'tutor',
        );
    }

    // ---------------------------------------------------------------- camino feliz

    public function test_el_tutor_registra_la_nota_de_su_hijo(): void
    {
        $e = $this->escenario();

        $respuesta = $this->actingAs($e['userTutor'])->post(route('familia.resultados.store'), [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $e['evaluable']->id,
            'puntuacion'   => 7.5,
            'comentario'   => 'Se le atragantaron las ecuaciones.',
        ]);

        $respuesta->assertRedirect(route('familia.inicio', ['alumno' => $e['alumno']->id]));
        $respuesta->assertSessionHas('exito');

        $resultado = Resultado::first();

        $this->assertNotNull($resultado);
        $this->assertSame(7.5, (float) $resultado->puntuacion_obtenida);
        $this->assertSame('DECLARADO', $resultado->origen, 'La familia declara; solo el centro verifica.');
        $this->assertSame('VALIDO', $resultado->estado);
        $this->assertSame($e['userTutor']->id, $resultado->registrado_por);
        $this->assertSame('Se le atragantaron las ecuaciones.', $resultado->comentario);
    }

    /**
     * La familia ya no adjunta comentario: el campo se retiró del formulario.
     * Y no basta con quitarlo de la vista — se comprueba que tampoco entra si
     * alguien lo manda a mano en la petición.
     */
    public function test_la_familia_no_adjunta_comentario(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['userTutor'])->post(route('familia.resultados.store'), [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $e['evaluable']->id,
            'puntuacion'   => 5,
            'comentario'   => 'Esto no debería guardarse.',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Resultado::first()->comentario);
    }

    public function test_deja_traza_en_auditorias(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['userTutor'])->post(route('familia.resultados.store'), [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $e['evaluable']->id,
            'puntuacion'   => 9,
        ]);

        $traza = Auditoria::first();

        $this->assertNotNull($traza, 'Tocar notas de menores sin dejar traza no es aceptable.');
        $this->assertSame('resultado', $traza->entidad);
        $this->assertSame(Auditoria::CREAR, $traza->accion);
        $this->assertSame(Resultado::first()->id, $traza->entidad_id);
        $this->assertSame($e['userTutor']->id, $traza->autor_id);
        $this->assertSame(9.0, (float) $traza->valor_nuevo['puntuacion_obtenida']);
        $this->assertNull($traza->valor_anterior);
    }

    // ---------------------------------------------------------------- validación

    public function test_la_puntuacion_no_puede_superar_el_maximo(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['userTutor'])
            ->from(route('familia.inicio'))
            ->post(route('familia.resultados.store'), [
                'alumno_id'    => $e['alumno']->id,
                'evaluable_id' => $e['evaluable']->id,
                'puntuacion'   => 11,   // el examen es sobre 10
            ])
            ->assertSessionHasErrors('puntuacion');

        $this->assertSame(0, Resultado::count());
    }

    public function test_la_puntuacion_no_puede_ser_negativa(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['userTutor'])
            ->from(route('familia.inicio'))
            ->post(route('familia.resultados.store'), [
                'alumno_id'    => $e['alumno']->id,
                'evaluable_id' => $e['evaluable']->id,
                'puntuacion'   => -1,
            ])
            ->assertSessionHasErrors('puntuacion');

        $this->assertSame(0, Resultado::count());
    }

    public function test_no_se_registra_dos_veces_el_mismo_evaluable(): void
    {
        $e = $this->escenario();

        $envio = [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $e['evaluable']->id,
            'puntuacion'   => 6,
        ];

        $this->actingAs($e['userTutor'])->post(route('familia.resultados.store'), $envio)
            ->assertSessionHasNoErrors();

        $this->actingAs($e['userTutor'])
            ->from(route('familia.inicio'))
            ->post(route('familia.resultados.store'), $envio)
            ->assertSessionHasErrors('evaluable_id');

        $this->assertSame(1, Resultado::count());
    }

    public function test_una_prueba_anulada_no_admite_nota(): void
    {
        $e = $this->escenario();
        $e['evaluable']->update(['estado' => 'ANULADO']);

        $this->actingAs($e['userTutor'])
            ->from(route('familia.inicio'))
            ->post(route('familia.resultados.store'), [
                'alumno_id'    => $e['alumno']->id,
                'evaluable_id' => $e['evaluable']->id,
                'puntuacion'   => 8,
            ])
            ->assertSessionHasErrors('evaluable_id');

        $this->assertSame(0, Resultado::count());
    }

    // ---------------------------------------------------------------- aislamiento

    /** La regla de seguridad más importante del sistema. */
    public function test_un_tutor_no_puede_registrar_la_nota_de_un_alumno_que_no_tutela(): void
    {
        $mia  = $this->escenario('a');
        $otra = $this->escenario('b');

        $this->actingAs($mia['userTutor'])->post(route('familia.resultados.store'), [
            'alumno_id'    => $otra['alumno']->id,      // el hijo de otra familia
            'evaluable_id' => $otra['evaluable']->id,
            'puntuacion'   => 10,
        ])->assertForbidden();

        $this->assertSame(0, Resultado::count());
    }

    public function test_no_se_admite_un_evaluable_de_otro_grupo(): void
    {
        $mia  = $this->escenario('a');
        $otra = $this->escenario('b');

        // El alumno es suyo, pero el examen es de un grupo al que no pertenece.
        $this->actingAs($mia['userTutor'])->post(route('familia.resultados.store'), [
            'alumno_id'    => $mia['alumno']->id,
            'evaluable_id' => $otra['evaluable']->id,
            'puntuacion'   => 10,
        ])->assertForbidden();

        $this->assertSame(0, Resultado::count());
    }

    public function test_no_se_admite_una_asignatura_en_la_que_no_esta_matriculado(): void
    {
        $e = $this->escenario();

        // Otra asignatura del mismo grupo, pero el alumno no está matriculado.
        $otraAsignatura = Asignatura::create([
            'centro_id'       => $e['centro']->id,
            'denominacion'    => 'Musica',
            'horas_semanales' => 2,
        ]);

        $imparticion = Imparticion::create([
            'profesor_id'   => $e['profesor']->id,
            'asignatura_id' => $otraAsignatura->id,
            'grupo_id'      => $e['grupo']->id,
        ]);

        $ajeno = Evaluable::create([
            'imparticion_id'    => $imparticion->id,
            'tipo'              => 'TAREA',
            'titulo'            => 'Trabajo de audicion',
            'fecha_prevista'    => now()->toDateString(),
            'puntuacion_maxima' => 10,
            'creado_por'        => $e['profesor']->id,
        ]);

        $this->actingAs($e['userTutor'])->post(route('familia.resultados.store'), [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $ajeno->id,
            'puntuacion'   => 8,
        ])->assertForbidden();

        $this->assertSame(0, Resultado::count());
    }

    public function test_una_cuenta_de_centro_no_alcanza_la_ruta_de_familia(): void
    {
        $e = $this->escenario();

        $direccion = User::create([
            'name'      => 'Direccion',
            'email'     => 'direccion@prueba.test',
            'password'  => 'secreto-de-prueba',
            'rol'       => User::ROL_CENTRO,
            'centro_id' => $e['centro']->id,
            'activo'    => true,
        ]);

        $this->actingAs($direccion)->post(route('familia.resultados.store'), [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $e['evaluable']->id,
            'puntuacion'   => 10,
        ])->assertForbidden();

        $this->assertSame(0, Resultado::count());
    }

    public function test_sin_sesion_se_va_al_acceso(): void
    {
        $e = $this->escenario();

        $this->post(route('familia.resultados.store'), [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $e['evaluable']->id,
            'puntuacion'   => 10,
        ])->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------- RN-01

    public function test_la_nota_que_contradice_a_la_verificada_queda_en_discrepancia(): void
    {
        $e = $this->escenario();

        // El centro ya había verificado un 4.
        Resultado::create([
            'evaluable_id'        => $e['evaluable']->id,
            'alumno_id'           => $e['alumno']->id,
            'puntuacion_obtenida' => 4,
            'origen'              => 'VERIFICADO',
            'estado'              => 'VALIDO',
            'registrado_por'      => $e['userTutor']->id,
        ]);

        $this->actingAs($e['userTutor'])->post(route('familia.resultados.store'), [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $e['evaluable']->id,
            'puntuacion'   => 8,          // la familia declara un 8
        ])->assertSessionHas('aviso');

        $declarado = Resultado::where('origen', 'DECLARADO')->first();

        $this->assertNotNull($declarado);
        $this->assertSame('DISCREPANCIA', $declarado->estado);
        $this->assertSame(2, Resultado::count(), 'Los dos valores conviven: no se pisa el del centro.');
    }

    public function test_si_coincide_con_la_verificada_no_hay_discrepancia(): void
    {
        $e = $this->escenario();

        Resultado::create([
            'evaluable_id'        => $e['evaluable']->id,
            'alumno_id'           => $e['alumno']->id,
            'puntuacion_obtenida' => 8,
            'origen'              => 'VERIFICADO',
            'estado'              => 'VALIDO',
            'registrado_por'      => $e['userTutor']->id,
        ]);

        $this->actingAs($e['userTutor'])->post(route('familia.resultados.store'), [
            'alumno_id'    => $e['alumno']->id,
            'evaluable_id' => $e['evaluable']->id,
            'puntuacion'   => 8,
        ])->assertSessionHas('exito');

        $this->assertSame('VALIDO', Resultado::where('origen', 'DECLARADO')->first()->estado);
    }

    // ---------------------------------------------------------------- formulario

    public function test_el_formulario_solo_ofrece_pruebas_sin_nota(): void
    {
        $e = $this->escenario();

        $pendiente = Evaluable::create([
            'imparticion_id'    => $e['imparticion']->id,
            'tipo'              => 'TAREA',
            'titulo'            => 'Ejercicios del tema 5',
            'fecha_prevista'    => now()->toDateString(),
            'puntuacion_maxima' => 10,
            'creado_por'        => $e['profesor']->id,
        ]);

        // El examen del escenario ya tiene nota; la tarea no.
        Resultado::create([
            'evaluable_id'        => $e['evaluable']->id,
            'alumno_id'           => $e['alumno']->id,
            'puntuacion_obtenida' => 7,
            'origen'              => 'DECLARADO',
            'estado'              => 'VALIDO',
            'registrado_por'      => $e['userTutor']->id,
        ]);

        $respuesta = $this->actingAs($e['userTutor'])->get(route('familia.inicio'));

        $respuesta->assertOk();
        $respuesta->assertSee($pendiente->titulo);
        $respuesta->assertDontSee($e['evaluable']->titulo);
    }

    public function test_el_formulario_no_ofrece_pruebas_de_otro_alumno(): void
    {
        $mia  = $this->escenario('a');
        $otra = $this->escenario('b');

        $respuesta = $this->actingAs($mia['userTutor'])->get(route('familia.inicio'));

        $respuesta->assertOk();
        $respuesta->assertSee($mia['evaluable']->titulo);
        $respuesta->assertDontSee($otra['evaluable']->titulo);
    }

    // ------------------------------------------- la pantalla de gráficos

    public function test_la_pantalla_de_graficos_responde_y_desglosa_por_tipo(): void
    {
        $e = $this->escenario();

        Resultado::create([
            'evaluable_id'        => $e['evaluable']->id,   // es de tipo EXAMEN
            'alumno_id'           => $e['alumno']->id,
            'puntuacion_obtenida' => 8,
            'registrado_por'      => $e['userTutor']->id,
        ]);

        $respuesta = $this->actingAs($e['userTutor'])->get(route('familia.rendimiento'));

        $respuesta->assertOk();
        $respuesta->assertSee('Exámenes y ejercicios, por separado');

        $porTipo = $respuesta->viewData('rendimiento')['por_tipo'];

        $this->assertSame(80.0, $porTipo['EXAMEN']['media'], 'Un 8 sobre 10 son 80 puntos.');
        $this->assertSame(1, $porTipo['EXAMEN']['n']);
        $this->assertNull($porTipo['TAREA']['media'], 'Sin tareas, la media no es cero: no existe.');
    }

    /**
     * La misma regla de siempre, ahora en la pantalla nueva: el alumno se busca
     * entre los tutelados, así que un id ajeno en la URL no abre nada.
     */
    public function test_los_graficos_no_abren_al_hijo_de_otra_familia(): void
    {
        $mia  = $this->escenario('a');
        $otra = $this->escenario('b');

        $respuesta = $this->actingAs($mia['userTutor'])
            ->get(route('familia.rendimiento', ['alumno' => $otra['alumno']->id]));

        $respuesta->assertOk();
        $this->assertSame($mia['alumno']->id, $respuesta->viewData('alumno')->id);
        $respuesta->assertDontSee($otra['alumno']->nombre_completo);
    }

    public function test_una_cuenta_de_centro_no_alcanza_los_graficos(): void
    {
        $e = $this->escenario();

        $direccion = User::create([
            'name' => 'Direccion', 'email' => 'direccion.graficos@prueba.test',
            'password' => 'secreto-de-prueba', 'rol' => User::ROL_CENTRO,
            'centro_id' => $e['centro']->id, 'activo' => true,
        ]);

        $this->actingAs($direccion)->get(route('familia.rendimiento'))->assertForbidden();
    }
}
