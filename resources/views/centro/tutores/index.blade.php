@extends('layouts.app')
@section('titulo', 'Tutores · TUTOLAR')
@section('migas', '<span>Gestión</span> › <b>Tutores</b>')

@section('contenido')
@include('centro.partials.flash')
@include('centro.partials.cabecera', [
    'titulo'      => 'Tutores',
    'sub'         => $tutores->total() . ' ' . ($tutores->total() === 1 ? 'tutor legal' : 'tutores legales'),
    'accionUrl'   => route('centro.tutores.create'),
    'accionTexto' => 'Nuevo tutor',
])

<div class="card">
    <form method="GET" class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input class="form-control" type="search" name="q" value="{{ $q }}" style="max-width:320px"
               placeholder="Buscar por nombre, apellidos o correo" aria-label="Buscar tutor">
        <button class="btn btn-primary btn-sm" type="submit">Buscar</button>
        @if ($q)<a class="btn btn-outline-secondary btn-sm" href="{{ route('centro.tutores.index') }}">Limpiar</a>@endif
    </form>

    @if ($tutores->isEmpty())
        @include('centro.partials.vacio', [
            'mensaje' => 'No hay tutores que mostrar.',
            'pista'   => 'Los tutores se dan de alta aquí o se registran con un código de vinculación.',
        ])
    @else
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">Tutores legales del centro</caption>
            <thead><tr>
                <th scope="col">Tutor</th>
                <th scope="col">Correo</th>
                <th scope="col">Teléfono</th>
                <th scope="col" class="num">Alumnos</th>
                <th scope="col">Cuenta</th>
                <th scope="col"></th>
            </tr></thead>
            <tbody>
            @foreach ($tutores as $tutor)
                <tr>
                    <td class="fw-semibold">
                        <a href="{{ route('centro.tutores.show', $tutor) }}">{{ $tutor->nombre_completo }}</a>
                    </td>
                    <td class="text-body-secondary">{{ $tutor->user?->email ?? '—' }}</td>
                    <td class="text-body-secondary">{{ $tutor->telefono }}</td>
                    <td class="num">{{ $tutor->alumnos_count }}</td>
                    <td>
                        <span class="tl-chip {{ $tutor->user?->activo ? 'tl-alto' : 'tl-sin' }}">
                            {{ $tutor->user?->activo ? 'ACTIVA' : 'INACTIVA' }}
                        </span>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('centro.tutores.edit', $tutor) }}">Editar</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $tutores->links() }}</div>
    @endif
</div>
@endsection
