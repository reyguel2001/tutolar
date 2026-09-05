{{--
    Barra + chip del nivel de rendimiento.

    Es el componente que hace cumplir la regla del sistema de diseño: el color
    nunca viaja solo. Aquí lo acompañan la longitud de la barra, los tres
    segmentos y la palabra. Con cualquiera de las tres el nivel se lee.

    Parámetros: $ira (array devuelto por RendimientoService::calcularIra)
--}}
@php
    $nivel = $ira['nivel'];
    $token = \App\Support\Nivel::token($nivel);
@endphp

<div class="d-flex align-items-center gap-3">
    <div class="tl-meter flex-grow-1" style="min-width:80px">
        <i class="tl-fill-{{ $token }}" style="width: {{ $ira['valor'] ?? 0 }}%"></i>
    </div>
    <span class="tl-chip tl-{{ $token }}">
        @include('partials.segmentos', ['nivel' => $nivel])
        {{ \App\Support\Nivel::etiquetaCorta($nivel) }}
    </span>
</div>
