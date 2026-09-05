{{--
    Los tres segmentos del nivel de rendimiento.

    Es la señal que no depende del color: uno lleno es BAJO, dos MEDIO, tres
    ALTO y ninguno SIN DATOS. Va siempre acompañado de la palabra, nunca solo,
    así que aquí es decorativo para el lector de pantalla.

    Parámetros: $nivel (constante de Nivel), $tam ('sm' por defecto | 'g')
--}}
@php
    $llenos = \App\Support\Nivel::segmentos($nivel);
@endphp
<span class="tl-segs{{ ($tam ?? 'sm') === 'g' ? ' g' : '' }}" aria-hidden="true">
    @for ($i = 1; $i <= 3; $i++)<i @class(['on' => $i <= $llenos])></i>@endfor
</span>
