{{--
    Cabecera del alumno. Parámetros: $alumno, y opcionalmente $activa
    ('inicio' o 'rendimiento') para marcar en qué pantalla estamos.

    Las dos pantallas de un hijo comparten identidad, así que comparten
    cabecera: quien pasa de una a otra tiene que ver que sigue mirando al mismo
    niño, no aterrizar en algo que parece otra aplicación.
--}}
@php $activa = $activa ?? 'inicio'; @endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        @include('partials.avatar', ['persona' => $alumno, 'tam' => 'lg'])
        <div>
            <h1 class="tl-titulo">{{ $alumno->nombre_completo }}</h1>
            <div class="text-body-secondary" style="font-size:.87rem">
                {{ $alumno->grupo?->nombre_completo }} · {{ $alumno->centro?->denominacion }}
            </div>
        </div>
    </div>

    <nav class="tl-pestanas" aria-label="Secciones de {{ $alumno->nombre }}">
        <a class="{{ $activa === 'inicio' ? 'on' : '' }}"
           href="{{ route('familia.inicio', ['alumno' => $alumno->id]) }}"
           @if ($activa === 'inicio') aria-current="page" @endif>Resumen</a>
        <a class="{{ $activa === 'rendimiento' ? 'on' : '' }}"
           href="{{ route('familia.rendimiento', ['alumno' => $alumno->id]) }}"
           @if ($activa === 'rendimiento') aria-current="page" @endif>Gráficos</a>
    </nav>
</div>
