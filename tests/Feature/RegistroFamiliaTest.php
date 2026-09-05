<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Centro;
use App\Models\CodigoVinculacion;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Tutela;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Registro de familias con código de vinculación.
 *
 * Estas pruebas defienden una sola idea: **quien no tiene código no llega a un
 * menor**. Un formulario de registro abierto sobre datos de menores es un
 * agujero, no una comodidad, así que casi todo lo que hay aquí comprueba lo que
 * *no* se puede hacer.
 */
class RegistroFamiliaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function escenario(string $sufijo = 'a'): array
    {
        $centro = Centro::create([
            'denominacion' => "IES {$sufijo}", 'cif' => "Q0000{$sufijo}",
            'email' => "centro.{$sufijo}@prueba.test",
        ]);

        $curso = Curso::create([
            'centro_id' => $centro->id, 'denominacion' => '3º ESO', 'anio_academico' => '2026/2027',
        ]);
        $grupo = Grupo::create(['curso_id' => $curso->id, 'denominacion' => 'A']);

        $direccion = User::create([
            'name' => "Direccion {$sufijo}", 'email' => "direccion.{$sufijo}@prueba.test",
            'password' => 'secreto-de-prueba', 'rol' => User::ROL_CENTRO,
            'centro_id' => $centro->id, 'activo' => true,
        ]);

        $alumno = Alumno::create([
            'centro_id' => $centro->id, 'grupo_id' => $grupo->id,
            'nombre' => 'Hugo', 'apellidos' => "Vega {$sufijo}",
            'fecha_nacimiento' => now()->subYears(14)->toDateString(), 'activo' => true,
        ]);

        return compact('centro', 'curso', 'grupo', 'direccion', 'alumno');
    }

    /** @return array<string, string> */
    private function formulario(string $codigo, array $cambios = []): array
    {
        return array_merge([
            'codigo'                => $codigo,
            'nombre'                => 'Marta',
            'apellidos'             => 'Vega Blanco',
            'telefono'              => '600111222',
            'parentesco'            => 'MADRE',
            'email'                 => 'marta@familia.test',
            'password'              => 'contrasena-larga',
            'password_confirmation' => 'contrasena-larga',
        ], $cambios);
    }

    // ------------------------------------------------------- camino feliz

    public function test_con_un_codigo_valido_se_crea_la_cuenta_y_la_tutela(): void
    {
        $e      = $this->escenario();
        $codigo = CodigoVinculacion::generarPara($e['alumno']);

        $this->post(route('registro.store'), $this->formulario($codigo->codigo))
            ->assertRedirect(route('familia.inicio'));

        $usuario = User::where('email', 'marta@familia.test')->first();

        $this->assertNotNull($usuario);
        $this->assertSame(User::ROL_TUTOR_LEGAL, $usuario->rol);
        // El centro sale del alumno, no de nada que haya escrito quien se registra.
        $this->assertSame($e['centro']->id, $usuario->centro_id);

        $tutor = TutorLegal::where('user_id', $usuario->id)->first();
        $this->assertNotNull($tutor);
        $this->assertTrue($tutor->alumnos->contains('id', $e['alumno']->id));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_codigo_se_gasta_al_usarlo(): void
    {
        $e      = $this->escenario();
        $codigo = CodigoVinculacion::generarPara($e['alumno']);

        $this->post(route('registro.store'), $this->formulario($codigo->codigo));

        $this->assertNotNull($codigo->fresh()->usado_en);

        // Y ya no vale para nadie más.
        $this->post('/salir');
        $this->post(route('registro.store'), $this->formulario($codigo->codigo, [
            'email' => 'otra@familia.test',
        ]))->assertSessionHasErrors('codigo');

        $this->assertNull(User::where('email', 'otra@familia.test')->first());
    }

    /** Da igual cómo lo copie del papel: minúsculas, guiones o espacios. */
    public function test_el_codigo_se_acepta_como_venga_escrito(): void
    {
        $e      = $this->escenario();
        $codigo = CodigoVinculacion::generarPara($e['alumno']);
        $sucio  = strtolower(substr($codigo->codigo, 0, 4)) . '- ' . strtolower(substr($codigo->codigo, 4));

        $this->post(route('registro.store'), $this->formulario($sucio))
            ->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('email', 'marta@familia.test')->first());
    }

    // ---------------------------------------------- lo que no se puede hacer

    public function test_sin_codigo_no_hay_registro(): void
    {
        $this->escenario();

        $this->post(route('registro.store'), $this->formulario(''))
            ->assertSessionHasErrors('codigo');

        $this->assertSame(0, TutorLegal::count());
    }

    public function test_un_codigo_inventado_no_vale(): void
    {
        $this->escenario();

        $this->post(route('registro.store'), $this->formulario('ZZZZ9999'))
            ->assertSessionHasErrors('codigo');

        $this->assertSame(0, TutorLegal::count());
    }

    public function test_un_codigo_caducado_no_vale(): void
    {
        $e      = $this->escenario();
        $codigo = CodigoVinculacion::generarPara($e['alumno']);
        $codigo->update(['caduca_en' => now()->subDay()]);

        $this->post(route('registro.store'), $this->formulario($codigo->codigo))
            ->assertSessionHasErrors('codigo');

        $this->assertSame(0, TutorLegal::count());
    }

    /**
     * El formulario no pregunta por el alumno ni por el centro, pero aunque
     * alguien los añada a mano en la petición, se ignoran: los dos salen del
     * código.
     */
    public function test_no_se_puede_elegir_alumno_por_la_puerta_de_atras(): void
    {
        $mio   = $this->escenario('a');
        $ajeno = $this->escenario('b');

        $codigo = CodigoVinculacion::generarPara($mio['alumno']);

        $this->post(route('registro.store'), $this->formulario($codigo->codigo, [
            'alumno_id' => (string) $ajeno['alumno']->id,
            'centro_id' => (string) $ajeno['centro']->id,
        ]))->assertSessionHasNoErrors();

        $tutor = TutorLegal::first();

        $this->assertTrue($tutor->alumnos->contains('id', $mio['alumno']->id));
        $this->assertFalse($tutor->alumnos->contains('id', $ajeno['alumno']->id));
        $this->assertSame($mio['centro']->id, User::where('email', 'marta@familia.test')->first()->centro_id);
    }

    public function test_el_registro_respeta_el_maximo_de_dos_familias(): void
    {
        $e = $this->escenario();

        // Dos familias ya vinculadas.
        foreach (['una', 'otra'] as $i => $sufijo) {
            $u = User::create([
                'name' => "Familia {$sufijo}", 'email' => "f{$sufijo}@prueba.test",
                'password' => 'secreto-de-prueba', 'rol' => User::ROL_TUTOR_LEGAL, 'activo' => true,
            ]);
            $t = TutorLegal::create([
                'user_id' => $u->id, 'nombre' => 'Tutor', 'apellidos' => $sufijo, 'telefono' => '600000000',
            ]);
            DB::table('tutelas')->insert([
                'tutor_legal_id' => $t->id, 'alumno_id' => $e['alumno']->id,
                'parentesco' => 'TUTOR', 'vinculado_en' => now(), 'activa' => true,
            ]);
        }

        $codigo = CodigoVinculacion::generarPara($e['alumno']);

        $this->post(route('registro.store'), $this->formulario($codigo->codigo))
            ->assertSessionHasErrors('codigo');

        $this->assertSame(2, Tutela::activasDe($e['alumno']->id));
        $this->assertNull(User::where('email', 'marta@familia.test')->first());
        // Y el código no se ha quemado: sigue sirviendo si se libera una plaza.
        $this->assertNull($codigo->fresh()->usado_en);
    }

    public function test_no_se_puede_registrar_con_un_correo_que_ya_existe(): void
    {
        $e      = $this->escenario();
        $codigo = CodigoVinculacion::generarPara($e['alumno']);

        $this->post(route('registro.store'), $this->formulario($codigo->codigo, [
            'email' => $e['direccion']->email,
        ]))->assertSessionHasErrors('email');

        $this->assertSame(0, TutorLegal::count());
    }

    public function test_las_contrasenas_tienen_que_coincidir(): void
    {
        $e      = $this->escenario();
        $codigo = CodigoVinculacion::generarPara($e['alumno']);

        $this->post(route('registro.store'), $this->formulario($codigo->codigo, [
            'password_confirmation' => 'otra-cosa-distinta',
        ]))->assertSessionHasErrors('password');

        $this->assertSame(0, TutorLegal::count());
    }

    // ------------------------------------------------- generación del código

    public function test_el_centro_genera_codigos_de_sus_alumnos(): void
    {
        $e = $this->escenario();

        $this->actingAs($e['direccion'])
            ->from(route('centro.alumno', $e['alumno']))
            ->post(route('centro.alumnos.codigo', $e['alumno']))
            ->assertSessionHas('exito');

        $this->assertSame(1, CodigoVinculacion::where('alumno_id', $e['alumno']->id)->count());
    }

    public function test_un_centro_no_genera_codigos_de_alumnos_de_otro(): void
    {
        $mio   = $this->escenario('a');
        $ajeno = $this->escenario('b');

        $this->actingAs($mio['direccion'])
            ->post(route('centro.alumnos.codigo', $ajeno['alumno']))
            ->assertNotFound();

        $this->assertSame(0, CodigoVinculacion::count());
    }

    public function test_la_pantalla_de_registro_responde_y_el_acceso_enlaza_con_ella(): void
    {
        $this->get(route('registro'))->assertOk()->assertSee('Crear cuenta de familia');
        $this->get(route('login'))->assertOk()->assertSee(route('registro'));
    }

    /** Con `?codigo=` en la URL se ve a quién se va a vincular antes de escribir nada. */
    public function test_el_codigo_en_la_url_enseña_de_quien_es(): void
    {
        $e      = $this->escenario();
        $codigo = CodigoVinculacion::generarPara($e['alumno']);

        $this->get(route('registro', ['codigo' => $codigo->codigo]))
            ->assertOk()
            ->assertSee($e['alumno']->nombre_completo);
    }
}
