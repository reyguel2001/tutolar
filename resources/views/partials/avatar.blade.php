{{--
    Avatar de una persona.

    Parámetros:
      $persona  · cualquier modelo con el trait TieneAvatar (User, Alumno,
                  Profesor, TutorLegal)
      $tam      · 'xs' | 'sm' | 'md' | 'lg' | 'xl', o un número de píxeles
      $etiqueta · texto alternativo; por defecto, el nombre de la persona

    Con foto se muestra la foto. Sin foto se dibuja una silueta —de adulto o de
    niño según de quién sea— sobre el color que le corresponde a esa persona,
    que sale de su nombre y por tanto es siempre el mismo.

    Por qué silueta y no iniciales: en la pantalla de una familia conviven la
    madre y el hijo, y dos juegos de iniciales sobre dos círculos de colores no
    dicen cuál es cuál. La silueta sí, sin leer nada.
--}}
@php
    use App\Support\Avatar;

    $px       = Avatar::pixeles($tam ?? 'sm');
    $foto     = $persona->avatarUrl();
    $tipo     = $persona->tipoAvatar();
    $color    = $persona->colorAvatar();
    $nombre   = $persona->nombre_completo ?? $persona->name ?? '';
    $etiqueta = $etiqueta ?? $nombre;

    // Geometría sobre un lienzo de 40×40, escalado después por CSS.
    // El niño tiene la cabeza proporcionalmente mayor y los hombros más
    // estrechos: es la diferencia que el ojo lee como «niño» sin explicárselo.
    $silueta = $tipo === Avatar::NINO
        ? ['cy' => 16.0, 'r' => 7.8, 'hombros' => 9.0]    // cabeza grande, hombros estrechos
        : ['cy' => 14.0, 'r' => 5.7, 'hombros' => 12.6];  // cabeza pequeña, hombros anchos

    $baseY = 35;
@endphp

<span class="tl-avatar" style="width: {{ $px }}px; height: {{ $px }}px; background: {{ $foto ? 'transparent' : $color }}"
      title="{{ $nombre }}">
    @if ($foto)
        <img src="{{ $foto }}" alt="{{ $etiqueta }}" width="{{ $px }}" height="{{ $px }}" loading="lazy">
    @else
        <svg viewBox="0 0 40 40" width="{{ $px }}" height="{{ $px }}"
             role="img" aria-label="{{ $etiqueta }}">
            <g fill="#fff" fill-opacity=".92">
                <circle cx="20" cy="{{ $silueta['cy'] }}" r="{{ $silueta['r'] }}"/>
                <path d="M{{ 20 - $silueta['hombros'] }} {{ $baseY }}
                         a{{ $silueta['hombros'] }} {{ $silueta['hombros'] }} 0 0 1 {{ 2 * $silueta['hombros'] }} 0 Z"/>
            </g>
        </svg>
    @endif
</span>
