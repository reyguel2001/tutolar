{{--
    Exámenes frente a ejercicios, por asignatura.

    Parámetros: $rendimiento (el array que devuelve Alumno::rendimiento()).

    DECISIÓN DE COLOR. Las dos barras llevan color de **serie** —marino los
    exámenes, azul medio los ejercicios—, nunca color de nivel. Los tonos de
    nivel significan una sola cosa en toda la aplicación, que es cómo va el
    alumno; usarlos aquí para decir «tipo de prueba» rompería esa lectura.

    El par (#053F5C, #429EBD) sale de la propia paleta y está comprobado en
    tests/diseno/paleta.js: separa por encima del umbral en visión normal y en
    las tres formas de daltonismo, y no se confunde con ninguno de los cuatro
    tonos de nivel ni con el ámbar de acción.

    Y como el color nunca viaja solo: leyenda arriba, cifra al final de cada
    barra y tabla equivalente debajo. El gráfico se puede leer impreso en blanco
    y negro, que es como acaba en más de una tutoría.
--}}
@php
    $porTipo = $rendimiento['por_tipo'];
    $filas   = collect($rendimiento['asignaturas'])
        ->filter(fn ($f) => $f['por_tipo']['EXAMEN']['n'] > 0 || $f['por_tipo']['TAREA']['n'] > 0)
        ->sortBy(fn ($f) => mb_strtolower($f['asignatura']->denominacion))
        ->values();

    // Geometría del SVG. El contenedor crece con el contenido: nada de altura
    // fija que deje el eje fuera y obligue a un scroll dentro de la tarjeta.
    $ancho    = 760;
    $izquierda = 196;          // columna de nombres de asignatura
    $derecha   = 700;          // fin de la zona de trazado; queda sitio para la cifra
    $escala    = $derecha - $izquierda;   // 504 px = 100 puntos
    $banda     = 46;           // alto por asignatura
    $grosor    = 14;           // barra fina: el dato es lo único que grita
    $arriba    = 26;
    $alto      = $arriba + $filas->count() * $banda + 30;

    $x = fn ($v) => $izquierda + $escala * max(0, min(100, $v)) / 100;

    // Barra con el extremo redondeado y la base cuadrada. Un rect con rx
    // redondearía también el arranque, y el arranque es la línea del cero.
    $barra = function (float $x0, float $x1, float $y, float $h) {
        $r = min(4, max(0, $x1 - $x0));
        return "M {$x0} {$y} H " . ($x1 - $r)
             . " a {$r} {$r} 0 0 1 {$r} {$r}"
             . " V " . ($y + $h - $r)
             . " a {$r} {$r} 0 0 1 -{$r} {$r}"
             . " H {$x0} Z";
    };
@endphp

<div class="card mt-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Exámenes y ejercicios, por separado</span>
        <span class="tl-leyenda" aria-hidden="true">
            <span><i style="background: var(--tl-serie-examen)"></i>Exámenes</span>
            <span><i style="background: var(--tl-serie-tarea)"></i>Ejercicios y tareas</span>
        </span>
    </div>

    <div class="card-body">
        {{-- Los dos titulares: cómo va en cada cosa, en todo el curso. --}}
        <div class="row g-3 mb-4">
            @foreach ([
                ['EXAMEN', 'Media en exámenes',            'examen'],
                ['TAREA',  'Media en ejercicios y tareas', 'tarea'],
            ] as [$clave, $rotulo, $serie])
                @php $d = $porTipo[$clave]; @endphp
                <div class="col-sm-6">
                    <div class="tl-tile">
                        <div class="tl-tile-lbl">
                            <i style="background: var(--tl-serie-{{ $serie }})" aria-hidden="true"></i>{{ $rotulo }}
                        </div>
                        <div class="tl-tile-val">
                            {{ $d['media'] === null ? '—' : rtrim(rtrim(number_format($d['media'], 1, ',', '.'), '0'), ',') }}@if($d['media'] !== null)<span>/100</span>@endif
                        </div>
                        <div class="tl-tile-sub">
                            @if ($d['n'] === 0)
                                Todavía no hay ninguna nota
                            @else
                                {{ $d['n'] }} {{ $d['n'] === 1 ? 'nota registrada' : 'notas registradas' }}
                                @if ($d['n'] === 1) · una sola nota da poca idea @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($filas->isEmpty())
            <p class="text-body-tertiary mb-0" style="font-size:.85rem">
                Cuando apuntes las primeras notas, aquí aparecerá la comparación
                asignatura por asignatura.
            </p>
        @else
            <div class="tl-grafico">
                <svg viewBox="0 0 {{ $ancho }} {{ $alto }}" width="100%" height="{{ $alto }}"
                     role="img" preserveAspectRatio="xMinYMin meet"
                     aria-label="Media de exámenes y de ejercicios en cada asignatura, sobre 100.">
                    {{-- Rejilla: línea fina, continua, un paso por debajo de la superficie. --}}
                    @foreach ([0, 25, 50, 75, 100] as $t)
                        <line x1="{{ $x($t) }}" y1="{{ $arriba - 8 }}" x2="{{ $x($t) }}" y2="{{ $alto - 26 }}"
                              stroke="var(--tl-border)" stroke-width="1"/>
                        <text x="{{ $x($t) }}" y="{{ $alto - 10 }}" text-anchor="middle"
                              fill="var(--tl-text-3)" font-size="11"
                              style="font-variant-numeric: tabular-nums">{{ $t }}</text>
                    @endforeach

                    @foreach ($filas as $i => $fila)
                        @php
                            $y  = $arriba + $i * $banda;
                            $ex = $fila['por_tipo']['EXAMEN'];
                            $ta = $fila['por_tipo']['TAREA'];
                        @endphp

                        <text x="{{ $izquierda - 12 }}" y="{{ $y + 24 }}" text-anchor="end"
                              fill="var(--tl-text)" font-size="12.5">
                            {{ \Illuminate\Support\Str::limit($fila['asignatura']->denominacion, 26) }}
                        </text>

                        @foreach ([['EXAMEN', $ex, 'examen', 4], ['TAREA', $ta, 'tarea', 22]] as [$k, $d, $serie, $dy])
                            @if ($d['media'] === null)
                                <text x="{{ $izquierda + 4 }}" y="{{ $y + $dy + 11 }}"
                                      fill="var(--tl-text-3)" font-size="11">sin notas</text>
                            @else
                                <path d="{{ $barra($x(0), $x($d['media']), $y + $dy, $grosor) }}"
                                      fill="var(--tl-serie-{{ $serie }})">
                                    <title>{{ $fila['asignatura']->denominacion }} · {{ $k === 'EXAMEN' ? 'exámenes' : 'ejercicios y tareas' }}: {{ $d['media'] }} sobre 100 con {{ $d['n'] }} {{ $d['n'] === 1 ? 'nota' : 'notas' }}</title>
                                </path>
                                <text x="{{ $x($d['media']) + 8 }}" y="{{ $y + $dy + 11 }}"
                                      fill="var(--tl-text-2)" font-size="11"
                                      style="font-variant-numeric: tabular-nums">{{ round($d['media']) }}</text>
                            @endif
                        @endforeach
                    @endforeach
                </svg>
            </div>

            {{-- La misma información sin depender de la vista ni del color. --}}
            <details class="tl-tabla-alt mt-3">
                <summary>Ver los mismos datos en una tabla</summary>
                <div class="table-responsive mt-2">
                    <table class="tl-table">
                        <caption class="visually-hidden">Media de exámenes y de ejercicios por asignatura</caption>
                        <thead><tr>
                            <th scope="col">Asignatura</th>
                            <th scope="col" class="num">Exámenes</th>
                            <th scope="col" class="num">Notas</th>
                            <th scope="col" class="num">Ejercicios</th>
                            <th scope="col" class="num">Notas</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($filas as $fila)
                            <tr>
                                <td class="fw-semibold">{{ $fila['asignatura']->denominacion }}</td>
                                <td class="num">{{ $fila['por_tipo']['EXAMEN']['media'] === null ? '—' : number_format($fila['por_tipo']['EXAMEN']['media'], 1, ',', '.') }}</td>
                                <td class="num text-body-secondary">{{ $fila['por_tipo']['EXAMEN']['n'] }}</td>
                                <td class="num">{{ $fila['por_tipo']['TAREA']['media'] === null ? '—' : number_format($fila['por_tipo']['TAREA']['media'], 1, ',', '.') }}</td>
                                <td class="num text-body-secondary">{{ $fila['por_tipo']['TAREA']['n'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        <p class="text-body-tertiary mt-3 mb-0" style="font-size:.75rem">
            Sobre 100. Las notas recientes pesan más que las antiguas: una de hace
            tres meses cuenta la mitad que una de esta semana.
        </p>
    </div>
</div>
