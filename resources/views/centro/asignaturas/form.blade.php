@extends('layouts.app')
@php $nuevo = ! $asignatura->exists; @endphp
@section('titulo', ($nuevo ? 'Nueva asignatura' : 'Editar asignatura') . ' · TUTOLAR')
@section('migas', '<span>Gestión</span> › <a href="' . route('centro.asignaturas.index') . '">Asignaturas</a> › <b>' . ($nuevo ? 'Nueva' : e($asignatura->denominacion)) . '</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', ['titulo' => $nuevo ? 'Nueva asignatura' : 'Editar asignatura'])

<form method="POST" action="{{ $nuevo ? route('centro.asignaturas.store') : route('centro.asignaturas.update', $asignatura) }}">
    @csrf
    @unless ($nuevo) @method('PUT') @endunless

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-8">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="denominacion">Denominación</label>
                <input class="form-control" id="denominacion" name="denominacion" required maxlength="120"
                       value="{{ old('denominacion', $asignatura->denominacion) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="horas_semanales">Horas semanales</label>
                <input class="form-control" id="horas_semanales" name="horas_semanales" type="number"
                       min="1" max="40" required value="{{ old('horas_semanales', $asignatura->horas_semanales) }}">
                <div class="text-body-tertiary mt-1" style="font-size:.75rem">Peso en el índice global.</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Plan de estudios · en qué cursos se imparte</div>
        <div class="card-body">
            @if ($cursos->isEmpty())
                <p class="text-body-secondary mb-0" style="font-size:.85rem">
                    Todavía no hay cursos creados. Créalos en
                    <a href="{{ route('centro.grupos.index') }}">Grupos</a> y vuelve aquí
                    para decidir en cuáles entra esta materia.
                </p>
            @else
                @php $marcados = old('cursos', $asignatura->exists ? $asignatura->cursos->pluck('id')->all() : []); @endphp
                <div class="row g-2">
                    @foreach ($cursos as $c)
                        <div class="col-md-3">
                            <label class="d-flex gap-2 align-items-center" style="font-size:.85rem">
                                <input type="checkbox" name="cursos[]" value="{{ $c->id }}"
                                       @checked(in_array($c->id, $marcados, false))>
                                <span>{{ $c->denominacion }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="text-body-tertiary mt-3" style="font-size:.75rem">
                    Si no marcas ninguno, la asignatura queda disponible en todos los cursos.
                    Marcar cursos limita dónde se puede asignar un docente y a quién se puede matricular.
                </div>
            @endif
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $nuevo ? 'Crear' : 'Guardar cambios' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('centro.asignaturas.index') }}">Cancelar</a>
    </div>
</form>
@endsection
