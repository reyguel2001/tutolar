{{--
    Botón de baja con confirmación.

    No usa confirm() del navegador: un diálogo nativo bloquea la página y en un
    ordenador de secretaría es justo donde alguien pulsa Aceptar sin leer. En su
    lugar el botón pide una segunda pulsación y dice qué va a pasar.

    Parámetros: $url, $texto (opcional), $confirmacion (opcional).
--}}
<form method="POST" action="{{ $url }}" class="d-inline tl-confirmar"
      data-confirmacion="{{ $confirmacion ?? '¿Seguro? Pulsa otra vez para confirmar.' }}">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $texto ?? 'Eliminar' }}</button>
</form>
