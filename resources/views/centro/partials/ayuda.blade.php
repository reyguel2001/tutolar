{{--
    Ayuda de pantalla, plegada.

    Antes esto eran dos o tres avisos apilados encima de la tabla, siempre
    abiertos, que empujaban el contenido hacia abajo y que quien usa la pantalla
    a diario deja de leer a la segunda semana. La explicación sigue estando —es
    lo que hace que estas pantallas se entiendan sin manual— pero se pide.

    Los avisos abiertos se reservan para lo que depende de los datos: una
    confirmación, un error o una advertencia que solo aparece cuando toca.

    Parámetros: $puntos, un array de ['titulo' => string, 'texto' => string|html]
--}}
<details class="tl-ayuda mb-3">
    <summary>Cómo funciona esta pantalla</summary>
    <div class="cuerpo">
        @foreach ($puntos as $p)
            <p><b>{{ $p['titulo'] }}</b> {!! $p['texto'] !!}</p>
        @endforeach
    </div>
</details>
