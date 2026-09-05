@extends('layouts.app')
@section('titulo', $profesor->nombre_completo . ' · TUTOLAR')
@section('migas', '<span>Gestión</span> › <a href="' . route('centro.profesores.index') . '">Profesores</a> › <b>' . e($profesor->nombre_completo) . '</b>')

@section('contenido')
@include('centro.partials.flash')

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="tl-titulo">{{ $profesor->nombre_completo }}</h1>
        <div class="text-body-secondary" style="font-size:.87rem">{{ $profesor->user?->email }}</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('centro.profesores.edit', $profesor) }}">Editar</a>
        @include('centro.partials.confirmar', [
            'url'          => route('centro.profesores.destroy', $profesor),
            'texto'        => 'Eliminar',
            'confirmacion' => 'Se borrará la cuenta. Pulsa otra vez.',
        ])
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Asignaturas que imparte</div>
            @if ($profesor->imparticiones->isEmpty())
                @include('centro.partials.vacio', ['mensaje' => 'No tiene ninguna asignatura asignada.'])
            @else
            <div class="table-responsive">
                <table class="tl-table">
                    <caption class="visually-hidden">Asignaturas y grupos</caption>
                    <thead><tr><th scope="col">Asignatura</th><th scope="col">Grupo</th></tr></thead>
                    <tbody>
                    @foreach ($profesor->imparticiones as $i)
                        <tr>
                            <td class="fw-semibold">{{ $i->asignatura?->denominacion }}</td>
                            <td class="text-body-secondary">{{ $i->grupo?->nombre_completo }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Tutorías de grupo</div>
            @if ($tutorias->isEmpty())
                @include('centro.partials.vacio', ['mensaje' => 'No es tutor de ningún grupo.'])
            @else
                <div class="card-body d-flex flex-wrap gap-2">
                    @foreach ($tutorias as $g)
                        <span class="tl-chip tl-info">{{ $g->nombre_completo }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
