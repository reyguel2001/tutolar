{{--
    Cabecera de pantalla: título, frase de contexto y acción principal.
    Parámetros: $titulo, $sub (opcional), $accionUrl y $accionTexto (opcionales).
--}}
<div class="tl-cab">
    <div>
        <h1>{{ $titulo }}</h1>
        @isset($sub)<p>{{ $sub }}</p>@endisset
    </div>
    @isset($accionUrl)
        <a class="btn btn-primary" href="{{ $accionUrl }}">{{ $accionTexto ?? 'Nuevo' }}</a>
    @endisset
</div>
