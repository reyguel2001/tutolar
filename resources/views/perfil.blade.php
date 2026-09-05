@extends('layouts.app')

@section('titulo', 'Mi perfil · TUTOLAR')
@section('migas', '<b>Mi perfil</b>')

@section('contenido')
@include('centro.partials.flash')

<div class="d-flex align-items-center gap-3 mb-4">
    @include('partials.avatar', ['persona' => $usuario, 'tam' => 'lg'])
    <div>
        <h1 class="tl-titulo">{{ $usuario->name }}</h1>
        <div class="text-body-secondary" style="font-size:.87rem">
            {{ $usuario->etiquetaRol() }} · {{ $usuario->email }}
        </div>
    </div>
</div>

<div class="card" style="max-width:640px">
    <div class="card-header">Foto de perfil</div>
    <div class="card-body">
        <div class="tl-foto">
            @include('partials.avatar', ['persona' => $usuario, 'tam' => 'xl'])

            <div class="tl-foto-acciones">
                <form method="POST" action="{{ route('perfil.foto') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label fw-semibold" style="font-size:.85rem" for="foto">
                        Elegir una imagen
                    </label>
                    <input class="form-control mb-2" type="file" id="foto" name="foto"
                           accept="image/jpeg,image/png,image/webp" required
                           @if ($errors->has('foto')) aria-invalid="true" @endif>
                    <button class="btn btn-primary btn-sm" type="submit">Guardar foto</button>
                </form>

                @if ($usuario->tieneFoto())
                    <form method="POST" action="{{ route('perfil.foto.quitar') }}" class="tl-confirmar"
                          data-confirmacion="Pulsa otra vez para quitarla">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Quitar la foto</button>
                    </form>
                @endif
            </div>
        </div>

        @error('foto')
            <div class="tl-banner tl-bajo mt-3" role="alert">
                <span aria-hidden="true">⚠️</span><div>{{ $message }}</div>
            </div>
        @enderror

        <p class="text-body-tertiary mt-3 mb-0" style="font-size:.78rem">
            JPG, PNG o WEBP, hasta 2 MB. Sin foto no pasa nada: se dibuja un
            avatar con tu color, que sale de tu nombre y es siempre el mismo.
        </p>
    </div>
</div>
@endsection
