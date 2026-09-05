@extends('layouts.app')

@section('titulo', 'Alumnos · TUTOLAR')
@section('migas', '<span>Centro</span> › <b>Alumnos</b>')

@section('contenido')
@php use App\Support\Nivel; @endphp

@include('centro.partials.flash')

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="tl-titulo">Alumnos</h1>
        <div class="text-body-secondary" style="font-size:.87rem">
            {{ $total }} {{ $total === 1 ? 'alumno' : 'alumnos' }}
            @if ($filtros['estado']) · filtrado por {{ Nivel::etiqueta($filtros['estado']) }} @endif
            @if ($verBajas) · incluyendo bajas @endif
        </div>
    </div>
    <a class="btn btn-primary" href="{{ route('centro.alumnos.create') }}">Nuevo alumno</a>
</div>

<div class="tl-banner tl-sin mb-3">
    <span aria-hidden="true">🔒</span>
    <div>Listado confidencial con datos de menores. Cada consulta y cada exportación
        quedan registradas en el sistema de auditoría del centro.</div>
</div>

<div class="card">
    {{-- ---------- Barra de filtros ---------- --}}
    <form method="GET" class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input class="form-control" type="search" name="q" value="{{ $filtros['q'] }}"
               placeholder="Buscar por nombre o apellidos"
               aria-label="Buscar alumno" style="max-width:260px">

        <select class="form-select" name="estado" aria-label="Filtrar por rendimiento" style="max-width:200px">
            <option value="">Rendimiento: todos</option>
            @foreach ([Nivel::BAJO, Nivel::MEDIO, Nivel::ALTO, Nivel::SIN_DATOS] as $c)
                <option value="{{ $c }}" @selected($filtros['estado'] === $c)>{{ Nivel::etiqueta($c) }}</option>
            @endforeach
        </select>

        <select class="form-select" name="grupo" aria-label="Filtrar por grupo" style="max-width:180px">
            <option value="">Grupo: todos</option>
            @foreach ($grupos as $g)
                <option value="{{ $g->id }}" @selected((string) $filtros['grupo'] === (string) $g->id)>
                    {{ $g->nombre_completo }}
                </option>
            @endforeach
        </select>

        <label class="d-flex align-items-center gap-2" style="font-size:.8rem">
            <input type="checkbox" name="bajas" value="1" @checked($verBajas)>
            <span>Ver también las bajas</span>
        </label>

        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        @if ($filtros['q'] || $filtros['estado'] || $filtros['grupo'] || $verBajas)
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('centro.alumnos') }}">Limpiar</a>
        @endif
    </form>

    {{-- ---------- Tabla ---------- --}}
    <div class="table-responsive">
        <table class="tl-table">
            <caption class="visually-hidden">
                Alumnos del centro ordenados de menor a mayor rendimiento
            </caption>
            <thead>
                <tr>
                    <th scope="col">Alumno</th>
                    <th scope="col">Grupo</th>
                    <th scope="col" class="num">IRG</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Asignaturas en rojo</th>
                    <th scope="col">Familia</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($alumnos as $fila)
                @php
                    $a   = $fila['modelo'];
                    $irg = $fila['irg'];
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @include('partials.avatar', ['persona' => $a, 'tam' => 'sm'])
                            <a href="{{ route('centro.alumno', $a) }}" class="fw-semibold">
                                {{ $a->nombre_completo }}
                            </a>
                        </div>
                    </td>
                    <td class="text-body-secondary">{{ $a->grupo?->nombre_completo }}</td>
                    <td class="num tl-cifra {{ Nivel::clase($irg['nivel']) }}">
                        {{ Nivel::cifra($irg['valor']) }}
                    </td>
                    <td>
                        <span class="tl-chip {{ Nivel::clase($irg['nivel']) }}">
                            @include('partials.segmentos', ['nivel' => $irg['nivel']])
                            {{ Nivel::etiquetaCorta($irg['nivel']) }}
                        </span>
                    </td>
                    <td class="text-body-secondary" style="font-size:.8rem">
                        {{ $fila['en_bajo'] ? implode(', ', $fila['en_bajo']) : '—' }}
                    </td>
                    <td>
                        @if ($fila['vinculada'])
                            <span class="text-body-secondary" style="font-size:.8rem">Vinculada</span>
                        @else
                            <span class="tl-chip tl-bajo">SIN VINCULAR</span>
                        @endif
                        @unless ($a->activo)
                            <span class="tl-chip tl-sin">BAJA</span>
                        @endunless
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('centro.alumnos.edit', $a) }}">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">
                    <div class="tl-empty">
                        <div class="em">🔍</div>
                        <b>Ningún alumno coincide con estos filtros</b>
                        <p class="mb-0">Prueba a limpiarlos o a buscar por otro apellido.</p>
                    </div>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- ---------- Paginación ---------- --}}
    @if ($paginas > 1)
        <div class="card-header border-top border-bottom-0 d-flex align-items-center">
            <span class="text-body-secondary" style="font-size:.8rem; font-weight:400">
                Mostrando {{ ($pagina - 1) * $porPagina + 1 }}–{{ min($pagina * $porPagina, $total) }}
                de {{ $total }}
            </span>
            <nav class="ms-auto" aria-label="Paginación de alumnos">
                <ul class="pagination pagination-sm mb-0">
                    @for ($p = 1; $p <= $paginas; $p++)
                        <li class="page-item {{ $p === $pagina ? 'active' : '' }}">
                            <a class="page-link"
                               href="{{ route('centro.alumnos', array_merge(array_filter($filtros), ['pagina' => $p])) }}">
                                {{ $p }}
                            </a>
                        </li>
                    @endfor
                </ul>
            </nav>
        </div>
    @endif
</div>
@endsection
