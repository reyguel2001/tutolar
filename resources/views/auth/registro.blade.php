@extends('layouts.acceso')
@section('titulo', 'Crear cuenta de familia · TUTOLAR')

@section('formulario')
    <h1 class="fs-3 fw-bold mb-2" style="letter-spacing:-.7px">Crear cuenta de familia</h1>
    <p class="text-body-secondary mb-4" style="font-size:.9rem">
        Necesitas el <b>código de vinculación</b> que te ha dado el centro.
        Es lo que dice de qué alumno eres familia: por eso aquí no se pregunta
        por ningún nombre de alumno.
    </p>

    @if ($errors->any())
        <div class="tl-banner tl-bajo mb-3" role="alert">
            <span aria-hidden="true">⚠️</span>
            <div>
                <b>No se ha podido crear la cuenta.</b>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('registro.store') }}" class="row g-3" novalidate>
        @csrf

        <div class="col-12">
            <label class="form-label" for="codigo">Código de vinculación</label>
            <input class="form-control tl-codigo" type="text" id="codigo" name="codigo"
                   value="{{ old('codigo', $codigoEscrito) }}" required autofocus
                   maxlength="20" autocomplete="off" spellcheck="false"
                   placeholder="XXXX-XXXX" aria-describedby="ayuda-codigo"
                   @if ($errors->has('codigo')) aria-invalid="true" @endif>
            <div class="mt-1" id="ayuda-codigo" style="font-size:.78rem">
                @if ($codigo)
                    <span class="tl-chip tl-alto">✓ Te vincularás a {{ $codigo->alumno?->nombre_completo }}</span>
                @else
                    <span class="text-body-tertiary">Ocho letras y números. Da igual cómo lo escribas: mayúsculas, minúsculas o con guion.</span>
                @endif
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="nombre">Tu nombre</label>
            <input class="form-control" type="text" id="nombre" name="nombre"
                   value="{{ old('nombre') }}" required maxlength="100" autocomplete="given-name">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="apellidos">Tus apellidos</label>
            <input class="form-control" type="text" id="apellidos" name="apellidos"
                   value="{{ old('apellidos') }}" required maxlength="150" autocomplete="family-name">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="parentesco">Eres su…</label>
            <select class="form-control" id="parentesco" name="parentesco" required>
                @foreach (\App\Models\Tutela::PARENTESCOS as $p)
                    <option value="{{ $p }}" @selected(old('parentesco') === $p)>
                        {{ \App\Models\Tutela::etiqueta($p) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="telefono">Teléfono</label>
            <input class="form-control" type="tel" id="telefono" name="telefono"
                   value="{{ old('telefono') }}" required maxlength="20" autocomplete="tel">
        </div>

        <div class="col-12">
            <label class="form-label" for="email">Correo electrónico</label>
            <input class="form-control" type="email" id="email" name="email"
                   value="{{ old('email') }}" required maxlength="180" autocomplete="username">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="password">Contraseña</label>
            <input class="form-control" type="password" id="password" name="password"
                   required autocomplete="new-password" minlength="8">
            <div class="text-body-tertiary mt-1" style="font-size:.75rem">Ocho caracteres como mínimo.</div>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Repite la contraseña</label>
            <input class="form-control" type="password" id="password_confirmation"
                   name="password_confirmation" required autocomplete="new-password" minlength="8">
        </div>

        <div class="col-12 mt-4">
            <button class="btn btn-primary w-100" type="submit" style="min-height:48px">
                Crear cuenta y entrar
            </button>
        </div>
    </form>

    <div class="mt-4 pt-4 border-top" style="font-size:.85rem">
        ¿Ya tienes cuenta? <a href="{{ route('login') }}">Entrar</a>
        <div class="mt-2">
            ¿No tienes código? <a href="{{ route('solicitud.create') }}">Pídeselo al centro desde aquí</a>.
        </div>
    </div>
@endsection

@section('marca')
    <div class="tl-mark">T</div>
    <h2>Sigue a tu hijo sin esperar a la evaluación.</h2>
    <p>
        El profesorado avisa de cada examen y de cada tarea. Tú apuntas la nota
        en dos toques y ves al momento cómo va, asignatura por asignatura.
    </p>
@endsection
