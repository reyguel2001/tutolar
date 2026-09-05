@extends('layouts.app')
@php $nuevo = ! $tutor->exists; @endphp
@section('titulo', ($nuevo ? 'Nuevo tutor' : 'Editar tutor') . ' · TUTOLAR')
@section('migas', '<span>Gestión</span> › <a href="' . route('centro.tutores.index') . '">Tutores</a> › <b>' . ($nuevo ? 'Nuevo' : e($tutor->nombre_completo)) . '</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo' => $nuevo ? 'Nuevo tutor legal' : 'Editar ' . $tutor->nombre_completo,
    'sub'    => 'Se crea también su cuenta de acceso a TUTOLAR.',
])

<form method="POST" action="{{ $nuevo ? route('centro.tutores.store') : route('centro.tutores.update', $tutor) }}">
    @csrf
    @unless ($nuevo) @method('PUT') @endunless

    <div class="card mb-3">
        <div class="card-header">Datos personales</div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="nombre">Nombre</label>
                <input class="form-control" id="nombre" name="nombre" required maxlength="100"
                       value="{{ old('nombre', $tutor->nombre) }}">
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="apellidos">Apellidos</label>
                <input class="form-control" id="apellidos" name="apellidos" required maxlength="150"
                       value="{{ old('apellidos', $tutor->apellidos) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="telefono">Teléfono</label>
                <input class="form-control" id="telefono" name="telefono" required maxlength="20"
                       value="{{ old('telefono', $tutor->telefono) }}">
            </div>
        </div>
    </div>

    @include('centro.partials.cuenta', ['usuario' => $tutor->user ?? null, 'nuevo' => $nuevo])

    @if ($nuevo)
    <div class="card mb-3">
        <div class="card-header">Alumnos que tutela <span class="fw-normal text-body-tertiary">(opcional)</span></div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold" style="font-size:.85rem" for="parentesco">Parentesco</label>
                    <select class="form-control" id="parentesco" name="parentesco">
                        @foreach (\App\Models\Tutela::PARENTESCOS as $p)
                            <option value="{{ $p }}" @selected(old('parentesco') === $p)>{{ \App\Models\Tutela::etiqueta($p) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-2" style="max-height:280px; overflow:auto">
                @foreach ($alumnos as $a)
                    <div class="col-md-6">
                        <label class="d-flex gap-2 align-items-center" style="font-size:.85rem">
                            <input type="checkbox" name="alumnos[]" value="{{ $a->id }}"
                                   @checked(in_array($a->id, old('alumnos', []), false))>
                            <span>{{ $a->nombre_completo }}
                                <span class="text-body-tertiary">· {{ $a->grupo?->nombre_completo }}</span></span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $nuevo ? 'Crear tutor' : 'Guardar cambios' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('centro.tutores.index') }}">Cancelar</a>
    </div>
</form>
@endsection
