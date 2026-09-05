@extends('layouts.app')
@section('titulo', 'Solicitudes de vinculación · TUTOLAR')
@section('migas', '<span>Gestión</span> › <b>Solicitudes de familias</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo' => 'Solicitudes de familias',
    'sub'    => 'Peticiones de código de vinculación llegadas desde la web.',
])

<div class="tl-banner tl-sin mb-3">
    <span aria-hidden="true">🔎</span>
    <div>Lo que hay en cada fila es <b>lo que esa persona ha escrito</b>, sin comprobar.
        Contrástalo con lo que sabes de la familia antes de aprobar: al aprobar se emite
        un código que da acceso a las notas de ese menor. Y entrégaselo por un medio que
        ya tengas de esa familia, <b>no por el correo que ha puesto en la solicitud</b>.</div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        @foreach ([
            \App\Models\SolicitudVinculacion::PENDIENTE => 'Pendientes',
            \App\Models\SolicitudVinculacion::APROBADA  => 'Aprobadas',
            \App\Models\SolicitudVinculacion::RECHAZADA => 'Descartadas',
        ] as $clave => $rotulo)
            <a class="btn btn-sm {{ $estado === $clave ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('centro.solicitudes.index', ['estado' => $clave]) }}">
                {{ $rotulo }}
                @if ($clave === \App\Models\SolicitudVinculacion::PENDIENTE && $pendientes > 0)
                    ({{ $pendientes }})
                @endif
            </a>
        @endforeach
    </div>

    @if ($solicitudes->isEmpty())
        @include('centro.partials.vacio', [
            'mensaje' => 'No hay solicitudes con ese estado.',
            'pista'   => 'Las familias las envían desde «Pedir un código» en la pantalla de acceso.',
        ])
    @else
        <div class="card-body">
        @foreach ($solicitudes as $s)
            <div class="tl-solicitud">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="tl-solicitud-lbl">Dice ser familia de</div>
                        <div class="fw-semibold">{{ $s->alumno_declarado }}</div>
                        @if ($s->alumno_curso)
                            <div class="text-body-secondary" style="font-size:.82rem">{{ $s->alumno_curso }}</div>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <div class="tl-solicitud-lbl">Quien lo pide</div>
                        <div class="fw-semibold">{{ $s->nombre_completo }}</div>
                        <div class="text-body-secondary" style="font-size:.82rem">
                            {{ \App\Models\Tutela::etiqueta($s->parentesco) }} ·
                            {{ $s->telefono }} · {{ $s->email }}
                        </div>
                    </div>

                    @if ($s->mensaje)
                        <div class="col-12">
                            <div class="tl-solicitud-lbl">Mensaje</div>
                            <div style="font-size:.87rem">{{ $s->mensaje }}</div>
                        </div>
                    @endif
                </div>

                <div class="text-body-tertiary mt-2" style="font-size:.75rem">
                    Recibida el {{ $s->created_at?->format('d/m/Y H:i') }}
                    @if ($s->estado !== \App\Models\SolicitudVinculacion::PENDIENTE)
                        · {{ $s->estado === \App\Models\SolicitudVinculacion::APROBADA ? 'Aprobada' : 'Descartada' }}
                        el {{ $s->resuelta_en?->format('d/m/Y') }}
                        por {{ $s->resolutor?->name ?? 'una cuenta ya eliminada' }}
                        @if ($s->codigo_emitido) · código emitido @endif
                    @endif
                </div>

                @if ($s->estado === \App\Models\SolicitudVinculacion::PENDIENTE)
                    @php $candidatos = $s->candidatos(); @endphp

                    <div class="mt-3 pt-3 border-top">
                        @if ($candidatos->isEmpty())
                            <div class="tl-banner tl-medio mb-2">
                                <span aria-hidden="true">⚠️</span>
                                <div>Ningún alumno del centro se parece a ese nombre. Compruébalo
                                    en <a href="{{ route('centro.alumnos', ['q' => $s->alumno_apellidos]) }}">el
                                    listado</a> antes de descartarla.</div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('centro.solicitudes.aprobar', $s) }}"
                                  class="d-flex flex-wrap gap-2 align-items-end">
                                @csrf
                                <div style="flex:1 1 320px">
                                    <label class="form-label fw-semibold" style="font-size:.8rem"
                                           for="alumno-{{ $s->id }}">Corresponde a</label>
                                    <select class="form-control" id="alumno-{{ $s->id }}" name="alumno_id" required>
                                        <option value="">Señala el alumno…</option>
                                        @foreach ($candidatos as $a)
                                            <option value="{{ $a->id }}">
                                                {{ $a->nombre_completo }} · {{ $a->grupo?->nombre_completo }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-primary" type="submit">Aprobar y emitir código</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('centro.solicitudes.rechazar', $s) }}"
                              class="mt-2 tl-confirmar" data-confirmacion="Pulsa otra vez para descartarla">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Descartar</button>
                            <span class="text-body-tertiary ms-2" style="font-size:.75rem">
                                No se avisa a quien la envió.
                            </span>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach

        {{ $solicitudes->links() }}
        </div>
    @endif
</div>
@endsection
