@extends('layouts.app')

@section('titulo', $alumno->nombre_completo . ' · TUTOLAR')
@section('migas', '<span>Centro</span> › <span>Alumnos</span> › <b>' . e($alumno->nombre_completo) . '</b>')

@section('contenido')
@include('centro.partials.flash')
@php use App\Support\Nivel; $irg = $rendimiento['global']; @endphp

<div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('centro.alumnos.edit', $alumno) }}">Editar ficha</a>
    <form method="POST" action="{{ route('centro.alumnos.estado', $alumno) }}" class="d-inline">
        @csrf @method('PATCH')
        <button class="btn btn-outline-secondary btn-sm" type="submit">
            {{ $alumno->activo ? 'Dar de baja' : 'Reactivar' }}
        </button>
    </form>
    @include('centro.partials.confirmar', [
        'url'          => route('centro.alumnos.destroy', $alumno),
        'texto'        => 'Eliminar',
        'confirmacion' => 'Solo si no tiene histórico. Pulsa otra vez.',
    ])
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        @include('partials.avatar', ['persona' => $alumno, 'tam' => 'lg'])
        <div>
            <h1 class="tl-titulo">{{ $alumno->nombre_completo }}</h1>
            <div class="text-body-secondary" style="font-size:.87rem">
                {{ $alumno->grupo?->nombre_completo }} · {{ $alumno->edad }} años ·
                {{ $alumno->tutores->count() }}
                {{ $alumno->tutores->count() === 1 ? 'familia vinculada' : 'familias vinculadas' }}
            </div>
        </div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('centro.alumnos') }}">← Volver al listado</a>
</div>

@include('centro.partials.flash')

@if ($alumno->tutores->isEmpty())
    <div class="tl-banner tl-bajo mb-3">
        <span aria-hidden="true">⚠️</span>
        <div><b>Sin familia vinculada.</b> Este alumno no recibe avisos y nadie puede registrar
            sus resultados. Genera un código de vinculación aquí abajo y dáselo a la familia.</div>
    </div>
@endif

{{--
    Códigos de vinculación.

    Es la autorización del centro puesta por escrito: con este código, y solo
    con él, una familia puede crear su cuenta y quedar atada a este alumno. Sin
    esta pieza, el registro tendría que preguntar por el alumno en un desplegable
    y cualquiera podría vincularse a cualquier menor.
--}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Vinculación de familias</span>
        <span class="fw-normal text-body-tertiary" style="font-size:.75rem">
            {{ $alumno->tutores->count() }} de {{ \App\Models\Tutela::MAX_POR_ALUMNO }} plazas ocupadas
        </span>
    </div>
    <div class="card-body">
        @forelse ($codigos as $c)
            <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                <span class="tl-codigo-chip">{{ $c->bonito() }}</span>
                <span class="text-body-secondary" style="font-size:.82rem">
                    Vale hasta el {{ $c->caduca_en->format('d/m/Y') }} · para una sola cuenta
                </span>
            </div>
        @empty
            <p class="text-body-secondary mb-3" style="font-size:.87rem">
                No hay ningún código activo para {{ $alumno->nombre }}.
            </p>
        @endforelse

        <form method="POST" action="{{ route('centro.alumnos.codigo', $alumno) }}" class="mt-3">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" type="submit">Generar un código nuevo</button>
        </form>

        <p class="text-body-tertiary mt-3 mb-0" style="font-size:.78rem">
            La familia lo introduce en <b>Crear cuenta de familia</b>, en la pantalla de acceso.
            Caduca a los {{ \App\Models\CodigoVinculacion::DIAS_VALIDEZ }} días y se gasta al usarlo.
        </p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                @include('partials.dial', [
                    'valor'    => $irg['valor'],
                    'nivel' => $irg['nivel'],
                    'etiqueta' => $irg['etiqueta'],
                    'tam'      => 170,
                ])
                <div class="text-body-tertiary mt-3" style="font-size:.75rem">
                    Índice de Rendimiento Global<br>
                    {{ $rendimiento['total_resultados'] }} resultados registrados
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Rendimiento por asignatura</span>
                <span class="fw-normal text-body-tertiary" style="font-size:.75rem">De peor a mejor</span>
            </div>
            <div class="table-responsive">
                <table class="tl-table">
                    <caption class="visually-hidden">Índice por asignatura</caption>
                    <thead>
                        <tr>
                            <th scope="col">Asignatura</th>
                            <th scope="col">Profesor</th>
                            <th scope="col" class="num">Exám.</th>
                            <th scope="col" class="num">Tareas</th>
                            <th scope="col" class="num">IRA</th>
                            <th scope="col" style="width:200px">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rendimiento['asignaturas'] as $fila)
                        @php $ira = $fila['ira']; @endphp
                        <tr>
                            <td class="fw-semibold">{{ $fila['asignatura']->denominacion }}</td>
                            <td class="text-body-secondary">
                                {{ $fila['profesor']?->nombre_completo ?? '—' }}
                            </td>
                            <td class="num text-body-secondary">
                                {{ $ira['media_examenes'] === null ? '—' : round($ira['media_examenes']) }}
                            </td>
                            <td class="num text-body-secondary">
                                {{ $ira['media_tareas'] === null ? '—' : round($ira['media_tareas']) }}
                            </td>
                            <td class="num tl-cifra {{ Nivel::clase($ira['nivel']) }}">
                                {{ Nivel::cifra($ira['valor']) }}
                            </td>
                            <td>@include('partials.nivel', ['ira' => $ira])</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">Últimos movimientos</div>
            <div class="table-responsive">
                <table class="tl-table">
                    <caption class="visually-hidden">Historial de resultados registrados</caption>
                    <thead>
                        <tr>
                            <th scope="col">Evaluable</th>
                            <th scope="col">Asignatura</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Registrado por</th>
                            <th scope="col">Origen</th>
                            <th scope="col">Cuándo</th>
                            <th scope="col" class="num">Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($movimientos as $r)
                        @php
                            $nota = (float) $r->puntuacion_obtenida;
                            $c = Nivel::de($nota * 10);
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $r->evaluable?->titulo ?? '—' }}</td>
                            <td class="text-body-secondary">
                                {{ $r->evaluable?->imparticion?->asignatura?->denominacion ?? '—' }}
                            </td>
                            <td class="text-body-secondary">
                                {{ $r->evaluable?->tipo === 'EXAMEN' ? 'Examen' : 'Tarea' }}
                            </td>
                            <td class="text-body-secondary">{{ $r->autor?->name ?? 'Sistema' }}</td>
                            <td>
                                <span class="tl-chip {{ $r->estaVerificado() ? 'tl-info' : 'tl-sin' }}">
                                    {{ $r->origen }}
                                </span>
                            </td>
                            <td class="text-body-tertiary">{{ $r->created_at->diffForHumans(short: true) }}</td>
                            <td class="num tl-cifra {{ Nivel::clase($c) }}">
                                {{ number_format($nota, 1, ',', '') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">
                            <div class="tl-empty">
                                <div class="em">📊</div>
                                <b>Todavía no hay resultados registrados</b>
                                <p class="mb-0">
                                    TUTOLAR necesita al menos dos resultados por asignatura
                                    para calcular el rendimiento.
                                </p>
                            </div>
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
