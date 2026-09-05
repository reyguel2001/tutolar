<?php

namespace Tests\Unit;

use App\Services\RendimientoService;
use App\Support\Nivel;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas del motor de rendimiento.
 *
 * Son las que hay que poder enseñar en la defensa: cubren las reglas de negocio
 * del documento de análisis, no solo que el código no reviente.
 */
class RendimientoTest extends TestCase
{
    private RendimientoService $motor;

    protected function setUp(): void
    {
        $this->motor = new RendimientoService();
    }

    /** Una nota de 8 sobre 10 son 80 puntos sobre 100. */
    public function test_normaliza_a_escala_de_cien(): void
    {
        $this->assertSame(80.0, $this->motor->normalizar(8, 10));
        $this->assertSame(80.0, $this->motor->normalizar(4, 5));
        $this->assertSame(80.0, $this->motor->normalizar(16, 20));
    }

    public function test_rechaza_una_puntuacion_maxima_invalida(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->motor->normalizar(5, 0);
    }

    /** RN-05: sin datos suficientes no se emite juicio de rendimiento. */
    public function test_sin_resultados_devuelve_gris(): void
    {
        $ira = $this->motor->calcularIra([]);
        $this->assertNull($ira['valor']);
        $this->assertSame(Nivel::SIN_DATOS, $ira['nivel']);
    }

    /** Un solo resultado tampoco basta, aunque sea excelente. */
    public function test_un_solo_resultado_devuelve_gris(): void
    {
        $ira = $this->motor->calcularIra([
            ['nota' => 95.0, 'dias' => 1, 'tipo' => 'EXAMEN'],
        ]);

        $this->assertNull($ira['valor'], 'Un único resultado no puede producir un índice');
        $this->assertSame(Nivel::SIN_DATOS, $ira['nivel']);
        $this->assertSame(1, $ira['num_resultados']);
    }

    /** Con solo exámenes, el índice se calcula sin el peso de las tareas. */
    public function test_solo_examenes(): void
    {
        $ira = $this->motor->calcularIra([
            ['nota' => 40.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
            ['nota' => 40.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
        ]);

        $this->assertSame(40.0, $ira['valor']);
        $this->assertSame(Nivel::BAJO, $ira['nivel']);
        $this->assertNull($ira['media_tareas']);
    }

    /** Ponderación 60/40 entre exámenes y tareas. */
    public function test_pondera_examenes_y_tareas(): void
    {
        $ira = $this->motor->calcularIra([
            ['nota' => 50.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
            ['nota' => 50.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
            ['nota' => 100.0, 'dias' => 0, 'tipo' => 'TAREA'],
            ['nota' => 100.0, 'dias' => 0, 'tipo' => 'TAREA'],
        ]);

        // 50 × 0,60 + 100 × 0,40 = 70
        $this->assertSame(70.0, $ira['valor']);
        $this->assertSame(Nivel::ALTO, $ira['nivel']);
    }

    /** Un resultado de hace 90 días pesa exactamente la mitad que uno de hoy. */
    public function test_peso_por_recencia(): void
    {
        $this->assertSame(1.0, $this->motor->pesoRecencia(0));
        $this->assertEqualsWithDelta(0.5, $this->motor->pesoRecencia(90), 0.0001);
        $this->assertEqualsWithDelta(0.25, $this->motor->pesoRecencia(180), 0.0001);
    }

    /** El resultado reciente arrastra el índice hacia sí. */
    public function test_lo_reciente_pesa_mas(): void
    {
        $ira = $this->motor->calcularIra([
            ['nota' => 100.0, 'dias' => 0,   'tipo' => 'EXAMEN'],
            ['nota' => 0.0,   'dias' => 180, 'tipo' => 'EXAMEN'],
        ]);

        // Pesos 1 y 0,25 → (100×1 + 0×0,25) / 1,25 = 80
        $this->assertSame(80.0, $ira['valor']);
    }

    /** Los umbrales son inclusivos por arriba: 50 ya es MEDIO y 70 ya es ALTO. */
    public function test_fronteras_del_semaforo(): void
    {
        $this->assertSame(Nivel::BAJO,      Nivel::de(49.9));
        $this->assertSame(Nivel::MEDIO,     Nivel::de(50.0));
        $this->assertSame(Nivel::MEDIO,     Nivel::de(69.9));
        $this->assertSame(Nivel::ALTO,      Nivel::de(70.0));
        $this->assertSame(Nivel::SIN_DATOS, Nivel::de(null));
    }

    /** Un centro puede configurar sus propios umbrales. */
    public function test_umbrales_configurables_por_centro(): void
    {
        $motor = new RendimientoService(0.60, 0.40, 40, 80);

        $ira = $motor->calcularIra([
            ['nota' => 45.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
            ['nota' => 45.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
        ]);

        // Con el umbral bajo en 40, un 45 ya no está en BAJO.
        $this->assertSame(Nivel::MEDIO, $ira['nivel']);
    }

    /** El IRG pondera por horas semanales. */
    public function test_irg_pondera_por_horas(): void
    {
        $irg = $this->motor->calcularIrg([
            ['ira' => 40.0, 'horas' => 4],
            ['ira' => 80.0, 'horas' => 1],
        ]);

        // (40×4 + 80×1) / 5 = 48
        $this->assertSame(48.0, $irg['valor']);
        $this->assertSame(Nivel::BAJO, $irg['nivel']);
    }

    /** Las asignaturas en gris no entran en el promedio global. */
    public function test_irg_ignora_las_asignaturas_sin_datos(): void
    {
        $irg = $this->motor->calcularIrg([
            ['ira' => 80.0, 'horas' => 3],
            ['ira' => null, 'horas' => 3],
        ]);

        $this->assertSame(80.0, $irg['valor']);
    }

    public function test_irg_gris_si_ninguna_asignatura_tiene_datos(): void
    {
        $irg = $this->motor->calcularIrg([
            ['ira' => null, 'horas' => 3],
            ['ira' => null, 'horas' => 4],
        ]);

        $this->assertNull($irg['valor']);
        $this->assertSame(Nivel::SIN_DATOS, $irg['nivel']);
    }

    public function test_tendencia(): void
    {
        $this->assertSame('ASCENDENTE',  $this->motor->calcularTendencia(70, 60)['tipo']);
        $this->assertSame('ESTABLE',     $this->motor->calcularTendencia(62, 60)['tipo']);
        $this->assertSame('DESCENDENTE', $this->motor->calcularTendencia(50, 60)['tipo']);
        $this->assertSame('DESCONOCIDA', $this->motor->calcularTendencia(null, 60)['tipo']);
    }

    public function test_isf(): void
    {
        $this->assertSame(87.5, $this->motor->calcularIsf(14, 16));
        $this->assertNull($this->motor->calcularIsf(0, 0), 'Sin notificaciones no hay ISF que medir');
    }

    /** La alerta salta al entrar en rojo, no cada vez que se está en rojo. */
    public function test_alerta_al_cruzar_a_rojo(): void
    {
        $this->assertTrue($this->motor->requiereAlerta(45, 55));
        $this->assertFalse($this->motor->requiereAlerta(45, 48), 'Ya estaba en rojo: no se repite la alerta');
        $this->assertFalse($this->motor->requiereAlerta(75, 72));
    }

    /** También salta ante una caída fuerte, aunque no se cruce el umbral. */
    public function test_alerta_por_caida_fuerte(): void
    {
        $this->assertTrue($this->motor->requiereAlerta(74, 92));
        $this->assertFalse($this->motor->requiereAlerta(85, 92));
    }

    /** El estado gris nunca muestra un cero. */
    public function test_la_cifra_gris_es_un_guion(): void
    {
        $this->assertSame('—', Nivel::cifra(null));
        $this->assertSame('0', Nivel::cifra(0.0));
    }

    // ------------------------------ desglose de exámenes frente a ejercicios

    public function test_el_desglose_separa_examenes_de_tareas(): void
    {
        $d = $this->motor->desglosePorTipo([
            ['nota' => 90.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
            ['nota' => 70.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
            ['nota' => 40.0, 'dias' => 0, 'tipo' => 'TAREA'],
        ]);

        $this->assertSame(80.0, $d['EXAMEN']['media']);
        $this->assertSame(2, $d['EXAMEN']['n']);
        $this->assertSame(40.0, $d['TAREA']['media']);
        $this->assertSame(1, $d['TAREA']['n']);
    }

    /** Sin notas de un tipo, media nula: el gráfico dibujará «sin notas», no un cero. */
    public function test_un_tipo_sin_notas_no_vale_cero(): void
    {
        $d = $this->motor->desglosePorTipo([
            ['nota' => 60.0, 'dias' => 0, 'tipo' => 'EXAMEN'],
        ]);

        $this->assertSame(60.0, $d['EXAMEN']['media']);
        $this->assertNull($d['TAREA']['media']);
        $this->assertSame(0, $d['TAREA']['n']);
    }

    /** El desglose usa la misma ponderación por recencia que el índice. */
    public function test_el_desglose_pondera_por_recencia(): void
    {
        $d = $this->motor->desglosePorTipo([
            ['nota' => 100.0, 'dias' => 0,  'tipo' => 'EXAMEN'],
            ['nota' => 40.0,  'dias' => 90, 'tipo' => 'EXAMEN'],   // pesa la mitad
        ]);

        // (100·1 + 40·0,5) / 1,5 = 80
        $this->assertSame(80.0, $d['EXAMEN']['media']);
    }

    public function test_sin_ninguna_nota_las_dos_medias_son_nulas(): void
    {
        $d = $this->motor->desglosePorTipo([]);

        $this->assertNull($d['EXAMEN']['media']);
        $this->assertNull($d['TAREA']['media']);
        $this->assertSame(0, $d['EXAMEN']['n']);
        $this->assertSame(0, $d['TAREA']['n']);
    }
}
