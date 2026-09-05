<?php

namespace App\Services;

use App\Support\Nivel;

/**
 * Motor de rendimiento de TUTOLAR.
 *
 * Es lógica pura: no toca la base de datos ni depende de Laravel, así que se
 * puede probar sin levantar nada. Recibe resultados ya normalizados y devuelve
 * índices, colores y tendencias.
 *
 * Fórmulas, según el documento de análisis y diseño (§6):
 *   nota_normalizada = obtenida / máxima × 100
 *   peso_recencia    = 0,5 ^ (días / 90)
 *   IRA              = (M_ex × 0,60 + M_ta × 0,40) / (0,60 + 0,40)
 *   IRG              = Σ(IRA × horas) / Σ(horas)
 */
class RendimientoService
{
    /** Semivida del peso por recencia, en días. */
    public const SEMIVIDA_DIAS = 90;

    public function __construct(
        private float $pesoExamenes = 0.60,
        private float $pesoTareas   = 0.40,
        private float $umbralBajo   = 50,
        private float $umbralAlto  = 70,
    ) {
    }

    /**
     * Normaliza una puntuación a la escala 0–100.
     */
    public function normalizar(float $obtenida, float $maxima): float
    {
        if ($maxima <= 0) {
            throw new \InvalidArgumentException('La puntuación máxima debe ser mayor que cero.');
        }

        return ($obtenida / $maxima) * 100;
    }

    /**
     * Peso por recencia: un suspenso de hace tres meses no debe pesar igual
     * que uno de esta semana.
     */
    public function pesoRecencia(int $diasTranscurridos): float
    {
        return pow(0.5, max(0, $diasTranscurridos) / self::SEMIVIDA_DIAS);
    }

    /**
     * Media ponderada por recencia de un conjunto de resultados.
     *
     * @param array<int, array{nota: float, dias: int}> $resultados
     */
    public function mediaPonderada(array $resultados): ?float
    {
        if ($resultados === []) {
            return null;
        }

        $numerador = 0.0;
        $denominador = 0.0;

        foreach ($resultados as $r) {
            $peso = $this->pesoRecencia($r['dias']);
            $numerador   += $r['nota'] * $peso;
            $denominador += $peso;
        }

        return $denominador > 0 ? $numerador / $denominador : null;
    }

    /**
     * Índice de Rendimiento por Asignatura.
     *
     * @param array<int, array{nota: float, dias: int, tipo: string}> $resultados
     *        tipo es 'EXAMEN' o 'TAREA'.
     *
     * @return array{valor: float|null, color: string, etiqueta: string,
     *               num_resultados: int, media_examenes: float|null, media_tareas: float|null}
     */
    public function calcularIra(array $resultados): array
    {
        $n = count($resultados);

        // Principio 1: sin datos suficientes no se emite juicio de rendimiento.
        if ($n < Nivel::MIN_RESULTADOS) {
            return $this->resultadoGris($n);
        }

        $examenes = array_values(array_filter($resultados, fn ($r) => $r['tipo'] === 'EXAMEN'));
        $tareas   = array_values(array_filter($resultados, fn ($r) => $r['tipo'] === 'TAREA'));

        $mediaEx = $this->mediaPonderada($examenes);
        $mediaTa = $this->mediaPonderada($tareas);

        if ($mediaEx !== null && $mediaTa !== null) {
            $valor = ($mediaEx * $this->pesoExamenes + $mediaTa * $this->pesoTareas)
                   / ($this->pesoExamenes + $this->pesoTareas);
        } else {
            // Solo hay una de las dos categorías: el índice se calcula con ella.
            $valor = $mediaEx ?? $mediaTa;
        }

        if ($valor === null) {
            return $this->resultadoGris($n);
        }

        $valor = round($valor, 1);
        $nivel = Nivel::de($valor, $this->umbralBajo, $this->umbralAlto);

        return [
            'valor'          => $valor,
            'nivel' => $nivel,
            'etiqueta'       => Nivel::etiqueta($nivel),
            'num_resultados' => $n,
            'media_examenes' => $mediaEx === null ? null : round($mediaEx, 1),
            'media_tareas'   => $mediaTa === null ? null : round($mediaTa, 1),
        ];
    }

    /**
     * Desglose descriptivo por tipo de prueba: exámenes por un lado, ejercicios
     * y tareas por otro.
     *
     * `calcularIra()` ya separa las dos medias, pero solo las publica cuando hay
     * datos suficientes para emitir un índice, porque un índice es un juicio.
     * Esto es otra cosa: una media que se enseña tal cual, con cuántas notas la
     * sostienen, para que la familia vea de dónde viene el número. Por eso no
     * lleva color de nivel — el color aquí identifica el tipo de prueba, no
     * el estado del alumno, y mezclarlos rompería la regla de que el color de
     * estado nunca significa otra cosa.
     *
     * @param  array<int, array{nota: float, dias: int, tipo: string}> $resultados
     * @return array{
     *   EXAMEN: array{media: float|null, n: int},
     *   TAREA:  array{media: float|null, n: int}
     * }
     */
    public function desglosePorTipo(array $resultados): array
    {
        $desglose = [];

        foreach (['EXAMEN', 'TAREA'] as $tipo) {
            $delTipo = array_values(array_filter($resultados, fn ($r) => $r['tipo'] === $tipo));
            $media   = $this->mediaPonderada($delTipo);

            $desglose[$tipo] = [
                'media' => $media === null ? null : round($media, 1),
                'n'     => count($delTipo),
            ];
        }

        return $desglose;
    }

    /**
     * Índice de Rendimiento Global, ponderado por horas semanales.
     * Las asignaturas en gris no participan: no se puede promediar lo que no se sabe.
     *
     * @param array<int, array{ira: float|null, horas: int}> $asignaturas
     *
     * @return array{valor: float|null, color: string, etiqueta: string}
     */
    public function calcularIrg(array $asignaturas): array
    {
        $numerador = 0.0;
        $denominador = 0.0;

        foreach ($asignaturas as $a) {
            if ($a['ira'] === null) {
                continue;
            }
            $horas = max(1, $a['horas']);
            $numerador   += $a['ira'] * $horas;
            $denominador += $horas;
        }

        if ($denominador === 0.0) {
            $nivel = Nivel::SIN_DATOS;

            return ['valor' => null, 'nivel' => $nivel, 'etiqueta' => Nivel::etiqueta($nivel)];
        }

        $valor = round($numerador / $denominador, 1);
        $nivel = Nivel::de($valor, $this->umbralBajo, $this->umbralAlto);

        return ['valor' => $valor, 'nivel' => $nivel, 'etiqueta' => Nivel::etiqueta($nivel)];
    }

    /**
     * Tendencia comparando la ventana de 30 días actual con la anterior.
     *
     * @return array{tipo: string, delta: float|null, simbolo: string, texto: string}
     */
    public function calcularTendencia(?float $irgActual, ?float $irgAnterior): array
    {
        if ($irgActual === null || $irgAnterior === null) {
            return ['tipo' => 'DESCONOCIDA', 'delta' => null, 'simbolo' => '·', 'texto' => 'Sin histórico'];
        }

        $delta = round($irgActual - $irgAnterior, 1);

        if ($delta > 5) {
            return ['tipo' => 'ASCENDENTE', 'delta' => $delta, 'simbolo' => '↑', 'texto' => 'Mejorando'];
        }
        if ($delta < -5) {
            return ['tipo' => 'DESCENDENTE', 'delta' => $delta, 'simbolo' => '↓', 'texto' => 'Empeorando'];
        }

        return ['tipo' => 'ESTABLE', 'delta' => $delta, 'simbolo' => '→', 'texto' => 'Estable'];
    }

    /**
     * Índice de Seguimiento Familiar: mide la implicación de la familia,
     * nunca el rendimiento del alumno. Se muestra siempre por separado.
     */
    public function calcularIsf(int $registrados, int $notificados): ?float
    {
        if ($notificados <= 0) {
            return null;
        }

        return round(($registrados / $notificados) * 100, 1);
    }

    /**
     * ¿Debe dispararse una alerta de rendimiento?
     * Salta al cruzar a rojo o al caer más de 15 puntos en 30 días.
     */
    public function requiereAlerta(?float $valorNuevo, ?float $valorAnterior): bool
    {
        if ($valorNuevo === null) {
            return false;
        }

        $entraEnBajo = $valorNuevo < $this->umbralBajo
            && ($valorAnterior === null || $valorAnterior >= $this->umbralBajo);

        $caidaFuerte = $valorAnterior !== null && ($valorAnterior - $valorNuevo) > 15;

        return $entraEnBajo || $caidaFuerte;
    }

    /** @return array{valor: null, color: string, etiqueta: string, num_resultados: int,
     *                media_examenes: null, media_tareas: null} */
    private function resultadoGris(int $n): array
    {
        return [
            'valor'          => null,
            'nivel' => Nivel::SIN_DATOS,
            'etiqueta'       => Nivel::etiqueta(Nivel::SIN_DATOS),
            'num_resultados' => $n,
            'media_examenes' => null,
            'media_tareas'   => null,
        ];
    }
}
