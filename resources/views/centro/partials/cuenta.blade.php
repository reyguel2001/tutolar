{{--
    Bloque de cuenta de acceso, compartido por tutores y profesores.
    Parámetros: $usuario (User|null), $nuevo (bool).
--}}
<div class="card mb-3">
    <div class="card-header">Cuenta de acceso</div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.85rem" for="email">Correo electrónico</label>
            <input class="form-control" id="email" name="email" type="email" required maxlength="180"
                   value="{{ old('email', $usuario?->email) }}" autocomplete="off">
            <div class="text-body-tertiary mt-1" style="font-size:.75rem">Es con lo que inicia sesión.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.85rem" for="password">
                Contraseña @unless ($nuevo)<span class="fw-normal text-body-tertiary">(opcional)</span>@endunless
            </label>
            <input class="form-control" id="password" name="password" type="password"
                   autocomplete="new-password" @if ($nuevo) required @endif minlength="8">
            <div class="text-body-tertiary mt-1" style="font-size:.75rem">
                {{ $nuevo ? 'Mínimo 8 caracteres.' : 'Déjala vacía para no cambiarla.' }}
            </div>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.85rem" for="password_confirmation">Repetir contraseña</label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password"
                   autocomplete="new-password" @if ($nuevo) required @endif>
        </div>

        <div class="col-12">
            <label class="d-flex gap-2 align-items-center" style="font-size:.85rem">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $usuario?->activo ?? true))>
                <span>Cuenta activa — si se desmarca, no puede iniciar sesión.</span>
            </label>
        </div>
    </div>
</div>
