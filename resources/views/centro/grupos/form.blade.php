@extends('layouts.app')
@php $nuevo = ! $grupo->exists; @endphp
@section('titulo', ($nuevo ? 'Nuevo grupo' : 'Editar grupo') . ' · TUTOLAR')
@section('migas', '<span>Actividad académica</span> › <a href="' . route('centro.grupos.index') . '">Grupos</a> › <b>' . ($nuevo ? 'Nuevo' : e($grupo->nombre_completo)) . '</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', ['titulo' => $nuevo ? 'Nuevo grupo' : 'Editar grupo'])

<form method="POST" action="{{ $nuevo ? route('centro.grupos.store') : route('centro.grupos.update', $grupo) }}">
    @csrf
    @unless ($nuevo) @method('PUT') @endunless

    <div class="card mb-3" style="max-width:720px">
        <div class="card-body row g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="curso_id">Curso</label>
                <select class="form-control" id="curso_id" name="curso_id" required>
                    <option value="">Elige un curso…</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->id }}" @selected((int) old('curso_id', $grupo->curso_id) === $c->id)>
                            {{ $c->denominacion }} · {{ $c->anio_academico }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="denominacion">Letra o nombre</label>
                <input class="form-control" id="denominacion" name="denominacion" required maxlength="20"
                       placeholder="B" value="{{ old('denominacion', $grupo->denominacion) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="tutor_grupo_id">
                    Tutor de grupo <span class="fw-normal text-body-tertiary">(opcional)</span>
                </label>
                <select class="form-control" id="tutor_grupo_id" name="tutor_grupo_id">
                    <option value="">Sin asignar</option>
                    @foreach ($profesores as $p)
                        <option value="{{ $p->id }}" @selected((int) old('tutor_grupo_id', $grupo->tutor_grupo_id) === $p->id)>
                            {{ $p->nombre_completo }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $nuevo ? 'Crear grupo' : 'Guardar cambios' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('centro.grupos.index') }}">Cancelar</a>
    </div>
</form>
@endsection
