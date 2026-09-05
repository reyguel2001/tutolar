@extends('layouts.acceso')
@section('titulo', 'Pedir un código de vinculación · TUTOLAR')

@section('formulario')
    @if (session('exito'))
        <div class="tl-banner tl-alto mb-4" role="status" aria-live="polite">
            <span aria-hidden="true">✅</span>
            <div>{{ session('exito') }}</div>
        </div>

        <h1 class="fs-3 fw-bold mb-2" style="letter-spacing:-.7px">Solicitud enviada</h1>
        <p class="text-body-secondary" style="font-size:.9rem">
            El centro la revisará y, si todo encaja, te hará llegar el código.
            Cuando lo tengas, vuelve a <a href="{{ route('registro') }}">crear tu cuenta</a>.
        </p>
        <a class="btn btn-outline-secondary mt-3" href="{{ route('login') }}">← Volver al acceso</a>
    @else

    <h1 class="fs-3 fw-bold mb-2" style="letter-spacing:-.7px">Pedir un código de vinculación</h1>
    <p class="text-body-secondary mb-4" style="font-size:.9rem">
        Rellena esto y el centro lo revisará. <b>Esto no crea ninguna cuenta
        ni te da acceso a nada</b>: solo le pide al centro que compruebe que
        eres quien dices y te mande el código.
    </p>

    @if ($errors->any())
        <div class="tl-banner tl-bajo mb-3" role="alert">
            <span aria-hidden="true">⚠️</span>
            <div>
                <b>Falta algo por rellenar.</b>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('solicitud.store') }}" class="row g-3" novalidate>
        @csrf

        <div class="col-12">
            <label class="form-label" for="centro_id">Centro educativo</label>
            <select class="form-control" id="centro_id" name="centro_id" required autofocus>
                <option value="">Elige el centro…</option>
                @foreach ($centros as $c)
                    <option value="{{ $c->id }}" @selected((int) old('centro_id') === $c->id)>
                        {{ $c->denominacion }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-12"><div class="tl-sep">Datos del alumno</div></div>

        <div class="col-md-5">
            <label class="form-label" for="alumno_nombre">Nombre</label>
            <input class="form-control" type="text" id="alumno_nombre" name="alumno_nombre"
                   value="{{ old('alumno_nombre') }}" required maxlength="100">
        </div>

        <div class="col-md-7">
            <label class="form-label" for="alumno_apellidos">Apellidos</label>
            <input class="form-control" type="text" id="alumno_apellidos" name="alumno_apellidos"
                   value="{{ old('alumno_apellidos') }}" required maxlength="150">
        </div>

        <div class="col-12">
            <label class="form-label" for="alumno_curso">Curso y grupo <span class="fw-normal text-body-tertiary">(si lo sabes)</span></label>
            <input class="form-control" type="text" id="alumno_curso" name="alumno_curso"
                   value="{{ old('alumno_curso') }}" maxlength="80" placeholder="3º ESO B">
        </div>

        <div class="col-12"><div class="tl-sep">Tus datos</div></div>

        <div class="col-md-5">
            <label class="form-label" for="nombre">Nombre</label>
            <input class="form-control" type="text" id="nombre" name="nombre"
                   value="{{ old('nombre') }}" required maxlength="100" autocomplete="given-name">
        </div>

        <div class="col-md-7">
            <label class="form-label" for="apellidos">Apellidos</label>
            <input class="form-control" type="text" id="apellidos" name="apellidos"
                   value="{{ old('apellidos') }}" required maxlength="150" autocomplete="family-name">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="parentesco">Eres su…</label>
            <select class="form-control" id="parentesco" name="parentesco" required>
                @foreach (\App\Models\Tutela::PARENTESCOS as $p)
                    <option value="{{ $p }}" @selected(old('parentesco') === $p)>{{ \App\Models\Tutela::etiqueta($p) }}</option>
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
                   value="{{ old('email') }}" required maxlength="180" autocomplete="email">
        </div>

        <div class="col-12">
            <label class="form-label" for="mensaje">Algo que ayude al centro a reconocerte <span class="fw-normal text-body-tertiary">(opcional)</span></label>
            <textarea class="form-control" id="mensaje" name="mensaje" rows="2" maxlength="500"
                      placeholder="Soy la madre; el año pasado hablé con la tutora…">{{ old('mensaje') }}</textarea>
        </div>

        <div class="col-12 mt-4">
            <button class="btn btn-primary w-100" type="submit" style="min-height:48px">
                Enviar la solicitud
            </button>
        </div>
    </form>

    <div class="mt-4 pt-4 border-top" style="font-size:.85rem">
        ¿Ya tienes el código? <a href="{{ route('registro') }}">Crear la cuenta</a> ·
        ¿Ya tienes cuenta? <a href="{{ route('login') }}">Entrar</a>
        <div class="text-body-tertiary mt-2" style="font-size:.78rem">
            El centro puede tardar en responder. Si tienes prisa, llamar sigue
            siendo más rápido: esto es para cuando la secretaría está cerrada.
        </div>
    </div>
    @endif
@endsection

@section('marca')
    <div class="tl-mark">T</div>
    <h2>El centro decide quién ve las notas de un menor.</h2>
    <p>
        Por eso no basta con rellenar un formulario: alguien del centro
        comprueba que eres su familia antes de darte acceso. Es un paso más,
        y es el que hace que esto se pueda usar con datos de menores.
    </p>
@endsection
