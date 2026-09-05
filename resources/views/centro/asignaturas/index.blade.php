@extends('layouts.app')
@section('titulo', 'Asignaturas · TUTOLAR')
@section('migas', '<span>Gestión</span> › <b>Asignaturas</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo'      => 'Asignaturas',
    'sub'         => $asignaturas->total() . ' en el catálogo del centro',
    'accionUrl'   => route('centro.asignaturas.create'),
    'accionTexto' => 'Nueva asignatura',
])

@include('centro.partials.ayuda', ['puntos' => [
    ['titulo' => 'Horas semanales:', 'texto' => 'son el peso de la asignatura en el Índice de
        Rendimiento Global. Cambiarlas mueve el nivel de todos los alumnos matriculados.'],
    ['titulo' => 'Se imparte en:', 'texto' => 'es el plan de estudios, los cursos en los que entra
        la materia. Limita dónde se puede asignar un docente y a quién se puede matricular.
        <b>TODOS</b> significa que no se ha restringido.'],
    ['titulo' => 'Las dos últimas columnas', 'texto' => 'son las que bloquean el borrado y llevan a
        la pantalla donde se resuelven. <b>Grupos de asignatura</b> es quién la imparte;
        <b>Alumnos matriculados</b>, quién la cursa. Una materia con docente y sin alumnos
        —o al revés— no produce ninguna nota.'],
]])

@unless ($hayCursos)
    <div class="tl-banner tl-medio mb-3">
        <span aria-hidden="true">⚠️</span>
        <div>No hay ningún curso creado, así que ninguna asignatura tiene plan de estudios:
            todas quedan disponibles en todos los cursos.</div>
    </div>
@endunless

<div class="card">
    <form method="GET" class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input class="form-control" type="search" name="q" value="{{ $q }}" style="max-width:320px"
               placeholder="Buscar asignatura" aria-label="Buscar asignatura">
        <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
        @if ($q)<a class="btn btn-outline-secondary btn-sm" href="{{ route('centro.asignaturas.index') }}">Limpiar</a>@endif
    </form>

    @if ($asignaturas->isEmpty())
        @include('centro.partials.vacio', ['mensaje' => 'No hay asignaturas todavía.'])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Catálogo de asignaturas</caption>
            <thead><tr>
                <th scope="col">Asignatura</th>
                <th scope="col">Se imparte en</th>
                <th scope="col" class="num">Horas semanales</th>
                <th scope="col" class="num">Grupos de asignatura</th>
                <th scope="col" class="num">Alumnos matriculados</th>
                <th scope="col"></th>
            </tr></thead>
            <tbody>
            @foreach ($asignaturas as $a)
                <tr>
                    <td class="fw-semibold">{{ $a->denominacion }}</td>
                    <td>
                        @forelse ($a->cursos as $c)
                            <span class="tl-chip tl-info">{{ $c->denominacion }}</span>
                        @empty
                            <span class="tl-chip tl-sin" title="Sin restricción de plan de estudios">TODOS</span>
                        @endforelse
                    </td>
                    <td class="num">{{ $a->horas_semanales }}</td>
                    <td class="num">
                        @if ($a->imparticiones_count > 0)
                            <a href="{{ route('centro.imparticiones.index', ['asignatura' => $a->id]) }}"
                               title="Ver quién la imparte y a qué grupos">{{ $a->imparticiones_count }}</a>
                        @else
                            <span class="tl-chip tl-bajo" title="Nadie la imparte: no se le pueden poner exámenes">0</span>
                        @endif
                    </td>
                    <td class="num">
                        @if ($a->alumnos_count > 0)
                            <a href="{{ route('centro.matriculas.index', ['asignatura' => $a->id]) }}"
                               title="Ver los alumnos matriculados">{{ $a->alumnos_count }}</a>
                        @else
                            <span class="tl-chip tl-sin" title="Nadie la cursa todavía">0</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('centro.asignaturas.edit', $a) }}">Editar</a>
                        @include('centro.partials.confirmar', ['url' => route('centro.asignaturas.destroy', $a)])
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $asignaturas->links() }}</div>
    @endif
</div>
@endsection
