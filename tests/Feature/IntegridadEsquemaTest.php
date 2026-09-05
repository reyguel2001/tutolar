<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\Centro;
use App\Models\Curso;
use App\Models\Evaluable;
use App\Models\Grupo;
use App\Models\Imparticion;
use App\Models\Notificacion;
use App\Models\Profesor;
use App\Models\Resultado;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integridad del esquema.
 *
 * Las demás pruebas comprueban que la **aplicación** no deja hacer cosas. Estas
 * comprueban que tampoco las deja hacer la **base de datos**, que es la
 * diferencia entre una regla y una costumbre: un seeder, una importación CSV o
 * alguien con phpMyAdmin abierto se saltan el controlador, pero no una clave
 * foránea.
 *
 * La regla que defienden es la que se decidió al reestructurar el esquema:
 *
 *   · Lo que es **historial académico** —alumnos, imparticiones, evaluables,
 *     resultados, tutelas, avisos emitidos— no desaparece en cascada. Para
 *     borrarlo hay que ir a por ello.
 *   · Lo que es **configuración o proyección** —plan de estudios, matrículas,
 *     reparto de avisos, índices de rendimiento— sí, porque se puede
 *     reconstruir.
 */
class IntegridadEsquemaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function escenario(): array
    {
        $centro = Centro::create([
            'denominacion' => 'IES Prueba', 'cif' => 'Q9999999X', 'email' => 'centro@prueba.test',
        ]);

        $curso = Curso::create([
            'centro_id' => $centro->id, 'denominacion' => '3º ESO', 'anio_academico' => '2026/2027',
        ]);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'denominacion' => 'A']);

        $asignatura = Asignatura::create([
            'centro_id' => $centro->id, 'denominacion' => 'Matematicas', 'horas_semanales' => 4,
        ]);

        $direccion = User::create([
            'name' => 'Direccion', 'email' => 'direccion@prueba.test', 'password' => 'secreto-de-prueba',
            'rol' => User::ROL_CENTRO, 'centro_id' => $centro->id, 'activo' => true,
        ]);

        $userDocente = User::create([
            'name' => 'Docente', 'email' => 'docente@prueba.test', 'password' => 'secreto-de-prueba',
            'rol' => User::ROL_PROFESOR, 'centro_id' => $centro->id, 'activo' => true,
        ]);

        $profesor = Profesor::create([
            'user_id' => $userDocente->id, 'centro_id' => $centro->id,
            'nombre' => 'Ana', 'apellidos' => 'Ruiz',
        ]);

        $alumno = Alumno::create([
            'centro_id' => $centro->id, 'grupo_id' => $grupo->id,
            'nombre' => 'Hugo', 'apellidos' => 'Vega',
            'fecha_nacimiento' => now()->subYears(14)->toDateString(), 'activo' => true,
        ]);

        $imparticion = Imparticion::create([
            'profesor_id' => $profesor->id, 'asignatura_id' => $asignatura->id, 'grupo_id' => $grupo->id,
        ]);

        return compact(
            'centro', 'curso', 'grupo', 'asignatura',
            'direccion', 'userDocente', 'profesor', 'alumno', 'imparticion',
        );
    }

    private function userFamilia(?int $centroId = null, string $correo = 'familia@prueba.test'): TutorLegal
    {
        $user = User::create([
            'name' => 'Marta Vega', 'email' => $correo, 'password' => 'secreto-de-prueba',
            'rol' => User::ROL_TUTOR_LEGAL, 'centro_id' => $centroId, 'activo' => true,
        ]);

        return TutorLegal::create([
            'user_id' => $user->id, 'nombre' => 'Marta', 'apellidos' => 'Vega', 'telefono' => '600000000',
        ]);
    }

    // --------------------------------------------- el historial no se borra

    public function test_la_base_de_datos_impide_borrar_un_grupo_con_alumnos(): void
    {
        $e = $this->escenario();

        $this->expectException(QueryException::class);

        DB::table('grupos')->where('id', $e['grupo']->id)->delete();
    }

    public function test_la_base_de_datos_impide_borrar_una_asignatura_matriculada(): void
    {
        $e = $this->escenario();
        $e['alumno']->asignaturas()->attach($e['asignatura']->id);

        $this->expectException(QueryException::class);

        DB::table('asignaturas')->where('id', $e['asignatura']->id)->delete();
    }

    public function test_la_base_de_datos_impide_borrar_un_evaluable_con_notas(): void
    {
        $e = $this->escenario();

        $evaluable = Evaluable::create([
            'imparticion_id' => $e['imparticion']->id, 'tipo' => 'EXAMEN', 'titulo' => 'Tema 1',
            'fecha_prevista' => now()->toDateString(), 'creado_por' => $e['profesor']->id,
        ]);

        Resultado::create([
            'evaluable_id' => $evaluable->id, 'alumno_id' => $e['alumno']->id,
            'puntuacion_obtenida' => 7.5, 'registrado_por' => $e['direccion']->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('evaluables')->where('id', $evaluable->id)->delete();
    }

    public function test_una_tutela_no_desaparece_al_borrar_la_familia(): void
    {
        $e     = $this->escenario();
        $tutor = $this->userFamilia();

        DB::table('tutelas')->insert([
            'tutor_legal_id' => $tutor->id, 'alumno_id' => $e['alumno']->id,
            'parentesco' => 'MADRE', 'vinculado_en' => now(), 'activa' => false,
        ]);

        // Aunque la tutela esté retirada, sigue siendo la traza de quién pudo
        // ver a este menor. Borrar el perfil de la familia no se la lleva.
        $this->expectException(QueryException::class);

        DB::table('tutores_legales')->where('id', $tutor->id)->delete();
    }

    public function test_un_aviso_emitido_sobrevive_a_su_examen(): void
    {
        $e = $this->escenario();

        $evaluable = Evaluable::create([
            'imparticion_id' => $e['imparticion']->id, 'tipo' => 'EXAMEN', 'titulo' => 'Tema 1',
            'fecha_prevista' => now()->toDateString(), 'creado_por' => $e['profesor']->id,
        ]);

        $aviso = Notificacion::create([
            'tipo' => 'EXAMEN_PROGRAMADO', 'emisor_id' => $e['userDocente']->id,
            'evaluable_id' => $evaluable->id, 'grupo_id' => $e['grupo']->id,
            'titulo' => 'Examen del tema 1',
        ]);

        DB::table('evaluables')->where('id', $evaluable->id)->delete();

        $this->assertDatabaseHas('notificaciones', ['id' => $aviso->id, 'evaluable_id' => null]);
    }

    // ------------------------------------ la configuración sí se va con ella

    public function test_el_plan_de_estudios_se_va_con_la_asignatura(): void
    {
        $e = $this->escenario();
        $e['asignatura']->cursos()->sync([$e['curso']->id]);

        // Sin matrículas ni imparticiones, la asignatura sí se puede borrar.
        DB::table('imparticiones')->where('id', $e['imparticion']->id)->delete();
        DB::table('asignaturas')->where('id', $e['asignatura']->id)->delete();

        $this->assertDatabaseEmpty('asignatura_curso');
    }

    public function test_el_reparto_de_un_aviso_se_va_con_el_aviso(): void
    {
        $e     = $this->escenario();
        $tutor = $this->userFamilia();

        $aviso = Notificacion::create([
            'tipo' => 'AVISO_GENERAL', 'emisor_id' => $e['userDocente']->id,
            'grupo_id' => $e['grupo']->id, 'titulo' => 'Salida al museo',
        ]);

        DB::table('notificacion_destinatarios')->insert([
            'notificacion_id' => $aviso->id, 'tutor_legal_id' => $tutor->id, 'alumno_id' => $e['alumno']->id,
        ]);

        DB::table('notificaciones')->where('id', $aviso->id)->delete();

        $this->assertDatabaseEmpty('notificacion_destinatarios');
    }

    // ------------------------------------------------------------ unicidad

    public function test_no_hay_dos_asignaturas_con_el_mismo_nombre_en_un_centro(): void
    {
        $e = $this->escenario();

        $this->expectException(QueryException::class);

        Asignatura::create([
            'centro_id' => $e['centro']->id, 'denominacion' => 'Matematicas', 'horas_semanales' => 3,
        ]);
    }

    public function test_dos_centros_si_pueden_llamar_igual_a_su_asignatura(): void
    {
        $e = $this->escenario();

        $otro = Centro::create([
            'denominacion' => 'IES Otro', 'cif' => 'Q1111111X', 'email' => 'otro@prueba.test',
        ]);

        $gemela = Asignatura::create([
            'centro_id' => $otro->id, 'denominacion' => 'Matematicas', 'horas_semanales' => 3,
        ]);

        $this->assertNotSame($e['asignatura']->id, $gemela->id);
    }

    public function test_no_hay_dos_cursos_con_la_misma_denominacion_en_un_centro(): void
    {
        $e = $this->escenario();

        $this->expectException(QueryException::class);

        Curso::create([
            'centro_id' => $e['centro']->id, 'denominacion' => '3º ESO', 'anio_academico' => '2027/2028',
        ]);
    }

    // ------------------------------- la aplicación avisa antes de que falle

    public function test_el_parentesco_solo_admite_los_valores_del_dominio(): void
    {
        $e = $this->escenario();
        // Con centro: si no, el controlador ni siquiera encuentra al tutor y
        // devolvería un 404 en vez de un error de validación.
        $tutor = $this->userFamilia($e['centro']->id);

        $this->actingAs($e['direccion'])
            ->post(route('centro.tutores.vincular', $tutor), [
                'alumno_id'  => $e['alumno']->id,
                'parentesco' => 'PRIMO SEGUNDO',
            ])
            ->assertSessionHasErrors('parentesco');

        $this->assertDatabaseCount('tutelas', 0);
    }

    public function test_no_se_borra_un_alumno_con_una_tutela_ya_retirada(): void
    {
        $e     = $this->escenario();
        $tutor = $this->userFamilia();

        DB::table('tutelas')->insert([
            'tutor_legal_id' => $tutor->id, 'alumno_id' => $e['alumno']->id,
            'parentesco' => 'MADRE', 'vinculado_en' => now(), 'activa' => false,
        ]);

        // Antes esto pasaba el control de la aplicación —que solo miraba las
        // tutelas activas— y reventaba contra la clave foránea.
        $this->actingAs($e['direccion'])
            ->delete(route('centro.alumnos.destroy', $e['alumno']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('alumnos', ['id' => $e['alumno']->id]);
    }

    public function test_no_se_borra_un_profesor_que_puso_examenes(): void
    {
        $e = $this->escenario();

        Evaluable::create([
            'imparticion_id' => $e['imparticion']->id, 'tipo' => 'TAREA', 'titulo' => 'Ejercicios 1 a 10',
            'fecha_prevista' => now()->toDateString(), 'creado_por' => $e['profesor']->id,
        ]);

        // Se le retira la impartición: sin la comprobación nueva, el
        // controlador lo dejaría pasar y fallaría `evaluables.creado_por`.
        DB::table('imparticiones')->where('id', $e['imparticion']->id)->update([
            'profesor_id' => Profesor::create([
                'user_id' => User::create([
                    'name' => 'Otro', 'email' => 'otro.docente@prueba.test', 'password' => 'secreto-de-prueba',
                    'rol' => User::ROL_PROFESOR, 'centro_id' => $e['centro']->id, 'activo' => true,
                ])->id,
                'centro_id' => $e['centro']->id, 'nombre' => 'Luis', 'apellidos' => 'Soto',
            ])->id,
        ]);

        $this->actingAs($e['direccion'])
            ->delete(route('centro.profesores.destroy', $e['profesor']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('profesores', ['id' => $e['profesor']->id]);
    }
}
