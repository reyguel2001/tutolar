@extends('layouts.app')
@php $nuevo = ! $profesor->exists; @endphp
@section('titulo', ($nuevo ? 'Nuevo profesor' : 'Editar profesor') . ' · TUTOLAR')
@section('migas', '<span>Gestión</span> › <a href="' . route('centro.profesores.index') . '">Profesores</a> › <b>' . ($nuevo ? 'Nuevo' : e($profesor->nombre_completo)) . '</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo' => $nuevo ? 'Nuevo profesor' : 'Editar ' . $profesor->nombre_completo,
    'sub'    => 'Se crea también su cuenta de acceso con rol de profesorado.',
])

<form method="POST" action="{{ $nuevo ? route('centro.profesores.store') : route('centro.profesores.update', $profesor) }}">
    @csrf
    @unless ($nuevo) @method('PUT') @endunless

    <div class="card mb-3">
        <div class="card-header">Datos personales</div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="nombre">Nombre</label>
                <input class="form-control" id="nombre" name="nombre" required maxlength="100"
                       value="{{ old('nombre', $profesor->nombre) }}">
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="apellidos">Apellidos</label>
                <input class="form-control" id="apellidos" name="apellidos" required maxlength="150"
                       value="{{ old('apellidos', $profesor->apellidos) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="telefono">
                    Teléfono <span class="fw-normal text-body-tertiary">(opcional)</span>
                </label>
                <input class="form-control" id="telefono" name="telefono" maxlength="20"
                       value="{{ old('telefono', $profesor->user?->telefono) }}">
            </div>
        </div>
    </div>

    @include('centro.partials.cuenta', ['usuario' => $profesor->user ?? null, 'nuevo' => $nuevo])

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $nuevo ? 'Crear profesor' : 'Guardar cambios' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('centro.profesores.index') }}">Cancelar</a>
    </div>
</form>
@endsection
