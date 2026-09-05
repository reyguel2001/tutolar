<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\Auditoria;
use App\Models\Centro;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Imparticion;
use App\Models\Profesor;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Zona de dirección.
 *
 * La regla que estas pruebas defienden es una sola y es la más importante de
 * toda la aplicación: **un centro no ve ni toca nada de otro centro**. Está
 * implementada en `BaseCentroController`, y aquí se comprueba pantalla por
 * pantalla que nadie se la ha saltado.
 */
class PanelCentroTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function instituto(string $sufijo): array
    {
        $centro = Centro::create([
            'denominacion' => "IES {$sufijo}",
            'cif'          => "Q00000{$sufijo}",
            'email'        => "centro.{$sufijo}@prueba.test",
        ]);

        $curso  = Curso::create([
            'centro_id' => $centro->id, 'denominacion' => '3º ESO', 'anio_academico' => '2026/2027',
        ]);
        $grupo  = Grupo::create(['curso_id' => $curso->id, 'denominacion' => 'A']);

        $asignatura = Asignatura::create([
            'centro_id' => $centro->id, 'denominacion' => "Matematicas {$sufijo}", 'horas_semanales' => 4,
        ]);

        $direccion = User::create([
            'name' => "Direccion {$sufijo}", 'email' => "direccion.{$sufijo}@prueba.test",
            'password' => 'secreto-de-prueba', 'rol' => User::ROL_CENTRO,
            'centro_id' => $centro->id, 'activo' => true,
        ]);

        $userDocente = User::create([
            'name' => "Docente {$sufijo}", 'email' => "docente.{$sufijo}@prueba.test",
            'password' => 'secreto-de-prueba', 'rol' => User::ROL_PROFESOR,
            'centro_id' => $centro->id, 'activo' => true,
        ]);

        $profesor = Profesor::create([
            'user_id' => $userDocente->id, 'centro_id' => $centro->id,
            'nombre' => 'Ana', 'apellidos' => "Ruiz {$sufijo}",
        ]);

        $alumno = Alumno::create([
            'centro_id' => $centro->id, 'grupo_id' => $grupo->id,
            'nombre' => 'Hugo', 'apellidos' => "Vega {$sufijo}",
            'fecha_nacimiento' => now()->subYears(14)->toDateString(), 'activo' => true,
        ]);
        $alumno->asignaturas()->attach($asignatura->id);

        return compact('centro', 'curso', 'grupo', 'asignatura', 'direccion', 'profesor', 'alumno');
    }

    // ------------------------------------------------------- aislamiento

    public function test_el_listado_solo_muestra_alumnos_del_centro_propio(): void
    {
        $mio  = $this->instituto('a');
        $otro = $this->instituto('b');

        $respuesta = $this->actingAs($mio['direccion'])->get(route('centro.alumnos'));

        $respuesta->assertOk();
        $respuesta->assertSee($mio['alumno']->apellidos);
        $respuesta->assertDontSee($otro['alumno']->apellidos);
    }

    public function test_no_se_abre_la_ficha_de_un_alumno_de_otro_centro(): void
    {
        $mio  = $this->instituto('a');
        $otro = $this->instituto('b');

        $this->actingAs($mio['direccion'])
            ->get(route('centro.alumno', $otro['alumno']))
            ->assertNotFound();
    }

    public function test_no_se_edita_un_alumno_de_otro_centro(): void
    {
        $mio  = $this->instituto('a');
        $otro = $this->instituto('b');

        $this->actingAs($mio['direccion'])
            ->put(route('centro.alumnos.update', $otro['alumno']), [
                'nombre'           => 'Intruso',
                'apellidos'        => 'Intruso',
                'fecha_nacimiento' => '2011-05-05',
                'grupo_id'         => $otro['grupo']->id,
            ])
            ->assertNotFound();

        $this->assertSame('Hugo', $otro['alumno']->fresh()->nombre);
    }

    public function test_no_se_matricula_a_un_alumno_en_un_grupo_de_otro_centro(): void
    {
        $mio  = $this->instituto('a');
        $otro = $this->instituto('b');

        $this->actingAs($mio['direccion'])
            ->post(route('centro.alumnos.store'), [
                'nombre'           => 'Nuevo',
                'apellidos'        => 'Alumno',
                'fecha_nacimiento' => '2011-05-05',
                'grupo_id'         => $otro['grupo']->id,   // grupo ajeno
            ])
            ->assertForbidden();
    }

    public function test_la_matricula_no_alcanza_asignaturas_de_otro_centro(): void
    {
        $mio  = $this->instituto('a');
        $otro = $this->instituto('b');

        $this->actingAs($mio['direccion'])
            ->post(route('centro.matriculas.store'), [
                'alumno_id'     => $mio['alumno']->id,
                'asignatura_id' => $otro['asignatura']->id,
            ])
            ->assertNotFound();
    }

    public function test_el_listado_de_asignaturas_no_filtra_las_de_otro_centro(): void
    {
        $mio  = $this->instituto('a');
        $otro = $this->instituto('b');

        $respuesta = $this->actingAs($mio['direccion'])->get(route('centro.asignaturas.index'));

        $respuesta->assertOk();
        $respuesta->assertSee($mio['asignatura']->denominacion);
        $respuesta->assertDontSee($otro['asignatura']->denominacion);
    }

    // ------------------------------------------------------- roles

    public function test_un_profesor_no_entra_en_la_zona_de_direccion(): void
    {
        $mio = $this->instituto('a');

        $this->actingAs($mio['profesor']->user)
            ->get(route('centro.cuentas.index'))
            ->assertForbidden();
    }

    public function test_sin_sesion_la_zona_de_direccion_manda_al_acceso(): void
    {
        $this->get(route('centro.tutores.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------- escrituras

    public function test_dar_de_alta_un_alumno_lo_matricula_y_deja_auditoria(): void
    {
        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])->post(route('centro.alumnos.store'), [
            'nombre'           => 'Nerea',
            'apellidos'        => 'Cano Prieto',
            'fecha_nacimiento' => '2011-09-01',
            'grupo_id'         => $mio['grupo']->id,
            'asignaturas'      => [$mio['asignatura']->id],
        ])->assertSessionHasNoErrors();

        $alumno = Alumno::where('nombre', 'Nerea')->first();

        $this->assertNotNull($alumno);
        $this->assertSame($mio['centro']->id, $alumno->centro_id);
        $this->assertTrue($alumno->asignaturas->contains('id', $mio['asignatura']->id));
        $this->assertTrue(Auditoria::where('entidad', 'alumno')->where('entidad_id', $alumno->id)->exists());
    }

    public function test_dar_de_alta_un_tutor_crea_cuenta_y_perfil(): void
    {
        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])->post(route('centro.tutores.store'), [
            'nombre'                => 'Marta',
            'apellidos'             => 'Vega Blanco',
            'telefono'              => '600111222',
            'email'                 => 'marta@familia.test',
            'password'              => 'contrasena-larga',
            'password_confirmation' => 'contrasena-larga',
            'activo'                => '1',
            'alumnos'               => [$mio['alumno']->id],
            'parentesco'            => 'MADRE',
        ])->assertSessionHasNoErrors();

        $usuario = User::where('email', 'marta@familia.test')->first();

        $this->assertNotNull($usuario);
        $this->assertSame(User::ROL_TUTOR_LEGAL, $usuario->rol);
        $this->assertSame($mio['centro']->id, $usuario->centro_id);

        $tutor = TutorLegal::where('user_id', $usuario->id)->first();
        $this->assertNotNull($tutor);
        $this->assertTrue($tutor->alumnos()->where('alumnos.id', $mio['alumno']->id)->exists());
    }

    public function test_un_tutor_nuevo_no_puede_vincularse_a_un_alumno_de_otro_centro(): void
    {
        $mio  = $this->instituto('a');
        $otro = $this->instituto('b');

        $this->actingAs($mio['direccion'])->post(route('centro.tutores.store'), [
            'nombre'                => 'Intrusa',
            'apellidos'             => 'Intrusa',
            'telefono'              => '600111222',
            'email'                 => 'intrusa@familia.test',
            'password'              => 'contrasena-larga',
            'password_confirmation' => 'contrasena-larga',
            'activo'                => '1',
            'alumnos'               => [$otro['alumno']->id],
        ])->assertSessionHasNoErrors();

        $tutor = TutorLegal::where('nombre', 'Intrusa')->first();

        $this->assertNotNull($tutor);
        $this->assertSame(0, $tutor->alumnos()->count(), 'El alumno ajeno se ignora, no se vincula.');
    }

    // ------------------------------------------------------- borrados seguros

    public function test_no_se_borra_una_asignatura_con_matriculas(): void
    {
        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])
            ->from(route('centro.asignaturas.index'))
            ->delete(route('centro.asignaturas.destroy', $mio['asignatura']))
            ->assertSessionHas('error');

        $this->assertNotNull($mio['asignatura']->fresh());
    }

    public function test_no_se_borra_un_grupo_con_alumnos(): void
    {
        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])
            ->from(route('centro.grupos.index'))
            ->delete(route('centro.grupos.destroy', $mio['grupo']))
            ->assertSessionHas('error');

        $this->assertNotNull($mio['grupo']->fresh());
    }

    public function test_no_se_borra_un_profesor_que_imparte(): void
    {
        $mio = $this->instituto('a');

        Imparticion::create([
            'profesor_id'   => $mio['profesor']->id,
            'asignatura_id' => $mio['asignatura']->id,
            'grupo_id'      => $mio['grupo']->id,
        ]);

        $this->actingAs($mio['direccion'])
            ->from(route('centro.profesores.index'))
            ->delete(route('centro.profesores.destroy', $mio['profesor']))
            ->assertSessionHas('error');

        $this->assertNotNull($mio['profesor']->fresh());
    }

    public function test_no_hay_dos_grupos_con_la_misma_letra_en_el_mismo_curso(): void
    {
        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])
            ->from(route('centro.grupos.create'))
            ->post(route('centro.grupos.store'), [
                'curso_id'     => $mio['curso']->id,
                'denominacion' => 'A',   // ya existe
            ])
            ->assertSessionHasErrors('denominacion');
    }

    public function test_una_cuenta_no_puede_desactivarse_a_si_misma(): void
    {
        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])
            ->from(route('centro.cuentas.index'))
            ->patch(route('centro.cuentas.estado', $mio['direccion']))
            ->assertSessionHas('error');

        $this->assertTrue($mio['direccion']->fresh()->activo);
    }

    public function test_desactivar_una_cuenta_le_corta_el_acceso(): void
    {
        $mio = $this->instituto('a');
        $docente = $mio['profesor']->user;

        $this->actingAs($mio['direccion'])
            ->patch(route('centro.cuentas.estado', $docente))
            ->assertSessionHas('exito');

        $this->assertFalse($docente->fresh()->activo);

        // EnsureRol corta a las cuentas desactivadas antes de mirar el rol.
        $this->actingAs($docente->fresh())
            ->get(route('profesor.grupos'))
            ->assertRedirect(route('login'));
    }

    public function test_los_grupos_de_asignatura_se_filtran_por_curso(): void
    {
        $mio = $this->instituto('a');

        // Impartición en el curso que ya trae el escenario.
        Imparticion::create([
            'profesor_id'   => $mio['profesor']->id,
            'asignatura_id' => $mio['asignatura']->id,
            'grupo_id'      => $mio['grupo']->id,
        ]);

        // Segundo curso del mismo centro, con su grupo y su asignatura.
        $otroCurso = Curso::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => '4º ESO', 'anio_academico' => '2026/2027',
        ]);
        $otroGrupo = Grupo::create(['curso_id' => $otroCurso->id, 'denominacion' => 'A']);
        $historia  = Asignatura::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => 'Historia', 'horas_semanales' => 3,
        ]);
        Imparticion::create([
            'profesor_id'   => $mio['profesor']->id,
            'asignatura_id' => $historia->id,
            'grupo_id'      => $otroGrupo->id,
        ]);

        // Sin filtro salen las dos.
        $this->actingAs($mio['direccion'])
            ->get(route('centro.imparticiones.index'))
            ->assertSee('Historia')
            ->assertSee($mio['asignatura']->denominacion);

        // Filtrando por el primer curso, la de 4º desaparece.
        $this->actingAs($mio['direccion'])
            ->get(route('centro.imparticiones.index', ['curso' => $mio['curso']->id]))
            ->assertOk()
            ->assertSee($mio['asignatura']->denominacion)
            ->assertDontSee('Historia');

        // Y al revés.
        $this->actingAs($mio['direccion'])
            ->get(route('centro.imparticiones.index', ['curso' => $otroCurso->id]))
            ->assertSee('Historia')
            ->assertDontSee($mio['asignatura']->denominacion);
    }

    public function test_las_matriculas_se_filtran_por_curso(): void
    {
        $mio = $this->instituto('a');

        // Segundo curso del mismo centro, con su grupo y su alumno.
        $otroCurso = Curso::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => '4º ESO', 'anio_academico' => '2026/2027',
        ]);
        $otroGrupo = Grupo::create(['curso_id' => $otroCurso->id, 'denominacion' => 'A']);

        $deCuarto = Alumno::create([
            'centro_id' => $mio['centro']->id, 'grupo_id' => $otroGrupo->id,
            'nombre' => 'Irene', 'apellidos' => 'Pardo Solis',
            'fecha_nacimiento' => now()->subYears(15)->toDateString(), 'activo' => true,
        ]);

        // Sin filtro salen los dos.
        $this->actingAs($mio['direccion'])
            ->get(route('centro.matriculas.index'))
            ->assertSee($mio['alumno']->apellidos)
            ->assertSee($deCuarto->apellidos);

        // Filtrando por 3º ESO desaparece el de 4º.
        $this->actingAs($mio['direccion'])
            ->get(route('centro.matriculas.index', ['curso' => $mio['curso']->id]))
            ->assertOk()
            ->assertSee($mio['alumno']->apellidos)
            ->assertDontSee($deCuarto->apellidos);

        // Y al revés.
        $this->actingAs($mio['direccion'])
            ->get(route('centro.matriculas.index', ['curso' => $otroCurso->id]))
            ->assertSee($deCuarto->apellidos)
            ->assertDontSee($mio['alumno']->apellidos);
    }

    // ------------------------------------------------------- plan de estudios

    public function test_al_crear_una_asignatura_se_eligen_sus_cursos(): void
    {
        $mio = $this->instituto('a');

        $cuarto = Curso::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => '4º ESO', 'anio_academico' => '2026/2027',
        ]);

        $this->actingAs($mio['direccion'])->post(route('centro.asignaturas.store'), [
            'denominacion'    => 'Fisica y Quimica',
            'horas_semanales' => 3,
            'cursos'          => [$cuarto->id],
        ])->assertSessionHasNoErrors();

        $asignatura = Asignatura::where('denominacion', 'Fisica y Quimica')->first();

        $this->assertNotNull($asignatura);
        $this->assertSame([$cuarto->id], $asignatura->cursos->pluck('id')->all());
        $this->assertTrue($asignatura->seImparteEn($cuarto->id));
        $this->assertFalse($asignatura->seImparteEn($mio['curso']->id));
    }

    public function test_una_asignatura_sin_cursos_marcados_vale_para_todos(): void
    {
        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])->post(route('centro.asignaturas.store'), [
            'denominacion'    => 'Tutoria',
            'horas_semanales' => 1,
        ])->assertSessionHasNoErrors();

        $asignatura = Asignatura::where('denominacion', 'Tutoria')->first();

        $this->assertTrue($asignatura->cursos->isEmpty());
        $this->assertTrue($asignatura->seImparteEn($mio['curso']->id), 'Sin plan marcado, disponible en todos.');
    }

    public function test_no_se_puede_meter_una_asignatura_en_el_curso_de_otro_centro(): void
    {
        $mio  = $this->instituto('a');
        $otro = $this->instituto('b');

        $this->actingAs($mio['direccion'])->post(route('centro.asignaturas.store'), [
            'denominacion'    => 'Colada',
            'horas_semanales' => 2,
            'cursos'          => [$otro['curso']->id],
        ])->assertSessionHasNoErrors();

        $asignatura = Asignatura::where('denominacion', 'Colada')->first();

        $this->assertTrue($asignatura->cursos->isEmpty(), 'El curso ajeno se descarta.');
    }

    public function test_no_se_asigna_docente_a_un_curso_fuera_del_plan(): void
    {
        $mio = $this->instituto('a');

        $cuarto     = Curso::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => '4º ESO', 'anio_academico' => '2026/2027',
        ]);
        $solo4      = Asignatura::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => 'Filosofia', 'horas_semanales' => 2,
        ]);
        $solo4->cursos()->attach($cuarto->id);

        // El grupo del escenario es de 3º ESO, donde Filosofía no entra.
        $this->actingAs($mio['direccion'])
            ->from(route('centro.imparticiones.create'))
            ->post(route('centro.imparticiones.store'), [
                'profesor_id'   => $mio['profesor']->id,
                'asignatura_id' => $solo4->id,
                'grupo_id'      => $mio['grupo']->id,
            ])
            ->assertSessionHasErrors('asignatura_id');

        $this->assertSame(0, Imparticion::count());
    }

    public function test_no_se_matricula_a_un_alumno_en_una_materia_fuera_de_su_curso(): void
    {
        $mio = $this->instituto('a');

        $cuarto = Curso::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => '4º ESO', 'anio_academico' => '2026/2027',
        ]);
        $solo4 = Asignatura::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => 'Filosofia', 'horas_semanales' => 2,
        ]);
        $solo4->cursos()->attach($cuarto->id);

        $this->actingAs($mio['direccion'])
            ->from(route('centro.matriculas.index'))
            ->post(route('centro.matriculas.store'), [
                'alumno_id'     => $mio['alumno']->id,
                'asignatura_id' => $solo4->id,
            ])
            ->assertSessionHas('error');

        $this->assertFalse($mio['alumno']->fresh()->asignaturas->contains('id', $solo4->id));
    }

    public function test_el_alta_de_alumno_rechaza_materias_fuera_de_su_curso(): void
    {
        $mio = $this->instituto('a');

        $cuarto = Curso::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => '4º ESO', 'anio_academico' => '2026/2027',
        ]);
        $solo4 = Asignatura::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => 'Filosofia', 'horas_semanales' => 2,
        ]);
        $solo4->cursos()->attach($cuarto->id);

        $this->actingAs($mio['direccion'])
            ->from(route('centro.alumnos.create'))
            ->post(route('centro.alumnos.store'), [
                'nombre'           => 'Julia',
                'apellidos'        => 'Serrano Paz',
                'fecha_nacimiento' => '2011-03-03',
                'grupo_id'         => $mio['grupo']->id,   // 3º ESO
                'asignaturas'      => [$solo4->id],
            ])
            ->assertSessionHasErrors('asignaturas');

        $this->assertNull(Alumno::where('nombre', 'Julia')->first());
    }

    // ------------------------- el triángulo asignatura · docencia · matrícula

    public function test_crear_un_grupo_de_asignatura_puede_matricular_al_grupo(): void
    {
        $mio = $this->instituto('a');

        $nueva = Asignatura::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => 'Musica', 'horas_semanales' => 2,
        ]);

        $this->actingAs($mio['direccion'])
            ->post(route('centro.imparticiones.store'), [
                'profesor_id'       => $mio['profesor']->id,
                'asignatura_id'     => $nueva->id,
                'grupo_id'          => $mio['grupo']->id,
                'matricular_grupo'  => '1',
            ])
            ->assertSessionHas('exito');

        $this->assertTrue(
            $mio['alumno']->fresh()->asignaturas->contains('id', $nueva->id),
            'Al marcar la casilla, el alumno del grupo queda matriculado.',
        );
    }

    public function test_sin_matricular_al_grupo_la_asignacion_avisa_de_que_nadie_la_cursa(): void
    {
        $mio = $this->instituto('a');

        $nueva = Asignatura::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => 'Musica', 'horas_semanales' => 2,
        ]);

        // Sin la casilla queda una clase que no cursa nadie: el profesor podría
        // programar exámenes que ningún alumno vería. Se avisa, no se calla.
        $this->actingAs($mio['direccion'])
            ->post(route('centro.imparticiones.store'), [
                'profesor_id'   => $mio['profesor']->id,
                'asignatura_id' => $nueva->id,
                'grupo_id'      => $mio['grupo']->id,
            ])
            ->assertSessionHas('aviso')
            ->assertSessionMissing('exito');

        $this->assertFalse($mio['alumno']->fresh()->asignaturas->contains('id', $nueva->id));
    }

    public function test_se_matricula_al_grupo_desde_el_listado_de_grupos_de_asignatura(): void
    {
        $mio = $this->instituto('a');

        $nueva = Asignatura::create([
            'centro_id' => $mio['centro']->id, 'denominacion' => 'Musica', 'horas_semanales' => 2,
        ]);

        $imparticion = Imparticion::create([
            'profesor_id' => $mio['profesor']->id, 'asignatura_id' => $nueva->id, 'grupo_id' => $mio['grupo']->id,
        ]);

        $this->actingAs($mio['direccion'])
            ->from(route('centro.imparticiones.index'))
            ->post(route('centro.imparticiones.matricular', $imparticion))
            ->assertSessionHas('exito');

        $this->assertTrue($mio['alumno']->fresh()->asignaturas->contains('id', $nueva->id));

        // Repetirlo no duplica ni revienta la clave primaria compuesta.
        $this->actingAs($mio['direccion'])
            ->from(route('centro.imparticiones.index'))
            ->post(route('centro.imparticiones.matricular', $imparticion))
            ->assertSessionHas('aviso');
    }

    public function test_no_se_matricula_al_grupo_de_una_asignacion_de_otro_centro(): void
    {
        $mio   = $this->instituto('a');
        $ajeno = $this->instituto('b');

        $suya = Imparticion::create([
            'profesor_id'   => $ajeno['profesor']->id,
            'asignatura_id' => $ajeno['asignatura']->id,
            'grupo_id'      => $ajeno['grupo']->id,
        ]);

        $this->actingAs($mio['direccion'])
            ->post(route('centro.imparticiones.matricular', $suya))
            ->assertNotFound();
    }

    public function test_el_listado_de_asignaturas_cuenta_docencia_y_matriculas(): void
    {
        $mio = $this->instituto('a');   // el alumno ya está matriculado en su asignatura

        Imparticion::create([
            'profesor_id'   => $mio['profesor']->id,
            'asignatura_id' => $mio['asignatura']->id,
            'grupo_id'      => $mio['grupo']->id,
        ]);

        $respuesta = $this->actingAs($mio['direccion'])->get(route('centro.asignaturas.index'));

        $respuesta->assertOk();

        $fila = $respuesta->viewData('asignaturas')->firstWhere('id', $mio['asignatura']->id);

        $this->assertSame(1, (int) $fila->imparticiones_count);
        $this->assertSame(1, (int) $fila->alumnos_count, 'La matrícula tiene que verse antes de intentar borrar.');
    }

    public function test_un_alumno_de_baja_no_cuenta_como_matriculado(): void
    {
        $mio = $this->instituto('a');
        $mio['alumno']->update(['activo' => false]);

        $respuesta = $this->actingAs($mio['direccion'])->get(route('centro.asignaturas.index'));

        $fila = $respuesta->viewData('asignaturas')->firstWhere('id', $mio['asignatura']->id);

        $this->assertSame(0, (int) $fila->alumnos_count);
    }

    // ------------------------------- como mucho dos familias por alumno

    /** @return \App\Models\TutorLegal */
    private function familia(array $mio, string $sufijo)
    {
        $user = User::create([
            'name' => "Familia {$sufijo}", 'email' => "familia.{$sufijo}@prueba.test",
            'password' => 'secreto-de-prueba', 'rol' => User::ROL_TUTOR_LEGAL,
            'centro_id' => $mio['centro']->id, 'activo' => true,
        ]);

        return TutorLegal::create([
            'user_id' => $user->id, 'nombre' => 'Tutor', 'apellidos' => $sufijo, 'telefono' => '600000000',
        ]);
    }

    public function test_un_alumno_admite_dos_familias(): void
    {
        $mio = $this->instituto('a');

        foreach ([$this->familia($mio, 'madre'), $this->familia($mio, 'padre')] as $tutor) {
            $this->actingAs($mio['direccion'])
                ->from(route('centro.tutores.show', $tutor))
                ->post(route('centro.tutores.vincular', $tutor), [
                    'alumno_id' => $mio['alumno']->id, 'parentesco' => 'MADRE',
                ])
                ->assertSessionHas('exito');
        }

        $this->assertSame(2, DB::table('tutelas')->where('alumno_id', $mio['alumno']->id)->count());
    }

    public function test_la_tercera_familia_no_entra(): void
    {
        $mio = $this->instituto('a');

        foreach (['uno', 'dos'] as $sufijo) {
            $this->actingAs($mio['direccion'])->post(
                route('centro.tutores.vincular', $this->familia($mio, $sufijo)),
                ['alumno_id' => $mio['alumno']->id, 'parentesco' => 'MADRE'],
            );
        }

        $tercera = $this->familia($mio, 'tres');

        $this->actingAs($mio['direccion'])
            ->from(route('centro.tutores.show', $tercera))
            ->post(route('centro.tutores.vincular', $tercera), [
                'alumno_id' => $mio['alumno']->id, 'parentesco' => 'PADRE',
            ])
            ->assertSessionHas('error');

        $this->assertSame(2, DB::table('tutelas')->where('alumno_id', $mio['alumno']->id)->count());
    }

    /** Retirar una libera el sitio: el hueco lo marcan las tutelas activas. */
    public function test_al_retirar_una_familia_cabe_otra(): void
    {
        $mio      = $this->instituto('a');
        $primera  = $this->familia($mio, 'uno');
        $segunda  = $this->familia($mio, 'dos');
        $tercera  = $this->familia($mio, 'tres');

        foreach ([$primera, $segunda] as $tutor) {
            $this->actingAs($mio['direccion'])->post(route('centro.tutores.vincular', $tutor), [
                'alumno_id' => $mio['alumno']->id, 'parentesco' => 'MADRE',
            ]);
        }

        $this->actingAs($mio['direccion'])
            ->delete(route('centro.tutores.desvincular', [$primera, $mio['alumno']->id]));

        $this->actingAs($mio['direccion'])
            ->from(route('centro.tutores.show', $tercera))
            ->post(route('centro.tutores.vincular', $tercera), [
                'alumno_id' => $mio['alumno']->id, 'parentesco' => 'PADRE',
            ])
            ->assertSessionHas('exito');

        // Tres filas, dos activas: la retirada se conserva como traza.
        $this->assertSame(3, DB::table('tutelas')->where('alumno_id', $mio['alumno']->id)->count());
        $this->assertSame(2, DB::table('tutelas')
            ->where('alumno_id', $mio['alumno']->id)->where('activa', true)->count());
    }

    /** En el alta con varios alumnos, el que está lleno se avisa por su nombre. */
    public function test_el_alta_de_tutor_avisa_del_alumno_que_ya_esta_completo(): void
    {
        $mio = $this->instituto('a');

        foreach (['uno', 'dos'] as $sufijo) {
            $this->actingAs($mio['direccion'])->post(
                route('centro.tutores.vincular', $this->familia($mio, $sufijo)),
                ['alumno_id' => $mio['alumno']->id, 'parentesco' => 'MADRE'],
            );
        }

        $this->actingAs($mio['direccion'])
            ->post(route('centro.tutores.store'), [
                'nombre' => 'Luisa', 'apellidos' => 'Prado', 'telefono' => '611111111',
                'email' => 'luisa.prado@prueba.test', 'password' => 'secreto-de-prueba',
                'password_confirmation' => 'secreto-de-prueba',
                'alumnos' => [$mio['alumno']->id], 'parentesco' => 'TUTOR',
            ])
            ->assertSessionHas('aviso');

        // La cuenta sí se crea; lo que no se hace es la vinculación.
        $this->assertNotNull(User::where('email', 'luisa.prado@prueba.test')->first());
        $this->assertSame(2, DB::table('tutelas')->where('alumno_id', $mio['alumno']->id)->count());
    }

    // -------------------------------------------------------- avatares

    public function test_el_centro_sube_y_quita_la_foto_de_un_alumno(): void
    {
        \Illuminate\Support\Facades\Storage::fake('avatares');

        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])->put(route('centro.alumnos.update', $mio['alumno']), [
            'nombre'           => $mio['alumno']->nombre,
            'apellidos'        => $mio['alumno']->apellidos,
            'fecha_nacimiento' => $mio['alumno']->fecha_nacimiento->toDateString(),
            'grupo_id'         => $mio['grupo']->id,
            'foto'             => \Illuminate\Http\UploadedFile::fake()->image('hugo.jpg', 300, 300),
        ])->assertSessionHasNoErrors();

        $archivo = $mio['alumno']->fresh()->foto_url;

        $this->assertNotNull($archivo);
        \Illuminate\Support\Facades\Storage::disk('avatares')->assertExists($archivo);

        $this->actingAs($mio['direccion'])->put(route('centro.alumnos.update', $mio['alumno']), [
            'nombre'           => $mio['alumno']->nombre,
            'apellidos'        => $mio['alumno']->apellidos,
            'fecha_nacimiento' => $mio['alumno']->fecha_nacimiento->toDateString(),
            'grupo_id'         => $mio['grupo']->id,
            'quitar_foto'      => '1',
        ]);

        $this->assertNull($mio['alumno']->fresh()->foto_url);
        \Illuminate\Support\Facades\Storage::disk('avatares')->assertMissing($archivo);
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza(): void
    {
        \Illuminate\Support\Facades\Storage::fake('avatares');

        $mio = $this->instituto('a');

        $this->actingAs($mio['direccion'])
            ->from(route('centro.alumnos.edit', $mio['alumno']))
            ->put(route('centro.alumnos.update', $mio['alumno']), [
                'nombre'           => $mio['alumno']->nombre,
                'apellidos'        => $mio['alumno']->apellidos,
                'fecha_nacimiento' => $mio['alumno']->fecha_nacimiento->toDateString(),
                'grupo_id'         => $mio['grupo']->id,
                'foto'             => \Illuminate\Http\UploadedFile::fake()->create('virus.php', 40, 'text/php'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertNull($mio['alumno']->fresh()->foto_url);
    }

    public function test_las_paginas_de_la_zona_de_direccion_responden(): void
    {
        $mio = $this->instituto('a');

        foreach ([
            'centro.panel', 'centro.alumnos', 'centro.tutores.index', 'centro.profesores.index',
            'centro.asignaturas.index', 'centro.grupos.index', 'centro.imparticiones.index',
            'centro.matriculas.index', 'centro.cuentas.index',
        ] as $ruta) {
            $this->actingAs($mio['direccion'])
                ->get(route($ruta))
                ->assertOk("La ruta {$ruta} no responde 200.");
        }
    }
}
