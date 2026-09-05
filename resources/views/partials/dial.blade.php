{{--
    Dial de rendimiento.

    Parámetros: $valor (float|null), $nivel (constante de Nivel),
                $etiqueta (string), $tam (px, opcional).

    Sin datos no muestra un cero: muestra un guion y un arco vacío. Cero y
    «no sabemos» son cosas distintas, y confundirlas es el error más caro de
    esta aplicación.
--}}
@php
    $tam    = $tam ?? 170;
    $radio  = $tam / 2 - 14;
    $circ   = 2 * M_PI * $radio;
    $pct    = $valor === null ? 0 : max(0, min(100, $valor));
    $offset = $circ * (1 - $pct / 100);
    $token  = \App\Support\Nivel::token($nivel);
    $grosor = $tam >= 150 ? 13 : 9;
@endphp

<div class="tl-dial" style="width: {{ $tam }}px; height: {{ $tam }}px"
     role="img"
     aria-label="{{ $etiqueta }}{{ $valor === null ? '' : ': '.round($valor).' sobre 100' }}">
    <svg width="{{ $tam }}" height="{{ $tam }}" viewBox="0 0 {{ $tam }} {{ $tam }}" aria-hidden="true">
        <circle cx="{{ $tam/2 }}" cy="{{ $tam/2 }}" r="{{ $radio }}" fill="none"
                stroke="var(--tl-surface-3)" stroke-width="{{ $grosor }}"/>
        <circle cx="{{ $tam/2 }}" cy="{{ $tam/2 }}" r="{{ $radio }}" fill="none"
                stroke="var(--tl-{{ $token }}-fill)" stroke-width="{{ $grosor }}"
                stroke-linecap="round"
                stroke-dasharray="{{ round($circ, 1) }}"
                stroke-dashoffset="{{ round($offset, 1) }}"/>
    </svg>
    <div class="in">
        <div class="v" style="font-size: {{ $tam >= 150 ? '2.4rem' : '1.6rem' }}">
            {{ \App\Support\Nivel::cifra($valor) }}@if($valor !== null)<span>%</span>@endif
        </div>
        <div class="l" style="color: var(--tl-{{ $token }})">
            @include('partials.segmentos', ['nivel' => $nivel, 'tam' => 'g'])
            {{ $etiqueta }}
        </div>
    </div>
</div>
