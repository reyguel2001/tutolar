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
use App\Models\Notificacion;
use App\Models\NotificacionDestinatario;
use App\Models\Profesor;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Avisos del profesorado a las familias.
 *
 * Lo que se comprueba aquí, además de que funcione: que el aviso llegue
 * exactamente a quien tiene que llegar. Ni a las familias de otro grupo, ni a
 * las de alumnos que no cursan esa materia, ni a las de otro profesor.
 */
class NotificacionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function escenario(): array
    {
        $centro = Centro::create([
            'denominacion' => 'IES Prueba', 'cif' => 'Q0000001', 'email' => 'centro@prueba.test',
        ]);
        $curso = Curso::create([
            'centro_id' => $centro->id, 'denominacion' => '3º ESO', 'anio_academico' => '2026/2027',
        ]);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'denominacion' => 'A']);

        $mates  = Asignatura::create(['centro_id' => $centro->id, 'denominacion' => 'Matematicas', 'horas_semanales' => 4]);
        $musica = Asignatura::create(['centro_id' => $centro->id, 'denominacion' => 'Musica', 'horas_semanales' => 2]);

        $profesor      = $this->docente($centro, 'ana');
        $otroProfesor  = $this->docente($centro, 'luis');

        $imparticion = Imparticion::create([
            'profesor_id' => $profesor->id, 'asignatura_id' => $mates->id, 'grupo_id' => $grupo->id,
        ]);

        // Otra materia del mismo grupo, impartida por otro docente.
        $ajena = Imparticion::create([
            'profesor_id' => $otroProfesor->id, 'asignatura_id' => $musica->id, 'grupo_id' => $grupo->id,
        ]);

        // Tres alumnos del mismo grupo, con situaciones distintas.
        $conFamilia    = $this->alumno($centro, $grupo, 'Hugo',  [$mates->id]);
        $sinMatematicas = $this->alumno($centro, $grupo, 'Nerea', [$musica->id]);
        $sinFamilia    = $this->alumno($centro, $grupo, 'Alba',  [$mates->id]);

        $tutor = $this->tutor($centro, 'marta', $conFamilia);
        $this->tutor($centro, 'jose', $sinMatematicas);

        return compact(
            'centro', 'curso', 'grupo', 'mates', 'musica', 'profesor', 'otroProfesor',
            'imparticion', 'ajena', 'conFamilia', 'sinMatematicas', 'sinFamilia', 'tutor',
        );
    }

    private function docente(Centro $centro, string $slug): Profesor
    {
        $usuario = User::create([
            'name' => "Docente {$slug}", 'email' => "{$slug}@prueba.test",
            'password' => 'secreto-de-prueba', 'rol' => User::ROL_PROFESOR,
            'centro_id' => $centro->id, 'activo' => true,
        ]);

        return Profesor::create([
            'user_id' => $usuario->id, 'centro_id' => $centro->id,
            'nombre' => ucfirst($slug), 'apellidos' => 'Docente',
        ]);
    }

    /** @param array<int, int> $asignaturas */
    private function alumno(Centro $centro, Grupo $grupo, string $nombre, array $asignaturas): Alumno
    {
        $alumno = Alumno::create([
            'centro_id' => $centro->id, 'grupo_id' => $grupo->id,
            'nombre' => $nombre, 'apellidos' => 'Apellido',
            'fecha_nacimiento' => now()->subYears(14)->toDateString(), 'activo' => true,
        ]);
        $alumno->asignaturas()->attach($asignaturas);

        return $alumno;
    }

    private function tutor(Centro $centro, string $slug, Alumno $alumno): TutorLegal
    {
        $usuario = User::create([
            'name' => "Familia {$slug}", 'email' => "{$slug}@familia.test",
            'password' => 'secreto-de-prueba', 'rol' => User::ROL_TUTOR_LEGAL,
            'centro_id' => $centro->id, 'telefono' => '600000000', 'activo' => true,
        ]);

        $tutor = TutorLegal::create([
            'user_id' => $usuario->id, 'nombre' => ucfirst($slug),
            'apellidos' => 'Familia', 'telefono' => '600000000',
        ]);

        DB::table('tutelas')->insert([
            'tutor_legal_id' => $tutor->id, 'alumno_id' => $alumno->id,
            'parentesco' => 'MADRE', 'vinculado_en' => now(), 'activa' => true,
        ]);

        return $tutor;
    }

    // ------------------------------------------------------ programar

    public function test_programar_un_examen_crea_el_evaluable_y_reparte_el_aviso(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor']->user)->post(route('profesor.notificaciones.store'), [
            'tipo'              => Notificacion::EXAMEN_PROGRAMADO,
            'imparticion_id'    => $e['imparticion']->id,
            'titulo'            => 'Examen del tema 4',
            'fecha_prevista'    => now()->addWeek()->toDateString(),
            'puntuacion_maxima' => 10,
            'mensaje'           => 'Entra el tema entero.',
        ])->assertSessionHasNoErrors();

        $evaluable = Evaluable::first();
        $this->assertNotNull($evaluable, 'El aviso tiene que crear la prueba.');
        $this->assertSame('EXAMEN', $evaluable->tipo);
        $this->assertSame('NOTIFICADO', $evaluable->estado);
        $this->assertSame($e['imparticion']->id, $evaluable->imparticion_id);

        $notificacion = Notificacion::first();
        $this->assertNotNull($notificacion);
        $this->assertSame(Notificacion::EXAMEN_PROGRAMADO, $notificacion->tipo);
        $this->assertSame($e['grupo']->id, $notificacion->grupo_id);
        $this->assertStringContainsString('Matematicas', $notificacion->titulo);
    }

    public function test_el_aviso_solo_llega_a_las_familias_que_cursan_esa_materia(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor']->user)->post(route('profesor.notificaciones.store'), [
            'tipo'              => Notificacion::EXAMEN_PROGRAMADO,
            'imparticion_id'    => $e['imparticion']->id,
            'titulo'            => 'Examen del tema 4',
            'fecha_prevista'    => now()->addWeek()->toDateString(),
            'puntuacion_maxima' => 10,
        ]);

        $destinatarios = NotificacionDestinatario::all();

        // Solo Hugo: Nerea no cursa Matemáticas y Alba no tiene familia.
        $this->assertCount(1, $destinatarios);
        $this->assertSame($e['conFamilia']->id, $destinatarios->first()->alumno_id);
        $this->assertSame($e['tutor']->id, $destinatarios->first()->tutor_legal_id);
    }

    public function test_avisa_de_los_alumnos_sin_familia_vinculada(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor']->user)->post(route('profesor.notificaciones.store'), [
            'tipo'              => Notificacion::TAREA_ASIGNADA,
            'imparticion_id'    => $e['imparticion']->id,
            'titulo'            => 'Ejercicios de la pagina 87',
            'fecha_prevista'    => now()->addDays(3)->toDateString(),
            'puntuacion_maxima' => 10,
        ])->assertSessionHas('aviso');   // Alba no tiene familia
    }

    public function test_programar_sin_titulo_no_cuela(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor']->user)
            ->from(route('profesor.notificaciones.create'))
            ->post(route('profesor.notificaciones.store'), [
                'tipo'           => Notificacion::EXAMEN_PROGRAMADO,
                'imparticion_id' => $e['imparticion']->id,
                'fecha_prevista' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasErrors(['titulo', 'puntuacion_maxima']);

        $this->assertSame(0, Notificacion::count());
        $this->assertSame(0, Evaluable::count());
    }

    // ------------------------------------------------------ resultados

    public function test_avisar_de_notas_deja_la_prueba_pendiente_de_registro(): void
    {
        $e = $this->escenario();

        $evaluable = Evaluable::create([
            'imparticion_id' => $e['imparticion']->id, 'tipo' => 'EXAMEN',
            'titulo' => 'Examen del tema 3', 'fecha_prevista' => now()->subWeek()->toDateString(),
            'puntuacion_maxima' => 10, 'estado' => 'NOTIFICADO', 'creado_por' => $e['profesor']->id,
        ]);

        $this->actingAs($e['profesor']->user)->post(route('profesor.notificaciones.store'), [
            'tipo'           => Notificacion::RESULTADOS_EXAMEN,
            'imparticion_id' => $e['imparticion']->id,
            'evaluable_id'   => $evaluable->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('PENDIENTE_REGISTRO', $evaluable->fresh()->estado);
        $this->assertSame(Notificacion::RESULTADOS_EXAMEN, Notificacion::first()->tipo);
    }

    public function test_avisar_de_notas_sin_elegir_prueba_no_cuela(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor']->user)
            ->from(route('profesor.notificaciones.create'))
            ->post(route('profesor.notificaciones.store'), [
                'tipo'           => Notificacion::RESULTADOS_EXAMEN,
                'imparticion_id' => $e['imparticion']->id,
            ])
            ->assertSessionHasErrors('evaluable_id');
    }

    public function test_no_se_admite_una_prueba_de_otra_materia(): void
    {
        $e = $this->escenario();

        $ajeno = Evaluable::create([
            'imparticion_id' => $e['ajena']->id, 'tipo' => 'EXAMEN',
            'titulo' => 'Examen de musica', 'fecha_prevista' => now()->toDateString(),
            'puntuacion_maxima' => 10, 'estado' => 'NOTIFICADO', 'creado_por' => $e['otroProfesor']->id,
        ]);

        $this->actingAs($e['profesor']->user)
            ->from(route('profesor.notificaciones.create'))
            ->post(route('profesor.notificaciones.store'), [
                'tipo'           => Notificacion::RESULTADOS_EXAMEN,
                'imparticion_id' => $e['imparticion']->id,
                'evaluable_id'   => $ajeno->id,
            ])
            ->assertSessionHasErrors('evaluable_id');
    }

    // ------------------------------------------------------ aislamiento

    public function test_un_profesor_no_avisa_sobre_la_materia_de_otro(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor']->user)->post(route('profesor.notificaciones.store'), [
            'tipo'              => Notificacion::EXAMEN_PROGRAMADO,
            'imparticion_id'    => $e['ajena']->id,       // es de otro docente
            'titulo'            => 'Examen colado',
            'fecha_prevista'    => now()->addWeek()->toDateString(),
            'puntuacion_maxima' => 10,
        ])->assertForbidden();

        $this->assertSame(0, Notificacion::count());
    }

    public function test_una_familia_no_alcanza_la_pantalla_del_profesorado(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['tutor']->user)
            ->get(route('profesor.notificaciones.index'))
            ->assertForbidden();
    }

    public function test_deja_traza_en_auditorias(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['profesor']->user)->post(route('profesor.notificaciones.store'), [
            'tipo'              => Notificacion::EXAMEN_PROGRAMADO,
            'imparticion_id'    => $e['imparticion']->id,
            'titulo'            => 'Examen del tema 4',
            'fecha_prevista'    => now()->addWeek()->toDateString(),
            'puntuacion_maxima' => 10,
        ]);

        $traza = Auditoria::where('entidad', 'notificacion')->first();

        $this->assertNotNull($traza);
        $this->assertSame(Notificacion::first()->id, $traza->entidad_id);
        $this->assertSame(1, $traza->valor_nuevo['destinatarios']);
    }

    // ------------------------------------------------------ bandeja de la familia

    public function test_la_familia_ve_su_aviso_y_queda_marcado_como_leido(): void
    {
        $e = $this->escenario();
        $this->emitirExamen($e);

        $this->actingAs($e['tutor']->user)
            ->get(route('familia.avisos.index'))
            ->assertOk()
            ->assertSee('Examen del tema 4');

        $this->assertNotNull(NotificacionDestinatario::first()->fresh()->leida_en);
        $this->assertNull(NotificacionDestinatario::first()->fresh()->confirmada_en, 'Leído no es confirmado.');
    }

    public function test_confirmar_marca_la_fecha_y_la_ve_el_profesor(): void
    {
        $e = $this->escenario();
        $this->emitirExamen($e);

        $destinatario = NotificacionDestinatario::first();

        $this->actingAs($e['tutor']->user)
            ->from(route('familia.avisos.index'))
            ->patch(route('familia.avisos.confirmar', $destinatario->id))
            ->assertSessionHas('exito');

        $this->assertNotNull($destinatario->fresh()->confirmada_en);

        $this->actingAs($e['profesor']->user)
            ->get(route('profesor.notificaciones.index'))
            ->assertOk()
            ->assertSee('1/1');
    }

    public function test_no_se_confirma_el_aviso_de_otra_familia(): void
    {
        $e = $this->escenario();
        $this->emitirExamen($e);

        $otroTutor = TutorLegal::where('id', '!=', $e['tutor']->id)->first();
        $destinatario = NotificacionDestinatario::first();

        $this->actingAs($otroTutor->user)
            ->patch(route('familia.avisos.confirmar', $destinatario->id))
            ->assertNotFound();

        $this->assertNull($destinatario->fresh()->confirmada_en);
    }

    /** @param array<string, mixed> $e */
    private function emitirExamen(array $e): void
    {
        $this->actingAs($e['profesor']->user)->post(route('profesor.notificaciones.store'), [
            'tipo'              => Notificacion::EXAMEN_PROGRAMADO,
            'imparticion_id'    => $e['imparticion']->id,
            'titulo'            => 'Examen del tema 4',
            'fecha_prevista'    => now()->addWeek()->toDateString(),
            'puntuacion_maxima' => 10,
        ]);
    }
}
