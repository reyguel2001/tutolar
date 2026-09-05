@extends('layouts.app')
@section('titulo', 'Grupos · TUTOLAR')
@section('migas', '<span>Actividad académica</span> › <b>Grupos</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo'      => 'Grupos',
    'sub'         => 'Grupos-clase, agrupados por curso.',
    'accionUrl'   => route('centro.grupos.create'),
    'accionTexto' => 'Nuevo grupo',
])

@if ($cursos->isEmpty())
    <div class="tl-banner tl-medio mb-3">
        <span aria-hidden="true">⚠️</span>
        <div>No hay ningún curso creado, y un grupo tiene que colgar de un curso.
            Crea primero uno abajo (por ejemplo «3º ESO»).</div>
    </div>
@endif

@foreach ($porCurso as $curso => $grupos)
<div class="card mb-3">
    <div class="card-header">{{ $curso }}</div>
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Grupos de {{ $curso }}</caption>
            <thead><tr>
                <th scope="col">Grupo</th><th scope="col">Tutor de grupo</th>
                <th scope="col" class="num">Alumnos</th><th scope="col" class="num">Asignaturas</th>
                <th scope="col"></th>
            </tr></thead>
            <tbody>
            @foreach ($grupos as $g)
                <tr>
                    <td class="fw-semibold">{{ $g->nombre_completo }}</td>
                    <td class="text-body-secondary">{{ $g->tutorGrupo?->nombre_completo ?? '—' }}</td>
                    <td class="num">{{ $g->alumnos_count }}</td>
                    <td class="num">{{ $g->imparticiones_count }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('centro.grupos.edit', $g) }}">Editar</a>
                        @include('centro.partials.confirmar', ['url' => route('centro.grupos.destroy', $g)])
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach

<div class="card">
    <div class="card-header">Cursos del centro</div>
    <div class="card-body">
        <p class="text-body-secondary" style="font-size:.85rem">
            El menú no tiene entrada propia para cursos porque solo sirven de contenedor
            de grupos. Se gestionan aquí.
        </p>

        @if ($cursos->isNotEmpty())
        <div class="table-responsive mb-3">
            <table class="tl-table">
                <caption class="visually-hidden">Cursos</caption>
                <thead><tr><th scope="col">Curso</th><th scope="col">Año académico</th>
                    <th scope="col" class="num">Grupos</th><th scope="col"></th></tr></thead>
                <tbody>
                @foreach ($cursos as $c)
                    <tr>
                        <td class="fw-semibold">{{ $c->denominacion }}</td>
                        <td class="text-body-secondary">{{ $c->anio_academico }}</td>
                        <td class="num">{{ $c->grupos_count }}</td>
                        <td class="text-end">
                            @include('centro.partials.confirmar', ['url' => route('centro.cursos.destroy', $c)])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <form method="POST" action="{{ route('centro.cursos.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="curso_denominacion">Nuevo curso</label>
                <input class="form-control" id="curso_denominacion" name="denominacion" required
                       maxlength="80" placeholder="3º ESO">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="anio_academico">Año académico</label>
                <input class="form-control" id="anio_academico" name="anio_academico" required
                       maxlength="9" placeholder="2026/2027" value="{{ old('anio_academico', '2026/2027') }}">
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Crear curso</button></div>
        </form>
    </div>
</div>
@endsection
