@extends('layouts.app')
@section('titulo', 'Profesores · TUTOLAR')
@section('migas', '<span>Gestión</span> › <b>Profesores</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo'      => 'Profesores',
    'sub'         => $profesores->total() . ' ' . ($profesores->total() === 1 ? 'profesor' : 'profesores'),
    'accionUrl'   => route('centro.profesores.create'),
    'accionTexto' => 'Nuevo profesor',
])

<div class="card">
    <form method="GET" class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input class="form-control" type="search" name="q" value="{{ $q }}" style="max-width:320px"
               placeholder="Buscar por nombre, apellidos o correo" aria-label="Buscar profesor">
        <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
        @if ($q)<a class="btn btn-outline-secondary btn-sm" href="{{ route('centro.profesores.index') }}">Limpiar</a>@endif
    </form>

    @if ($profesores->isEmpty())
        @include('centro.partials.vacio', ['mensaje' => 'Todavía no hay profesores dados de alta.'])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Profesores del centro</caption>
            <thead><tr>
                <th scope="col">Profesor</th><th scope="col">Correo</th>
                <th scope="col" class="num">Asignaturas</th><th scope="col">Cuenta</th><th scope="col"></th>
            </tr></thead>
            <tbody>
            @foreach ($profesores as $p)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('centro.profesores.show', $p) }}">{{ $p->nombre_completo }}</a></td>
                    <td class="text-body-secondary">{{ $p->user?->email ?? '—' }}</td>
                    <td class="num">{{ $p->imparticiones_count }}</td>
                    <td><span class="tl-chip {{ $p->user?->activo ? 'tl-alto' : 'tl-sin' }}">
                        {{ $p->user?->activo ? 'ACTIVA' : 'INACTIVA' }}</span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('centro.profesores.edit', $p) }}">Editar</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $profesores->links() }}</div>
    @endif
</div>
@endsection
