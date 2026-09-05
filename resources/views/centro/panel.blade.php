@extends('layouts.app')

@section('titulo', 'Panel del centro · TUTOLAR')
@section('org-titulo', $centro->denominacion)
@section('org-sub', 'Curso 2026/2027')
@section('migas', '<span>Centro</span> › <b>Panel</b>')

@section('contenido')
@php
    use App\Support\Nivel;
    $enBajo   = $reparto[Nivel::BAJO];
    $sinDatos = $reparto[Nivel::SIN_DATOS];
@endphp

<div class="tl-cab">
    <div>
        <h1>Panel del centro</h1>
        <p>{{ $centro->denominacion }} · {{ $cursos }} cursos · datos de {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('centro.alumnos') }}">Ver todos los alumnos</a>
        <a class="btn btn-primary" href="{{ route('centro.alumnos', ['estado' => Nivel::BAJO]) }}">
            Alumnos en riesgo
        </a>
    </div>
</div>

{{-- ---------- Indicadores ---------- --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card h-100"><div class="card-body tl-kpi">
            <div class="k">Alumnos seguidos</div>
            <div class="v">{{ $totalAlumnos }}</div>
            <div class="d">en {{ count($porCurso) }} cursos</div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100"><div class="card-body tl-kpi">
            <div class="k">En rendimiento bajo</div>
            <div class="v tl-cifra tl-bajo">{{ $enBajo }}</div>
            <div class="d">{{ $totalAlumnos ? round($enBajo / $totalAlumnos * 100) : 0 }} % del alumnado</div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100"><div class="card-body tl-kpi">
            <div class="k">Sin datos suficientes</div>
            <div class="v tl-cifra tl-sin">{{ $sinDatos }}</div>
            <div class="d">no se emite juicio sobre ellos</div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card h-100"><div class="card-body tl-kpi">
            <div class="k">Familias vinculadas</div>
            <div class="v tl-cifra tl-alto">{{ $porcVinculada }} %</div>
            <div class="d">{{ $sinFamilia }} alumnos sin familia</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    {{-- ---------- Distribución por curso ---------- --}}
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span>Rendimiento por curso</span>
                <span class="tl-leyenda">
                    <span><i class="tl-fill-bajo"></i> 0–{{ (int) $centro->umbral_bajo - 1 }}</span>
                    <span><i class="tl-fill-medio"></i> {{ (int) $centro->umbral_bajo }}–{{ (int) $centro->umbral_alto - 1 }}</span>
                    <span><i class="tl-fill-alto"></i> {{ (int) $centro->umbral_alto }}–100</span>
                    <span><i class="tl-fill-sin"></i> sin datos</span>
                </span>
            </div>
            <div class="card-body">
                @forelse ($porCurso as $fila)
                    @php
                        $r = $fila['reparto'];
                        $v = [
                            Nivel::BAJO      => $r[Nivel::BAJO]      ?? 0,
                            Nivel::MEDIO     => $r[Nivel::MEDIO]     ?? 0,
                            Nivel::ALTO      => $r[Nivel::ALTO]      ?? 0,
                            Nivel::SIN_DATOS => $r[Nivel::SIN_DATOS] ?? 0,
                        ];
                        /* El desglose iba escrito al lado en cuatro cifras que
                           repetían lo que ya decía la barra. Ahora vive en el
                           title y en la tabla alternativa de abajo, que es donde
                           lo busca quien de verdad necesita el número exacto. */
                        $detalle = "{$v[Nivel::BAJO]} bajo · {$v[Nivel::MEDIO]} medio · "
                                 . "{$v[Nivel::ALTO]} alto · {$v[Nivel::SIN_DATOS]} sin datos";
                    @endphp
                    <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                        <div style="width:90px; font-size:.87rem; font-weight:650">
                            {{ $fila['curso']->denominacion ?? '—' }}
                        </div>
                        <div class="tl-stack flex-grow-1" style="height:16px" title="{{ $detalle }}">
                            @foreach ($v as $nivel => $n)
                                @if ($n > 0)
                                    <i class="tl-fill-{{ Nivel::token($nivel) }}" style="flex: {{ $n }}"></i>
                                @endif
                            @endforeach
                        </div>
                        <div class="fw-bold text-end" style="width:44px; font-variant-numeric:tabular-nums">
                            {{ $fila['total'] }}
                        </div>
                    </div>
                @empty
                    <div class="tl-empty">
                        <div class="em">📊</div>
                        <b>Todavía no hay alumnos dados de alta</b>
                        <p class="mb-0">Ejecuta el seeder o da de alta el primer grupo.</p>
                    </div>
                @endforelse

                @if (count($porCurso))
                    {{-- Una barra apilada no se puede leer con un lector de pantalla
                         ni copiar a un acta. La tabla es la misma información. --}}
                    <details class="tl-tabla-alt mt-3 pt-3 border-top">
                        <summary>Ver las cifras exactas</summary>
                        <div class="table-responsive mt-2">
                            <table class="tl-table">
                                <thead><tr>
                                    <th scope="col">Curso</th>
                                    <th scope="col" class="num">Bajo</th>
                                    <th scope="col" class="num">Medio</th>
                                    <th scope="col" class="num">Alto</th>
                                    <th scope="col" class="num">Sin datos</th>
                                    <th scope="col" class="num">Total</th>
                                </tr></thead>
                                <tbody>
                                @foreach ($porCurso as $fila)
                                    @php $r = $fila['reparto']; @endphp
                                    <tr>
                                        <td>{{ $fila['curso']->denominacion ?? '—' }}</td>
                                        <td class="num">{{ $r[Nivel::BAJO] ?? 0 }}</td>
                                        <td class="num">{{ $r[Nivel::MEDIO] ?? 0 }}</td>
                                        <td class="num">{{ $r[Nivel::ALTO] ?? 0 }}</td>
                                        <td class="num">{{ $r[Nivel::SIN_DATOS] ?? 0 }}</td>
                                        <td class="num fw-bold">{{ $fila['total'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </div>
        </div>
    </div>

    {{-- ---------- Atención prioritaria ---------- --}}
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">Atención prioritaria</div>
            <div class="card-body d-flex flex-column gap-3">
                @if ($enBajo > 0)
                    <div>
                        <b style="font-size:.9rem">{{ $enBajo }} alumnos en rendimiento bajo</b>
                        <p class="text-body-secondary mb-1" style="font-size:.8rem">
                            Antes de la próxima evaluación.
                        </p>
                        <a href="{{ route('centro.alumnos', ['estado' => Nivel::BAJO]) }}"
                           style="font-size:.8rem">Ver el listado →</a>
                    </div>
                @endif

                @if ($sinFamilia > 0)
                    <div class="pt-3 border-top">
                        <b style="font-size:.9rem">{{ $sinFamilia }} alumnos sin familia vinculada</b>
                        <p class="text-body-secondary mb-0" style="font-size:.8rem">
                            No reciben avisos ni pueden tener resultados registrados. Es lo único
                            de esta lista que se resuelve hoy mismo.
                        </p>
                    </div>
                @endif

                @if ($sinDatos > 0)
                    <div class="pt-3 border-top">
                        <b style="font-size:.9rem">{{ $sinDatos }} fichas sin datos suficientes</b>
                        <p class="text-body-secondary mb-0" style="font-size:.8rem">
                            Menos de dos resultados. No se emite juicio sobre ellas.
                        </p>
                    </div>
                @endif

                @if ($enBajo === 0 && $sinFamilia === 0 && $sinDatos === 0)
                    <div class="tl-empty py-4">
                        <div class="em">✅</div>
                        <b>Nada pendiente</b>
                        <p class="mb-0" style="font-size:.85rem">Ningún alumno requiere atención inmediata.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ---------- Actividad reciente ---------- --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">Actividad reciente</div>
            <div class="table-responsive">
                <table class="tl-table">
                    <caption class="visually-hidden">Últimos resultados registrados en el centro</caption>
                    <thead>
                        <tr>
                            <th scope="col">Momento</th>
                            <th scope="col">Quién</th>
                            <th scope="col">Alumno</th>
                            <th scope="col">Asignatura</th>
                            <th scope="col">Origen</th>
                            <th scope="col" class="num">Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($actividad as $r)
                        @php
                            $nota10 = (float) $r->puntuacion_obtenida;
                            $nivelNota = Nivel::de($nota10 * 10,
                                (float) $centro->umbral_bajo, (float) $centro->umbral_alto);
                        @endphp
                        <tr>
                            <td class="text-body-tertiary text-nowrap">
                                {{ $r->created_at->diffForHumans(short: true) }}
                            </td>
                            <td>{{ $r->autor?->name ?? 'Sistema' }}</td>
                            <td>
                                <a href="{{ route('centro.alumno', $r->alumno) }}">
                                    {{ $r->alumno->nombre_completo }}
                                </a>
                                <small class="d-block text-body-tertiary">
                                    {{ $r->alumno->grupo?->nombre_completo }}
                                </small>
                            </td>
                            <td class="text-body-secondary">
                                {{ $r->evaluable?->imparticion?->asignatura?->denominacion ?? '—' }}
                            </td>
                            <td>
                                <span class="tl-chip {{ $r->estaVerificado() ? 'tl-info' : 'tl-sin' }}">
                                    {{ $r->origen }}
                                </span>
                            </td>
                            <td class="num tl-cifra {{ Nivel::clase($nivelNota) }}">
                                {{ number_format($nota10, 1, ',', '') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">
                            Todavía no hay resultados registrados.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
