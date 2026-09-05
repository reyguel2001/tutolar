@extends('layouts.app')
@php $nuevo = ! $cuenta->exists; @endphp
@section('titulo', ($nuevo ? 'Nueva cuenta' : 'Editar cuenta') . ' · TUTOLAR')
@section('migas', '<span>Administración</span> › <a href="' . route('centro.cuentas.index') . '">Cuentas</a> › <b>' . ($nuevo ? 'Nueva' : e($cuenta->email)) . '</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo' => $nuevo ? 'Nueva cuenta de dirección' : 'Editar cuenta',
    'sub'    => $nuevo
        ? 'Para dar de alta profesores o familias usa su propia pantalla: además de la cuenta hay que crear el perfil.'
        : 'Rol: ' . $cuenta->etiquetaRol() . ' — el rol de una cuenta no se cambia.',
])

<form method="POST" action="{{ $nuevo ? route('centro.cuentas.store') : route('centro.cuentas.update', $cuenta) }}">
    @csrf
    @unless ($nuevo) @method('PUT') @endunless

    <div class="card mb-3" style="max-width:720px">
        <div class="card-body row g-3">
            <div class="col-md-7">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="name">Nombre</label>
                <input class="form-control" id="name" name="name" required maxlength="150"
                       value="{{ old('name', $cuenta->name) }}">
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="telefono">
                    Teléfono <span class="fw-normal text-body-tertiary">(opcional)</span>
                </label>
                <input class="form-control" id="telefono" name="telefono" maxlength="20"
                       value="{{ old('telefono', $cuenta->telefono) }}">
            </div>
            <div class="col-md-7">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="email">Correo electrónico</label>
                <input class="form-control" id="email" name="email" type="email" required maxlength="180"
                       value="{{ old('email', $cuenta->email) }}" autocomplete="off">
            </div>
            <div class="col-md-5"></div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="password">
                    Contraseña @unless ($nuevo)<span class="fw-normal text-body-tertiary">(opcional)</span>@endunless
                </label>
                <input class="form-control" id="password" name="password" type="password" minlength="8"
                       autocomplete="new-password" @if ($nuevo) required @endif>
                <div class="text-body-tertiary mt-1" style="font-size:.75rem">
                    {{ $nuevo ? 'Mínimo 8 caracteres.' : 'Déjala vacía para no cambiarla.' }}
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.85rem" for="password_confirmation">Repetir</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password"
                       autocomplete="new-password" @if ($nuevo) required @endif>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $nuevo ? 'Crear cuenta' : 'Guardar cambios' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('centro.cuentas.index') }}">Cancelar</a>
    </div>
</form>
@endsection
