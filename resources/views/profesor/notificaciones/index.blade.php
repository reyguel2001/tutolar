@extends('layouts.app')
@section('titulo', 'Notificaciones · TUTOLAR')
@section('org-icono', '🧑‍🏫')
@section('org-titulo', $profesor->nombre_completo)
@section('org-sub', 'Profesorado')
@section('migas', '<span>Docencia</span> › <b>Notificar</b>')

@section('contenido')
@include('centro.partials.flash')

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="tl-titulo">Notificaciones</h1>
        <div class="text-body-secondary" style="font-size:.87rem">
            Avisos que has enviado a las familias.
        </div>
    </div>
    <a class="btn btn-primary" href="{{ route('profesor.notificaciones.create') }}">Nuevo aviso</a>
</div>

<div class="tl-banner tl-sin mb-3">
    <span aria-hidden="true">💡</span>
    <div><b>Leída</b> lo marca el sistema cuando la familia abre su bandeja.
        <b>Confirmada</b> lo marca ella a mano: es la que vale como «me he enterado».</div>
</div>

<div class="card">
    @if ($notificaciones->isEmpty())
        @include('centro.partials.vacio', [
            'mensaje' => 'Todavía no has enviado ningún aviso.',
            'pista'   => 'Puedes anunciar un examen, asignar una tarea o avisar de que ya hay notas.',
        ])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Avisos enviados</caption>
            <thead><tr>
                <th scope="col">Aviso</th>
                <th scope="col">Grupo y materia</th>
                <th scope="col">Enviado</th>
                <th scope="col" class="num">Familias</th>
                <th scope="col" style="width:200px">Confirmación</th>
            </tr></thead>
            <tbody>
            @foreach ($notificaciones as $n)
                @php
                    /* El porcentaje de confirmación se colorea con la misma
                       escala que el rendimiento. Antes había aquí un ternario
                       propio con sus umbrales y sus nombres de color: dos
                       sistemas de color en la misma aplicación es como acaban
                       apareciendo rojos donde el diseño pide gris. */
                    $confirmado = $n->destinatarios_count > 0
                        ? round($n->confirmadas_count / $n->destinatarios_count * 100)
                        : null;
                    $pct   = $confirmado ?? 0;
                    $token = \App\Support\Nivel::token(
                        \App\Support\Nivel::de($confirmado === null ? null : (float) $confirmado, 40, 70)
                    );
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <span aria-hidden="true">{{ $n->icono() }}</span> {{ $n->titulo }}
                        </div>
                        <div class="text-body-tertiary" style="font-size:.75rem">{{ $n->etiquetaTipo() }}</div>
                    </td>
                    <td class="text-body-secondary">
                        {{ $n->grupo?->nombre_completo ?? '—' }}
                        <span class="text-body-tertiary">·
                            {{ $n->evaluable?->imparticion?->asignatura?->denominacion ?? '—' }}</span>
                    </td>
                    <td class="text-body-secondary">{{ $n->emitida_en?->format('d/m/Y H:i') }}</td>
                    <td class="num">{{ $n->destinatarios_count }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="tl-meter flex-grow-1" style="min-width:70px">
                                <i class="tl-fill-{{ $token }}" style="width: {{ $pct }}%"></i>
                            </div>
                            <span class="text-body-secondary" style="font-size:.78rem; white-space:nowrap">
                                {{ $n->confirmadas_count }}/{{ $n->destinatarios_count }}
                            </span>
                        </div>
                        <div class="text-body-tertiary" style="font-size:.72rem">
                            {{ $n->leidas_count }} leídas
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $notificaciones->links() }}</div>
    @endif
</div>
@endsection
