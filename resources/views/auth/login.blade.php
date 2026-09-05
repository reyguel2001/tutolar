@extends('layouts.acceso')
@section('titulo', 'Acceder · TUTOLAR')

@section('formulario')
    <h1 class="fs-3 fw-bold mb-2" style="letter-spacing:-.7px">Accede a la plataforma</h1>
    <p class="text-body-secondary mb-4" style="font-size:.9rem">
        Centros, profesorado y familias entran por aquí.
        La plataforma reconoce tu rol y te lleva a tu espacio.
    </p>

    @if ($errors->any())
        <div class="tl-banner tl-bajo mb-3" role="alert">
            <span aria-hidden="true">⚠️</span>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">Correo electrónico</label>
            <input class="form-control" type="email" id="email" name="email"
                   value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Contraseña</label>
            <input class="form-control" type="password" id="password" name="password"
                   required autocomplete="current-password">
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="recordarme" name="recordarme" value="1">
            <label class="form-check-label" for="recordarme" style="font-size:.85rem">
                Mantener la sesión abierta
            </label>
        </div>

        <button class="btn btn-primary w-100" type="submit" style="min-height:48px">Entrar</button>
    </form>

    <div class="tl-banner tl-info mt-4">
        <span aria-hidden="true">👨‍👩‍👧</span>
        <div>
            <b>¿Eres una familia y aún no tienes cuenta?</b><br>
            Con el código que te ha dado el centro puedes
            <a href="{{ route('registro') }}">crear la tuya en un minuto</a>.
            ¿No lo tienes? <a href="{{ route('solicitud.create') }}">Pídelo aquí</a>.
        </div>
    </div>

    <div class="mt-4 pt-4 border-top" style="font-size:.8rem; color: var(--tl-text-3); line-height:1.7">
        Cuentas de prueba (contraseña <code>tutolar2026</code>):<br>
        <b>direccion@iescervantes.edu.es</b> — centro<br>
        <b>javier.ortega@iescervantes.edu.es</b> — profesorado
    </div>
@endsection

@section('marca')
    <div class="tl-mark">T</div>
    <h2>El rendimiento escolar, visible para quien puede hacer algo con él.</h2>
    <p>
        El profesorado avisa en un gesto. Las familias registran los resultados.
        El centro ve la fotografía completa y detecta a tiempo a quien se está
        quedando atrás.
    </p>
@endsection
