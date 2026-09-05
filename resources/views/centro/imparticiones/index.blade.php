@extends('layouts.app')
@section('titulo', 'Grupos de asignatura · TUTOLAR')
@section('migas', '<span>Actividad académica</span> › <b>Grupos de asignatura</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo'      => 'Grupos de asignatura',
    'sub'         => $imparticiones->total() . ' asignaciones · quién imparte qué, a qué grupo',
    'accionUrl'   => route('centro.imparticiones.create'),
    'accionTexto' => 'Nueva asignación',
])

<div class="tl-banner tl-sin mb-3">
    <span aria-hidden="true">🔗</span>
    <div>Un grupo de asignatura dice <b>quién da la clase</b>; la matrícula dice
        <b>quién la recibe</b>. Hacen falta las dos: sin matrícula el alumno no ve
        los exámenes y su nivel no se mueve. La columna <b>Alumnos</b> avisa
        cuando falta una de las dos.</div>
</div>

<div class="card">
    <form method="GET" class="card-header d-flex flex-wrap gap-2 align-items-center">
        <select class="form-select" name="curso" style="max-width:200px" aria-label="Filtrar por curso">
            <option value="">Curso: todos</option>
            @foreach ($cursos as $c)
                <option value="{{ $c->id }}" @selected((string) $filtros['curso'] === (string) $c->id)>{{ $c->denominacion }}</option>
            @endforeach
        </select>
        <select class="form-select" name="asignatura" style="max-width:220px" aria-label="Filtrar por asignatura">
            <option value="">Asignatura: todas</option>
            @foreach ($asignaturas as $a)
                <option value="{{ $a->id }}" @selected((string) $filtros['asignatura'] === (string) $a->id)>{{ $a->denominacion }}</option>
            @endforeach
        </select>
        <select class="form-select" name="profesor" style="max-width:220px" aria-label="Filtrar por profesor">
            <option value="">Profesor: todos</option>
            @foreach ($profesores as $p)
                <option value="{{ $p->id }}" @selected((string) $filtros['profesor'] === (string) $p->id)>{{ $p->nombre_completo }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        @if (array_filter($filtros))
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('centro.imparticiones.index') }}">Limpiar</a>
        @endif
    </form>

    @if ($imparticiones->isEmpty())
        @include('centro.partials.vacio', [
            'mensaje' => 'No hay ninguna asignación con esos filtros.',
            'pista'   => 'Cada asignación une un profesor, una asignatura y un grupo.',
        ])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Asignaciones de docencia</caption>
            <thead><tr>
                <th scope="col">Asignatura</th><th scope="col">Grupo</th><th scope="col">Profesor</th>
                <th scope="col">Alumnos</th>
                <th scope="col" class="num">Exámenes y tareas</th><th scope="col"></th>
            </tr></thead>
            <tbody>
            @foreach ($imparticiones as $i)
                @php
                    $enGrupo   = $cobertura['tamano'][$i->grupo_id] ?? 0;
                    $cursan    = $cobertura['matriculados'][$i->grupo_id . '-' . $i->asignatura_id] ?? 0;
                    $completo  = $enGrupo > 0 && $cursan >= $enGrupo;
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $i->asignatura?->denominacion }}</td>
                    <td class="text-body-secondary">{{ $i->grupo?->nombre_completo }}</td>
                    <td class="text-body-secondary">{{ $i->profesor?->nombre_completo }}</td>
                    <td>
                        @if ($cursan === 0)
                            <span class="tl-chip tl-bajo" title="Nadie cursa esta asignatura en este grupo">
                                Nadie la cursa
                            </span>
                            @if ($enGrupo > 0)
                                <form method="POST" class="d-inline"
                                      action="{{ route('centro.imparticiones.matricular', $i) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary ms-1" type="submit"
                                            title="Matricular a los {{ $enGrupo }} alumnos del grupo">
                                        Matricular al grupo
                                    </button>
                                </form>
                            @endif
                        @else
                            <a class="tl-chip {{ $completo ? 'tl-alto' : 'tl-medio' }}"
                               href="{{ route('centro.matriculas.index', ['curso' => $i->grupo?->curso_id, 'asignatura' => $i->asignatura_id]) }}"
                               title="Ver las matrículas de esta asignatura">
                                {{ $cursan }} de {{ $enGrupo }}
                            </a>
                        @endif
                    </td>
                    <td class="num">{{ $i->evaluables_count }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('centro.imparticiones.edit', $i) }}">Editar</a>
                        @include('centro.partials.confirmar', ['url' => route('centro.imparticiones.destroy', $i)])
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $imparticiones->links() }}</div>
    @endif
</div>
@endsection
