<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Centro;
use App\Models\CodigoVinculacion;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\SolicitudVinculacion;
use App\Models\Tutela;
use App\Models\TutorLegal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Solicitar un código sin tenerlo.
 *
 * La pantalla es pública, así que lo que hay que demostrar es lo que **no**
 * hace: no crea cuentas, no crea tutelas, no emite códigos, y sobre todo **no
 * dice si el alumno existe**. Un formulario público que confirmara eso sería un
 * comprobador de matrículas abierto a cualquiera.
 */
class SolicitudVinculacionTest extends TestCase
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
            'nombre' => 'Lucia', 'apellidos' => "Ramos Ortega {$sufijo}",
            'fecha_nacimiento' => now()->subYears(14)->toDateString(), 'activo' => true,
        ]);

        return compact('centro', 'curso', 'grupo', 'direccion', 'alumno');
    }

    /** @return array<string, mixed> */
    private function formulario(int $centroId, array $cambios = []): array
    {
        return array_merge([
            'centro_id'        => $centroId,
            'alumno_nombre'    => 'Lucia',
            'alumno_apellidos' => 'Ramos Ortega a',
            'alumno_curso'     => '3º ESO A',
            'nombre'           => 'Marta',
            'apellidos'        => 'Vega Blanco',
            'email'            => 'marta@familia.test',
            'telefono'         => '600111222',
            'parentesco'       => 'MADRE',
            'mensaje'          => 'Soy su madre.',
        ], $cambios);
    }

    // ------------------------------------------------ lo que sí hace

    public function test_la_solicitud_queda_pendiente_para_el_centro(): void
    {
        $e = $this->escenario();

        $this->post(route('solicitud.store'), $this->formulario($e['centro']->id))
            ->assertRedirect(route('solicitud.create'))
            ->assertSessionHas('exito');

        $s = SolicitudVinculacion::first();

        $this->assertNotNull($s);
        $this->assertSame(SolicitudVinculacion::PENDIENTE, $s->estado);
        $this->assertSame($e['centro']->id, $s->centro_id);
    }

    public function test_la_pantalla_responde_y_el_registro_enlaza_con_ella(): void
    {
        $this->escenario();

        $this->get(route('solicitud.create'))->assertOk()->assertSee('Pedir un código de vinculación');
        $this->get(route('registro'))->assertOk()->assertSee(route('solicitud.create'));
        $this->get(route('login'))->assertOk()->assertSee(route('solicitud.create'));
    }

    // -------------------------------------- lo que NO hace, que es lo que importa

    public function test_solicitar_no_crea_cuenta_ni_tutela_ni_codigo(): void
    {
        $e = $this->escenario();

        $this->post(route('solicitud.store'), $this->formulario($e['centro']->id));

        $this->assertNull(User::where('email', 'marta@familia.test')->first());
        $this->assertSame(0, TutorLegal::count());
        $this->assertSame(0, CodigoVinculacion::count());
        $this->assertSame(0, DB::table('tutelas')->count());
        $this->assertGuest();
    }

    /**
     * El mensaje es idéntico exista o no el alumno. Si cambiara, el formulario
     * sería un comprobador de matrículas: se prueban nombres hasta acertar.
     */
    public function test_la_respuesta_no_delata_si_el_alumno_existe(): void
    {
        $e = $this->escenario();

        $conAlumnoReal = $this->post(route('solicitud.store'), $this->formulario($e['centro']->id));
        $inventado     = $this->post(route('solicitud.store'), $this->formulario($e['centro']->id, [
            'alumno_nombre'    => 'Fulanito',
            'alumno_apellidos' => 'De Tal Que No Existe',
            'email'            => 'otra@familia.test',
        ]));

        $this->assertSame(
            $conAlumnoReal->getSession()->get('exito'),
            $inventado->getSession()->get('exito'),
            'El mensaje tiene que ser el mismo en los dos casos.',
        );

        // Y las dos se guardan igual: no se descarta la que no cuadra, porque
        // descartarla sería decidir sin mirar los registros de verdad.
        $this->assertSame(2, SolicitudVinculacion::count());
    }

    public function test_no_se_puede_solicitar_a_un_centro_inexistente(): void
    {
        $this->escenario();

        $this->post(route('solicitud.store'), $this->formulario(9999))
            ->assertSessionHasErrors('centro_id');

        $this->assertSame(0, SolicitudVinculacion::count());
    }

    // ------------------------------------------------- la bandeja del centro

    public function test_al_aprobar_se_emite_el_codigo_del_alumno_señalado(): void
    {
        $e = $this->escenario();
        $this->post(route('solicitud.store'), $this->formulario($e['centro']->id));

        $s = SolicitudVinculacion::first();

        $this->actingAs($e['direccion'])
            ->from(route('centro.solicitudes.index'))
            ->post(route('centro.solicitudes.aprobar', $s), ['alumno_id' => $e['alumno']->id])
            ->assertSessionHas('exito');

        $s->refresh();

        $this->assertSame(SolicitudVinculacion::APROBADA, $s->estado);
        $this->assertSame($e['direccion']->id, $s->resuelta_por);
        $this->assertNotNull($s->codigo_emitido);

        $codigo = CodigoVinculacion::find($s->codigo_emitido);
        $this->assertNotNull($codigo);
        $this->assertSame($e['alumno']->id, $codigo->alumno_id);
    }

    public function test_aprobar_exige_señalar_a_que_alumno(): void
    {
        $e = $this->escenario();
        $this->post(route('solicitud.store'), $this->formulario($e['centro']->id));

        $this->actingAs($e['direccion'])
            ->from(route('centro.solicitudes.index'))
            ->post(route('centro.solicitudes.aprobar', SolicitudVinculacion::first()))
            ->assertSessionHasErrors('alumno_id');

        $this->assertSame(0, CodigoVinculacion::count());
    }

    public function test_no_se_aprueba_señalando_a_un_alumno_de_otro_centro(): void
    {
        $mio   = $this->escenario('a');
        $ajeno = $this->escenario('b');

        $this->post(route('solicitud.store'), $this->formulario($mio['centro']->id));

        $this->actingAs($mio['direccion'])
            ->post(route('centro.solicitudes.aprobar', SolicitudVinculacion::first()),
                   ['alumno_id' => $ajeno['alumno']->id])
            ->assertForbidden();

        $this->assertSame(0, CodigoVinculacion::count());
    }

    public function test_un_centro_no_ve_ni_resuelve_solicitudes_de_otro(): void
    {
        $mio   = $this->escenario('a');
        $ajeno = $this->escenario('b');

        $this->post(route('solicitud.store'), $this->formulario($ajeno['centro']->id, [
            'alumno_apellidos' => 'Ramos Ortega b',
        ]));

        $ajena = SolicitudVinculacion::first();

        $this->actingAs($mio['direccion'])
            ->get(route('centro.solicitudes.index'))
            ->assertOk()
            ->assertDontSee('Ramos Ortega b');

        $this->actingAs($mio['direccion'])
            ->post(route('centro.solicitudes.aprobar', $ajena), ['alumno_id' => $mio['alumno']->id])
            ->assertNotFound();
    }

    public function test_no_se_aprueba_si_el_alumno_ya_tiene_dos_familias(): void
    {
        $e = $this->escenario();
        $this->post(route('solicitud.store'), $this->formulario($e['centro']->id));

        foreach (['una', 'otra'] as $sufijo) {
            $u = User::create([
                'name' => "F {$sufijo}", 'email' => "f{$sufijo}@prueba.test",
                'password' => 'secreto-de-prueba', 'rol' => User::ROL_TUTOR_LEGAL, 'activo' => true,
            ]);
            $t = TutorLegal::create([
                'user_id' => $u->id, 'nombre' => 'T', 'apellidos' => $sufijo, 'telefono' => '600000000',
            ]);
            DB::table('tutelas')->insert([
                'tutor_legal_id' => $t->id, 'alumno_id' => $e['alumno']->id,
                'parentesco' => 'TUTOR', 'vinculado_en' => now(), 'activa' => true,
            ]);
        }

        $this->actingAs($e['direccion'])
            ->from(route('centro.solicitudes.index'))
            ->post(route('centro.solicitudes.aprobar', SolicitudVinculacion::first()),
                   ['alumno_id' => $e['alumno']->id])
            ->assertSessionHas('error');

        $this->assertSame(0, CodigoVinculacion::count());
        $this->assertSame(SolicitudVinculacion::PENDIENTE, SolicitudVinculacion::first()->estado);
        $this->assertSame(2, Tutela::activasDe($e['alumno']->id));
    }

    public function test_descartar_una_solicitud_no_emite_nada(): void
    {
        $e = $this->escenario();
        $this->post(route('solicitud.store'), $this->formulario($e['centro']->id));

        $this->actingAs($e['direccion'])
            ->from(route('centro.solicitudes.index'))
            ->post(route('centro.solicitudes.rechazar', SolicitudVinculacion::first()))
            ->assertSessionHas('exito');

        $this->assertSame(SolicitudVinculacion::RECHAZADA, SolicitudVinculacion::first()->estado);
        $this->assertSame(0, CodigoVinculacion::count());
    }

    /** El ciclo entero: solicitar → aprobar → registrarse con el código emitido. */
    public function test_el_ciclo_completo_acaba_en_una_cuenta_vinculada(): void
    {
        $e = $this->escenario();

        $this->post(route('solicitud.store'), $this->formulario($e['centro']->id));

        $this->actingAs($e['direccion'])->post(
            route('centro.solicitudes.aprobar', SolicitudVinculacion::first()),
            ['alumno_id' => $e['alumno']->id],
        );

        $codigo = SolicitudVinculacion::first()->codigo_emitido;

        $this->post('/salir');
        $this->post(route('registro.store'), [
            'codigo'                => $codigo,
            'nombre'                => 'Marta',
            'apellidos'             => 'Vega Blanco',
            'telefono'              => '600111222',
            'parentesco'            => 'MADRE',
            'email'                 => 'marta@familia.test',
            'password'              => 'contrasena-larga',
            'password_confirmation' => 'contrasena-larga',
        ])->assertRedirect(route('familia.inicio'));

        $tutor = TutorLegal::first();

        $this->assertNotNull($tutor);
        $this->assertTrue($tutor->alumnos->contains('id', $e['alumno']->id));
    }

    public function test_una_familia_no_entra_en_la_bandeja_del_centro(): void
    {
        $e = $this->escenario();

        $u = User::create([
            'name' => 'Familia', 'email' => 'familia@prueba.test', 'password' => 'secreto-de-prueba',
            'rol' => User::ROL_TUTOR_LEGAL, 'centro_id' => $e['centro']->id, 'activo' => true,
        ]);

        $this->actingAs($u)->get(route('centro.solicitudes.index'))->assertForbidden();
    }
}
