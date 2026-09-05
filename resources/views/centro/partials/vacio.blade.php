{{-- Estado vacío de una tabla. Parámetros: $mensaje, $pista (opcional). --}}
<div class="tl-empty">
    <p class="mb-1"><b>{{ $mensaje }}</b></p>
    @isset($pista)
        <p class="mb-0 text-body-secondary" style="font-size:.87rem">{{ $pista }}</p>
    @endisset
</div>
