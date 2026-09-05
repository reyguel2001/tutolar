@extends('layouts.app')
@section('titulo', 'Avisos del centro · TUTOLAR')
@section('org-icono', '👨‍👩‍👧')
@section('org-titulo', $tutor->nombre_completo)
@section('org-sub', $hijos->count() . ' alumnos vinculados')
@section('migas', '<b>Avisos del centro</b>')

@section('contenido')
@include('centro.partials.flash')

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="tl-titulo">Avisos del centro</h1>
        <div class="text-body-secondary" style="font-size:.87rem">
            Exámenes, tareas y notas que anuncia el profesorado.
        </div>
    </div>
</div>

<div class="card">
    @if ($avisos->isEmpty())
        @include('centro.partials.vacio', [
            'mensaje' => 'No tienes ningún aviso todavía.',
            'pista'   => 'Aquí aparecerán los exámenes y tareas que anuncie el profesorado.',
        ])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Avisos recibidos</caption>
            <thead><tr>
                <th scope="col">Aviso</th>
                <th scope="col">Alumno</th>
                <th scope="col">Fecha</th>
                <th scope="col" style="width:220px"></th>
            </tr></thead>
            <tbody>
            @foreach ($avisos as $aviso)
                @php $n = $aviso->notificacion; @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <span aria-hidden="true">{{ $n?->icono() }}</span> {{ $n?->titulo }}
                        </div>
                        @if ($n?->mensaje)
                            <div class="text-body-secondary" style="font-size:.8rem">{{ $n->mensaje }}</div>
                        @endif
                        @if ($n?->evaluable)
                            <div class="text-body-tertiary" style="font-size:.75rem">
                                {{ $n->evaluable->fecha_prevista?->format('d/m/Y') }} ·
                                sobre {{ rtrim(rtrim(number_format((float) $n->evaluable->puntuacion_maxima, 2, ',', ''), '0'), ',') }}
                            </div>
                        @endif
                    </td>
                    <td class="text-body-secondary">{{ $aviso->alumno?->nombre }}</td>
                    <td class="text-body-secondary">{{ $n?->emitida_en?->format('d/m/Y H:i') }}</td>
                    <td class="text-end">
                        @if ($aviso->estaConfirmada())
                            <span class="tl-chip tl-alto">CONFIRMADO</span>
                        @else
                            <form method="POST" action="{{ route('familia.avisos.confirmar', $aviso->id) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Confirmar lectura</button>
                            </form>
                        @endif

                        @if ($n?->pideRegistro())
                            <a class="btn btn-sm btn-primary"
                               href="{{ route('familia.inicio', ['alumno' => $aviso->alumno_id]) }}#registrar">
                                Apuntar la nota
                            </a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $avisos->links() }}</div>
    @endif
</div>
@endsection
